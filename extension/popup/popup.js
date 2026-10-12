/**
 * popup.js - Controller for EMIS Student Exporter Popup UI
 * Supports single-sheet export for all students with NIS Lokal, real-time progress,
 * session in-memory caching to prevent re-downloading on timeout, and partial data retention.
 */

document.addEventListener('DOMContentLoaded', async () => {
  // Elements
  const connectionBadge = document.getElementById('connectionBadge');
  const connectionText = document.getElementById('connectionText');
  const detailStatus = document.getElementById('detailStatus');
  const listStatus = document.getElementById('listStatus');
  const noticeBanner = document.getElementById('noticeBanner');
  
  const toggleMaster = document.getElementById('toggleSensitiveMaster');
  const privacyWarning = document.getElementById('privacyWarning');
  const granularDetails = document.getElementById('granularDetails');
  
  const chkNik = document.getElementById('chkNik');
  const chkKk = document.getElementById('chkKk');
  const chkParentsNik = document.getElementById('chkParentsNik');
  const chkBansos = document.getElementById('chkBansos');
  const chkCoords = document.getElementById('chkCoords');

  const btnMassExport = document.getElementById('btnMassExport');
  const btnMassExportText = document.getElementById('btnMassExportText');
  const cachedActionsGroup = document.getElementById('cachedActionsGroup');
  const btnDownloadCached = document.getElementById('btnDownloadCached');
  const cachedCountBadge = document.getElementById('cachedCountBadge');
  const btnSyncCachedSis = document.getElementById('btnSyncCachedSis');
  const cachedSyncCountBadge = document.getElementById('cachedSyncCountBadge');
  const cacheNoticeRow = document.getElementById('cacheNoticeRow');
  const cacheNoticeText = document.getElementById('cacheNoticeText');
  const btnResetCache = document.getElementById('btnResetCache');

  const btnExportCurrentTab = document.getElementById('btnExportCurrentTab');
  const btnTestMock = document.getElementById('btnTestMock');

  const progressArea = document.getElementById('progressArea');
  const progressBar = document.getElementById('progressBar');
  const progressPercent = document.getElementById('progressPercent');
  const progressCounts = document.getElementById('progressCounts');
  const progressStatusMsg = document.getElementById('progressStatusMsg');
  const btnCancel = document.getElementById('btnCancel');

  // SIS Integration Elements
  const txtSisApiUrl = document.getElementById('txtSisApiUrl');
  const txtSisToken = document.getElementById('txtSisToken');
  const btnTestSisConnection = document.getElementById('btnTestSisConnection');
  const btnSaveSisSettings = document.getElementById('btnSaveSisSettings');
  const sisStatusNotice = document.getElementById('sisStatusNotice');
  const btnSyncSis = document.getElementById('btnSyncSis');
  const btnSyncSisText = document.getElementById('btnSyncSisText');

  let activeTabId = null;
  let isEmisPage = false;
  let pageStatus = null;
  let currentCachedCount = 0;
  let activeSyncClient = null;

  // Load saved privacy preferences and SIS settings
  if (chrome.storage && chrome.storage.local) {
    chrome.storage.local.get(['emisPrivacyConfig', 'sis_api_url', 'sis_api_token'], (result) => {
      if (result) {
        if (result.sis_api_url && txtSisApiUrl) {
          let savedUrl = result.sis_api_url.trim();
          // Auto upgrade http ke https untuk domain publik
          if (savedUrl.startsWith('http://api.min2tanggamus.sch.id')) {
            savedUrl = savedUrl.replace('http://', 'https://');
          }
          txtSisApiUrl.value = savedUrl;
        } else if (txtSisApiUrl) {
          txtSisApiUrl.value = 'https://api.min2tanggamus.sch.id/api/v1';
        }
        if (result.sis_api_token && txtSisToken) txtSisToken.value = result.sis_api_token;
        updateSyncButtonState();

        if (result.emisPrivacyConfig) {
          const cfg = result.emisPrivacyConfig;
          toggleMaster.checked = !!cfg.includeSensitive;
          if (cfg.options) {
            chkNik.checked = cfg.options.nik !== false;
            chkKk.checked = cfg.options.kk !== false;
            chkParentsNik.checked = cfg.options.parentsNik !== false;
            chkBansos.checked = cfg.options.bansos !== false;
            chkCoords.checked = !!cfg.options.coordinates;
          }
          updatePrivacyUI(toggleMaster.checked);
        }
      }
    });
  }

  function getPrivacyOptions() {
    return {
      includeSensitive: toggleMaster.checked,
      sensitiveOptions: {
        nik: chkNik.checked,
        kk: chkKk.checked,
        parentsNik: chkParentsNik.checked,
        bansos: chkBansos.checked,
        coordinates: chkCoords.checked
      }
    };
  }

  function savePrivacyPreferences() {
    if (chrome.storage && chrome.storage.local) {
      chrome.storage.local.set({
        emisPrivacyConfig: {
          includeSensitive: toggleMaster.checked,
          options: {
            nik: chkNik.checked,
            kk: chkKk.checked,
            parentsNik: chkParentsNik.checked,
            bansos: chkBansos.checked,
            coordinates: chkCoords.checked
          }
        }
      });
    }
  }

  function updatePrivacyUI(isChecked) {
    if (isChecked) {
      privacyWarning.classList.remove('hidden');
      granularDetails.classList.remove('hidden');
    } else {
      privacyWarning.classList.add('hidden');
      granularDetails.classList.add('hidden');
    }
  }

  toggleMaster.addEventListener('change', () => {
    updatePrivacyUI(toggleMaster.checked);
    savePrivacyPreferences();
  });

  [chkNik, chkKk, chkParentsNik, chkBansos, chkCoords].forEach(chk => {
    chk.addEventListener('change', savePrivacyPreferences);
  });

  function updateCacheUI(count) {
    currentCachedCount = count || 0;
    if (currentCachedCount > 0) {
      cacheNoticeRow.classList.remove('hidden');
      cacheNoticeText.textContent = `💾 ${currentCachedCount} siswa tersimpan di memori lokal`;
      if (btnMassExportText) {
        btnMassExportText.textContent = `Lanjutkan Ekspor (Sisa Siswa)`;
      }
      if (cachedActionsGroup) {
        cachedActionsGroup.classList.remove('hidden');
      }
      if (cachedCountBadge) cachedCountBadge.textContent = currentCachedCount;
      if (cachedSyncCountBadge) cachedSyncCountBadge.textContent = currentCachedCount;
    } else {
      cacheNoticeRow.classList.add('hidden');
      if (btnMassExportText) {
        btnMassExportText.textContent = `Mulai Ekspor Semua Siswa (1 Sheet)`;
      }
      if (cachedActionsGroup) {
        cachedActionsGroup.classList.add('hidden');
      }
    }
    updateSyncButtonState();
  }

  // Download Cached Data directly without re-fetching
  if (btnDownloadCached) {
    btnDownloadCached.addEventListener('click', () => {
      if (!activeTabId) return;
      btnDownloadCached.disabled = true;
      btnDownloadCached.textContent = '⏳ Menyiapkan file Excel...';

      chrome.tabs.sendMessage(activeTabId, {
        action: 'GET_SAVED_STUDENTS',
        institutionId: pageStatus?.snifferState?.institutionId
      }, (resp) => {
        btnDownloadCached.disabled = false;
        btnDownloadCached.innerHTML = `<span>💾 Unduh Data Tersimpan (<span id="cachedCountBadge">${currentCachedCount}</span> Siswa ke Excel)</span>`;

        const students = resp?.students || [];
        if (students.length === 0) {
          alert('Tidak ada data siswa yang tersimpan di memori saat ini.');
          return;
        }

        const privOpts = getPrivacyOptions();
        const normResult = EmisNormalizer.normalizeStudentsSingleSheet(students, privOpts);
        const filename = EmisExporter.generateFilename('Data_Tersimpan_Siswa_1_Sheet');
        EmisExporter.downloadSingleSheet(normResult.rows, 'Data_Siswa_Lengkap', filename);

        alert(`Sukses! ${normResult.rows.length} data siswa tersimpan berhasil diunduh ke berkas Excel:\n${filename}`);
      });
    });
  }

  // Send Cached Data directly to SIS
  if (btnSyncCachedSis) {
    btnSyncCachedSis.addEventListener('click', () => {
      if (!activeTabId) {
        alert('Tab aktif tidak ditemukan.');
        return;
      }

      const instId = pageStatus?.snifferState?.institutionId;
      if (!instId) {
        alert('ID Madrasah belum terdeteksi. Buka menu Kesiswaan di portal EMIS terlebih dahulu.');
        return;
      }

      btnSyncCachedSis.disabled = true;
      btnSyncCachedSis.textContent = '⏳ Mengambil data tersimpan...';

      chrome.tabs.sendMessage(activeTabId, {
        action: 'GET_SAVED_STUDENTS',
        institutionId: instId
      }, async (resp) => {
        btnSyncCachedSis.disabled = false;
        btnSyncCachedSis.innerHTML = `<span class="btn-icon">🚀</span> <span>Kirim Data Tersimpan ke SIS (<span id="cachedSyncCountBadge">${currentCachedCount}</span> Siswa)</span>`;

        const students = resp?.students || [];
        if (students.length === 0) {
          alert('Tidak ada data siswa yang tersimpan di memori saat ini.');
          return;
        }

        await executeSyncToSis(students, true);
      });
    });
  }

  // Reset Cache Button
  if (btnResetCache) {
    btnResetCache.addEventListener('click', () => {
      if (activeTabId) {
        chrome.tabs.sendMessage(activeTabId, { action: 'RESET_CACHE' }, () => {
          updateCacheUI(0);
          alert('Memori cache berhasil dibersihkan. Ekspor berikutnya akan dimulai dari awal.');
        });
      }
    });
  }

  // Listen for progress updates from content script
  chrome.runtime.onMessage.addListener((message) => {
    if (message.action === 'ON_BATCH_PROGRESS' && message.progress) {
      const p = message.progress;
      progressBar.style.width = `${p.percent || 0}%`;
      progressPercent.textContent = `${p.percent || 0}%`;
      progressCounts.textContent = `${p.current || 0} / ${p.total || 0} Siswa`;
      if (p.message && progressStatusMsg) {
        progressStatusMsg.textContent = p.message;
      }
    }
  });

  // Query active tab and check EMIS connection
  try {
    const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });
    if (!tab) {
      setConnectionState(false, 'Tab tidak ditemukan');
      return;
    }

    activeTabId = tab.id;
    const tabUrl = tab.url || '';

    if (!tabUrl.includes('emis.kemenag.go.id')) {
      setConnectionState(false, 'Bukan Portal EMIS');
      noticeBanner.innerHTML = 'Silakan buka <a href="https://emis.kemenag.go.id" target="_blank" class="link">emis.kemenag.go.id</a> pada tab ini.';
      return;
    }

    // Ping content script
    chrome.tabs.sendMessage(activeTabId, { action: 'GET_PAGE_STATUS' }, (resp) => {
      if (chrome.runtime.lastError || !resp || !resp.success) {
        setConnectionState(true, 'EMIS (Muat Ulang Diperlukan)');
        noticeBanner.textContent = 'Silakan muat ulang (refresh) halaman EMIS agar ekstensi tersambung ke sesi aktif.';
        return;
      }

      pageStatus = resp;
      isEmisPage = true;
      setConnectionState(true, 'Terhubung ke EMIS');

      // Check cached count in session
      if (resp.snifferState?.cachedCount) {
        updateCacheUI(resp.snifferState.cachedCount);
      }

      // Update status badges
      if (resp.currentStudentId || resp.snifferState?.lastDetailUrl) {
        detailStatus.textContent = 'Siap Diekspor';
        detailStatus.className = 'status-val text-success';
        btnExportCurrentTab.disabled = false;
      } else {
        detailStatus.textContent = 'Buka Form Siswa';
        detailStatus.className = 'status-val text-warning';
      }

      if (resp.snifferState?.lastListUrl || resp.snifferState?.institutionId) {
        listStatus.textContent = 'Siap (Endpoint Aktif)';
        listStatus.className = 'status-val text-success';
        btnMassExport.disabled = false;
        noticeBanner.textContent = 'Daftar siswa terdeteksi! Siap mengekspor semua siswa ke 1 sheet.';
      } else {
        listStatus.textContent = 'Menunggu Navigasi';
        listStatus.className = 'status-val text-muted';
        noticeBanner.textContent = 'Buka menu Kesiswaan di EMIS untuk mengaktifkan ekspor semua siswa.';
      }

      updateSyncButtonState();
    });
  } catch (err) {
    setConnectionState(false, 'Gagal terhubung');
  }

  function setConnectionState(isConnected, text) {
    connectionText.textContent = text;
    if (isConnected) {
      connectionBadge.className = 'badge badge-connected';
    } else {
      connectionBadge.className = 'badge badge-disconnected';
    }
  }

  // --- 1. Test Mock Sample Button (Single Sheet) ---
  btnTestMock.addEventListener('click', () => {
    try {
      if (typeof MOCK_STUDENT_RESULTS === 'undefined') {
        alert('Data mock tidak tersedia.');
        return;
      }

      const privOpts = getPrivacyOptions();
      const normResult = EmisNormalizer.normalizeStudentsSingleSheet([MOCK_STUDENT_RESULTS], privOpts);
      const filename = EmisExporter.generateFilename('Sample_Mock_1_Sheet');
      EmisExporter.downloadSingleSheet(normResult.rows, 'Data_Siswa_Lengkap', filename);

      alert(`Sukses! Berkas sampel 1 Sheet berhasil dibuat:\n${filename}\n\nMemuat 1 sheet lengkap dengan NIS Lokal dan identitas siswa.`);
    } catch (err) {
      alert('Gagal menghasilkan berkas mock: ' + err.message);
    }
  });

  // --- 2. Single Student Tab Export (Single Sheet) ---
  btnExportCurrentTab.addEventListener('click', async () => {
    if (!activeTabId) {
      alert('Tab aktif tidak ditemukan.');
      return;
    }

    btnExportCurrentTab.disabled = true;
    btnExportCurrentTab.textContent = '⏳ Mengambil Data...';

    try {
      chrome.tabs.sendMessage(activeTabId, { action: 'FETCH_CURRENT_TAB_STUDENT' }, (resp) => {
        btnExportCurrentTab.disabled = false;
        btnExportCurrentTab.textContent = '📄 Ekspor Tab Ini';

        if (chrome.runtime.lastError) {
          alert('Gagal berkomunikasi dengan halaman EMIS. Pastikan halaman sudah di-refresh.');
          return;
        }

        if (!resp || !resp.success || !resp.result?.ok) {
          alert(resp?.error || resp?.result?.error || 'Tidak ada data siswa yang berhasil diambil dari tab ini. Buka salah satu formulir siswa di EMIS.');
          return;
        }

        const studentData = resp.result.data?.results || resp.result.data;
        if (!studentData) {
          alert('Respons EMIS tidak memuat objek data siswa.');
          return;
        }

        const privOpts = getPrivacyOptions();
        const normResult = EmisNormalizer.normalizeStudentsSingleSheet([studentData], privOpts);
        const filename = EmisExporter.generateFilename('Siswa_Tab_Aktif_1_Sheet');
        EmisExporter.downloadSingleSheet(normResult.rows, 'Data_Siswa_Lengkap', filename);

        alert(`Sukses! Data siswa berhasil diekspor (1 Sheet) ke file:\n${filename}`);
      });
    } catch (err) {
      btnExportCurrentTab.disabled = false;
      btnExportCurrentTab.textContent = '📄 Ekspor Tab Ini';
      alert('Error: ' + err.message);
    }
  });

  // --- 3. Mass Export Button (Resumable, never re-downloads cached on timeout) ---
  btnMassExport.addEventListener('click', () => {
    if (!pageStatus?.snifferState?.lastListUrl && !pageStatus?.snifferState?.institutionId) {
      alert('Endpoint daftar siswa belum terdeteksi.\n\nSilakan buka menu Kesiswaan di EMIS agar ekstensi mengenali ID madrasah.');
      return;
    }

    btnMassExport.disabled = true;
    progressArea.classList.remove('hidden');
    progressBar.style.width = '0%';
    progressPercent.textContent = '0%';
    progressCounts.textContent = 'Memeriksa data...';
    progressStatusMsg.textContent = currentCachedCount > 0
      ? `Melanjutkan dari memori (${currentCachedCount} siswa sudah didata)...`
      : 'Menghubungkan ke API kesiswaan...';

    const privOpts = getPrivacyOptions();

    chrome.tabs.sendMessage(activeTabId, {
      action: 'START_MASS_EXPORT',
      institutionId: pageStatus.snifferState.institutionId
    }, (resp) => {
      btnMassExport.disabled = false;
      progressArea.classList.add('hidden');

      if (chrome.runtime.lastError) {
        alert('Gagal mengeksekusi ekspor: ' + chrome.runtime.lastError.message);
        return;
      }

      const resData = resp.result?.data || {};
      const students = resData.results || [];
      const isTimedOut = !!resData.timedOut;
      const isPartial = !!resData.isPartial || isTimedOut;

      if (students.length === 0) {
        alert(resData.error || resp.result?.error || resp?.error || 'Tidak ada data siswa yang berhasil didata.');
        return;
      }

      updateCacheUI(students.length);

      // Normalize into ONE single sheet
      const normResult = EmisNormalizer.normalizeStudentsSingleSheet(students, privOpts);
      const prefix = isPartial ? 'Semua_Siswa_Madrasah_1_Sheet_PARSIAL' : 'Semua_Siswa_Madrasah_1_Sheet';
      const filename = EmisExporter.generateFilename(prefix);
      EmisExporter.downloadSingleSheet(normResult.rows, 'Data_Siswa_Lengkap', filename);

      if (isTimedOut) {
        alert(`⚠️ KONEKSI TIME OUT!\n\nJangan khawatir: ${normResult.rows.length} siswa yang sudah didata berhasil diamankan dan diunduh ke berkas Excel:\n${filename}\n\nSeluruh data tersebut tetap tersimpan di memori lokal. Klik 'Lanjutkan Ekspor (Sisa Siswa)' untuk melanjutkan tanpa mengulang siswa yang sudah selesai ini!`);
      } else if (isPartial) {
        alert(`⚠️ EKSPOR PARSIAL TERSIMPAN!\n\nBerhasil mengunduh ${normResult.rows.length} siswa ke berkas Excel:\n${filename}\n\nCatatan: Data tersimpan di memori lokal. Jika Anda klik 'Lanjutkan Ekspor', sistem TIDAK AKAN mengulang siswa yang sudah didata ini.`);
      } else {
        alert(`🎉 EKSPOR 100% SELESAI!\n\nTotal: ${normResult.rows.length} siswa berhasil diekspor lengkap ke dalam 1 sheet Excel:\n${filename}\n\nSemua kolom NIS Lokal, NISN, Rombel, Alamat, dan Orang Tua terisi utuh.`);
      }
    });
  });

  // Cancel Button
  btnCancel.addEventListener('click', () => {
    if (activeSyncClient) {
      activeSyncClient.cancel();
      progressStatusMsg.textContent = 'Membatalkan sinkronisasi ke SIS...';
    } else if (activeTabId) {
      chrome.tabs.sendMessage(activeTabId, { action: 'CANCEL_MASS_EXPORT' });
      progressStatusMsg.textContent = 'Membatalkan proses & menyimpan data yang sudah terkumpul...';
    }
  });

  // --- SIS Integration Handlers ---
  function updateSyncButtonState() {
    const hasToken = !!(txtSisToken?.value?.trim());
    const hasData = !!(pageStatus?.snifferState?.institutionId || pageStatus?.snifferState?.lastListUrl || currentCachedCount > 0);
    if (btnSyncSis) {
      btnSyncSis.disabled = !(hasToken && hasData);
    }
    if (btnSyncCachedSis) {
      btnSyncCachedSis.disabled = !(hasToken && currentCachedCount > 0);
    }
    if (cachedSyncCountBadge) {
      cachedSyncCountBadge.textContent = currentCachedCount;
    }
  }

  function showSisNotice(msg, type = 'info') {
    if (!sisStatusNotice) return;
    sisStatusNotice.className = `notice-box notice-${type}`;
    sisStatusNotice.textContent = msg;
    sisStatusNotice.classList.remove('hidden');
  }

  if (txtSisToken) {
    txtSisToken.addEventListener('input', updateSyncButtonState);
  }

  if (btnSaveSisSettings) {
    btnSaveSisSettings.addEventListener('click', () => {
      const url = txtSisApiUrl?.value?.trim() || 'http://127.0.0.1:8000/api/v1';
      const token = txtSisToken?.value?.trim() || '';
      if (chrome.storage && chrome.storage.local) {
        chrome.storage.local.set({ sis_api_url: url, sis_api_token: token }, () => {
          showSisNotice('Pengaturan koneksi SIS berhasil disimpan.', 'info');
          updateSyncButtonState();
        });
      }
    });
  }

  if (btnTestSisConnection) {
    btnTestSisConnection.addEventListener('click', async () => {
      const url = txtSisApiUrl?.value?.trim() || 'http://127.0.0.1:8000/api/v1';
      const token = txtSisToken?.value?.trim() || '';
      if (!token) {
        showSisNotice('Token sinkronisasi belum diisi!', 'warning');
        return;
      }
      showSisNotice('Menghubungkan ke API SIS...', 'info');
      try {
        const client = new EmisSyncClient({ apiUrl: url, token });
        const res = await client.checkStatus();
        if (res?.success) {
          const inst = res.institution || res.data?.institution;
          const count = res.students_count ?? res.data?.students_count ?? 0;
          showSisNotice(`Tersambung! Madrasah: ${inst?.name || 'Terverifikasi'} (${count} siswa di SIS)`, 'info');
        } else {
          showSisNotice(res?.message || 'Gagal tersambung ke server SIS.', 'warning');
        }
      } catch (err) {
        const detail = err.message === 'Failed to fetch'
          ? 'Failed to fetch (Pastikan URL menggunakan https:// dan server API online)'
          : err.message;
        showSisNotice(`Koneksi Gagal: ${detail}`, 'warning');
      }
    });
  }

  /**
   * Helper eksekusi sinkronisasi data siswa ke backend SIS
   * @param {Array} students Array data siswa
   * @param {boolean} isPartial Flag penanda apakah data sinkronisasi merupakan data parsial/tersimpan
   */
  async function executeSyncToSis(students, isPartial = false) {
    const instId = pageStatus?.snifferState?.institutionId;
    if (!instId) {
      alert('ID Madrasah belum terdeteksi. Buka menu Kesiswaan di portal EMIS terlebih dahulu.');
      return false;
    }

    const token = txtSisToken?.value?.trim();
    if (!token) {
      alert('Token sinkronisasi SIS belum diisi. Buka Pengaturan Koneksi SIS dan masukkan token.');
      return false;
    }

    const apiUrl = txtSisApiUrl?.value?.trim() || 'http://127.0.0.1:8000/api/v1';

    const validStudents = (students || [])
      .filter(s => s && (s.emis_student_id || s.id || s.student_id))
      .map(s => ({
        ...s,
        emis_student_id: Number(s.emis_student_id || s.id || s.student_id || 0),
        full_name: String(s.full_name || s.name || s.nama || 'TANPA NAMA').trim()
      }));

    if (validStudents.length === 0) {
      alert('Tidak ada data siswa yang valid untuk dikirim ke SIS.');
      return false;
    }

    // Set UI state ke aktif mengirim
    btnSyncSis.disabled = true;
    btnMassExport.disabled = true;
    if (btnSyncCachedSis) btnSyncCachedSis.disabled = true;
    if (btnDownloadCached) btnDownloadCached.disabled = true;

    progressArea.classList.remove('hidden');
    progressBar.style.width = '0%';
    progressPercent.textContent = '0%';
    progressCounts.textContent = `0 / ${validStudents.length} Siswa`;
    progressStatusMsg.textContent = isPartial
      ? `Mengamankan ${validStudents.length} data siswa ke SIS (Sinkronisasi Otomatis)...`
      : 'Menghubungkan ke API backend SIS...';

    activeSyncClient = new EmisSyncClient({ apiUrl, token, chunkSize: 50 });

    try {
      const syncResult = await activeSyncClient.syncAll(instId, validStudents, (p) => {
        progressBar.style.width = `${p.percent || 0}%`;
        progressPercent.textContent = `${p.percent || 0}%`;
        progressCounts.textContent = `${p.processedCount || 0} / ${p.totalCount || 0} Siswa`;
        progressStatusMsg.textContent = p.message || 'Mengirim data ke SIS...';
      });

      const s = syncResult.summary;
      if (isPartial) {
        alert(`⚠️ SINKRONISASI DATA TERSIMPAN BERHASIL!\n\nSebanyak ${students.length} data siswa berhasil diamankan dan dikirim ke server SIS!\n\nBatch ID: ${syncResult.batchUuid}\nSiswa Baru Masuk: ${s.inserted}\nSiswa Diperbarui: ${s.updated}\nSiswa Gagal: ${s.failed || 0}\n\n💡 Catatan: Seluruh data aman dan tidak akan menimpa detail siswa yang sudah ada. Anda dapat melanjutkan sisa siswa kapan saja dengan mengklik tombol sinkronisasi lagi.`);
      } else {
        alert(`🎉 SINKRONISASI KE SIS 100% SUKSES!\n\nBatch ID: ${syncResult.batchUuid}\nTotal Siswa: ${s.total || students.length}\nSiswa Baru Masuk: ${s.inserted}\nSiswa Diperbarui: ${s.updated}\nSiswa Gagal: ${s.failed || 0}\n\nSemua data kesiswaan telah berhasil tersimpan di sistem SIS Madrasah!`);
      }
      return true;
    } catch (err) {
      const detail = err.message === 'Failed to fetch'
        ? 'Failed to fetch (Koneksi ke endpoint API SIS terputus. Pastikan URL diawali https:// dan server API aktif).'
        : err.message;
      alert('Gagal melakukan sinkronisasi ke SIS: ' + detail);
      return false;
    } finally {
      activeSyncClient = null;
      btnSyncSis.disabled = false;
      btnMassExport.disabled = false;
      if (btnDownloadCached) btnDownloadCached.disabled = false;
      updateSyncButtonState();
      progressArea.classList.add('hidden');
    }
  }

  if (btnSyncSis) {
    btnSyncSis.addEventListener('click', () => {
      const instId = pageStatus?.snifferState?.institutionId;
      if (!instId) {
        alert('ID Madrasah belum terdeteksi. Buka menu Kesiswaan di portal EMIS terlebih dahulu.');
        return;
      }
      const token = txtSisToken?.value?.trim();
      if (!token) {
        alert('Token sinkronisasi SIS belum diisi. Buka Pengaturan Koneksi SIS dan masukkan token.');
        return;
      }

      btnSyncSis.disabled = true;
      btnMassExport.disabled = true;
      if (btnSyncCachedSis) btnSyncCachedSis.disabled = true;
      progressArea.classList.remove('hidden');
      progressBar.style.width = '0%';
      progressPercent.textContent = '0%';
      progressCounts.textContent = 'Menyiapkan data siswa...';
      progressStatusMsg.textContent = currentCachedCount > 0
        ? `Memeriksa memori lokal (${currentCachedCount} siswa sudah didata)...`
        : 'Menghubungkan ke API kesiswaan EMIS...';

      chrome.tabs.sendMessage(activeTabId, {
        action: 'START_MASS_EXPORT',
        institutionId: instId
      }, async (resp) => {
        if (chrome.runtime.lastError) {
          btnSyncSis.disabled = false;
          btnMassExport.disabled = false;
          if (btnSyncCachedSis) btnSyncCachedSis.disabled = false;
          progressArea.classList.add('hidden');
          alert('Gagal mengambil data dari EMIS: ' + chrome.runtime.lastError.message);
          return;
        }

        const resData = resp?.result?.data || {};
        const students = resData.results || [];
        const isTimedOut = !!resData.timedOut;
        const isPartial = !!resData.isPartial || isTimedOut;

        if (students.length === 0) {
          btnSyncSis.disabled = false;
          btnMassExport.disabled = false;
          if (btnSyncCachedSis) btnSyncCachedSis.disabled = false;
          progressArea.classList.add('hidden');
          alert(resData.error || resp.result?.error || resp?.error || 'Tidak ada data siswa yang tersedia untuk dikirim.');
          return;
        }

        updateCacheUI(students.length);

        // Jika koneksi terhenti di tengah jalan / timeout (misal gagal di 264 siswa), tetap kirim otomatis ke SIS!
        if (isTimedOut || isPartial) {
          progressStatusMsg.textContent = `Koneksi EMIS terputus/selesai sebagian. Mengamankan ${students.length} siswa tersimpan ke SIS...`;
        }

        await executeSyncToSis(students, isPartial);
      });
    });
  }
});
