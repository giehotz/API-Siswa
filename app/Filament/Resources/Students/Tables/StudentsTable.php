<?php

namespace App\Filament\Resources\Students\Tables;

use App\Models\Student;
use App\Models\StudentLearningActivity;
use App\Services\Emis\StudentExcelExportService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class StudentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('emis_student_id')
                    ->label('ID EMIS')
                    ->sortable()
                    ->searchable()
                    ->copyable()
                    ->weight('bold'),

                TextColumn::make('full_name')
                    ->label('Nama Siswa')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                TextColumn::make('gender')
                    ->label('JK')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'L' => 'info',
                        'P' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('nisn')
                    ->label('NISN')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('local_nis')
                    ->label('NIS Lokal')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('latestLearningActivity.rombel_name')
                    ->label('Rombel')
                    ->badge()
                    ->color('primary')
                    ->placeholder('-'),

                TextColumn::make('latestLearningActivity.student_status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'Aktif', 'Siswa Baru' => 'success',
                        'Mutasi', 'Lulus' => 'warning',
                        default => 'gray',
                    })
                    ->placeholder('-'),

                TextColumn::make('parent.father_name')
                    ->label('Nama Ayah')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('institution.name')
                    ->label('Madrasah')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('last_synced_at')
                    ->label('Sinkron Terakhir')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('rombel')
                    ->label('Kelas / Rombel')
                    ->placeholder('Semua Rombel')
                    ->searchable()
                    ->options(function () {
                        return StudentLearningActivity::query()
                            ->whereNotNull('rombel_name')
                            ->where('rombel_name', '!=', '')
                            ->distinct()
                            ->orderBy('rombel_name')
                            ->pluck('rombel_name', 'rombel_name')
                            ->toArray();
                    })
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'],
                            fn (Builder $q, string $value) => $q->whereHas(
                                'learningActivities',
                                fn (Builder $la) => $la->where('rombel_name', $value)
                            )
                        );
                    }),

                SelectFilter::make('level')
                    ->label('Tingkat Kelas')
                    ->placeholder('Semua Tingkat')
                    ->options([
                        1 => 'Tingkat 1 (Kelas 1)',
                        2 => 'Tingkat 2 (Kelas 2)',
                        3 => 'Tingkat 3 (Kelas 3)',
                        4 => 'Tingkat 4 (Kelas 4)',
                        5 => 'Tingkat 5 (Kelas 5)',
                        6 => 'Tingkat 6 (Kelas 6)',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'],
                            fn (Builder $q, mixed $value) => $q->whereHas(
                                'learningActivities',
                                fn (Builder $la) => $la->where('level', (int) $value)
                            )
                        );
                    }),

                SelectFilter::make('student_status')
                    ->label('Status Siswa')
                    ->placeholder('Semua Status')
                    ->options([
                        'Aktif' => 'Aktif',
                        'Siswa Baru' => 'Siswa Baru',
                        'Mutasi' => 'Mutasi',
                        'Lulus' => 'Lulus',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'],
                            fn (Builder $q, string $value) => $q->whereHas(
                                'learningActivities',
                                fn (Builder $la) => $la->where('student_status', $value)
                            )
                        );
                    }),

                SelectFilter::make('gender')
                    ->label('Jenis Kelamin')
                    ->options([
                        'L' => 'Laki-laki (L)',
                        'P' => 'Perempuan (P)',
                    ]),

                SelectFilter::make('institution_id')
                    ->label('Madrasah')
                    ->relationship('institution', 'name'),

                TrashedFilter::make(),
            ])
            ->headerActions([
                Action::make('exportExcel')
                    ->label('Download Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->action(function (Table $table) {
                        $query = $table->getFilteredTableQuery() ?? Student::query();

                        return app(StudentExcelExportService::class)->export($query);
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('exportSelectedExcel')
                        ->label('Download Excel Terpilih')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('success')
                        ->action(function (Collection $records) {
                            $query = Student::whereIn('id', $records->pluck('id'));

                            return app(StudentExcelExportService::class)->export($query);
                        }),
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                ]),
            ]);
    }
}
