<?php

namespace App\Filament\Resources\SyncBatches;

use App\Filament\Resources\SyncBatches\Pages\CreateSyncBatch;
use App\Filament\Resources\SyncBatches\Pages\EditSyncBatch;
use App\Filament\Resources\SyncBatches\Pages\ListSyncBatches;
use App\Filament\Resources\SyncBatches\Pages\ViewSyncBatch;
use App\Filament\Resources\SyncBatches\Schemas\SyncBatchForm;
use App\Filament\Resources\SyncBatches\Schemas\SyncBatchInfolist;
use App\Filament\Resources\SyncBatches\Tables\SyncBatchesTable;
use App\Models\SyncBatch;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class SyncBatchResource extends Resource
{
    protected static ?string $model = SyncBatch::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPathRoundedSquare;

    protected static string|UnitEnum|null $navigationGroup = 'Sinkronisasi EMIS';

    protected static ?string $modelLabel = 'Riwayat Sinkronisasi';

    protected static ?string $pluralModelLabel = 'Riwayat Sinkronisasi';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return SyncBatchForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return SyncBatchInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SyncBatchesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSyncBatches::route('/'),
            'create' => CreateSyncBatch::route('/create'),
            'view' => ViewSyncBatch::route('/{record}'),
            'edit' => EditSyncBatch::route('/{record}/edit'),
        ];
    }
}
