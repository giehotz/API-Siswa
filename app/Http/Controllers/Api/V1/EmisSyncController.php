<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\EmisSyncRequest;
use App\Models\Institution;
use App\Models\SyncBatch;
use App\Services\Emis\StudentUpsertService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class EmisSyncController extends Controller
{
    /**
     * Memulai sesi batch sinkronisasi baru.
     */
    public function start(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'institution_id' => ['required', 'integer'],
            'total_expected' => ['nullable', 'integer', 'min:0'],
        ]);

        /** @var Institution $institution */
        $institution = $request->attributes->get('current_institution');

        $uuid = (string) Str::uuid();

        $batch = SyncBatch::withoutGlobalScopes()->create([
            'uuid' => $uuid,
            'institution_id' => $institution->id,
            'status' => 'running',
            'total_expected' => (int) ($validated['total_expected'] ?? 0),
            'total_received' => 0,
            'inserted_count' => 0,
            'updated_count' => 0,
            'failed_count' => 0,
            'started_at' => now(),
        ]);

        activity('emis_sync')
            ->causedBy($institution)
            ->performedOn($batch)
            ->withProperties(['total_expected' => $batch->total_expected])
            ->log("Sesi sinkronisasi dibuka (UUID: {$batch->uuid})");

        return response()->json([
            'success' => true,
            'batch_uuid' => $batch->uuid,
            'status' => $batch->status,
            'message' => 'Sesi sinkronisasi berhasil dibuka.',
        ], 201);
    }

    /**
     * Memproses satu chunk potongan siswa (maks. 50) secara idempoten.
     */
    public function sync(EmisSyncRequest $request, StudentUpsertService $upsertService): JsonResponse
    {
        /** @var Institution $institution */
        $institution = $request->attributes->get('current_institution');

        $batchUuid = $request->input('batch_uuid');

        /** @var SyncBatch $batch */
        $batch = SyncBatch::withoutGlobalScopes()
            ->where('institution_id', $institution->id)
            ->where('uuid', $batchUuid)
            ->first();

        if (! $batch) {
            // Jika batch belum terdaftar lewat /start, buat batch otomatis untuk kenyamanan integrasi
            $batch = SyncBatch::withoutGlobalScopes()->create([
                'uuid' => $batchUuid,
                'institution_id' => $institution->id,
                'status' => 'running',
                'total_expected' => 0,
                'total_received' => 0,
                'inserted_count' => 0,
                'updated_count' => 0,
                'failed_count' => 0,
                'started_at' => now(),
            ]);
        }

        $students = (array) $request->input('students', []);
        $chunkIndex = (int) $request->input('chunk.index', 1);

        $result = $upsertService->upsertChunk(
            $students,
            $institution->id,
            $batch->id,
            $chunkIndex
        );

        // Update akumulasi angka pada batch
        $batch->increment('total_received', count($students));
        $batch->increment('inserted_count', $result['inserted']);
        $batch->increment('updated_count', $result['updated']);
        $batch->increment('failed_count', count($result['failed']));

        return response()->json([
            'success' => true,
            'batch_uuid' => $batch->uuid,
            'chunk' => $chunkIndex,
            'inserted' => $result['inserted'],
            'updated' => $result['updated'],
            'skipped' => $result['skipped'],
            'failed' => $result['failed'],
        ]);
    }

    /**
     * Menutup sesi batch sinkronisasi dan mengembalikan laporan ringkasan.
     */
    public function finish(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'batch_uuid' => ['required', 'string', 'uuid'],
            'status' => ['nullable', 'string', 'in:completed,partial,failed'],
            'notes' => ['nullable', 'string'],
        ]);

        /** @var Institution $institution */
        $institution = $request->attributes->get('current_institution');

        /** @var SyncBatch|null $batch */
        $batch = SyncBatch::withoutGlobalScopes()
            ->where('institution_id', $institution->id)
            ->where('uuid', $validated['batch_uuid'])
            ->first();

        if (! $batch) {
            return response()->json([
                'success' => false,
                'message' => 'Batch sinkronisasi tidak ditemukan.',
            ], 404);
        }

        $targetStatus = $validated['status'] ?? ($batch->failed_count > 0 ? 'partial' : 'completed');
        $finishedAt = now();

        $batch->update([
            'status' => $targetStatus,
            'finished_at' => $finishedAt,
            'notes' => $validated['notes'] ?? $batch->notes,
        ]);

        activity('emis_sync')
            ->causedBy($institution)
            ->performedOn($batch)
            ->withProperties([
                'status' => $batch->status,
                'total_received' => $batch->total_received,
                'inserted' => $batch->inserted_count,
                'updated' => $batch->updated_count,
            ])
            ->log("Sesi sinkronisasi selesai dengan status {$batch->status}");

        $durationSeconds = $batch->started_at ? $finishedAt->diffInSeconds($batch->started_at) : 0;

        return response()->json([
            'success' => true,
            'batch_uuid' => $batch->uuid,
            'status' => $batch->status,
            'summary' => [
                'total_expected' => $batch->total_expected,
                'total_received' => $batch->total_received,
                'inserted' => $batch->inserted_count,
                'updated' => $batch->updated_count,
                'failed' => $batch->failed_count,
                'duration_seconds' => $durationSeconds,
            ],
            'message' => 'Sesi sinkronisasi selesai.',
        ]);
    }
}
