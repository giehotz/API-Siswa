<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Institution;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Student
 */
class StudentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $canViewFull = ($user instanceof User) || ($user instanceof Institution && $user->tokenCan('view-sensitive-data'));

        return [
            'id' => $this->id,
            'institution_id' => $this->institution_id,
            'emis_student_id' => $this->emis_student_id,
            'full_name' => $this->full_name,
            'gender' => $this->gender,
            'nisn' => $this->nisn,
            'local_nis' => $this->local_nis,
            'nik' => $this->maskIdentity($this->nik, $canViewFull),
            'kk_num' => $this->maskIdentity($this->kk_num, $canViewFull),
            'birth_place' => $this->birth_place,
            'birth_date' => $this->birth_date?->format('Y-m-d'),
            'address' => [
                'full_address' => $this->address,
                'rt' => $this->rt,
                'rw' => $this->rw,
                'sub_district' => $this->sub_district,
                'district' => $this->district,
                'city' => $this->city,
                'province' => $this->province,
                'postal_code' => $this->postal_code,
            ],
            'learning_activity' => $this->whenLoaded('latestLearningActivity', fn () => [
                'academic_year' => $this->latestLearningActivity?->academic_year,
                'semester' => $this->latestLearningActivity?->semester,
                'level' => $this->latestLearningActivity?->level,
                'rombel_name' => $this->latestLearningActivity?->rombel_name,
                'absent_number' => $this->latestLearningActivity?->absent_number,
                'student_status' => $this->latestLearningActivity?->student_status,
                'homeroom_teacher' => $this->latestLearningActivity?->homeroom_teacher,
            ]),
            'parent' => $this->whenLoaded('parent', fn () => [
                'father_name' => $this->parent?->father_name,
                'father_nik' => $this->maskIdentity($this->parent?->father_nik, $canViewFull),
                'mother_name' => $this->parent?->mother_name,
                'mother_nik' => $this->maskIdentity($this->parent?->mother_nik, $canViewFull),
                'guardian_name' => $this->parent?->guardian_name,
            ]),
            'vaccine' => $this->whenLoaded('vaccine', fn () => [
                'hepatitis_b' => (bool) $this->vaccine?->hepatitis_b,
                'polio' => (bool) $this->vaccine?->polio,
                'bcg' => (bool) $this->vaccine?->bcg,
                'campak' => (bool) $this->vaccine?->campak,
                'dpt' => (bool) $this->vaccine?->dpt,
                'covid19' => (bool) $this->vaccine?->covid19,
            ]),
            'aid' => $this->whenLoaded('aid', fn () => [
                'kip_number' => $this->aid?->kip_number,
                'is_kip_sync' => (bool) $this->aid?->is_kip_sync,
                'pkh_status' => (bool) $this->aid?->pkh_status,
                'pip_status' => (bool) $this->aid?->pip_status,
                'dtks' => (bool) $this->aid?->dtks,
            ]),
            'institution' => $this->whenLoaded('institution', fn () => [
                'id' => $this->institution?->id,
                'name' => $this->institution?->name,
                'nsm' => $this->institution?->nsm,
                'npsn' => $this->institution?->npsn,
            ]),
            'last_synced_at' => $this->last_synced_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    protected function maskIdentity(?string $value, bool $canViewFull): ?string
    {
        if (blank($value)) {
            return null;
        }

        if ($canViewFull) {
            return $value;
        }

        $len = strlen($value);
        if ($len <= 8) {
            return str_repeat('*', $len);
        }

        $start = substr($value, 0, 4);
        $end = substr($value, -4);
        $maskedMiddle = str_repeat('*', $len - 8);

        return "{$start}{$maskedMiddle}{$end}";
    }
}
