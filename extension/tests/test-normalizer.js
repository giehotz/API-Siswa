/**
 * test-normalizer.js
 * Automated unit test for normalizer, security sanitization, and XLSX export.
 */

const assert = require('assert');
const fs = require('fs');
const path = require('path');
const XLSX = require('../vendor/xlsx.full.min.js');
const { normalizeStudents, sanitizeCell } = require('../lib/normalizer.js');
const { buildWorkbook, exportToBuffer } = require('../lib/exporter.js');
const { MOCK_STUDENT_RESULTS } = require('../scripts/mock-sample.js');

console.log('=== RUNNING AUTOMATED UNIT TESTS ===\n');

// 1. Test formula injection sanitization
console.log('[Test 1] Formula Injection Defense...');
assert.strictEqual(sanitizeCell('=SUM(A1:A10)'), "'=SUM(A1:A10)");
assert.strictEqual(sanitizeCell('+12345'), "'+12345");
assert.strictEqual(sanitizeCell('-cmd|/c calc'), "'-cmd|/c calc");
assert.strictEqual(sanitizeCell('@echo off'), "'@echo off");
assert.strictEqual(sanitizeCell('Normal Text'), 'Normal Text');
assert.strictEqual(sanitizeCell(null), '');
assert.strictEqual(sanitizeCell(''), '');
console.log('✓ Formula injection sanitization passed.');

// 2. Test Default Privacy Mode (Sensitive fields DISABLED)
console.log('\n[Test 2] Normalizer with Default Privacy (includeSensitive = false)...');
const resDefault = normalizeStudents([MOCK_STUDENT_RESULTS], { includeSensitive: false });
const sheetsDef = resDefault.sheets;

assert.ok(sheetsDef['Data_Siswa'], 'Data_Siswa sheet missing');
assert.ok(sheetsDef['Data_Orang_Tua'], 'Data_Orang_Tua sheet missing');
assert.ok(sheetsDef['Data_Pembelajaran'], 'Data_Pembelajaran sheet missing');
assert.ok(sheetsDef['Data_Vaksin'], 'Data_Vaksin sheet missing');
assert.ok(sheetsDef['Data_Bantuan'], 'Data_Bantuan sheet missing');
assert.ok(sheetsDef['Log_Ekspor'], 'Log_Ekspor sheet missing');

// Verify sensitive columns are NOT present
const rowSiswaDef = sheetsDef['Data_Siswa'][0];
assert.strictEqual(rowSiswaDef['NIK Siswa'], undefined, 'NIK Siswa should be omitted by default');
assert.strictEqual(rowSiswaDef['No Kartu Keluarga'], undefined, 'No KK should be omitted by default');
assert.strictEqual(rowSiswaDef['Latitude'], undefined, 'Latitude should be omitted by default');

const rowOrangTuaDef = sheetsDef['Data_Orang_Tua'][0];
assert.strictEqual(rowOrangTuaDef['NIK Ayah'], undefined, 'NIK Ayah should be omitted by default');
assert.strictEqual(rowOrangTuaDef['NIK Ibu'], undefined, 'NIK Ibu should be omitted by default');

const rowBantuanDef = sheetsDef['Data_Bantuan'][0];
assert.strictEqual(rowBantuanDef['Penerima PKH'], undefined, 'PKH should be omitted by default');

// Verify data fidelity
assert.strictEqual(rowSiswaDef['NISN'], '0151234567');
assert.strictEqual(rowSiswaDef['Nama Lengkap'], 'AHMAD FAUZI PRATAMA');
assert.strictEqual(rowSiswaDef['Jenis Kelamin'], 'Perempuan'); // from HAR mock
assert.strictEqual(rowSiswaDef['Agama'], 'Islam');
console.log('✓ Default privacy mode passed.');

// 3. Test Sensitive Mode Enabled
console.log('\n[Test 3] Normalizer with Sensitive Mode Enabled...');
const resSens = normalizeStudents([MOCK_STUDENT_RESULTS], {
  includeSensitive: true,
  sensitiveOptions: {
    nik: true,
    kk: true,
    parentsNik: true,
    bansos: true,
    coordinates: true
  }
});
const sheetsSens = resSens.sheets;
const rowSiswaSens = sheetsSens['Data_Siswa'][0];
assert.strictEqual(rowSiswaSens['NIK Siswa'], '3201010105150001');
assert.strictEqual(rowSiswaSens['No Kartu Keluarga'], '3201010101010002');

const rowOrangTuaSens = sheetsSens['Data_Orang_Tua'][0];
assert.strictEqual(rowOrangTuaSens['NIK Ayah'], '3201010101750001');
assert.strictEqual(rowOrangTuaSens['NIK Ibu'], '3201010101800002');
console.log('✓ Sensitive mode passed.');

// 4. Test Vaccines Sheet
console.log('\n[Test 4] Vaccine Status Extraction...');
const rowVaksin = sheetsDef['Data_Vaksin'][0];
assert.strictEqual(rowVaksin['Hepatitis B'], 'Ya');
assert.strictEqual(rowVaksin['Polio'], 'Ya');
assert.strictEqual(rowVaksin['BCG'], 'Ya');
assert.strictEqual(rowVaksin['Campak'], 'Ya');
assert.strictEqual(rowVaksin['DPT'], 'Ya');
assert.strictEqual(rowVaksin['Covid-19'], 'Ya');
console.log('✓ Vaccine sheet passed.');

// 5. Test Learning Activity Sheet
console.log('\n[Test 5] Learning Activity Extraction...');
const rowLa = sheetsDef['Data_Pembelajaran'][0];
assert.strictEqual(rowLa['Nomor Absen'], 2);
assert.strictEqual(rowLa['Tanggal Mulai'], '2026-08-30');
console.log('✓ Learning activity sheet passed.');

// 6. Test XLSX Workbook Generation & Round-trip read
console.log('\n[Test 6] XLSX Workbook Export and Round-trip Validation...');
const buffer = exportToBuffer(sheetsSens);
assert.ok(buffer && buffer.length > 0, 'Buffer should not be empty');

const outPath = path.join(__dirname, 'output_test.xlsx');
// Read back in memory with SheetJS
const parsedWb = XLSX.read(Buffer.from(buffer), { type: 'buffer' });
console.log('Parsed sheet names:', parsedWb.SheetNames);
assert.deepStrictEqual(parsedWb.SheetNames, [
  'Data_Siswa',
  'Data_Orang_Tua',
  'Data_Pembelajaran',
  'Data_Vaksin',
  'Data_Bantuan',
  'Log_Ekspor'
]);

const parsedSiswa = XLSX.utils.sheet_to_json(parsedWb.Sheets['Data_Siswa']);
assert.strictEqual(parsedSiswa.length, 1);
assert.strictEqual(parsedSiswa[0]['NISN'], '0151234567');
console.log('✓ XLSX round-trip verification passed.');

// 7. Test Single Sheet Export & NIS Lokal Preservation
console.log('\n[Test 7] Single Sheet Export with NIS Lokal...');
const { normalizeStudentsSingleSheet } = require('../lib/normalizer.js');
const { buildSingleSheetWorkbook } = require('../lib/exporter.js');

const singleResult = normalizeStudentsSingleSheet([MOCK_STUDENT_RESULTS]);
assert.strictEqual(singleResult.sheetName, 'Data_Siswa_Lengkap');
assert.strictEqual(singleResult.rows.length, 1);

const rowSingle = singleResult.rows[0];
assert.strictEqual(rowSingle['NISN'], '0151234567');
assert.strictEqual(rowSingle['NIS Lokal'], '20261011002345', 'NIS Lokal must be populated and string');
assert.strictEqual(rowSingle['Nama Lengkap'], 'AHMAD FAUZI PRATAMA');
assert.strictEqual(rowSingle['Nama Ayah'], 'BAMBANG PRATAMA');
assert.strictEqual(rowSingle['Rombel'], 'Kelas  1 a');

// Build single-sheet workbook and parse back
const singleWb = buildSingleSheetWorkbook(singleResult.rows, 'Data_Siswa_Lengkap');
const singleAb = XLSX.write(singleWb, { bookType: 'xlsx', type: 'array' });
const parsedSingleWb = XLSX.read(Buffer.from(new Uint8Array(singleAb)), { type: 'buffer' });

assert.deepStrictEqual(parsedSingleWb.SheetNames, ['Data_Siswa_Lengkap'], 'Workbook must have exactly 1 sheet');
const parsedSingleRows = XLSX.utils.sheet_to_json(parsedSingleWb.Sheets['Data_Siswa_Lengkap']);
assert.strictEqual(parsedSingleRows.length, 1);
assert.strictEqual(parsedSingleRows[0]['NIS Lokal'], '20261011002345');
assert.strictEqual(parsedSingleRows[0]['NISN'], '0151234567');
console.log('✓ Single Sheet with NIS Lokal verified successfully.');

console.log('\n========================================');
console.log('ALL TESTS PASSED SUCCESSFULLY! (7/7)');
console.log('========================================');
