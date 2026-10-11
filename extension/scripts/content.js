/**
 * content.js - ISOLATED World Bridge
 * Bridges messages between the popup UI and the MAIN world inpage-sniffer.
 * Manages active batch watchdog to ensure timeouts never kill in-progress downloads.
 */

(function () {
  'use strict';

  let snifferState = {
    isReady: false,
    hasAuth: false,
    lastDetailUrl: null,
    lastListUrl: null,
    institutionId: null,
    cachedCount: 0
  };

  const pendingRequests = new Map();
  let watchdogResetFn = null;

  // --- Persistent Storage Helpers (chrome.storage.local) ---
  function getStorageKey(instId) {
    return `emis_students_${instId || 'default'}`;
  }

  function getListStorageKey(instId) {
    return `emis_list_${instId || 'default'}`;
  }

  async function getCachedStudentsMap(instId) {
    const key = getStorageKey(instId);
    return new Promise((resolve) => {
      chrome.storage.local.get([key], (res) => {
        resolve(res && res[key] ? res[key] : {});
      });
    });
  }

  async function saveStudentRecord(instId, student) {
    if (!student || !student.id) return;
    const key = getStorageKey(instId);
    chrome.storage.local.get([key], (res) => {
      const map = (res && res[key]) ? res[key] : {};
      map[String(student.id)] = student;
      chrome.storage.local.set({ [key]: map });
    });
  }

  async function saveListStudents(instId, students) {
    if (!Array.isArray(students)) return;
    const key = getListStorageKey(instId);
    chrome.storage.local.set({ [key]: students });
  }

  async function getListStudents(instId) {
    const key = getListStorageKey(instId);
    return new Promise((resolve) => {
      chrome.storage.local.get([key], (res) => {
        resolve(res && res[key] ? res[key] : []);
      });
    });
  }

  async function clearCache(instId) {
    const key = getStorageKey(instId);
    const listKey = getListStorageKey(instId);
    chrome.storage.local.remove([key, listKey]);
  }

  // Listen to postMessage events from inpage-sniffer.js
  window.addEventListener('message', (event) => {
    if (!event.data || typeof event.data !== 'object') return;

    if (event.data.source === 'EMIS_SNIFFER') {
      const { type, payload } = event.data;
      if (type === 'SNIFFER_READY' || type === 'AUTH_DETECTED') {
        snifferState.isReady = true;
        snifferState.hasAuth = payload.hasAuth || snifferState.hasAuth;
        if (payload.institutionId) snifferState.institutionId = payload.institutionId;
        if (payload.lastListEndpoint) snifferState.lastListUrl = payload.lastListEndpoint;
        if (payload.lastDetailEndpoint) snifferState.lastDetailUrl = payload.lastDetailEndpoint;
        if (payload.cachedCount !== undefined) snifferState.cachedCount = payload.cachedCount;
      } else if (type === 'DETAIL_ENDPOINT_DETECTED') {
        snifferState.lastDetailUrl = payload.url;
        snifferState.hasAuth = payload.hasAuth || snifferState.hasAuth;
      } else if (type === 'LIST_ENDPOINT_DETECTED') {
        snifferState.lastListUrl = payload.url;
        snifferState.institutionId = payload.institutionId || snifferState.institutionId;
        snifferState.hasAuth = payload.hasAuth || snifferState.hasAuth;
        if (payload.cachedCount !== undefined) snifferState.cachedCount = payload.cachedCount;
      } else if (type === 'STUDENT_SAVED_TO_CACHE') {
        saveStudentRecord(payload.institutionId || snifferState.institutionId, payload.student);
        snifferState.cachedCount = payload.cachedCount || (snifferState.cachedCount + 1);
      } else if (type === 'LIST_STUDENTS_CACHED') {
        saveListStudents(payload.institutionId || snifferState.institutionId, payload.students);
      } else if (type === 'CACHE_RESET') {
        clearCache(snifferState.institutionId);
        snifferState.cachedCount = 0;
      } else if (type === 'BATCH_PROGRESS') {
        // Reset watchdog timer on every progress report!
        if (watchdogResetFn) watchdogResetFn();

        // Forward progress event to popup
        chrome.runtime.sendMessage({
          action: 'ON_BATCH_PROGRESS',
          progress: payload
        }).catch(() => {});
      }
    } else if (event.data.source === 'EMIS_SNIFFER_RESPONSE') {
      const { requestId, status, ok, data, error } = event.data;
      const resolver = pendingRequests.get(requestId);
      if (resolver) {
        pendingRequests.delete(requestId);
        resolver({ status, ok, data, error });
      }
    }
  });

  // Inject inpage-sniffer.js into the document head
  function injectSniffer() {
    try {
      const script = document.createElement('script');
      script.src = chrome.runtime.getURL('scripts/inpage-sniffer.js');
      script.onload = () => script.remove();
      (document.head || document.documentElement).appendChild(script);
    } catch (err) {
      console.warn('[EMIS Exporter] Failed to inject sniffer script:', err);
    }
  }

  // Helper to dispatch fetch request to inpage context
  function dispatchPageFetch(action, url, options = {}) {
    return new Promise((resolve) => {
      const requestId = 'req_' + Date.now() + '_' + Math.random().toString(36).substring(2, 7);
      let silenceTimer = null;

      function resetSilenceTimer() {
        if (silenceTimer) clearTimeout(silenceTimer);
        // Timeout fires only if there is 45 seconds of complete silence
        silenceTimer = setTimeout(async () => {
          if (pendingRequests.has(requestId)) {
            pendingRequests.delete(requestId);
            watchdogResetFn = null;

            // On timeout, retrieve whatever students are already saved in storage
            const instId = options?.institutionId || snifferState.institutionId;
            const cachedMap = await getCachedStudentsMap(instId);
            const savedStudents = Object.values(cachedMap);

            resolve({
              status: 200,
              ok: true,
              data: {
                results: savedStudents,
                total: savedStudents.length,
                isPartial: true,
                timedOut: true,
                error: 'Waktu habis / koneksi terputus. Seluruh data yang sudah didata berhasil diamankan!'
              }
            });
          }
        }, 45000);
      }

      watchdogResetFn = resetSilenceTimer;
      resetSilenceTimer();

      pendingRequests.set(requestId, (res) => {
        if (silenceTimer) clearTimeout(silenceTimer);
        watchdogResetFn = null;
        resolve(res);
      });

      window.postMessage({
        source: 'EMIS_EXTENSION_DISPATCH',
        action: action,
        requestId: requestId,
        url: url,
        options: options
      }, '*');
    });
  }

  // Handle messages from popup
  chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
    if (!message || !message.action) return false;

    (async () => {
      try {
        if (message.action === 'GET_PAGE_STATUS') {
          const href = window.location.href;
          const pathname = window.location.pathname;
          let currentStudentId = null;

          const match = pathname.match(/\/kesiswaan\/([a-zA-Z0-9+=_-]{10,})/);
          if (match) {
            currentStudentId = match[1];
          }

          // Query chrome.storage.local for accurate saved student count
          const instId = snifferState.institutionId;
          const cachedMap = await getCachedStudentsMap(instId);
          const cachedList = Object.values(cachedMap);
          snifferState.cachedCount = cachedList.length;

          sendResponse({
            success: true,
            pageUrl: href,
            pathname: pathname,
            isEmis: window.location.hostname.includes('emis.kemenag.go.id'),
            snifferState: snifferState,
            cachedCount: cachedList.length,
            currentStudentId: currentStudentId
          });
        }
        else if (message.action === 'START_MASS_EXPORT') {
          const instId = message.institutionId || snifferState.institutionId;
          // Pre-load all saved students and student list from persistent storage
          const cachedMap = await getCachedStudentsMap(instId);
          const preloadedCache = Object.values(cachedMap);
          const cachedList = await getListStudents(instId);

          const result = await dispatchPageFetch('FETCH_ALL_STUDENTS', null, {
            institutionId: instId,
            preloadedCache: preloadedCache,
            cachedListStudents: cachedList
          });

          // Ensure any results returned in the response are also committed to storage
          if (result?.data?.results && Array.isArray(result.data.results)) {
            for (const st of result.data.results) {
              if (st && st.id && (st._detailSuccess || st.local_nis !== undefined || st.parents)) {
                cachedMap[String(st.id)] = st;
              }
            }
            const key = getStorageKey(instId);
            chrome.storage.local.set({ [key]: cachedMap });
          }

          sendResponse({ success: true, result });
        }
        else if (message.action === 'GET_SAVED_STUDENTS') {
          const instId = message.institutionId || snifferState.institutionId;
          const cachedMap = await getCachedStudentsMap(instId);
          sendResponse({
            success: true,
            students: Object.values(cachedMap)
          });
        }
        else if (message.action === 'CANCEL_MASS_EXPORT') {
          window.postMessage({
            source: 'EMIS_EXTENSION_DISPATCH',
            action: 'CANCEL_FETCH'
          }, '*');
          sendResponse({ success: true });
        }
        else if (message.action === 'RESET_CACHE') {
          await clearCache(snifferState.institutionId);
          snifferState.cachedCount = 0;
          window.postMessage({
            source: 'EMIS_EXTENSION_DISPATCH',
            action: 'RESET_CACHE'
          }, '*');
          sendResponse({ success: true });
        }
        else if (message.action === 'FETCH_STUDENT_BY_URL') {
          const fetchUrl = message.url;
          const result = await dispatchPageFetch('EXECUTE_PAGE_FETCH', fetchUrl);
          sendResponse({ success: true, result });
        }
        else if (message.action === 'FETCH_CURRENT_TAB_STUDENT') {
          let urlToFetch = snifferState.lastDetailUrl;
          if (!urlToFetch) {
            const pathname = window.location.pathname;
            const match = pathname.match(/\/kesiswaan\/([a-zA-Z0-9+=_-]{10,})/);
            if (match) {
              urlToFetch = `https://api-emis.kemenag.go.id/v1/students/students/${match[1]}`;
            }
          }

          if (!urlToFetch) {
            sendResponse({
              success: false,
              error: 'Belum ada endpoint detail siswa yang terdeteksi di tab ini. Pastikan Anda membuka formulir/detail siswa di EMIS.'
            });
            return;
          }

          const result = await dispatchPageFetch('EXECUTE_PAGE_FETCH', urlToFetch);
          sendResponse({ success: true, url: urlToFetch, result });
        }
        else if (message.action === 'SIS_CHECK_EMIS_SESSION') {
          const instId = message.institutionId || snifferState.institutionId;
          const cachedMap = await getCachedStudentsMap(instId);
          const studentCount = Object.keys(cachedMap).length;
          sendResponse({
            success: true,
            type: 'SIS_EMIS_STATUS_RESPONSE',
            isLoggedIn: !!snifferState.hasAuth,
            institutionId: instId,
            studentCount: studentCount,
            lastListUrl: snifferState.lastListUrl,
            lastDetailUrl: snifferState.lastDetailUrl
          });
        }
        else {
          sendResponse({ success: false, error: 'Aksi tidak dikenal.' });
        }
      } catch (err) {
        sendResponse({ success: false, error: err.message });
      }
    })();

    return true; // Keep channel open for async response
  });

  injectSniffer();
})();
