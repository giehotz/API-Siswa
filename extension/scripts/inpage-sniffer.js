/**
 * inpage-sniffer.js - MAIN World Interceptor
 * Intercepts EMIS API calls, generates opaque IDs using Web Crypto,
 * and executes authenticated batch fetches with:
 * - Session In-Memory Cache (never re-downloads already fetched students on timeout/resume)
 * - Per-request AbortController timeout & automatic retries
 * - Partial data retention on cancel/timeout (no data is discarded)
 */

(function () {
  'use strict';

  if (window.__EMIS_SNIFFER_ACTIVE__) return;
  window.__EMIS_SNIFFER_ACTIVE__ = true;

  const EMIS_API_HOST = 'api-emis.kemenag.go.id';
  let activeAuthHeader = null;
  let lastListEndpoint = null;
  let lastDetailEndpoint = null;
  let lastInstitutionId = null;
  let isCancelRequested = false;

  // Cache across the session (persists as long as EMIS tab is open)
  // Maps student.id -> full student object
  const sessionStudentCache = new Map();
  // Maps pageNumber -> array of student summaries
  const sessionListPageCache = new Map();

  const CRYPTO_KEY_STR = 'a2c36eb2w1em50d6665dc5d61a68b400';
  const IV_STR = 'emisBase64IVkeys';

  /**
   * Encrypt numeric student ID to EMIS opaque ID using standard Web Crypto API
   */
  async function encryptStudentId(id) {
    try {
      const enc = new TextEncoder();
      const key = await window.crypto.subtle.importKey(
        'raw',
        enc.encode(CRYPTO_KEY_STR),
        { name: 'AES-CBC' },
        false,
        ['encrypt']
      );
      const cipherBuffer = await window.crypto.subtle.encrypt(
        { name: 'AES-CBC', iv: enc.encode(IV_STR) },
        key,
        enc.encode(String(id))
      );
      const cipherBytes = new Uint8Array(cipherBuffer);
      let binary = '';
      for (let i = 0; i < cipherBytes.length; i++) {
        binary += String.fromCharCode(cipherBytes[i]);
      }
      return btoa(btoa(binary));
    } catch (err) {
      console.warn('[EMIS Exporter] Encryption fallback error:', err);
      return String(id);
    }
  }

  function notifyIsolated(type, payload) {
    window.postMessage({
      source: 'EMIS_SNIFFER',
      type: type,
      payload: payload
    }, '*');
  }

  // Intercept window.fetch
  const originalFetch = window.fetch;
  window.fetch = async function (...args) {
    try {
      const url = typeof args[0] === 'string' ? args[0] : (args[0] && args[0].url ? args[0].url : '');
      const init = args[1] || {};

      if (url && url.includes(EMIS_API_HOST)) {
        let auth = null;
        if (init.headers) {
          if (init.headers instanceof Headers) {
            auth = init.headers.get('Authorization') || init.headers.get('authorization');
          } else if (typeof init.headers === 'object') {
            auth = init.headers['Authorization'] || init.headers['authorization'];
          }
        }
        if (auth) {
          activeAuthHeader = auth;
        }

        const listMatch = url.match(/\/institution\/([0-9]+)\/student\/list/);
        if (listMatch) {
          lastInstitutionId = listMatch[1];
          lastListEndpoint = url;
          notifyIsolated('LIST_ENDPOINT_DETECTED', {
            url,
            institutionId: lastInstitutionId,
            hasAuth: !!activeAuthHeader,
            cachedCount: sessionStudentCache.size
          });
        }
        else if (url.includes('/students/students/')) {
          lastDetailEndpoint = url;
          notifyIsolated('DETAIL_ENDPOINT_DETECTED', { url, hasAuth: !!activeAuthHeader });
        }
      }
    } catch (err) {
      // Ignore interception errors
    }

    return originalFetch.apply(this, args);
  };

  // Intercept XMLHttpRequest
  const originalXhrOpen = XMLHttpRequest.prototype.open;
  const originalXhrSetRequestHeader = XMLHttpRequest.prototype.setRequestHeader;

  XMLHttpRequest.prototype.open = function (method, url, ...rest) {
    this._emis_url = url;
    return originalXhrOpen.call(this, method, url, ...rest);
  };

  XMLHttpRequest.prototype.setRequestHeader = function (header, value) {
    if (header && header.toLowerCase() === 'authorization') {
      activeAuthHeader = value;
      if (this._emis_url && this._emis_url.includes(EMIS_API_HOST)) {
        notifyIsolated('AUTH_DETECTED', { hasAuth: true });
      }
    }
    return originalXhrSetRequestHeader.call(this, header, value);
  };

  /**
   * Fetch with timeout & retry to handle slow/unstable connection
   */
  async function performFetchWithRetry(url, options = {}, retries = 2, timeoutMs = 15000) {
    let lastErr = null;
    for (let attempt = 0; attempt <= retries; attempt++) {
      if (isCancelRequested) throw new Error('Ekspor dibatalkan.');
      const controller = new AbortController();
      const timeoutId = setTimeout(() => controller.abort(), timeoutMs);

      try {
        const fetchOpts = {
          method: 'GET',
          credentials: 'include',
          headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json'
          },
          signal: controller.signal,
          ...options
        };
        if (activeAuthHeader && !fetchOpts.headers['Authorization']) {
          fetchOpts.headers['Authorization'] = activeAuthHeader;
        }

        const resp = await originalFetch(url, fetchOpts);
        clearTimeout(timeoutId);
        if (!resp.ok) {
          throw new Error(`HTTP ${resp.status} - ${resp.statusText}`);
        }
        return await resp.json();
      } catch (err) {
        clearTimeout(timeoutId);
        lastErr = err;
        if (err.name === 'AbortError') {
          lastErr = new Error(`Permintaan waktu habis (${timeoutMs / 1000}s)`);
        }
        if (attempt < retries && !isCancelRequested) {
          // Wait 1 second before retry
          await new Promise(r => setTimeout(r, 1000));
        }
      }
    }
    throw lastErr;
  }

  // Handle messages dispatched from isolated content script
  window.addEventListener('message', async (event) => {
    if (event.data?.source !== 'EMIS_EXTENSION_DISPATCH') return;

    const { action, requestId, url, options } = event.data;

    if (action === 'CANCEL_FETCH') {
      isCancelRequested = true;
      return;
    }

    if (action === 'RESET_CACHE') {
      sessionStudentCache.clear();
      sessionListPageCache.clear();
      notifyIsolated('CACHE_RESET', { cachedCount: 0 });
      return;
    }

    if (action === 'EXECUTE_PAGE_FETCH') {
      try {
        const json = await performFetchWithRetry(url, options, 1, 15000);
        window.postMessage({
          source: 'EMIS_SNIFFER_RESPONSE',
          requestId,
          status: 200,
          ok: true,
          data: json
        }, '*');
      } catch (err) {
        window.postMessage({
          source: 'EMIS_SNIFFER_RESPONSE',
          requestId,
          status: 0,
          ok: false,
          error: err.message
        }, '*');
      }
    }

    else if (action === 'FETCH_ALL_STUDENTS') {
      isCancelRequested = false;
      const targetInstId = options?.institutionId || lastInstitutionId;
      const baseUrl = `https://api-emis.kemenag.go.id/v1/students/institution/${targetInstId}/student/list?per_page=30`;

      // Pre-load from persistent storage (passed via options.preloadedCache)
      if (Array.isArray(options?.preloadedCache)) {
        for (const item of options.preloadedCache) {
          if (item && item.id) {
            sessionStudentCache.set(String(item.id), item);
          }
        }
      }

      const finalStudents = [];
      let totalStudentsCount = 0;
      let consecutiveTimeouts = 0;

      try {
        let allListStudents = [];

        // Check if full student list was already cached
        if (Array.isArray(options?.cachedListStudents) && options.cachedListStudents.length > 0) {
          allListStudents = [...options.cachedListStudents];
          totalStudentsCount = allListStudents.length;
        } else {
          // 1. Fetch Page 1 (or get from memory cache)
          let page1Resp = null;
          if (sessionListPageCache.has(1)) {
            page1Resp = sessionListPageCache.get(1);
          } else {
            notifyIsolated('BATCH_PROGRESS', {
              current: 0,
              total: 0,
              percent: 0,
              message: 'Mengambil daftar siswa halaman 1...'
            });
            page1Resp = await performFetchWithRetry(`${baseUrl}&page=1`, {}, 2, 20000);
            sessionListPageCache.set(1, page1Resp);
          }

          totalStudentsCount = (page1Resp.metadata?.pagination?.total) || (page1Resp.results?.length || 0);
          const lastPage = (page1Resp.metadata?.pagination?.last_page) || 1;
          allListStudents = [...(page1Resp.results || [])];

          // 2. Fetch remaining list pages (skipping cached pages)
          for (let p = 2; p <= lastPage; p++) {
            if (isCancelRequested) break;

            if (sessionListPageCache.has(p)) {
              const cachedPage = sessionListPageCache.get(p);
              if (cachedPage.results && Array.isArray(cachedPage.results)) {
                allListStudents.push(...cachedPage.results);
              }
            } else {
              notifyIsolated('BATCH_PROGRESS', {
                current: allListStudents.length,
                total: totalStudentsCount,
                percent: Math.round((allListStudents.length / totalStudentsCount) * 15),
                message: `Mengambil daftar halaman ${p} dari ${lastPage}...`
              });

              try {
                const pageResp = await performFetchWithRetry(`${baseUrl}&page=${p}`, {}, 2, 15000);
                sessionListPageCache.set(p, pageResp);
                if (pageResp.results && Array.isArray(pageResp.results)) {
                  allListStudents.push(...pageResp.results);
                }
              } catch (pageErr) {
                console.warn(`[EMIS Exporter] Gagal memuat daftar halaman ${p}:`, pageErr);
              }
              await new Promise(r => setTimeout(r, 120));
            }
          }

          // Notify isolated world to save the student list
          if (allListStudents.length > 0) {
            notifyIsolated('LIST_STUDENTS_CACHED', {
              institutionId: targetInstId,
              students: allListStudents
            });
          }
        }

        // 3. Fetch details for each student, SKIPPING ALREADY COLLECTED STUDENTS!
        const totalToDetail = allListStudents.length;

        for (let i = 0; i < totalToDetail; i++) {
          if (isCancelRequested) break;

          const studentSummary = allListStudents[i];
          const studentId = studentSummary.id;
          const sIdKey = String(studentId);
          const pct = 15 + Math.round(((i + 1) / totalToDetail) * 85);

          // CHECK CACHE: DO NOT RE-DOWNLOAD IF ALREADY COLLECTED!
          const cached = sessionStudentCache.get(sIdKey);
          const isAlreadyCollected = cached && (
            cached._detailSuccess === true ||
            cached.local_nis !== undefined ||
            (cached.parents && typeof cached.parents === 'object')
          );

          if (isAlreadyCollected) {
            finalStudents.push(cached);
            notifyIsolated('BATCH_PROGRESS', {
              current: i + 1,
              total: totalToDetail,
              percent: pct,
              message: `[Data Tersimpan] Melewati unduhan: ${studentSummary.full_name} (${i + 1}/${totalToDetail})`
            });
            continue; // NEVER RE-DOWNLOAD ALREADY COLLECTED STUDENT!
          }

          notifyIsolated('BATCH_PROGRESS', {
            current: i + 1,
            total: totalToDetail,
            percent: pct,
            message: `Mengambil detail & NIS Lokal (${i + 1}/${totalToDetail}): ${studentSummary.full_name}...`
          });

          try {
            const opaqueId = await encryptStudentId(studentId);
            const detailUrl = `https://api-emis.kemenag.go.id/v1/students/students/${opaqueId}`;
            // 12s timeout per student detail with 1 retry
            const detailResp = await performFetchWithRetry(detailUrl, {}, 1, 12000);
            const detailObj = detailResp.results || {};
            const merged = { ...studentSummary, ...detailObj, _detailSuccess: true };

            sessionStudentCache.set(sIdKey, merged);
            finalStudents.push(merged);
            consecutiveTimeouts = 0;

            // Notify isolated world to persist student to chrome.storage.local immediately
            notifyIsolated('STUDENT_SAVED_TO_CACHE', {
              institutionId: targetInstId,
              student: merged,
              cachedCount: sessionStudentCache.size
            });
          } catch (detErr) {
            console.warn(`[EMIS Exporter] Timeout/Gagal detail siswa ${studentSummary.full_name}:`, detErr);
            consecutiveTimeouts++;

            // Retain student summary so partial record exists, but DO NOT mark _detailSuccess so it can be resumed
            const fallback = {
              ...studentSummary,
              _fetchTimeout: true
            };
            finalStudents.push(fallback);

            // If 4 consecutive timeouts occur, stop loop gracefully and protect existing data
            if (consecutiveTimeouts >= 4) {
              console.warn('[EMIS Exporter] 4 kali time-out berturut-turut. Menghentikan proses & mengamankan data.');
              notifyIsolated('BATCH_PROGRESS', {
                current: i + 1,
                total: totalToDetail,
                percent: pct,
                message: `⚠️ Terjadi time out koneksi EMIS. Mengamankan ${finalStudents.length} data siswa yang sudah didata...`
              });
              break;
            }
          }

          if (i % 6 === 0) {
            await new Promise(r => setTimeout(r, 180));
          }
        }

        // Return successfully (even if partial due to cancel or timeout)
        const isPartial = isCancelRequested || consecutiveTimeouts >= 4 || (finalStudents.length < totalStudentsCount);

        window.postMessage({
          source: 'EMIS_SNIFFER_RESPONSE',
          requestId,
          status: 200,
          ok: true,
          data: {
            results: finalStudents,
            total: finalStudents.length,
            targetTotal: totalStudentsCount,
            isPartial: isPartial,
            timedOut: consecutiveTimeouts >= 4
          }
        }, '*');

      } catch (err) {
        // Even on error, return whatever data has already been accumulated!
        console.warn('[EMIS Exporter] Batch terhenti, mengembalikan seluruh data yang sudah didata:', err);
        const collectedSoFar = finalStudents.length > 0 ? finalStudents : Array.from(sessionStudentCache.values());

        window.postMessage({
          source: 'EMIS_SNIFFER_RESPONSE',
          requestId,
          status: 200,
          ok: true,
          data: {
            results: collectedSoFar,
            total: collectedSoFar.length,
            targetTotal: totalStudentsCount,
            isPartial: true,
            timedOut: true,
            error: err.message
          }
        }, '*');
      }
    }
  });

  notifyIsolated('SNIFFER_READY', {
    hasAuth: !!activeAuthHeader,
    lastListEndpoint,
    lastDetailEndpoint,
    institutionId: lastInstitutionId,
    cachedCount: sessionStudentCache.size
  });
})();
