<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class IdempotencyKey
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Hanya berlaku untuk metode mutasi (POST, PUT, PATCH)
        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH'])) {
            return $next($request);
        }

        $idempotencyKey = $request->header('Idempotency-Key') ?? $request->header('X-Idempotency-Key');

        if (! $idempotencyKey) {
            // Jika header tidak disediakan, tetap proses request seperti biasa
            return $next($request);
        }

        $cacheKey = 'idempotency:'.md5((string) $idempotencyKey);

        // Periksa apakah chunk/request ini sudah pernah berhasil diproses
        $cachedResponse = Cache::get($cacheKey);
        if ($cachedResponse !== null) {
            return new JsonResponse($cachedResponse['data'], $cachedResponse['status'], [
                'X-Idempotency-Replay' => 'true',
                'X-Idempotency-Key' => (string) $idempotencyKey,
            ]);
        }

        /** @var Response $response */
        $response = $next($request);

        // Hanya simpan cache untuk respon sukses (HTTP 200..299)
        if ($response->isSuccessful() && $response instanceof JsonResponse) {
            Cache::put($cacheKey, [
                'status' => $response->getStatusCode(),
                'data' => $response->getData(true),
            ], now()->addHours(24));
        }

        return $response;
    }
}
