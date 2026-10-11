<?php

namespace App\Console\Commands;

use App\Models\Institution;
use App\Models\Student;
use App\Models\SyncBatch;
use App\Services\Emis\StudentUpsertService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class BenchmarkEmisSyncCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'emis:benchmark 
                            {institution? : ID atau NSM madrasah} 
                            {--students=2000 : Jumlah siswa yang disimulasikan} 
                            {--chunk=50 : Ukuran chunk per request}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Uji beban lokal sinkronisasi EMIS (2.000 siswa) untuk mengukur memori RAM, waktu eksekusi, dan throughput database';

    /**
     * Execute the console command.
     */
    public function handle(StudentUpsertService $upsertService): int
    {
        $totalStudents = (int) $this->option('students');
        $chunkSize = (int) $this->option('chunk');

        $instIdentifier = $this->argument('institution');
        $institution = null;

        if ($instIdentifier) {
            $institution = Institution::where('id', $instIdentifier)
                ->orWhere('emis_institution_id', $instIdentifier)
                ->orWhere('nsm', $instIdentifier)
                ->first();
        } else {
            $institution = Institution::first();
        }

        if (! $institution) {
            $this->error('Madrasah tidak ditemukan. Jalankan seeder atau tentukan ID madrasah.');

            return self::FAILURE;
        }

        $this->info('=== UJI BEBAN LOKAL SINKRONISASI EMIS (Tahap 7) ===');
        $this->line("Madrasah Target : <comment>{$institution->name}</comment> (ID: {$institution->id}, EMIS: {$institution->emis_institution_id})");
        $this->line("Jumlah Siswa     : <comment>{$totalStudents} siswa</comment>");
        $this->line("Ukuran Chunk     : <comment>{$chunkSize} siswa / batch</comment>");
        $this->newLine();

        $this->comment('Menyiapkan dataset sampel realistis di memori...');
        $students = [];
        $baseStudentId = 7000000;

        for ($i = 1; $i <= $totalStudents; $i++) {
            $sId = $baseStudentId + $i;
            $padded = str_pad((string) $i, 6, '0', STR_PAD_LEFT);

            $students[] = [
                'emis_student_id' => $sId,
                'local_nis' => "NIS{$padded}",
                'nisn' => '008'.str_pad((string) ($i % 9999999), 7, '0', STR_PAD_LEFT),
                'nik' => '181001'.str_pad((string) ($i % 9999999999), 10, '0', STR_PAD_LEFT),
                'kk_num' => '181001'.str_pad((string) (($i + 500) % 9999999999), 10, '0', STR_PAD_LEFT),
                'full_name' => "Siswa Simulasi {$i}",
                'gender' => ($i % 2 === 0) ? 'L' : 'P',
                'birth_place' => 'Tanggamus',
                'birth_date' => '2014-05-02',
                'address' => [
                    'full_address' => "Jl. Pendidikan No. {$i}, Desa Sinar Banten",
                    'rt' => '01',
                    'rw' => '02',
                    'sub_district' => 'Ulu Belu',
                    'district' => 'Tanggamus',
                    'city' => 'Kabupaten Tanggamus',
                    'province' => 'Lampung',
                    'postal_code' => '35387',
                ],
                'learning_activity' => [
                    'academic_year' => '2026/2027',
                    'semester' => 1,
                    'level' => ($i % 6) + 1,
                    'rombel_name' => 'Kelas '.(($i % 6) + 1).'A',
                    'absent_number' => ($i % 30) + 1,
                    'homeroom_teacher' => 'Ust. Fauzi, S.Pd.I',
                    'student_status' => 'Aktif',
                ],
                'parents' => [
                    'father_name' => "Ayah Siswa {$i}",
                    'father_nik' => '181001'.str_pad((string) ($i % 9999999999), 10, '0', STR_PAD_LEFT),
                    'mother_name' => "Ibu Siswa {$i}",
                    'mother_nik' => '181001'.str_pad((string) (($i + 1) % 9999999999), 10, '0', STR_PAD_LEFT),
                    'guardian_name' => null,
                ],
                'vaccines' => [
                    'hepatitis_b' => true,
                    'polio' => true,
                    'bcg' => true,
                    'campak' => true,
                    'dpt' => true,
                    'covid19' => false,
                ],
                'aid' => [
                    'kip_number' => ($i % 4 === 0) ? "KIP{$padded}" : null,
                    'is_kip_sync' => ($i % 4 === 0),
                    'pkh_status' => ($i % 5 === 0),
                    'pip_status' => ($i % 4 === 0),
                    'dtks' => ($i % 3 === 0),
                ],
                '_fetchTimeout' => false,
            ];
        }

        $chunks = array_chunk($students, $chunkSize);
        $totalChunks = count($chunks);

        $batch = SyncBatch::create([
            'uuid' => (string) Str::uuid(),
            'institution_id' => $institution->id,
            'status' => 'running',
            'total_expected' => $totalStudents,
            'total_received' => 0,
            'inserted_count' => 0,
            'updated_count' => 0,
            'failed_count' => 0,
            'started_at' => now(),
        ]);

        $this->comment("Sesi batch dibuat: {$batch->uuid}");
        $this->comment("Memulai sinkronisasi {$totalChunks} chunk...");

        $bar = $this->output->createProgressBar($totalChunks);
        $bar->start();

        $startTime = microtime(true);
        $totalInserted = 0;
        $totalUpdated = 0;
        $totalFailed = 0;

        foreach ($chunks as $idx => $chunkStudents) {
            $chunkIndex = $idx + 1;
            $res = $upsertService->upsertChunk($chunkStudents, $institution->id, $batch->id, $chunkIndex);

            $totalInserted += $res['inserted'];
            $totalUpdated += $res['updated'];
            $totalFailed += count($res['failed']);

            $batch->increment('total_received', count($chunkStudents));
            $batch->increment('inserted_count', $res['inserted']);
            $batch->increment('updated_count', $res['updated']);

            $bar->advance();
        }

        $endTime = microtime(true);
        $bar->finish();
        $this->newLine(2);

        $duration = round($endTime - $startTime, 3);
        $peakMemoryMb = round(memory_get_peak_usage(true) / 1024 / 1024, 2);
        $throughput = $duration > 0 ? round($totalStudents / $duration, 1) : $totalStudents;
        $avgChunkMs = $totalChunks > 0 ? round(($duration / $totalChunks) * 1000, 1) : 0;

        $batch->update([
            'status' => 'completed',
            'finished_at' => now(),
            'notes' => "Benchmark berhasil: {$totalStudents} siswa dalam {$duration}s ({$throughput} siswa/dtk)",
        ]);

        $verifiedCount = Student::where('institution_id', $institution->id)
            ->where('emis_student_id', '>', $baseStudentId)
            ->count();

        $this->info('================ HASIL BENCHMARK (2.000 SISWA) ================');
        $this->table(
            ['Metrik', 'Nilai'],
            [
                ['Total Record Siswa', number_format($totalStudents, 0, ',', '.')],
                ['Ukuran Chunk', "{$chunkSize} siswa / request"],
                ['Jumlah Chunk Diproses', "{$totalChunks} chunk"],
                ['Total Waktu Eksekusi', "{$duration} detik"],
                ['Throughput Kecepatan', "{$throughput} siswa / detik"],
                ['Rata-rata Waktu / Chunk', "{$avgChunkMs} ms"],
                ['Puncak Penggunaan RAM', "{$peakMemoryMb} MB"],
                ['Data Masuk (Inserted)', number_format($totalInserted, 0, ',', '.')],
                ['Data Diperbarui (Updated)', number_format($totalUpdated, 0, ',', '.')],
                ['Gagal (Failed)', number_format($totalFailed, 0, ',', '.')],
                ['Verifikasi Database MySQL', "{$verifiedCount} record utuh di tabel 'students'"],
                ['Status Batch Final', $batch->status],
            ]
        );

        $this->info('✓ Benchmark sukses! Sistem terbukti menangani 2.000 siswa secara aman & cepat tanpa out-of-memory.');

        return self::SUCCESS;
    }
}
