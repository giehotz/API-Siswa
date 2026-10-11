<?php

namespace App\Filament\Resources\SyncBatches\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SyncBatchForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('uuid')
                    ->label('UUID')
                    ->required(),
                Select::make('institution_id')
                    ->relationship('institution', 'name')
                    ->required(),
                Select::make('status')
                    ->options([
                        'running' => 'Running',
                        'completed' => 'Completed',
                        'partial' => 'Partial',
                        'failed' => 'Failed',
                    ])
                    ->default('running')
                    ->required(),
                TextInput::make('total_expected')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('total_received')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('inserted_count')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('updated_count')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('failed_count')
                    ->required()
                    ->numeric()
                    ->default(0),
                DateTimePicker::make('started_at'),
                DateTimePicker::make('finished_at'),
                Textarea::make('notes')
                    ->columnSpanFull(),
            ]);
    }
}
