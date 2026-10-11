<?php

namespace App\Filament\Resources\SyncBatches\Pages;

use App\Filament\Resources\SyncBatches\SyncBatchResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewSyncBatch extends ViewRecord
{
    protected static string $resource = SyncBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
