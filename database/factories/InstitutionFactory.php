<?php

namespace Database\Factories;

use App\Models\Institution;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Institution>
 */
class InstitutionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'emis_institution_id' => fake()->unique()->numberBetween(10000, 99999),
            'name' => 'MIS '.fake()->company(),
            'nsm' => fake()->numerify('11121806####'),
            'npsn' => fake()->numerify('6975####'),
            'address' => fake()->address(),
            'is_active' => true,
        ];
    }
}
