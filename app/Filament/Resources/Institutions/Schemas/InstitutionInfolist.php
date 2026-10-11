<?php

namespace App\Filament\Resources\Institutions\Schemas;

use App\Models\Institution;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class InstitutionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('emis_institution_id')
                    ->numeric(),
                TextEntry::make('name'),
                TextEntry::make('nsm')
                    ->placeholder('-'),
                TextEntry::make('npsn')
                    ->placeholder('-'),
                TextEntry::make('address')
                    ->placeholder('-')
                    ->columnSpanFull(),
                IconEntry::make('is_active')
                    ->boolean(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('deleted_at')
                    ->dateTime()
                    ->visible(fn (Institution $record): bool => $record->trashed()),
            ]);
    }
}
