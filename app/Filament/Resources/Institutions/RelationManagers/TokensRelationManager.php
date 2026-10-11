<?php

namespace App\Filament\Resources\Institutions\RelationManagers;

use App\Models\Institution;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Livewire\Component;

class TokensRelationManager extends RelationManager
{
    protected static string $relationship = 'tokens';

    protected static ?string $title = 'Tabel Token Sanctum';

    protected static ?string $modelLabel = 'Token';

    protected static ?string $pluralModelLabel = 'Daftar Token';

    protected static bool $isLazy = true;

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->heading('Tabel Token Sinkronisasi EMIS')
            ->description('Daftar token API Sanctum yang aktif untuk sinkronisasi ekstensi Chrome madrasah ini.')
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Token')
                    ->searchable()
                    ->weight('bold'),

                TextColumn::make('abilities')
                    ->label('Hak Akses (Abilities)')
                    ->badge()
                    ->color('success'),

                TextColumn::make('last_used_at')
                    ->label('Terakhir Dipakai')
                    ->dateTime()
                    ->placeholder('Belum pernah digunakan')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Diterbitkan Pada')
                    ->dateTime()
                    ->sortable(),
            ])
            ->headerActions([
                Action::make('create_token')
                    ->label('Terbitkan Token Baru')
                    ->icon('heroicon-o-key')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Terbitkan Token Sinkronisasi EMIS')
                    ->modalDescription('Token Sanctum ini akan dibuat dengan ability [emis:sync] untuk dimasukkan ke ekstensi Chrome.')
                    ->modalSubmitActionLabel('Konfirmasi')
                    ->modalCancelActionLabel('Batal')
                    ->action(function (Component $livewire) {
                        /** @var Institution $institution */
                        $institution = $this->getOwnerRecord();
                        $token = $institution->createToken('EMIS-Extension-Sync', ['emis:sync']);
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
                            ->title("Token untuk {$institution->name} Berhasil Disalin ke Clipboard!")
                            ->body("Token Sanctum otomatis disalin ke clipboard:\n\n{$plainTextToken}")
                            ->icon('heroicon-o-clipboard-document-check')
                            ->iconColor('success')
                            ->persistent()
                            ->success()
                            ->send();
                    }),
            ])
            ->recordActions([
                DeleteAction::make()
                    ->label('Cabut Token')
                    ->modalHeading('Cabut Token Sanctum')
                    ->modalDescription('Token ini akan dihapus dan tidak bisa lagi digunakan oleh ekstensi Chrome.'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->label('Cabut Token Terpilih'),
                ]),
            ]);
    }
}
