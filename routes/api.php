<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\EmisStatusController;
use App\Http\Controllers\Api\V1\EmisSyncController;
use App\Http\Controllers\Api\V1\StudentController;
use App\Http\Controllers\Api\V1\SyncBatchController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'name' => config('app.name', 'SIS EMIS API'),
        'version' => '1.0.0',
        'status' => 'active',
        'endpoints' => [
            'v1' => url('/api/v1'),
            'docs' => url('/docs/api'),
        ],
    ]);
});

Route::prefix('v1')->group(function () {
    Route::get('/', function () {
        return response()->json([
            'name' => config('app.name', 'SIS EMIS API'),
            'version' => 'v1',
            'status' => 'active',
            'message' => 'SIS EMIS API v1 siap digunakan.',
            'endpoints' => [
                'emis_status' => url('/api/v1/emis/status'),
                'students' => url('/api/v1/students'),
                'students_rombels' => url('/api/v1/students/rombels'),
                'sync_batches' => url('/api/v1/sync-batches'),
            ],
        ]);
    });

    // Autentikasi Admin Dashboard
    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        // Pembacaan Data Kesiswaan & Riwayat Sinkronisasi (Tahap 6 & Integrasi Eksternal)
        Route::get('/students', [StudentController::class, 'index']);
        Route::get('/students/rombels', [StudentController::class, 'rombels']);
        Route::get('/students/export-json', [StudentController::class, 'exportJson']);
        Route::get('/students/{student}', [StudentController::class, 'show']);

        Route::get('/sync-batches', [SyncBatchController::class, 'index']);
        Route::get('/sync-batches/{syncBatch}', [SyncBatchController::class, 'show']);

        // Endpoint Sinkronisasi EMIS (Token Madrasah Sanctum: emis:sync)
        Route::middleware(['emis.institution', 'throttle:60,1'])->prefix('emis')->group(function () {
            Route::get('/status', [EmisStatusController::class, 'show']);
            Route::post('/sync/start', [EmisSyncController::class, 'start']);
            Route::post('/sync', [EmisSyncController::class, 'sync'])->middleware('idempotency');
            Route::post('/sync/finish', [EmisSyncController::class, 'finish']);
        });
    });
});
