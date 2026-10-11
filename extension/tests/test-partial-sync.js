/**
 * test-partial-sync.js
 * Unit test verifying partial data retention and sending saved students to SIS.
 * Scenario: e.g. 264 students gathered before timeout or connection drop.
 */

const assert = require('assert');
const EmisSyncClient = require('../lib/sync-client.js');

console.log('=== TEST PARTIAL & SAVED STUDENTS SYNC TO SIS ===\n');

let mockRequests = [];
global.fetch = async (url, options) => {
  mockRequests.push({ url, options });

  if (url.includes('/emis/sync/start')) {
    const body = JSON.parse(options.body);
    return {
      status: 200,
      ok: true,
      json: async () => ({
        success: true,
        data: {
          batch_uuid: 'batch-partial-264-uuid',
          status: 'running',
          institution_id: body.institution_id
        }
      })
    };
  }

  if (url.includes('/emis/sync/finish')) {
    return {
      status: 200,
      ok: true,
      json: async () => ({
        success: true,
        data: {
          status: 'completed',
          summary: {
            total: 264,
            inserted: 64,
            updated: 200,
            failed: 0
          }
        }
      })
    };
  }

  if (url.includes('/emis/sync')) {
    const body = JSON.parse(options.body);
    return {
      status: 200,
      ok: true,
      json: async () => ({
        success: true,
        data: {
          batch_uuid: body.batch_uuid,
          chunk: body.chunk.index,
          inserted: 10,
          updated: body.students.length - 10,
          failed: []
        }
      })
    };
  }

  return { status: 404, ok: false, json: async () => ({ message: 'Not found' }) };
};

const mockStorage = new Map();
global.localStorage = {
  getItem: (k) => mockStorage.get(k) || null,
  setItem: (k, v) => mockStorage.set(k, String(v)),
  removeItem: (k) => mockStorage.delete(k)
};

(async () => {
  // Simulate 264 saved students in memory
  const savedCount = 264;
  const savedStudents = Array.from({ length: savedCount }, (_, i) => ({
    emis_student_id: 2000 + i,
    full_name: `Siswa Tersimpan ${i + 1}`,
    gender: i % 2 === 0 ? 'L' : 'P',
    _detailSuccess: true
  }));

  console.log(`[Step 1] Preparing sync client for ${savedCount} saved students...`);
  const client = new EmisSyncClient({
    apiUrl: 'http://127.0.0.1:8000/api/v1',
    token: 'operator-token-abc',
    chunkSize: 50
  });

  const progressSnapshots = [];
  const syncResult = await client.syncAll(65859, savedStudents, (p) => {
    progressSnapshots.push(p);
  });

  assert.strictEqual(syncResult.success, true);
  assert.strictEqual(syncResult.batchUuid, 'batch-partial-264-uuid');
  assert.strictEqual(syncResult.summary.total, 264);
  console.log(`✓ Partial sync completed: ${syncResult.summary.total} students dispatched.`);

  // 264 students / 50 chunk size = 6 chunks (50, 50, 50, 50, 50, 14)
  // Total HTTP requests: 1 start + 6 chunks + 1 finish = 8 requests
  const chunkRequests = mockRequests.filter(r => r.url.endsWith('/emis/sync'));
  assert.strictEqual(chunkRequests.length, 6, 'Must chunk 264 students into 6 requests');
  console.log(`✓ 264 students split into ${chunkRequests.length} chunks of max 50.`);

  const lastChunkBody = JSON.parse(chunkRequests[5].options.body);
  assert.strictEqual(lastChunkBody.students.length, 14, 'Last chunk must have 14 students');
  assert.strictEqual(lastChunkBody.chunk.index, 6);
  assert.strictEqual(lastChunkBody.chunk.total, 6);
  console.log('✓ Last chunk size and indices verified.');

  console.log('\n========================================');
  console.log('PARTIAL 264 STUDENTS SYNC TEST 100% PASSED!');
  console.log('========================================');
})();
