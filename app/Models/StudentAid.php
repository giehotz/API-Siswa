<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentAid extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'student_id',
        'kip_number',
        'is_kip_sync',
        'pkh_status',
        'pip_status',
        'dtks',
        'fund_source',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_kip_sync' => 'boolean',
            'pkh_status' => 'boolean',
            'pip_status' => 'boolean',
            'dtks' => 'boolean',
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
