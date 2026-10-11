<?php

namespace App\Models;

use Database\Factories\InstitutionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Institution extends Authenticatable
{
    /** @use HasFactory<InstitutionFactory> */
    use HasApiTokens, HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'emis_institution_id',
        'name',
        'nsm',
        'npsn',
        'address',
        'is_active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'emis_institution_id' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Siswa yang terdaftar di madrasah ini.
     *
     * @return HasMany<Student, $this>
     */
    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    /**
     * Riwayat batch sinkronisasi EMIS madrasah ini.
     *
     * @return HasMany<SyncBatch, $this>
     */
    public function syncBatches(): HasMany
    {
        return $this->hasMany(SyncBatch::class);
    }
}
