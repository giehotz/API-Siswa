<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentLearningActivity extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'student_id',
        'academic_year',
        'semester',
        'level',
        'rombel_name',
        'absent_number',
        'homeroom_teacher',
        'classroom',
        'student_status',
        'status_description',
        'admission_date',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'semester' => 'integer',
            'level' => 'integer',
            'absent_number' => 'integer',
            'admission_date' => 'date',
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
