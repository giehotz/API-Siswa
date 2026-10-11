<?php

namespace App\Filament\Resources\SyncBatches\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class SyncBatchInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('uuid')
                    ->label('UUID'),
                TextEntry::make('institution.name')
                    ->label('Institution'),
                TextEntry::make('status')
                    ->badge(),
                TextEntry::make('total_expected')
                    ->numeric(),
                TextEntry::make('total_received')
                    ->numeric(),
                TextEntry::make('inserted_count')
                    ->numeric(),
                TextEntry::make('updated_count')
                    ->numeric(),
                TextEntry::make('failed_count')
                    ->numeric(),
                TextEntry::make('started_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('finished_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('notes')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
