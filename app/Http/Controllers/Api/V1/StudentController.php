<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\StudentResource;
use App\Models\Institution;
use App\Models\Student;
use App\Models\StudentLearningActivity;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class StudentController extends Controller
{
    /**
     * Daftar Siswa Madrasah
     *
     * Menampilkan daftar seluruh siswa dengan dukungan filter, pencarian, pengurutan, dan paginasi.
     * Otomatis terisolasi berdasarkan madrasah pengakses (multi-tenancy).
     *
     * @response AnonymousResourceCollection<StudentResource>
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $students = QueryBuilder::for(Student::class)
            ->with(['parent', 'latestLearningActivity', 'vaccine', 'aid', 'institution'])
            ->allowedFilters(
                AllowedFilter::partial('full_name'),
                AllowedFilter::exact('gender'),
                AllowedFilter::exact('nisn'),
                AllowedFilter::exact('local_nis'),
                AllowedFilter::exact('institution_id'),
                AllowedFilter::callback('rombel', function ($query, $value) {
                    $normalized = str_replace(' ', '', strtolower((string) $value));
                    $query->whereHas('latestLearningActivity', function ($q) use ($value, $normalized) {
                        $q->where('rombel_name', $value)
                            ->orWhereRaw("REPLACE(LOWER(rombel_name), ' ', '') = ?", [$normalized])
                            ->orWhereRaw("REPLACE(LOWER(rombel_name), ' ', '') LIKE ?", ["%{$normalized}%"]);
                    });
                }),
                AllowedFilter::callback('level', function ($query, $value) {
                    $query->whereHas('latestLearningActivity', function ($q) use ($value) {
                        $q->where('level', (int) $value);
                    });
                }),
                AllowedFilter::callback('status', function ($query, $value) {
                    $query->whereHas('latestLearningActivity', function ($q) use ($value) {
                        $q->where('student_status', 'LIKE', "%{$value}%");
                    });
                }),
            )
            ->allowedSorts(
                'full_name',
                'nisn',
                'local_nis',
                'created_at',
                'last_synced_at',
            )
            ->allowedIncludes(
                'parent',
                'learningActivities',
                'latestLearningActivity',
                'vaccine',
                'aid',
                'institution',
            )
            ->defaultSort('-created_at')
            ->paginate($request->integer('per_page', 25))
            ->appends($request->query());

        return StudentResource::collection($students);
    }

    /**
     * Daftar Rombongan Belajar (Rombel) dari EMIS
     */
    public function rombels(Request $request): JsonResponse
    {
        $rombels = StudentLearningActivity::query()
            ->select('rombel_name', 'level', DB::raw('count(distinct student_id) as student_count'))
            ->whereNotNull('rombel_name')
            ->where('rombel_name', '!=', '')
            ->groupBy('rombel_name', 'level')
            ->orderBy('level')
            ->orderBy('rombel_name')
            ->get();

        return response()->json([
            'status' => 'success',
            'total' => $rombels->count(),
            'rombels' => $rombels,
        ]);
    }

    /**
     * Ekspor Data Siswa Datar (Bulk Flattened JSON) untuk Aplikasi Eksternal (CBT, Absensi, E-Rapor)
     *
     * Format 1-level tanpa sub-objek bersarang, siap mapping langsung ke tabel database eksternal.
     * Mendukung filter rombel, tingkat, status, gender, pencarian nama/NISN.
     */
    public function exportJson(Request $request): JsonResponse
    {
        $user = $request->user();
        $institution = $user instanceof Institution
            ? $user
            : ($user?->institution ?? Institution::first());

        $canViewFull = ($user instanceof User) || ($user instanceof Institution && $user->tokenCan('view-sensitive-data'));

        $students = QueryBuilder::for(Student::class)
            ->with(['parent', 'latestLearningActivity', 'vaccine', 'aid', 'institution'])
            ->allowedFilters(
                AllowedFilter::partial('full_name'),
                AllowedFilter::exact('gender'),
                AllowedFilter::exact('nisn'),
                AllowedFilter::exact('local_nis'),
                AllowedFilter::exact('institution_id'),
                AllowedFilter::callback('rombel', function ($query, $value) {
                    $normalized = str_replace(' ', '', strtolower((string) $value));
                    $query->whereHas('latestLearningActivity', function ($q) use ($value, $normalized) {
                        $q->where('rombel_name', $value)
                            ->orWhereRaw("REPLACE(LOWER(rombel_name), ' ', '') = ?", [$normalized])
                            ->orWhereRaw("REPLACE(LOWER(rombel_name), ' ', '') LIKE ?", ["%{$normalized}%"]);
                    });
                }),
                AllowedFilter::callback('level', function ($query, $value) {
                    $query->whereHas('latestLearningActivity', function ($q) use ($value) {
                        $q->where('level', (int) $value);
                    });
                }),
                AllowedFilter::callback('status', function ($query, $value) {
                    $query->whereHas('latestLearningActivity', function ($q) use ($value) {
                        $q->where('student_status', 'LIKE', "%{$value}%");
                    });
                }),
            )
            ->allowedSorts(
                'full_name',
                'nisn',
                'local_nis',
                'created_at',
            )
            ->defaultSort('full_name')
            ->limit($request->integer('limit', 2000))
            ->get();

        $flattened = $students->map(function (Student $student) use ($canViewFull) {
            $p = $student->parent;
            $la = $student->latestLearningActivity;
            $aid = $student->aid;

            $maskIdentity = function (?string $val) use ($canViewFull): ?string {
                if (blank($val)) {
                    return null;
                }
                if ($canViewFull) {
                    return $val;
                }

                return strlen($val) > 8
                    ? substr($val, 0, 6).'********'.substr($val, -2)
                    : '********';
            };

            return [
                'sis_student_id' => $student->id,
                'emis_student_id' => $student->emis_student_id,
                'nisn' => $student->nisn,
                'local_nis' => $student->local_nis,
                'full_name' => $student->full_name,
                'gender' => $student->gender,
                'birth_place' => $student->birth_place,
                'birth_date' => $student->birth_date?->format('Y-m-d'),
                'rombel_name' => $la?->rombel_name,
                'level' => $la?->level,
                'academic_year' => $la?->academic_year,
                'semester' => $la?->semester,
                'absent_number' => $la?->absent_number,
                'student_status' => $la?->student_status,
                'homeroom_teacher' => $la?->homeroom_teacher,
                'nik' => $maskIdentity($student->nik),
                'kk_num' => $maskIdentity($student->kk_num),
                'full_address' => $student->address,
                'father_name' => $p?->father_name,
                'mother_name' => $p?->mother_name,
                'guardian_name' => $p?->guardian_name,
                'phone_number' => $student->phone_number ?? $p?->father_phone ?? $p?->mother_phone,
                'email' => $student->email,
                'kip_number' => $aid?->kip_number,
                'pkh_status' => $aid?->pkh_status ? 'Ya' : 'Tidak',
                'pip_status' => $aid?->pip_status ? 'Ya' : 'Tidak',
            ];
        });

        return response()->json([
            'institution' => $institution ? [
                'id' => $institution->id,
                'name' => $institution->name,
                'nsm' => $institution->nsm,
                'npsn' => $institution->npsn,
            ] : null,
            'exported_at' => now()->toIso8601String(),
            'total_records' => $flattened->count(),
            'available_rombels' => StudentLearningActivity::query()
                ->whereNotNull('rombel_name')
                ->where('rombel_name', '!=', '')
                ->distinct()
                ->pluck('rombel_name')
                ->values(),
            'students' => $flattened,
        ]);
    }

    /**
     * Detail Siswa Madrasah
     *
     * Menampilkan rincian data 1 siswa lengkap beserta relasi orang tua, rombel, vaksin, dan bansos.
     * Field sensitif NIK & KK disensor secara selektif jika pengakses bukan admin berwenang.
     */
    public function show(Request $request, Student $student): StudentResource
    {
        $student->loadMissing([
            'parent',
            'learningActivities',
            'latestLearningActivity',
            'vaccine',
            'aid',
            'institution',
        ]);

        return new StudentResource($student);
    }
}
