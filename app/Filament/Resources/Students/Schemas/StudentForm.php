<?php

namespace App\Filament\Resources\Students\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class StudentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('institution_id')
                    ->relationship('institution', 'name')
                    ->required(),
                TextInput::make('emis_student_id')
                    ->required()
                    ->numeric(),
                TextInput::make('local_nis'),
                TextInput::make('nisn'),
                Textarea::make('nik')
                    ->columnSpanFull(),
                TextInput::make('nik_hash'),
                Textarea::make('kk_num')
                    ->columnSpanFull(),
                TextInput::make('family_head_name'),
                TextInput::make('full_name')
                    ->required(),
                Select::make('gender')
                    ->options(['L' => 'L', 'P' => 'P']),
                TextInput::make('birth_place'),
                DatePicker::make('birth_date'),
                TextInput::make('religion'),
                TextInput::make('child_of_num')
                    ->numeric(),
                TextInput::make('siblings_num')
                    ->numeric(),
                TextInput::make('phone_number')
                    ->tel(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email(),
                TextInput::make('hobby'),
                TextInput::make('life_goal'),
                Textarea::make('full_address')
                    ->columnSpanFull(),
                TextInput::make('rt'),
                TextInput::make('rw'),
                TextInput::make('sub_district'),
                TextInput::make('district'),
                TextInput::make('city'),
                TextInput::make('province'),
                TextInput::make('postal_code'),
                TextInput::make('residence_status'),
                TextInput::make('distance_to_school'),
                TextInput::make('travel_time'),
                TextInput::make('transportation'),
                TextInput::make('latitude')
                    ->numeric(),
                TextInput::make('longitude')
                    ->numeric(),
                TextInput::make('raw_payload'),
                DateTimePicker::make('last_synced_at'),
            ]);
    }
}
