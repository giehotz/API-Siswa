<?php

namespace App\Filament\Resources\SyncBatches\Pages;

use App\Filament\Resources\SyncBatches\SyncBatchResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditSyncBatch extends EditRecord
{
    protected static string $resource = SyncBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
