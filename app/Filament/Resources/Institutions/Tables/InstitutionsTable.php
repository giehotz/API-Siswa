<?php

namespace App\Filament\Resources\Institutions\Tables;

use App\Models\Institution;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Livewire\Component;

class InstitutionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('emis_institution_id')
                    ->label('ID EMIS')
                    ->sortable()
                    ->searchable()
                    ->weight('bold'),

                TextColumn::make('name')
                    ->label('Nama Madrasah')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                TextColumn::make('nsm')
                    ->label('NSM')
                    ->searchable(),

                TextColumn::make('npsn')
                    ->label('NPSN')
                    ->searchable(),

                TextColumn::make('students_count')
                    ->counts('students')
                    ->label('Jml Siswa')
                    ->badge()
                    ->color('info')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),

                TextColumn::make('tokens_count')
                    ->counts('tokens')
                    ->label('Jml Token Aktif')
                    ->badge()
                    ->color('warning')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('latest_token')
                    ->label('Token Terakhir')
                    ->state(function (Institution $record): string {
                        $latest = $record->tokens()->latest()->first();

                        return $latest
                            ? "{$latest->name} (".($latest->created_at?->diffForHumans() ?? 'baru saja').')'
                            : 'Belum ada';
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Terdaftar')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                Action::make('issue_token')
                    ->label('Buat Token')
                    ->icon('heroicon-o-key')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Terbitkan Token Sinkronisasi EMIS')
                    ->modalDescription('Token Sanctum ini akan dibuat dengan ability [emis:sync] untuk dimasukkan ke ekstensi Chrome.')
                    ->modalSubmitActionLabel('Konfirmasi')
                    ->modalCancelActionLabel('Batal')
                    ->action(function (Institution $record, Component $livewire) {
                        $token = $record->createToken('EMIS-Extension-Sync', ['emis:sync']);
                        $plainTextToken = $token->plainTextToken;

                        // Otomatis salin ke clipboard browser pengguna
                        $livewire->js("
                            if (navigator.clipboard && window.isSecureContext) {
                                navigator.clipboard.writeText('{$plainTextToken}');
                            } else {
                                const ta = document.createElement('textarea');
                                ta.value = '{$plainTextToken}';
                                ta.style.position = 'fixed';
                                ta.style.opacity = '0';
                                document.body.appendChild(ta);
                                ta.focus();
                                ta.select();
                                document.execCommand('copy');
                                document.body.removeChild(ta);
                            }
                        ");

                        Notification::make()
                            ->title("Token untuk {$record->name} Berhasil Disalin ke Clipboard!")
                            ->body("Token Sanctum otomatis disalin ke clipboard:\n\n{$plainTextToken}")
                            ->icon('heroicon-o-clipboard-document-check')
                            ->iconColor('success')
                            ->persistent()
                            ->success()
                            ->send();
                    }),
                Action::make('view_tokens')
                    ->label('Tabel Token')
                    ->icon('heroicon-o-list-bullet')
                    ->color('gray')
                    ->modalHeading(fn (Institution $record): string => "Tabel Token Sanctum - {$record->name}")
                    ->modalDescription('Daftar riwayat token sinkronisasi ekstensi yang aktif untuk madrasah ini.')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->modalContent(fn (Institution $record) => view('filament.modals.institution-tokens', ['institution' => $record])),
                ViewAction::make(),
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
