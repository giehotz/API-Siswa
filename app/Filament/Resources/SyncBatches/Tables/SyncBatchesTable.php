<?php

namespace App\Filament\Resources\SyncBatches\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SyncBatchesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('uuid')
                    ->label('Batch ID')
                    ->searchable()
                    ->copyable()
                    ->limit(13)
                    ->tooltip(fn ($record): string => (string) $record->uuid)
                    ->weight('bold'),

                TextColumn::make('institution.name')
                    ->label('Madrasah')
                    ->searchable()
                    ->sortable(),

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
                    ->color('success')
                    ->sortable(),

                TextColumn::make('updated_count')
                    ->label('Diperbarui')
                    ->numeric()
                    ->badge()
                    ->color('info')
                    ->sortable(),

                TextColumn::make('failed_count')
                    ->label('Gagal')
                    ->numeric()
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'gray')
                    ->sortable(),

                TextColumn::make('started_at')
                    ->label('Waktu Mulai')
                    ->dateTime('d M Y H:i:s')
                    ->sortable(),

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
            ->defaultSort('started_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'completed' => 'Selesai (Completed)',
                        'running' => 'Sedang Berjalan (Running)',
                        'partial' => 'Sebagian (Partial)',
                        'failed' => 'Gagal (Failed)',
                    ]),

                SelectFilter::make('institution_id')
                    ->label('Madrasah')
                    ->relationship('institution', 'name'),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
