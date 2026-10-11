<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\SyncBatches\SyncBatchResource;
use App\Models\SyncBatch;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class LatestSyncBatchesWidget extends TableWidget
{
    protected static ?string $heading = 'Aktivitas Sinkronisasi EMIS Terbaru';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(SyncBatch::query()->with('institution')->latest('started_at')->limit(5))
            ->paginated(false)
            ->columns([
                TextColumn::make('uuid')
                    ->label('Batch ID')
                    ->limit(13)
                    ->copyable()
                    ->tooltip(fn ($record): string => (string) $record->uuid)
                    ->weight('bold'),

                TextColumn::make('institution.name')
                    ->label('Madrasah')
                    ->placeholder('-'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'running' => 'warning',
                        'partial' => 'info',
                        'failed' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'completed' => 'Selesai',
                        'running' => 'Sedang Berjalan',
                        'partial' => 'Sebagian',
                        'failed' => 'Gagal',
                        default => ucfirst($state),
                    }),

                TextColumn::make('progress')
                    ->label('Diterima / Target')
                    ->state(fn ($record): string => "{$record->total_received} / {$record->total_expected}"),

                TextColumn::make('inserted_count')
                    ->label('Baru')
                    ->numeric()
                    ->badge()
                    ->color('success'),

                TextColumn::make('updated_count')
                    ->label('Diperbarui')
                    ->numeric()
                    ->badge()
                    ->color('info'),

                TextColumn::make('failed_count')
                    ->label('Gagal')
                    ->numeric()
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'gray'),

                TextColumn::make('started_at')
                    ->label('Waktu Mulai')
                    ->dateTime('d M Y H:i:s'),

                TextColumn::make('duration')
                    ->label('Durasi')
                    ->state(function ($record): string {
                        if (! $record->started_at || ! $record->finished_at) {
                            return '-';
                        }
                        $seconds = $record->started_at->diffInSeconds($record->finished_at);
                        if ($seconds < 60) {
                            return "{$seconds} dtk";
                        }
                        $minutes = floor($seconds / 60);
                        $remainingSeconds = $seconds % 60;

                        return "{$minutes}m {$remainingSeconds}s";
                    }),
            ])
            ->recordActions([
                ViewAction::make()
                    ->url(fn (SyncBatch $record): string => SyncBatchResource::getUrl('view', ['record' => $record])),
            ]);
    }
}
