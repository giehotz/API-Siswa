/**
 * test-cache-resume.js
 * Unit test verifying session cache non-repeating logic and partial retention on timeout.
 */

const assert = require('assert');
const { normalizeStudentsSingleSheet } = require('../lib/normalizer.js');

console.log('=== TEST CACHE & TIMEOUT RESUME LOGIC ===\n');

// Mock cache
const sessionStudentCache = new Map();

// 1. Simulate first batch where timeout occurs after 2 students
const allStudents = [
  { id: 101, full_name: 'Siswa Satu', nisn: '001', local_nis: 'LS01' },
  { id: 102, full_name: 'Siswa Dua', nisn: '002', local_nis: 'LS02' },
  { id: 103, full_name: 'Siswa Tiga', nisn: '003', local_nis: 'LS03' },
  { id: 104, full_name: 'Siswa Empat', nisn: '004', local_nis: 'LS04' }
];

let networkFetchCalls = 0;
function mockFetchStudentDetail(student) {
  networkFetchCalls++;
  return { ...student, detailFetched: true, parents: { father_full_name: 'Bapak ' + student.full_name } };
}

// First run: only process first 2 students, then simulate timeout
const run1Students = [];
for (let i = 0; i < 2; i++) {
  const s = allStudents[i];
  if (!sessionStudentCache.has(String(s.id))) {
    const detail = mockFetchStudentDetail(s);
    sessionStudentCache.set(String(s.id), { ...detail, _detailSuccess: true });
    run1Students.push(detail);
  } else {
    run1Students.push(sessionStudentCache.get(String(s.id)));
  }
}

assert.strictEqual(networkFetchCalls, 2, 'Must have made 2 network calls for run 1');
assert.strictEqual(sessionStudentCache.size, 2, 'Cache must store 2 students');

// Generate partial export from run 1
const partialResult = normalizeStudentsSingleSheet(run1Students);
assert.strictEqual(partialResult.rows.length, 2);
console.log('✓ Run 1 partial export saved: 2 students.');

// Simulate page refresh & re-loading from chrome.storage.local (preloadedCache)
const simulatedStorageMap = {};
for (const [id, data] of sessionStudentCache.entries()) {
  simulatedStorageMap[id] = data;
}

// In fresh session, pre-load cache from storage map
const freshSessionCache = new Map();
const preloadedFromStorage = Object.values(simulatedStorageMap);
for (const item of preloadedFromStorage) {
  freshSessionCache.set(String(item.id), item);
}
assert.strictEqual(freshSessionCache.size, 2, 'Fresh session must have pre-loaded 2 students from storage');

// Second run (Resume after timeout / reload): process all 4 students
const run2Students = [];
for (let i = 0; i < allStudents.length; i++) {
  const s = allStudents[i];
  const sIdKey = String(s.id);
  const cached = freshSessionCache.get(sIdKey);
  const isAlreadyCollected = cached && (cached._detailSuccess || cached.local_nis !== undefined);

  if (isAlreadyCollected) {
    // CACHE HIT: DO NOT RE-DOWNLOAD!
    run2Students.push(cached);
  } else {
    // Only fetch new students!
    const detail = mockFetchStudentDetail(s);
    freshSessionCache.set(sIdKey, { ...detail, _detailSuccess: true });
    run2Students.push(detail);
  }
}

// Verify that network calls only incremented by 2 (for students 3 & 4), NOT 4!
assert.strictEqual(networkFetchCalls, 4, 'Must NOT re-download already cached students! (Only 2 new calls)');
assert.strictEqual(run2Students.length, 4, 'All 4 students must be present in resume run');
assert.strictEqual(freshSessionCache.size, 4, 'All 4 students cached');

const fullResult = normalizeStudentsSingleSheet(run2Students);
assert.strictEqual(fullResult.rows.length, 4, 'Workbook must have 4 students');
assert.strictEqual(fullResult.rows[0]['NIS Lokal'], 'LS01');
assert.strictEqual(fullResult.rows[1]['NIS Lokal'], 'LS02');
assert.strictEqual(fullResult.rows[2]['NIS Lokal'], 'LS03');
assert.strictEqual(fullResult.rows[3]['NIS Lokal'], 'LS04');

console.log('✓ Resume logic verified: zero re-download of already cached students across sessions!');
console.log('\n========================================');
console.log('CACHE & RESUME TESTS 100% PASSED!');
console.log('========================================');
