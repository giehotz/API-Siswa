<?php

namespace App\Services\Emis;

use App\Models\Student;
use App\Models\StudentAid;
use App\Models\StudentLearningActivity;
use App\Models\StudentParent;
use App\Models\StudentVaccine;
use App\Models\SyncLog;
use Illuminate\Support\Facades\DB;
use Throwable;

class StudentUpsertService
{
    public function __construct(
        protected PayloadMapper $mapper,
    ) {}

    /**
     * Jalankan proses upsert batch siswa secara aman dan idempoten.
     *
     * @param  array<int, array<string, mixed>>  $students
     * @return array{inserted: int, updated: int, skipped: int, failed: array<int, array<string, mixed>>}
     */
    public function upsertChunk(
        array $students,
        int $institutionId,
        int $syncBatchId,
        int $chunkIndex = 1
    ): array {
        $inserted = 0;
        $updated = 0;
        $skipped = 0;
        $failed = [];

        foreach ($students as $raw) {
            $rawEmisId = $raw['emis_student_id'] ?? $raw['id'] ?? $raw['student_id'] ?? 0;
            if (is_array($rawEmisId)) {
                $rawEmisId = $rawEmisId['id'] ?? $rawEmisId['value'] ?? 0;
            }
            $emisStudentId = is_numeric($rawEmisId) ? (int) $rawEmisId : 0;

            $rawFullName = $raw['full_name'] ?? $raw['name'] ?? $raw['nama'] ?? null;
            if (is_array($rawFullName)) {
                $rawFullName = $rawFullName['name'] ?? $rawFullName['label'] ?? null;
            }
            $fullName = is_scalar($rawFullName) ? trim((string) $rawFullName) : 'TANPA NAMA';
            if ($fullName === '') {
                $fullName = 'TANPA NAMA';
            }

            if ($emisStudentId <= 0) {
                $failed[] = [
                    'emis_student_id' => $emisStudentId,
                    'full_name' => $fullName,
                    'error' => 'emis_student_id tidak valid atau bernilai 0.',
                ];

                continue;
            }

            $isNew = false;
            try {
                DB::transaction(function () use (
                    $raw,
                    $institutionId,
                    $emisStudentId,
                    &$isNew
                ) {
                    $isPartialDetail = (bool) ($raw['_fetchTimeout'] ?? false);
                    $studentData = $this->mapper->mapStudent($raw, $institutionId);

                    /** @var Student|null $existingStudent */
                    $existingStudent = Student::withoutGlobalScopes()
                        ->where('institution_id', $institutionId)
                        ->where('emis_student_id', $emisStudentId)
                        ->first();

                    if ($existingStudent) {
                        // Siswa sudah ada sebelumnya di database
                        if ($isPartialDetail) {
                            // HANYA perbarui field dasar yang ada di list, JANGAN timpa field detail dengan null
                            $safeUpdate = array_filter($studentData, fn ($val) => $val !== null);
                            $existingStudent->update($safeUpdate);
                        } else {
                            $existingStudent->update($studentData);
                        }
                        $student = $existingStudent;
                        $isNew = false;
                    } else {
                        // Siswa baru dibuat
                        $student = Student::withoutGlobalScopes()->create($studentData);
                        $isNew = true;
                    }

                    // Hanya perbarui relasi detail jika BUKAN kegagalan unduh detail (_fetchTimeout = false)
                    if (! $isPartialDetail) {
                        // 1. Data Orang Tua
                        $parentData = $this->mapper->mapParent($raw, $student->id);
                        if ($parentData !== null) {
                            StudentParent::updateOrCreate(
                                ['student_id' => $student->id],
                                $parentData
                            );
                        }

                        // 2. Data Pembelajaran / Rombel
                        $activityData = $this->mapper->mapLearningActivity($raw, $student->id);
                        if ($activityData !== null) {
                            StudentLearningActivity::updateOrCreate(
                                [
                                    'student_id' => $student->id,
                                    'academic_year' => $activityData['academic_year'],
                                    'semester' => $activityData['semester'],
                                ],
                                $activityData
                            );
                        }

                        // 3. Data Vaksin
                        $vaccineData = $this->mapper->mapVaccine($raw, $student->id);
                        if ($vaccineData !== null) {
                            StudentVaccine::updateOrCreate(
                                ['student_id' => $student->id],
                                $vaccineData
                            );
                        }

                        // 4. Data Bantuan / KIP
                        $aidData = $this->mapper->mapAid($raw, $student->id);
                        if ($aidData !== null) {
                            StudentAid::updateOrCreate(
                                ['student_id' => $student->id],
                                $aidData
                            );
                        }
                    }
                });

                if ($isNew) {
                    $inserted++;
                } else {
                    $updated++;
                }
            } catch (Throwable $e) {
                $failed[] = [
                    'emis_student_id' => $emisStudentId,
                    'full_name' => $fullName,
                    'error' => $e->getMessage(),
                ];

                // Catat kegagalan ke tabel sync_logs
                SyncLog::create([
                    'sync_batch_id' => $syncBatchId,
                    'emis_student_id' => $emisStudentId,
                    'chunk_index' => $chunkIndex,
                    'level' => 'error',
                    'message' => "Gagal memproses siswa {$fullName} (ID: {$emisStudentId}): {$e->getMessage()}",
                    'context' => [
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                    ],
                ]);
            }
        }

        return [
            'inserted' => $inserted,
            'updated' => $updated,
            'skipped' => $skipped,
            'failed' => $failed,
        ];
    }
}
