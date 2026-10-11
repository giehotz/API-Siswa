<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentVaccine extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'student_id',
        'hepatitis_b',
        'polio',
        'bcg',
        'campak',
        'dpt',
        'covid19',
        'raw_vaccine_data',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'hepatitis_b' => 'boolean',
            'polio' => 'boolean',
            'bcg' => 'boolean',
            'campak' => 'boolean',
            'dpt' => 'boolean',
            'covid19' => 'boolean',
            'raw_vaccine_data' => 'array',
        ];
    }

    /**
     * Siswa yang bersangkutan.
     *
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
