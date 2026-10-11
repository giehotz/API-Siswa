<?php

namespace App\Services\Emis;

class PayloadMapper
{
    /**
     * Petakan data siswa mentah dari ekstensi ke kolom tabel students.
     *
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    public function mapStudent(array $raw, int $institutionId): array
    {
        $address = is_array($raw['address'] ?? null) ? $raw['address'] : [];
        $rawAddressString = is_string($raw['address'] ?? null) ? $raw['address'] : null;

        // Gender mapping: supports 'L'/'P', 1/2, 'Laki-laki'/'Perempuan', or {id, name}
        $rawGender = $raw['gender'] ?? $raw['m_gender'] ?? $raw['m_gender_id'] ?? $raw['gender_id'] ?? null;
        if (is_array($rawGender)) {
            $rawGender = $rawGender['name'] ?? $rawGender['id'] ?? null;
        }

        $gender = null;
        if ($rawGender === 'L' || $rawGender === 'P') {
            $gender = $rawGender;
        } elseif (is_string($rawGender)) {
            $lowerG = strtolower(trim($rawGender));
            if (str_starts_with($lowerG, 'l') || str_contains($lowerG, 'laki')) {
                $gender = 'L';
            } elseif (str_starts_with($lowerG, 'p') || str_contains($lowerG, 'perempuan')) {
                $gender = 'P';
            }
        } elseif (is_numeric($rawGender)) {
            $gender = (int) $rawGender === 1 ? 'L' : ((int) $rawGender === 2 ? 'P' : null);
        }

        // Full address extraction
        $fullAddress = $raw['full_address'] ?? $address['full_address'] ?? $rawAddressString ?? null;
        $rt = $raw['rt'] ?? $address['rt'] ?? null;
        $rw = $raw['rw'] ?? $address['rw'] ?? null;
        $postalCode = $raw['postal_code_num'] ?? $raw['postal_code'] ?? $address['postal_code'] ?? null;
        $phone = $raw['phone_number'] ?? $raw['handphone'] ?? null;

        $subDistrict = $address['sub_district'] ?? $raw['sub_district'] ?? $raw['m_sub_district'] ?? null;
        $district = $address['district'] ?? $raw['district'] ?? $raw['m_district'] ?? null;
        $city = $address['city'] ?? $raw['city'] ?? $raw['m_city'] ?? null;
        $province = $address['province'] ?? $raw['province'] ?? $raw['m_province'] ?? null;

        $residenceStatus = $address['residence_status'] ?? $raw['residence_status'] ?? $raw['m_residence_status'] ?? null;
        $distanceToSchool = $address['distance_to_school'] ?? $raw['distance_to_school'] ?? $raw['m_residence_distance'] ?? null;
        $travelTime = $address['travel_time'] ?? $raw['travel_time'] ?? $raw['m_interval_time'] ?? null;
        $transportation = $address['transportation'] ?? $raw['transportation'] ?? $raw['m_transportation'] ?? null;

        $lat = isset($raw['latitude']) && is_numeric($raw['latitude'])
            ? (float) $raw['latitude']
            : (isset($address['latitude']) && is_numeric($address['latitude']) ? (float) $address['latitude'] : null);

        $lng = isset($raw['longitude']) && is_numeric($raw['longitude'])
            ? (float) $raw['longitude']
            : (isset($address['longitude']) && is_numeric($address['longitude']) ? (float) $address['longitude'] : null);

        $rawEmisId = $raw['emis_student_id'] ?? $raw['id'] ?? $raw['student_id'] ?? 0;
        if (is_array($rawEmisId)) {
            $rawEmisId = $rawEmisId['id'] ?? $rawEmisId['value'] ?? 0;
        }
        $emisStudentId = is_numeric($rawEmisId) ? (int) $rawEmisId : 0;

        $fullName = $this->sanitizeString($raw['full_name'] ?? $raw['name'] ?? $raw['nama'] ?? null, 255) ?? 'TANPA NAMA';

        return [
            'institution_id' => $institutionId,
            'emis_student_id' => $emisStudentId,
            'local_nis' => $this->sanitizeString($raw['local_nis'] ?? null, 30),
            'nisn' => $this->sanitizeString($raw['nisn'] ?? null, 10),
            'nik' => $this->sanitizeString($raw['nik'] ?? null, 20),
            'kk_num' => $this->sanitizeString($raw['kk_num'] ?? null, 20),
            'family_head_name' => $this->sanitizeString($raw['family_head_name'] ?? null),
            'full_name' => $fullName,
            'gender' => $gender,
            'birth_place' => $this->sanitizeString($raw['birth_place'] ?? null),
            'birth_date' => $this->sanitizeString($raw['birth_date'] ?? null),
            'religion' => $this->sanitizeString($raw['religion'] ?? $raw['m_religion'] ?? null, 50),
            'child_of_num' => isset($raw['child_of_num']) && is_numeric($raw['child_of_num']) ? (int) $raw['child_of_num'] : null,
            'siblings_num' => isset($raw['siblings_num']) && is_numeric($raw['siblings_num']) ? (int) $raw['siblings_num'] : null,
            'phone_number' => $this->sanitizeString($phone, 20),
            'email' => $this->sanitizeString($raw['email'] ?? null, 100),
            'hobby' => $this->sanitizeString($raw['hobby'] ?? $raw['m_hobby'] ?? null, 50),
            'life_goal' => $this->sanitizeString($raw['life_goal'] ?? $raw['m_life_goal'] ?? null, 50),

            // Alamat & Domisili
            'full_address' => $this->sanitizeString($fullAddress),
            'rt' => $this->sanitizeString($rt, 5),
            'rw' => $this->sanitizeString($rw, 5),
            'sub_district' => $this->sanitizeString($subDistrict),
            'district' => $this->sanitizeString($district),
            'city' => $this->sanitizeString($city),
            'province' => $this->sanitizeString($province),
            'postal_code' => $this->sanitizeString($postalCode, 10),
            'residence_status' => $this->sanitizeString($residenceStatus),
            'distance_to_school' => $this->sanitizeString($distanceToSchool),
            'travel_time' => $this->sanitizeString($travelTime),
            'transportation' => $this->sanitizeString($transportation),
            'latitude' => $lat,
            'longitude' => $lng,

            'raw_payload' => config('emis.store_raw_payload', false) ? $raw : null,
            'last_synced_at' => now(),
        ];
    }

    /**
     * Petakan data orang tua & wali.
     *
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>|null
     */
    public function mapParent(array $raw, int $studentId): ?array
    {
        $parents = $raw['parents'] ?? null;
        if (! is_array($parents)) {
            return null;
        }

        return [
            'student_id' => $studentId,
            'father_name' => $this->sanitizeString($parents['father_name'] ?? $parents['father_full_name'] ?? null),
            'father_nik' => $this->sanitizeString($parents['father_nik'] ?? null, 20),
            'father_life_status' => $this->sanitizeString($parents['father_life_status'] ?? null, 20),
            'father_phone' => $this->sanitizeString($parents['father_phone'] ?? $parents['father_phone_number'] ?? null, 20),
            'father_education' => $this->sanitizeString($parents['father_education'] ?? $parents['father_last_education'] ?? $parents['father_latest_education'] ?? null),
            'father_job' => $this->sanitizeString($parents['father_job'] ?? $parents['father_m_job'] ?? null),
            'father_income' => $this->sanitizeString($parents['father_income'] ?? $parents['father_average_income_per_month'] ?? null),

            'mother_name' => $this->sanitizeString($parents['mother_name'] ?? $parents['mother_full_name'] ?? null),
            'mother_nik' => $this->sanitizeString($parents['mother_nik'] ?? null, 20),
            'mother_life_status' => $this->sanitizeString($parents['mother_life_status'] ?? null, 20),
            'mother_phone' => $this->sanitizeString($parents['mother_phone'] ?? $parents['mother_phone_number'] ?? null, 20),
            'mother_education' => $this->sanitizeString($parents['mother_education'] ?? $parents['mother_last_education'] ?? $parents['mother_latest_education'] ?? null),
            'mother_job' => $this->sanitizeString($parents['mother_job'] ?? $parents['mother_m_job'] ?? null),
            'mother_income' => $this->sanitizeString($parents['mother_income'] ?? $parents['mother_average_income_per_month'] ?? null),

            'guardian_name' => $this->sanitizeString($parents['guardian_name'] ?? $parents['wali_full_name'] ?? null),
            'guardian_job' => $this->sanitizeString($parents['guardian_job'] ?? $parents['wali_m_job'] ?? $parents['wali_job'] ?? null),
            'guardian_phone' => $this->sanitizeString($parents['guardian_phone'] ?? $parents['wali_phone_number'] ?? null, 20),
            'guardian_relationship' => $this->sanitizeString($parents['guardian_relationship'] ?? $parents['wali'] ?? null),
        ];
    }

    /**
     * Petakan aktivitas pembelajaran / rombel.
     *
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>|null
     */
    public function mapLearningActivity(array $raw, int $studentId): ?array
    {
        $activity = $raw['learning_activity'] ?? null;
        if (! is_array($activity)) {
            return null;
        }

        $studyGroup = is_array($activity['study_group'] ?? null) ? $activity['study_group'] : [];
        $academicYearObj = is_array($activity['academic_year'] ?? null) ? $activity['academic_year'] : [];
        $academicYearStr = is_string($activity['academic_year'] ?? null)
            ? $activity['academic_year']
            : ($academicYearObj['year'] ?? $academicYearObj['name'] ?? null);

        if (empty($academicYearStr) && empty($studyGroup) && empty($activity['rombel_name'])) {
            return null;
        }

        $semester = 1;
        if (isset($activity['semester']) && is_numeric($activity['semester'])) {
            $semester = (int) $activity['semester'];
        } elseif (isset($academicYearObj['semester']) && is_string($academicYearObj['semester'])) {
            $semLower = strtolower($academicYearObj['semester']);
            $semester = str_contains($semLower, 'genap') || str_contains($semLower, '2') ? 2 : 1;
        }

        $rawLevel = $activity['level'] ?? $studyGroup['level'] ?? $activity['m_level_id'] ?? null;
        if (is_array($rawLevel)) {
            $rawLevel = $rawLevel['name'] ?? $rawLevel['id'] ?? null;
        }
        $level = is_numeric($rawLevel) ? (int) $rawLevel : null;

        $studentStatus = $this->sanitizeString($activity['student_status'] ?? null, 30) ?? 'Aktif';
        $statusDesc = $this->sanitizeString($activity['status_description'] ?? null);

        return [
            'student_id' => $studentId,
            'academic_year' => $this->sanitizeString($academicYearStr, 20) ?: (date('Y').'/'.(date('Y') + 1)),
            'semester' => $semester,
            'level' => $level,
            'rombel_name' => $this->sanitizeString($activity['rombel_name'] ?? $studyGroup['name'] ?? null, 50),
            'absent_number' => isset($activity['absent_number']) && is_numeric($activity['absent_number']) ? (int) $activity['absent_number'] : null,
            'homeroom_teacher' => $this->sanitizeString($activity['homeroom_teacher'] ?? $studyGroup['teacher_name'] ?? null),
            'classroom' => $this->sanitizeString($activity['classroom'] ?? $studyGroup['room_name'] ?? null),
            'student_status' => $studentStatus,
            'status_description' => $statusDesc,
            'admission_date' => $this->sanitizeString($activity['admission_date'] ?? null),
        ];
    }

    /**
     * Petakan riwayat vaksin siswa.
     *
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>|null
     */
    public function mapVaccine(array $raw, int $studentId): ?array
    {
        $vaccines = $raw['vaccines'] ?? null;
        $studentVaccineTypes = $raw['student_vaccine_types'] ?? null;
        $vaccineTypeIds = $raw['vaccine_type_ids'] ?? null;

        if (! is_array($vaccines) && ! is_array($studentVaccineTypes) && empty($vaccineTypeIds)) {
            return null;
        }

        $hepa = false;
        $polio = false;
        $bcg = false;
        $campak = false;
        $dpt = false;
        $covid = false;

        if (is_array($vaccines)) {
            $hepa = (bool) ($vaccines['hepatitis_b'] ?? false);
            $polio = (bool) ($vaccines['polio'] ?? false);
            $bcg = (bool) ($vaccines['bcg'] ?? false);
            $campak = (bool) ($vaccines['campak'] ?? false);
            $dpt = (bool) ($vaccines['dpt'] ?? false);
            $covid = (bool) ($vaccines['covid19'] ?? false);
        }

        if (is_array($studentVaccineTypes)) {
            foreach ($studentVaccineTypes as $vt) {
                $name = strtolower($vt['m_vaccine_type']['name'] ?? $vt['name'] ?? '');
                $typeId = (int) ($vt['m_vaccine_type_id'] ?? $vt['id'] ?? 0);
                if (str_contains($name, 'hepatitis') || $typeId === 1) {
                    $hepa = true;
                }
                if (str_contains($name, 'polio') || $typeId === 2) {
                    $polio = true;
                }
                if (str_contains($name, 'bcg') || $typeId === 3) {
                    $bcg = true;
                }
                if (str_contains($name, 'campak') || $typeId === 4) {
                    $campak = true;
                }
                if (str_contains($name, 'dpt') || $typeId === 5) {
                    $dpt = true;
                }
                if (str_contains($name, 'covid') || $typeId === 6) {
                    $covid = true;
                }
            }
        }

        if (! empty($vaccineTypeIds) && is_string($vaccineTypeIds)) {
            $ids = array_map('intval', explode(',', $vaccineTypeIds));
            if (in_array(1, $ids)) {
                $hepa = true;
            }
            if (in_array(2, $ids)) {
                $polio = true;
            }
            if (in_array(3, $ids)) {
                $bcg = true;
            }
            if (in_array(4, $ids)) {
                $campak = true;
            }
            if (in_array(5, $ids)) {
                $dpt = true;
            }
            if (in_array(6, $ids)) {
                $covid = true;
            }
        }

        return [
            'student_id' => $studentId,
            'hepatitis_b' => $hepa,
            'polio' => $polio,
            'bcg' => $bcg,
            'campak' => $campak,
            'dpt' => $dpt,
            'covid19' => $covid,
            'raw_vaccine_data' => $studentVaccineTypes ?? ($vaccines['raw'] ?? null),
        ];
    }

    /**
     * Petakan bantuan sosial & KIP.
     *
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>|null
     */
    public function mapAid(array $raw, int $studentId): ?array
    {
        $aid = $raw['aid'] ?? [];
        $hasAidRoot = isset($raw['nomor_kip']) || isset($raw['pkh_family']) || isset($raw['dtks_is_active']) || isset($raw['m_fund_source']);

        if (empty($aid) && ! $hasAidRoot) {
            return null;
        }

        $kipNumber = $aid['kip_number'] ?? $raw['nomor_kip'] ?? null;
        $isKipSync = (bool) ($aid['is_kip_sync'] ?? ($raw['is_kip_sync'] ?? false));
        $pkh = (bool) ($aid['pkh_status'] ?? (($raw['pkh_is_active'] ?? '') === '1' || ($raw['pkh_family'] ?? '') === '1' || ! empty($raw['pkh_is_active']) || ! empty($raw['pkh_family'])));
        $dtks = (bool) ($aid['dtks'] ?? (($raw['dtks_is_active'] ?? '') === '1' || ! empty($raw['dtks_is_active'])));
        $pip = (bool) ($aid['pip_status'] ?? false);
        $fundSource = $aid['fund_source'] ?? $raw['m_fund_source'] ?? (isset($raw['m_fund_source_id']) && is_scalar($raw['m_fund_source_id']) ? (string) $raw['m_fund_source_id'] : null);

        return [
            'student_id' => $studentId,
            'kip_number' => $this->sanitizeString($kipNumber, 30),
            'is_kip_sync' => $isKipSync,
            'pkh_status' => $pkh,
            'pip_status' => $pip,
            'dtks' => $dtks,
            'fund_source' => $this->sanitizeString($fundSource),
        ];
    }

    /**
     * Bersihkan nilai string untuk menjaga konsistensi format.
     * Mampu mengekstrak teks secara rekursif dari array/objek relasi EMIS
     * seperti ['name' => '...'], ['label' => '...'], ['year' => '...'], dll.
     */
    protected function sanitizeString(mixed $val, ?int $maxLen = null): ?string
    {
        if ($val === null || $val === '') {
            return null;
        }

        // Resolusi rekursif jika bernilai array (misal relasi EMIS {id: 1, name: '...'})
        while (is_array($val)) {
            $extracted = $val['name']
                ?? $val['year']
                ?? $val['label']
                ?? $val['title']
                ?? $val['value']
                ?? $val['description']
                ?? $val['keterangan']
                ?? $val['text']
                ?? (isset($val[0]) ? $val[0] : null);

            if ($extracted === null || $extracted === $val) {
                return null;
            }

            $val = $extracted;
        }

        // Resolusi jika bernilai objek
        if (is_object($val)) {
            $val = $val->name
                ?? $val->year
                ?? $val->label
                ?? $val->title
                ?? $val->value
                ?? (method_exists($val, '__toString') ? (string) $val : null);

            if ($val === null) {
                return null;
            }
        }

        if (! is_scalar($val)) {
            return null;
        }

        if (is_bool($val)) {
            return null;
        }

        $str = trim((string) $val);
        if ($str === '') {
            return null;
        }

        if ($maxLen !== null && mb_strlen($str) > $maxLen) {
            return mb_substr($str, 0, $maxLen);
        }

        return $str;
    }
}
