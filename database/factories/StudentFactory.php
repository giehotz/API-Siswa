<?php

namespace Database\Factories;

use App\Models\Institution;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'institution_id' => Institution::factory(),
            'emis_student_id' => fake()->unique()->numberBetween(1000000000, 9999999999),
            'local_nis' => fake()->numerify('00#####'),
            'nisn' => fake()->numerify('00########'),
            'nik' => fake()->numerify('181001##########'),
            'kk_num' => fake()->numerify('181001##########'),
            'family_head_name' => fake()->name('male'),
            'full_name' => fake()->name(),
            'gender' => fake()->randomElement(['L', 'P']),
            'birth_place' => fake()->city(),
            'birth_date' => fake()->dateTimeBetween('-15 years', '-6 years')->format('Y-m-d'),
            'religion' => 'Islam',
            'child_of_num' => fake()->numberBetween(1, 5),
            'siblings_num' => fake()->numberBetween(0, 4),
            'phone_number' => fake()->numerify('08##########'),
            'email' => fake()->safeEmail(),
            'hobby' => fake()->randomElement(['Membaca', 'Sepak Bola', 'Menggambar']),
            'life_goal' => fake()->randomElement(['Guru', 'Dokter', 'Insinyur', 'Ustadz']),
            'full_address' => fake()->streetAddress(),
            'rt' => fake()->numerify('0#'),
            'rw' => fake()->numerify('0#'),
            'sub_district' => fake()->streetName(),
            'district' => fake()->city(),
            'city' => 'Kabupaten Tanggamus',
            'province' => 'Lampung',
            'postal_code' => fake()->numerify('35###'),
            'residence_status' => 'Milik Sendiri',
            'distance_to_school' => '< 1 km',
            'travel_time' => '10 menit',
            'transportation' => 'Jalan Kaki',
            'latitude' => -5.3582,
            'longitude' => 104.7214,
            'raw_payload' => null,
            'last_synced_at' => now(),
        ];
    }
}
