<?php

namespace App\Http\Resources\Api\V1;

use App\Models\SyncBatch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SyncBatch
 */
class SyncBatchResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'institution_id' => $this->institution_id,
            'institution' => $this->whenLoaded('institution', fn () => [
                'id' => $this->institution?->id,
                'name' => $this->institution?->name,
                'nsm' => $this->institution?->nsm,
            ]),
            'status' => $this->status,
            'total_expected' => $this->total_expected,
            'total_received' => $this->total_received,
            'inserted_count' => $this->inserted_count,
            'updated_count' => $this->updated_count,
            'failed_count' => $this->failed_count,
            'notes' => $this->notes,
            'started_at' => $this->started_at?->toISOString(),
            'finished_at' => $this->finished_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
