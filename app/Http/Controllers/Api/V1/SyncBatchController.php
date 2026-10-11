<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\SyncBatchResource;
use App\Models\SyncBatch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class SyncBatchController extends Controller
{
    /**
     * Riwayat Batch Sinkronisasi
     *
     * Menampilkan daftar sesi batch sinkronisasi EMIS dengan status, progres, dan jumlah record.
     *
     * @response AnonymousResourceCollection<SyncBatchResource>
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $batches = QueryBuilder::for(SyncBatch::class)
            ->with('institution')
            ->allowedFilters(
                AllowedFilter::exact('status'),
                AllowedFilter::exact('institution_id'),
            )
            ->allowedSorts(
                'started_at',
                'finished_at',
                'created_at',
                'total_received',
                'total_expected',
            )
            ->allowedIncludes(
                'institution',
            )
            ->defaultSort('-started_at')
            ->paginate($request->integer('per_page', 20))
            ->appends($request->query());

        return SyncBatchResource::collection($batches);
    }

    /**
     * Detail Batch Sinkronisasi
     *
     * Menampilkan informasi rincian satu batch transfer sinkronisasi EMIS.
     */
    public function show(SyncBatch $syncBatch): SyncBatchResource
    {
        $syncBatch->loadMissing('institution');

        return new SyncBatchResource($syncBatch);
    }
}
