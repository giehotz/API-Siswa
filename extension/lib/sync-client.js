/**
 * sync-client.js - Client Sinkronisasi EMIS ke SIS Backend
 *
 * Mengatur alur: start -> kirim chunk (default 50) -> finish.
 * Dilengkapi retry berulang dengan exponential backoff dan kapabilitas resume dari storage.
 */

(function (root, factory) {
  if (typeof module === 'object' && module.exports) {
    module.exports = factory();
  } else {
    root.EmisSyncClient = factory();
  }
})(typeof globalThis !== 'undefined' ? globalThis : this, function () {
  'use strict';

  const STORAGE_KEY_ACTIVE_BATCH = 'sis_active_sync_batch';
  const DEFAULT_CHUNK_SIZE = 50;
  const MAX_RETRIES = 3;
  const BASE_BACKOFF_MS = 1000;

  // Helper untuk akses storage asynchronous (chrome.storage.local atau fallback memory/localStorage)
  const storageAdapter = {
    async get(key) {
      if (typeof chrome !== 'undefined' && chrome.storage && chrome.storage.local) {
        return new Promise((resolve) => {
          chrome.storage.local.get([key], (res) => resolve(res ? res[key] : null));
        });
      }
      try {
        const val = localStorage.getItem(key);
        return val ? JSON.parse(val) : null;
      } catch {
        return null;
      }
    },
    async set(key, value) {
      if (typeof chrome !== 'undefined' && chrome.storage && chrome.storage.local) {
        return new Promise((resolve) => {
          chrome.storage.local.set({ [key]: value }, resolve);
        });
      }
      try {
        localStorage.setItem(key, JSON.stringify(value));
      } catch {
        // ignore
      }
    },
    async remove(key) {
      if (typeof chrome !== 'undefined' && chrome.storage && chrome.storage.local) {
        return new Promise((resolve) => {
          chrome.storage.local.remove([key], resolve);
        });
      }
      try {
        localStorage.removeItem(key);
      } catch {
        // ignore
      }
    }
  };

  function sleep(ms) {
    return new Promise((resolve) => setTimeout(resolve, ms));
  }

  function normalizeApiUrl(url) {
    let clean = (url || 'http://127.0.0.1:8000/api/v1').trim();
    if (clean.endsWith('/')) {
      clean = clean.slice(0, -1);
    }
    return clean;
  }

  /**
   * Eksekusi HTTP Request dengan retry & backoff
   */
  async function fetchWithRetry(url, options, maxRetries = MAX_RETRIES) {
    let lastError = null;

    for (let attempt = 0; attempt <= maxRetries; attempt++) {
      try {
        const res = await fetch(url, options);

        // Jika respons 5xx atau 429, lakukan retry
        if ((res.status >= 500 || res.status === 429) && attempt < maxRetries) {
          const delay = BASE_BACKOFF_MS * Math.pow(2, attempt);
          await sleep(delay);
          continue;
        }

        const data = await res.json().catch(() => null);

        if (!res.ok) {
          const errMsg = data?.message || `HTTP ${res.status}: Gagal memproses permintaan`;
          const err = new Error(errMsg);
          err.status = res.status;
          err.data = data;
          throw err;
        }

        return data;
      } catch (err) {
        lastError = err;
        if (err.status && err.status < 500 && err.status !== 429) {
          // Jangan retry kesalahan 4xx (misal 401 Unauthorized, 403 Forbidden, 422 Unprocessable)
          throw err;
        }
        if (attempt < maxRetries) {
          const delay = BASE_BACKOFF_MS * Math.pow(2, attempt);
          await sleep(delay);
        }
      }
    }

    throw lastError || new Error('Gagal menghubungi server API SIS setelah beberapa percobaan.');
  }

  class EmisSyncClient {
    constructor(options = {}) {
      this.apiUrl = normalizeApiUrl(options.apiUrl);
      this.token = (options.token || '').trim();
      this.chunkSize = options.chunkSize || DEFAULT_CHUNK_SIZE;
      this.isCancelled = false;
    }

    cancel() {
      this.isCancelled = true;
    }

    /**
     * Memeriksa status koneksi & madrasah di server SIS
     */
    async checkStatus() {
      const url = `${this.apiUrl}/emis/status`;
      return fetchWithRetry(url, {
        method: 'GET',
        headers: {
          Authorization: `Bearer ${this.token}`,
          Accept: 'application/json'
        }
      });
    }

    /**
     * Memulai sesi batch baru di server SIS
     */
    async startBatch(institutionId, totalExpected) {
      const url = `${this.apiUrl}/emis/sync/start`;
      return fetchWithRetry(url, {
        method: 'POST',
        headers: {
          Authorization: `Bearer ${this.token}`,
          'Content-Type': 'application/json',
          Accept: 'application/json'
        },
        body: JSON.stringify({
          institution_id: Number(institutionId),
          total_expected: Number(totalExpected)
        })
      });
    }

    /**
     * Mengirim satu chunk data siswa dengan Idempotency-Key
     */
    async sendChunk(batchUuid, institutionId, chunkIndex, totalChunks, chunkStudents) {
      const url = `${this.apiUrl}/emis/sync`;
      const idempotencyKey = `${batchUuid}-${chunkIndex}`;

      // Pastikan setiap objek siswa memiliki emis_student_id & full_name yang valid sebelum dikirim
      const preparedStudents = (chunkStudents || []).map((s) => {
        if (!s || typeof s !== 'object') return s;
        const sId = Number(s.emis_student_id || s.id || s.student_id || 0);
        const name = String(s.full_name || s.name || s.nama || 'TANPA NAMA').trim();
        return {
          ...s,
          emis_student_id: sId,
          full_name: name
        };
      });

      return fetchWithRetry(url, {
        method: 'POST',
        headers: {
          Authorization: `Bearer ${this.token}`,
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'Idempotency-Key': idempotencyKey
        },
        body: JSON.stringify({
          batch_uuid: batchUuid,
          institution_id: Number(institutionId),
          synced_at: new Date().toISOString(),
          chunk: {
            index: chunkIndex,
            total: totalChunks
          },
          students: preparedStudents
        })
      });
    }

    /**
     * Menyelesaikan sesi batch sinkronisasi
     */
    async finishBatch(batchUuid, status = 'completed') {
      const url = `${this.apiUrl}/emis/sync/finish`;
      return fetchWithRetry(url, {
        method: 'POST',
        headers: {
          Authorization: `Bearer ${this.token}`,
          'Content-Type': 'application/json',
          Accept: 'application/json'
        },
        body: JSON.stringify({
          batch_uuid: batchUuid,
          status: status
        })
      });
    }

    /**
     * Menjalankan seluruh siklus sinkronisasi data siswa ke SIS
     * Mendukung pemulihan (resume) jika batch sebelumnya belum tuntas.
     */
    async syncAll(institutionId, students, onProgress = () => {}) {
      if (this.isCancelled) {
        throw new Error('Sinkronisasi dibatalkan oleh pengguna.');
      }

      if (!this.token) {
        throw new Error('Token sinkronisasi SIS belum diisi. Silakan atur token di pengaturan ekstensi.');
      }

      if (!Array.isArray(students) || students.length === 0) {
        throw new Error('Tidak ada data siswa untuk disinkronkan.');
      }

      const totalStudents = students.length;
      const totalChunks = Math.ceil(totalStudents / this.chunkSize);

      // Cek apakah ada sesi batch yang tersimpan untuk di-resume
      let savedBatch = await storageAdapter.get(STORAGE_KEY_ACTIVE_BATCH);
      let batchUuid = null;
      let completedChunks = [];

      if (
        savedBatch &&
        savedBatch.institutionId === Number(institutionId) &&
        savedBatch.total === totalStudents &&
        savedBatch.status === 'running'
      ) {
        batchUuid = savedBatch.batchUuid;
        completedChunks = savedBatch.completedChunks || [];
      } else {
        onProgress({
          stage: 'starting',
          message: 'Memulai sesi sinkronisasi di server SIS...',
          percent: 5,
          currentChunk: 0,
          totalChunks,
          processedCount: 0,
          totalCount: totalStudents
        });

        const startRes = await this.startBatch(institutionId, totalStudents);
        batchUuid = startRes?.data?.batch_uuid || startRes?.batch_uuid;

        if (!batchUuid) {
          throw new Error('Gagal mendapatkan UUID batch dari server SIS.');
        }

        savedBatch = {
          batchUuid,
          institutionId: Number(institutionId),
          total: totalStudents,
          totalChunks,
          completedChunks: [],
          status: 'running',
          startedAt: new Date().toISOString()
        };
        await storageAdapter.set(STORAGE_KEY_ACTIVE_BATCH, savedBatch);
      }

      const summary = {
        inserted: 0,
        updated: 0,
        failed: 0,
        chunksProcessed: 0
      };

      // Proses tiap chunk
      for (let i = 0; i < totalChunks; i++) {
        if (this.isCancelled) {
          await this.finishBatch(batchUuid, 'partial');
          await storageAdapter.remove(STORAGE_KEY_ACTIVE_BATCH);
          throw new Error('Sinkronisasi dibatalkan oleh pengguna.');
        }

        const chunkIndex = i + 1;

        // Jika chunk ini sudah pernah dikirim sebelumnya, lewati
        if (completedChunks.includes(chunkIndex)) {
          const processedCount = Math.min(chunkIndex * this.chunkSize, totalStudents);
          const percent = Math.round((chunkIndex / totalChunks) * 90);
          onProgress({
            stage: 'syncing',
            message: `Melompati chunk ${chunkIndex}/${totalChunks} (sudah terkirim sebelumnya)...`,
            percent,
            currentChunk: chunkIndex,
            totalChunks,
            processedCount,
            totalCount: totalStudents
          });
          continue;
        }

        const startIdx = i * this.chunkSize;
        const endIdx = Math.min(startIdx + this.chunkSize, totalStudents);
        const chunkStudents = students.slice(startIdx, endIdx);

        const processedCount = startIdx;
        const percent = Math.round((i / totalChunks) * 90);

        onProgress({
          stage: 'syncing',
          message: `Mengirim chunk ${chunkIndex}/${totalChunks} (${chunkStudents.length} siswa)...`,
          percent,
          currentChunk: chunkIndex,
          totalChunks,
          processedCount,
          totalCount: totalStudents
        });

        const chunkRes = await this.sendChunk(batchUuid, institutionId, chunkIndex, totalChunks, chunkStudents);

        summary.inserted += chunkRes?.data?.inserted ?? chunkRes?.inserted ?? 0;
        summary.updated += chunkRes?.data?.updated ?? chunkRes?.updated ?? 0;
        summary.failed += (chunkRes?.data?.failed?.length ?? chunkRes?.failed?.length ?? 0);
        summary.chunksProcessed++;

        completedChunks.push(chunkIndex);
        savedBatch.completedChunks = completedChunks;
        await storageAdapter.set(STORAGE_KEY_ACTIVE_BATCH, savedBatch);
      }

      // Selesai seluruh chunk
      onProgress({
        stage: 'finishing',
        message: 'Menyelesaikan sesi sinkronisasi...',
        percent: 95,
        currentChunk: totalChunks,
        totalChunks,
        processedCount: totalStudents,
        totalCount: totalStudents
      });

      const finishRes = await this.finishBatch(batchUuid, 'completed');
      await storageAdapter.remove(STORAGE_KEY_ACTIVE_BATCH);

      onProgress({
        stage: 'completed',
        message: 'Sinkronisasi ke SIS berhasil!',
        percent: 100,
        currentChunk: totalChunks,
        totalChunks,
        processedCount: totalStudents,
        totalCount: totalStudents,
        summary: finishRes?.data?.summary || summary
      });

      return {
        success: true,
        batchUuid,
        summary: finishRes?.data?.summary || summary
      };
    }
  }

  return EmisSyncClient;
});
