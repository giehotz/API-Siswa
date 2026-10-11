<?php

namespace App\Http\Middleware;

use App\Models\Institution;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureInstitutionMatches
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Unauthenticated: Token otentikasi tidak ditemukan atau tidak valid.',
            ], 401);
        }

        // Pastikan token memiliki ability 'emis:sync'
        if (method_exists($user, 'tokenCan') && ! $user->tokenCan('emis:sync')) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Forbidden: Token ini tidak memiliki izin untuk melakukan sinkronisasi EMIS (ability: emis:sync).',
            ], 403);
        }

        // Ambil data instansi pemilik token
        /** @var Institution|null $institution */
        $institution = null;
        if ($user instanceof Institution) {
            $institution = $user;
        } elseif (isset($user->institution_id)) {
            $institution = Institution::find($user->institution_id);
        }

        if (! $institution) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Forbidden: Instansi madrasah pemilik token tidak terdaftar.',
            ], 403);
        }

        if (! $institution->is_active) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Forbidden: Status akun madrasah sedang dinonaktifkan.',
            ], 403);
        }

        // Periksa kesesuaian institution_id pada payload body / query parameter jika ada
        $requestedInstitutionId = $request->input('institution_id') ?? $request->route('institution_id');

        if ($requestedInstitutionId !== null) {
            $reqId = (int) $requestedInstitutionId;
            if ($reqId !== (int) $institution->emis_institution_id && $reqId !== (int) $institution->id) {
                activity('security')
                    ->causedBy($user)
                    ->withProperties(['token_institution_id' => $institution->id, 'requested_institution_id' => $reqId])
                    ->log('Percobaan akses lintas madrasah ditolak (INSTITUTION_MISMATCH)');

                return new JsonResponse([
                    'success' => false,
                    'message' => 'Forbidden: institution_id pada request tidak cocok dengan madrasah pemilik token.',
                    'error' => 'INSTITUTION_MISMATCH',
                ], 403);
            }
        }

        // Lampirkan instansi ke request attribute agar controller tidak perlu query ulang
        $request->attributes->set('current_institution', $institution);

        return $next($request);
    }
}
