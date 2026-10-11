<?php

namespace App\Services\Emis;

use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StudentExcelExportService
{
    /**
     * Header 75 kolom sesuai standar ekspor EMIS Kemenag.
     *
     * @var list<string>
     */
    public const HEADERS = [
        'No',
        'NISN',
        'NIS Lokal',
        'Nama Lengkap',
        'Jenis Kelamin',
        'Tempat Lahir',
        'Tanggal Lahir',
        'Agama',
        'Kewarganegaraan',
        'Anak Ke',
        'Jumlah Saudara',
        'Alamat Lengkap',
        'RT',
        'RW',
        'Kelurahan / Desa',
        'Kecamatan',
        'Kabupaten / Kota',
        'Provinsi',
        'Kode Pos',
        'Status Tempat Tinggal',
        'Jarak ke Madrasah',
        'Waktu Tempuh',
        'Transportasi',
        'No Handphone',
        'Email',
        'Cita-cita',
        'Hobi',
        'Tahun Ajaran',
        'Semester',
        'Tingkat / Kelas',
        'Rombel',
        'Nomor Absen',
        'Wali Kelas',
        'Ruang Kelas',
        'Status Siswa',
        'Keterangan Status',
        'Tanggal Masuk',
        'Vaksin Hepatitis B',
        'Vaksin Polio',
        'Vaksin BCG',
        'Vaksin Campak',
        'Vaksin DPT',
        'Vaksin Covid-19',
        'Sumber Dana Pendidikan',
        'Nomor KIP',
        'KIP Tersinkron',
        'Nama Ayah',
        'Status Ayah',
        'Pendidikan Ayah',
        'Pekerjaan Ayah',
        'Penghasilan Ayah',
        'No HP Ayah',
        'Alamat Ayah',
        'Nama Ibu',
        'Status Ibu',
        'Pendidikan Ibu',
        'Pekerjaan Ibu',
        'Penghasilan Ibu',
        'No HP Ibu',
        'Alamat Ibu',
        'Nama Wali',
        'Status Wali',
        'Pekerjaan Wali',
        'Penghasilan Wali',
        'No HP Wali',
        'NIK Siswa',
        'No Kartu Keluarga',
        'Nama Kepala Keluarga',
        'NIK Ayah',
        'NIK Ibu',
        'NIK Wali',
        'Penerima PKH',
        'Penerima BPNT',
        'Anggota PBI',
        'DTKS Aktif',
    ];

    /**
     * Ekspor daftar siswa ke file Excel XLSX dan kembalikan respon unduhan biner.
     *
     * @param  Builder<Student>|null  $query
     */
    public function export(?Builder $query = null, ?string $filename = null): BinaryFileResponse
    {
        $query ??= Student::query();

        // Eager load seluruh relasi pendukung untuk mencegah N+1 query
        $query->with([
            'parent',
            'latestLearningActivity',
            'vaccine',
            'aid',
            'institution',
        ]);

        $tempPath = tempnam(sys_get_temp_dir(), 'emis_export_').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($tempPath);

        // Tulis baris header
        $writer->addRow(Row::fromValues(self::HEADERS));

        $no = 1;
        // Streaming chunk data untuk efisiensi memori (memory safe)
        $query->chunk(200, function ($students) use ($writer, &$no) {
            /** @var Student $s */
            foreach ($students as $s) {
                $p = $s->parent;
                $la = $s->latestLearningActivity;
                $v = $s->vaccine;
                $aid = $s->aid;

                $gender = match ($s->gender) {
                    'L' => 'Laki-laki',
                    'P' => 'Perempuan',
                    default => $s->gender ?? '',
                };

                $semester = match ($la?->semester) {
                    1 => 'Ganjil',
                    2 => 'Genap',
                    default => $la?->semester ? (string) $la->semester : '',
                };

                $rowValues = [
                    $no++,
                    $s->nisn ?? '',
                    $s->local_nis ?? '',
                    $s->full_name ?? '',
                    $gender,
                    $s->birth_place ?? '',
                    $s->birth_date?->format('Y-m-d') ?? '',
                    $s->religion ?? '',
                    'wni',
                    $s->child_of_num ?? '',
                    $s->siblings_num ?? '',
                    $s->full_address ?? '',
                    $s->rt ?? '',
                    $s->rw ?? '',
                    $s->sub_district ?? '',
                    $s->district ?? '',
                    $s->city ?? '',
                    $s->province ?? '',
                    $s->postal_code ?? '',
                    $s->residence_status ?? '',
                    $s->distance_to_school ?? '',
                    $s->travel_time ?? '',
                    $s->transportation ?? '',
                    $s->phone_number ?? '',
                    $s->email ?? '',
                    $s->life_goal ?? '',
                    $s->hobby ?? '',
                    $la?->academic_year ?? '',
                    $semester,
                    $la?->level ?? '',
                    $la?->rombel_name ?? '',
                    $la?->absent_number ?? '',
                    $la?->homeroom_teacher ?? '',
                    $la?->classroom ?? '',
                    $la?->student_status ?? '',
                    $la?->status_description ?? '',
                    $la?->admission_date?->format('Y-m-d') ?? '',
                    $v?->hepatitis_b ? 'Ya' : 'Tidak',
                    $v?->polio ? 'Ya' : 'Tidak',
                    $v?->bcg ? 'Ya' : 'Tidak',
                    $v?->campak ? 'Ya' : 'Tidak',
                    $v?->dpt ? 'Ya' : 'Tidak',
                    $v?->covid19 ? 'Ya' : 'Tidak',
                    $aid?->fund_source ?? '',
                    $aid?->kip_number ?? '',
                    $aid?->is_kip_sync ? 'Ya' : 'Tidak',
                    $p?->father_name ?? '',
                    $p?->father_life_status ?? '',
                    $p?->father_education ?? '',
                    $p?->father_job ?? '',
                    $p?->father_income ?? '',
                    $p?->father_phone ?? '',
                    $s->full_address ?? '',
                    $p?->mother_name ?? '',
                    $p?->mother_life_status ?? '',
                    $p?->mother_education ?? '',
                    $p?->mother_job ?? '',
                    $p?->mother_income ?? '',
                    $p?->mother_phone ?? '',
                    $s->full_address ?? '',
                    $p?->guardian_name ?? '',
                    $p?->guardian_relationship ?? '',
                    $p?->guardian_job ?? '',
                    '',
                    $p?->guardian_phone ?? '',
                    $s->nik ?? '',
                    $s->kk_num ?? '',
                    $s->family_head_name ?? '',
                    $p?->father_nik ?? '',
                    $p?->mother_nik ?? '',
                    $p?->father_nik ?? '',
                    $aid?->pkh_status ? 'Ya' : 'Tidak',
                    $aid?->pkh_status ? 'Ya' : 'Tidak',
                    $aid?->dtks ? 'Ya' : 'Tidak',
                    $aid?->dtks ? 'Ya' : 'Tidak',
                ];

                $writer->addRow(Row::fromValues($rowValues));
            }
        });

        $writer->close();

        $filename ??= 'EMIS_Siswa_Data_Tersimpan_'.date('Ymd_His').'.xlsx';

        return response()->download(
            $tempPath,
            $filename,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]
        )->deleteFileAfterSend(true);
    }
}
