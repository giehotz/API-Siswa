/**
 * test-sync-client.js
 * Unit test verifying EmisSyncClient: chunking, idempotency key, retry with backoff, and sync lifecycle.
 */

const assert = require('assert');
const EmisSyncClient = require('../lib/sync-client.js');

console.log('=== TEST EMIS SYNC CLIENT MODULE ===\n');

// Mock fetch environment
let mockRequests = [];
let failCountUntilSuccess = 0;

global.fetch = async (url, options) => {
  mockRequests.push({ url, options });

  // Simulate 500 retry on specific URL if set
  if (failCountUntilSuccess > 0 && url.includes('/emis/sync') && !url.includes('/start') && !url.includes('/finish')) {
    failCountUntilSuccess--;
    return {
      status: 500,
      ok: false,
      json: async () => ({ message: 'Temporary Server Error' })
    };
  }

  if (url.includes('/emis/status')) {
    return {
      status: 200,
      ok: true,
      json: async () => ({
        success: true,
        data: {
          institution: { id: 65859, name: "MIS Mathla'ul Anwar" },
          students_count: 120
        }
      })
    };
  }

  if (url.includes('/emis/sync/start')) {
    const body = JSON.parse(options.body);
    return {
      status: 200,
      ok: true,
      json: async () => ({
        success: true,
        data: {
          batch_uuid: 'batch-test-uuid-12345',
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
            total: 120,
            inserted: 20,
            updated: 100,
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

  return {
    status: 404,
    ok: false,
    json: async () => ({ message: 'Not found' })
  };
};

// Global localStorage mock for Node.js
const mockStorage = new Map();
global.localStorage = {
  getItem: (k) => mockStorage.get(k) || null,
  setItem: (k, v) => mockStorage.set(k, String(v)),
  removeItem: (k) => mockStorage.delete(k)
};

(async () => {
  // Test 1: Instantiation & checkStatus
  console.log('Test 1: Check status...');
  const client = new EmisSyncClient({
    apiUrl: 'http://127.0.0.1:8000/api/v1/',
    token: 'test-token-123',
    chunkSize: 50
  });

  assert.strictEqual(client.apiUrl, 'http://127.0.0.1:8000/api/v1');
  const statusRes = await client.checkStatus();
  assert.strictEqual(statusRes.success, true);
  assert.strictEqual(statusRes.data.institution.id, 65859);
  console.log('✓ Status endpoint verified.');

  // Test 2: Full syncAll workflow (120 students in chunks of 50 -> 3 chunks)
  console.log('\nTest 2: Full syncAll (120 students in 3 chunks)...');
  mockRequests = [];
  const fakeStudents = Array.from({ length: 120 }, (_, i) => ({
    emis_student_id: 1000 + i,
    full_name: `Siswa ${i + 1}`,
    gender: i % 2 === 0 ? 'L' : 'P'
  }));

  const progressEvents = [];
  const syncResult = await client.syncAll(65859, fakeStudents, (p) => {
    progressEvents.push(p);
  });

  assert.strictEqual(syncResult.success, true);
  assert.strictEqual(syncResult.batchUuid, 'batch-test-uuid-12345');
  assert.strictEqual(progressEvents.length >= 4, true);

  // Check request counts: 1 start + 3 chunks + 1 finish = 5 requests
  assert.strictEqual(mockRequests.length, 5, 'Must make exactly 5 HTTP requests (1 start, 3 chunks, 1 finish)');

  // Verify Idempotency-Key headers
  const chunkRequests = mockRequests.filter(r => r.url.endsWith('/emis/sync'));
  assert.strictEqual(chunkRequests.length, 3);
  assert.strictEqual(chunkRequests[0].options.headers['Idempotency-Key'], 'batch-test-uuid-12345-1');
  assert.strictEqual(chunkRequests[1].options.headers['Idempotency-Key'], 'batch-test-uuid-12345-2');
  assert.strictEqual(chunkRequests[2].options.headers['Idempotency-Key'], 'batch-test-uuid-12345-3');
  console.log('✓ Full sync cycle and Idempotency-Key headers verified.');

  // Test 3: Retry mechanism with transient 500 error
  console.log('\nTest 3: Retry mechanism with backoff on HTTP 500...');
  failCountUntilSuccess = 1; // 1 fail, then succeed on retry
  mockRequests = [];
  const smallStudents = fakeStudents.slice(0, 10);
  const retryResult = await client.syncAll(65859, smallStudents);
  assert.strictEqual(retryResult.success, true);
  console.log('✓ Retry on transient error succeeded without failure.');

  // Test 4: Cancellation
  console.log('\nTest 4: Cancellation support...');
  const cancelClient = new EmisSyncClient({
    apiUrl: 'http://127.0.0.1:8000/api/v1',
    token: 'test-token',
    chunkSize: 10
  });

  cancelClient.cancel();
  try {
    await cancelClient.syncAll(65859, fakeStudents);
    assert.fail('Should have thrown on cancel');
  } catch (err) {
    assert.strictEqual(err.message.includes('dibatalkan'), true);
  }
  console.log('✓ Cancellation verified.');

  console.log('\n========================================');
  console.log('ALL EMIS SYNC CLIENT TESTS PASSED (100%)!');
  console.log('========================================');
})().catch(err => {
  console.error('Test Failed:', err);
  process.exit(1);
});
