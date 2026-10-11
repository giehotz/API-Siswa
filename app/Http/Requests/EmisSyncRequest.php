<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class EmisSyncRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Persiapkan dan normalisasi input sebelum validasi dijalankan.
     * Mengamankan kompatibilitas jika ekstensi mengirim field 'id' bawaan EMIS alih-alih 'emis_student_id'.
     */
    protected function prepareForValidation(): void
    {
        $students = $this->input('students');

        if (is_array($students)) {
            $normalized = [];
            foreach ($students as $index => $item) {
                if (is_array($item)) {
                    if (empty($item['emis_student_id']) && ! empty($item['id'])) {
                        $item['emis_student_id'] = is_numeric($item['id']) ? (int) $item['id'] : $item['id'];
                    } elseif (empty($item['emis_student_id']) && ! empty($item['student_id'])) {
                        $item['emis_student_id'] = is_numeric($item['student_id']) ? (int) $item['student_id'] : $item['student_id'];
                    }

                    if (empty($item['full_name'])) {
                        $item['full_name'] = $item['name'] ?? $item['nama'] ?? 'TANPA NAMA';
                    }

                    $normalized[$index] = $item;
                } else {
                    $normalized[$index] = $item;
                }
            }

            $this->merge(['students' => $normalized]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'batch_uuid' => ['required', 'string', 'uuid'],
            'institution_id' => ['required', 'integer'],
            'synced_at' => ['nullable', 'date'],
            'chunk' => ['required', 'array'],
            'chunk.index' => ['required', 'integer', 'min:1'],
            'chunk.total' => ['required', 'integer', 'min:1'],
            'students' => ['required', 'array', 'min:1', 'max:50'],
            'students.*.emis_student_id' => ['required', 'integer'],
            'students.*.full_name' => ['required', 'string', 'max:255'],
            'students.*.nisn' => ['nullable', 'string', 'max:10'],
            'students.*.local_nis' => ['nullable', 'string', 'max:30'],
            'students.*.nik' => ['nullable', 'string', 'max:30'],
            'students.*.kk_num' => ['nullable', 'string', 'max:30'],
            'students.*._fetchTimeout' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Pesan error khusus dalam bahasa Indonesia.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'batch_uuid.required' => 'Identitas batch_uuid wajib disertakan.',
            'batch_uuid.uuid' => 'Format batch_uuid harus berupa UUID yang valid.',
            'institution_id.required' => 'institution_id wajib disertakan.',
            'students.required' => 'Daftar students wajib disertakan.',
            'students.array' => 'Daftar students harus berupa array.',
            'students.min' => 'Setiap chunk minimal harus memuat 1 siswa.',
            'students.max' => 'Setiap chunk maksimal hanya boleh memuat 50 siswa.',
            'students.*.emis_student_id.required' => 'Setiap siswa wajib memiliki emis_student_id.',
            'students.*.full_name.required' => 'Nama lengkap siswa wajib diisi.',
        ];
    }
}
