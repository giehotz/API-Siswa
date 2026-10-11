<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncLog extends Model
{
    /**
     * Disable default updated_at since logs are append-only.
     */
    public const UPDATED_AT = null;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'sync_batch_id',
        'emis_student_id',
        'chunk_index',
        'level',
        'message',
        'context',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'emis_student_id' => 'integer',
            'chunk_index' => 'integer',
            'context' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Batch yang membawahi log ini.
     *
     * @return BelongsTo<SyncBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(SyncBatch::class, 'sync_batch_id');
    }
}
