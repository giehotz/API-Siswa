<?php

namespace App\Filament\Resources\SyncBatches\Pages;

use App\Filament\Resources\SyncBatches\SyncBatchResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSyncBatches extends ListRecords
{
    protected static string $resource = SyncBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
