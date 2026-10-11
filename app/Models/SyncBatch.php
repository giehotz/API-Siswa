<?php

namespace App\Models;

use App\Models\Scopes\InstitutionScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SyncBatch extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'institution_id',
        'status',
        'total_expected',
        'total_received',
        'inserted_count',
        'updated_count',
        'failed_count',
        'started_at',
        'finished_at',
        'notes',
    ];

    /**
     * Boot the model.
     */
    protected static function booted(): void
    {
        static::addGlobalScope(new InstitutionScope);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_expected' => 'integer',
            'total_received' => 'integer',
            'inserted_count' => 'integer',
            'updated_count' => 'integer',
            'failed_count' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * Madrasah tempat sinkronisasi dilakukan.
     *
     * @return BelongsTo<Institution, $this>
     */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    /**
     * Log peristiwa / error pada batch sinkronisasi ini.
     *
     * @return HasMany<SyncLog, $this>
     */
    public function logs(): HasMany
    {
        return $this->hasMany(SyncLog::class);
    }
}
