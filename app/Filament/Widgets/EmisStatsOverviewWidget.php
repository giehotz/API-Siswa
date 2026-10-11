<?php

namespace App\Filament\Widgets;

use App\Models\Institution;
use App\Models\Student;
use App\Models\SyncBatch;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class EmisStatsOverviewWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $totalStudents = Student::count();
        $maleCount = Student::where('gender', 'L')->count();
        $femaleCount = Student::where('gender', 'P')->count();

        $totalInstitutions = Institution::count();

        $latestBatch = SyncBatch::with('institution')->latest('started_at')->first();

        $latestBatchStatus = $latestBatch ? match ($latestBatch->status) {
            'completed' => 'Selesai',
            'running' => 'Sedang Berjalan',
            'partial' => 'Sebagian',
            'failed' => 'Gagal',
            default => ucfirst((string) $latestBatch->status),
        } : 'Belum Ada';

        $latestBatchDesc = $latestBatch
            ? "{$latestBatch->total_received} siswa ({$latestBatch->inserted_count} baru, {$latestBatch->updated_count} update)"
            : 'Belum ada riwayat sinkronisasi';

        $latestBatchColor = $latestBatch ? match ($latestBatch->status) {
            'completed' => 'success',
            'running' => 'warning',
            'failed' => 'danger',
            default => 'info',
        } : 'gray';

        $completedBatches = SyncBatch::where('status', 'completed')->count();
        $totalBatches = SyncBatch::count();

        return [
            Stat::make('Total Siswa', number_format($totalStudents, 0, ',', '.'))
                ->description("Laki-laki: {$maleCount} | Perempuan: {$femaleCount}")
                ->descriptionIcon(Heroicon::OutlinedAcademicCap)
                ->color('primary'),

            Stat::make('Madrasah Terdaftar', number_format($totalInstitutions, 0, ',', '.'))
                ->description('Lembaga aktif di sistem')
                ->descriptionIcon(Heroicon::OutlinedBuildingLibrary)
                ->color('success'),

            Stat::make('Status Sinkronisasi Terakhir', $latestBatchStatus)
                ->description($latestBatchDesc)
                ->descriptionIcon(Heroicon::OutlinedArrowPathRoundedSquare)
                ->color($latestBatchColor),

            Stat::make('Batch Sukses', "{$completedBatches} / {$totalBatches}")
                ->description('Tingkat keberhasilan transfer')
                ->descriptionIcon(Heroicon::OutlinedCheckCircle)
                ->color('info'),
        ];
    }
}
