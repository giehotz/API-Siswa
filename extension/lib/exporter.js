/**
 * exporter.js - EMIS XLSX Exporter
 * Converts normalized sheet data into an Excel (.xlsx) workbook using local SheetJS.
 */

(function (root, factory) {
  if (typeof module !== 'undefined' && module.exports) {
    const XLSX = require('../vendor/xlsx.full.min.js');
    module.exports = factory(XLSX);
  } else {
    root.EmisExporter = factory(root.XLSX);
  }
})(typeof globalThis !== 'undefined' ? globalThis : this, function (XLSX) {
  'use strict';

  if (!XLSX) {
    console.error('SheetJS (XLSX) is required by EmisExporter.');
  }

  /**
   * Format current date and time for filename
   */
  function getTimestampString() {
    const d = new Date();
    const pad = n => String(n).padStart(2, '0');
    const y = d.getFullYear();
    const m = pad(d.getMonth() + 1);
    const day = pad(d.getDate());
    const hr = pad(d.getHours());
    const min = pad(d.getMinutes());
    const sec = pad(d.getSeconds());
    return `${y}${m}${day}_${hr}${min}${sec}`;
  }

  /**
   * Generate sanitized filename without student names per PRD Sec 9
   */
  function generateFilename(institutionName) {
    const ts = getTimestampString();
    let nameSlug = 'Ekspor';
    if (institutionName && typeof institutionName === 'string') {
      nameSlug = institutionName.replace(/[^a-zA-Z0-9_-]/g, '_').substring(0, 30);
    }
    return `EMIS_Siswa_${nameSlug}_${ts}.xlsx`;
  }

  /**
   * Calculate automatic column widths based on maximum string lengths
   */
  function computeColWidths(rows) {
    if (!rows || rows.length === 0) return [];
    const keys = Object.keys(rows[0]);
    return keys.map(k => {
      let maxLen = k.length;
      for (let i = 0; i < Math.min(rows.length, 100); i++) {
        const val = rows[i][k];
        if (val !== null && val !== undefined) {
          const strLen = String(val).length;
          if (strLen > maxLen) maxLen = strLen;
        }
      }
      return { wch: Math.min(Math.max(maxLen + 3, 10), 60) };
    });
  }

  /**
   * Build XLSX Workbook from normalized sheets
   * @param {Object} sheets Object mapping sheetName -> array of row objects
   * @returns {Object} SheetJS workbook
   */
  function buildWorkbook(sheets) {
    const wb = XLSX.utils.book_new();

    for (const [sheetName, rows] of Object.entries(sheets)) {
      if (!Array.isArray(rows) || rows.length === 0) {
        const emptyWs = XLSX.utils.aoa_to_sheet([['Data Kosong']]);
        XLSX.utils.book_append_sheet(wb, emptyWs, sheetName);
        continue;
      }

      const ws = XLSX.utils.json_to_sheet(rows);
      ws['!cols'] = computeColWidths(rows);
      XLSX.utils.book_append_sheet(wb, ws, sheetName);
    }

    return wb;
  }

  /**
   * Export workbook to binary Uint8Array
   */
  function exportToBuffer(sheets) {
    const wb = buildWorkbook(sheets);
    const ab = XLSX.write(wb, { bookType: 'xlsx', type: 'array' });
    return new Uint8Array(ab);
  }

  /**
   * Trigger direct browser download of workbook
   * @param {Object} sheets Normalized sheets
   * @param {string} [filename] Optional filename
   */
  function downloadWorkbook(sheets, filename) {
    const fname = filename || generateFilename();
    const buffer = exportToBuffer(sheets);
    const blob = new Blob([buffer], {
      type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    });

    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = fname;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);

    setTimeout(() => {
      URL.revokeObjectURL(url);
    }, 5000);

    return fname;
  }

  /**
   * Build XLSX Workbook for a SINGLE SHEET
   */
  function buildSingleSheetWorkbook(rows, sheetName = 'Data_Siswa_Lengkap') {
    const wb = XLSX.utils.book_new();
    const ws = XLSX.utils.json_to_sheet(rows || []);
    ws['!cols'] = computeColWidths(rows || []);
    XLSX.utils.book_append_sheet(wb, ws, sheetName);
    return wb;
  }

  /**
   * Trigger browser download for a SINGLE SHEET workbook
   */
  function downloadSingleSheet(rows, sheetName = 'Data_Siswa_Lengkap', filename) {
    const fname = filename || generateFilename();
    const wb = buildSingleSheetWorkbook(rows, sheetName);
    const ab = XLSX.write(wb, { bookType: 'xlsx', type: 'array' });
    const buffer = new Uint8Array(ab);
    const blob = new Blob([buffer], {
      type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    });

    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = fname;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);

    setTimeout(() => {
      URL.revokeObjectURL(url);
    }, 5000);

    return fname;
  }

  return {
    generateFilename,
    buildWorkbook,
    buildSingleSheetWorkbook,
    exportToBuffer,
    downloadWorkbook,
    downloadSingleSheet
  };
});
