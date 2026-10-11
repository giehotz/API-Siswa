<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentParent extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'student_id',
        'father_name',
        'father_nik',
        'father_life_status',
        'father_phone',
        'father_education',
        'father_job',
        'father_income',
        'mother_name',
        'mother_nik',
        'mother_life_status',
        'mother_phone',
        'mother_education',
        'mother_job',
        'mother_income',
        'guardian_name',
        'guardian_job',
        'guardian_phone',
        'guardian_relationship',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'father_nik' => 'encrypted',
            'mother_nik' => 'encrypted',
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
