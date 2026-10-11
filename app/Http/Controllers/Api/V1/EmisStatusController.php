<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Institution;
use App\Models\Student;
use App\Models\SyncBatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmisStatusController extends Controller
{
    /**
     * Cek status madrasah, jumlah siswa tersimpan, dan waktu sinkronisasi terakhir.
     */
    public function show(Request $request): JsonResponse
    {
        /** @var Institution $institution */
        $institution = $request->attributes->get('current_institution');

        $studentsCount = Student::withoutGlobalScopes()
            ->where('institution_id', $institution->id)
            ->count();

        $lastBatch = SyncBatch::withoutGlobalScopes()
            ->where('institution_id', $institution->id)
            ->where('status', 'completed')
            ->latest('finished_at')
            ->first();

        return response()->json([
            'success' => true,
            'is_registered' => true,
            'institution' => [
                'id' => $institution->id,
                'emis_institution_id' => $institution->emis_institution_id,
                'name' => $institution->name,
                'nsm' => $institution->nsm,
                'npsn' => $institution->npsn,
                'is_active' => $institution->is_active,
            ],
            'students_count' => $studentsCount,
            'last_synced_at' => $lastBatch?->finished_at?->toIso8601String(),
        ]);
    }
}
