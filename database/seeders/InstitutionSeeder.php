<?php

namespace Database\Seeders;

use App\Models\Institution;
use Illuminate\Database\Seeder;

class InstitutionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Institution::updateOrCreate(
            ['emis_institution_id' => 65859],
            [
                'name' => 'MIS Mathla\'ul Anwar',
                'nsm' => '111218060012',
                'npsn' => '69752134',
                'address' => 'Jl. Raya Gisting, Tanggamus, Lampung',
                'is_active' => true,
            ]
        );
    }
}
