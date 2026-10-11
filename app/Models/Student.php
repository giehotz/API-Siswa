<?php

namespace App\Models;

use App\Models\Scopes\InstitutionScope;
use Database\Factories\StudentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    /** @use HasFactory<StudentFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'institution_id',
        'emis_student_id',
        'local_nis',
        'nisn',
        'nik',
        'nik_hash',
        'kk_num',
        'family_head_name',
        'full_name',
        'gender',
        'birth_place',
        'birth_date',
        'religion',
        'child_of_num',
        'siblings_num',
        'phone_number',
        'email',
        'hobby',
        'life_goal',
        'full_address',
        'rt',
        'rw',
        'sub_district',
        'district',
        'city',
        'province',
        'postal_code',
        'residence_status',
        'distance_to_school',
        'travel_time',
        'transportation',
        'latitude',
        'longitude',
        'raw_payload',
        'last_synced_at',
    ];

    /**
     * Boot the model.
     */
    protected static function booted(): void
    {
        static::addGlobalScope(new InstitutionScope);

        static::saving(function (Student $student) {
            if ($student->nik && ! empty($student->nik)) {
                $hashKey = config('emis.nik_hash_key', 'sis-emis-secret-hash-key-2026');
                $student->nik_hash = hash_hmac('sha256', (string) $student->nik, $hashKey);
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'emis_student_id' => 'integer',
            'birth_date' => 'date',
            'last_synced_at' => 'datetime',
            'raw_payload' => 'array',
            'nik' => 'encrypted',
            'kk_num' => 'encrypted',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    /**
     * Madrasah tempat siswa bernaung.
     *
     * @return BelongsTo<Institution, $this>
     */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    /**
     * Data orang tua dan wali siswa.
     *
     * @return HasOne<StudentParent, $this>
     */
    public function parent(): HasOne
    {
        return $this->hasOne(StudentParent::class);
    }

    /**
     * Riwayat aktivitas pembelajaran / rombel siswa.
     *
     * @return HasMany<StudentLearningActivity, $this>
     */
    public function learningActivities(): HasMany
    {
        return $this->hasMany(StudentLearningActivity::class);
    }

    /**
     * Aktivitas pembelajaran / rombel terbaru.
     *
     * @return HasOne<StudentLearningActivity, $this>
     */
    public function latestLearningActivity(): HasOne
    {
        return $this->hasOne(StudentLearningActivity::class)->latestOfMany();
    }

    /**
     * Data vaksin siswa.
     *
     * @return HasOne<StudentVaccine, $this>
     */
    public function vaccine(): HasOne
    {
        return $this->hasOne(StudentVaccine::class);
    }

    /**
     * Data bantuan / bansos siswa.
     *
     * @return HasOne<StudentAid, $this>
     */
    public function aid(): HasOne
    {
        return $this->hasOne(StudentAid::class);
    }
}
