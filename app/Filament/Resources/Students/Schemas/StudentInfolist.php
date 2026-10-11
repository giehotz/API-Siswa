<?php

namespace App\Filament\Resources\Students\Schemas;

use App\Models\Student;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class StudentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('StudentDetails')
                    ->columnSpanFull()
                    ->tabs([
                        // TAB 1: IDENTITAS & KEPENDUDUKAN
                        Tab::make('Identitas & Kependudukan')
                            ->icon('heroicon-o-user')
                            ->schema([
                                Section::make('Data Pribadi Siswa')
                                    ->description('Informasi pokok identitas siswa sesuai data EMIS Kemenag')
                                    ->schema([
                                        Grid::make(3)->schema([
                                            TextEntry::make('full_name')
                                                ->label('Nama Lengkap')
                                                ->weight('bold')
                                                ->columnSpan(2),

                                            TextEntry::make('emis_student_id')
                                                ->label('ID Siswa EMIS')
                                                ->copyable()
                                                ->badge()
                                                ->color('gray'),

                                            TextEntry::make('nisn')
                                                ->label('NISN')
                                                ->copyable()
                                                ->weight('medium')
                                                ->placeholder('-'),

                                            TextEntry::make('local_nis')
                                                ->label('NIS Lokal')
                                                ->copyable()
                                                ->placeholder('-'),

                                            TextEntry::make('gender')
                                                ->label('Jenis Kelamin')
                                                ->badge()
                                                ->formatStateUsing(fn (?string $state): string => match ($state) {
                                                    'L' => 'Laki-laki (L)',
                                                    'P' => 'Perempuan (P)',
                                                    default => $state ?? '-',
                                                })
                                                ->color(fn (?string $state): string => match ($state) {
                                                    'L' => 'info',
                                                    'P' => 'danger',
                                                    default => 'gray',
                                                }),

                                            TextEntry::make('birth_place')
                                                ->label('Tempat Lahir')
                                                ->placeholder('-'),

                                            TextEntry::make('birth_date')
                                                ->label('Tanggal Lahir')
                                                ->date('d F Y')
                                                ->placeholder('-'),

                                            TextEntry::make('religion')
                                                ->label('Agama')
                                                ->placeholder('-'),

                                            TextEntry::make('nationality')
                                                ->label('Kewarganegaraan')
                                                ->default('WNI')
                                                ->placeholder('WNI'),

                                            TextEntry::make('child_of_num')
                                                ->label('Anak Ke-')
                                                ->placeholder('-'),

                                            TextEntry::make('siblings_num')
                                                ->label('Jumlah Saudara')
                                                ->placeholder('-'),

                                            TextEntry::make('life_goal')
                                                ->label('Cita-cita')
                                                ->placeholder('-'),

                                            TextEntry::make('hobby')
                                                ->label('Hobi')
                                                ->placeholder('-'),
                                        ]),
                                    ]),

                                Section::make('Data Kependudukan (Dukcapil)')
                                    ->description('Nomor Identitas Kependudukan dan Kartu Keluarga')
                                    ->schema([
                                        Grid::make(3)->schema([
                                            TextEntry::make('nik')
                                                ->label('NIK Siswa')
                                                ->copyable()
                                                ->weight('medium')
                                                ->placeholder('-'),

                                            TextEntry::make('kk_num')
                                                ->label('Nomor Kartu Keluarga (KK)')
                                                ->copyable()
                                                ->placeholder('-'),

                                            TextEntry::make('family_head_name')
                                                ->label('Nama Kepala Keluarga')
                                                ->placeholder('-'),

                                            TextEntry::make('institution.name')
                                                ->label('Madrasah / Lembaga')
                                                ->badge()
                                                ->color('primary')
                                                ->columnSpanFull(),
                                        ]),
                                    ]),
                            ]),

                        // TAB 2: AKADEMIK & ROMBEL
                        Tab::make('Akademik & Rombel')
                            ->icon('heroicon-o-academic-cap')
                            ->schema([
                                Section::make('Rombongan Belajar & Penempatan Kelas')
                                    ->description('Informasi rombel aktif dan tahun pelajaran terkini')
                                    ->schema([
                                        Grid::make(3)->schema([
                                            TextEntry::make('latestLearningActivity.academic_year')
                                                ->label('Tahun Ajaran')
                                                ->badge()
                                                ->color('primary')
                                                ->placeholder('-'),

                                            TextEntry::make('latestLearningActivity.semester')
                                                ->label('Semester')
                                                ->formatStateUsing(fn ($state) => $state == 1 ? '1 (Ganjil)' : ($state == 2 ? '2 (Genap)' : ($state ?? '-')))
                                                ->badge()
                                                ->color('info')
                                                ->placeholder('-'),

                                            TextEntry::make('latestLearningActivity.level')
                                                ->label('Tingkat / Kelas')
                                                ->formatStateUsing(fn ($state) => $state ? "Tingkat {$state}" : '-')
                                                ->badge()
                                                ->placeholder('-'),

                                            TextEntry::make('latestLearningActivity.rombel_name')
                                                ->label('Nama Rombel')
                                                ->weight('bold')
                                                ->color('primary')
                                                ->placeholder('-'),

                                            TextEntry::make('latestLearningActivity.absent_number')
                                                ->label('Nomor Absen')
                                                ->placeholder('-'),

                                            TextEntry::make('latestLearningActivity.classroom')
                                                ->label('Ruang Kelas')
                                                ->placeholder('-'),

                                            TextEntry::make('latestLearningActivity.homeroom_teacher')
                                                ->label('Wali Kelas')
                                                ->weight('medium')
                                                ->placeholder('-')
                                                ->columnSpan(2),

                                            TextEntry::make('latestLearningActivity.admission_date')
                                                ->label('Tanggal Masuk')
                                                ->date('d F Y')
                                                ->placeholder('-'),

                                            TextEntry::make('latestLearningActivity.student_status')
                                                ->label('Status Siswa')
                                                ->badge()
                                                ->color(fn (?string $state): string => match ($state) {
                                                    'active', 'Aktif', 'Siswa Baru' => 'success',
                                                    'Mutasi', 'Lulus' => 'warning',
                                                    default => 'gray',
                                                })
                                                ->placeholder('-'),

                                            TextEntry::make('latestLearningActivity.status_description')
                                                ->label('Keterangan Status')
                                                ->placeholder('-')
                                                ->columnSpan(2),
                                        ]),
                                    ]),
                            ]),

                        // TAB 3: ORANG TUA & WALI
                        Tab::make('Orang Tua & Wali')
                            ->icon('heroicon-o-users')
                            ->schema([
                                Section::make('Data Ayah Kandung')
                                    ->schema([
                                        Grid::make(3)->schema([
                                            TextEntry::make('parent.father_name')
                                                ->label('Nama Ayah')
                                                ->weight('bold')
                                                ->placeholder('-'),

                                            TextEntry::make('parent.father_nik')
                                                ->label('NIK Ayah')
                                                ->copyable()
                                                ->placeholder('-'),

                                            TextEntry::make('parent.father_life_status')
                                                ->label('Status Ayah')
                                                ->badge()
                                                ->color(fn (?string $state) => match ($state) {
                                                    'Masih Hidup', 'Hidup' => 'success',
                                                    'Meninggal' => 'danger',
                                                    default => 'gray',
                                                })
                                                ->placeholder('-'),

                                            TextEntry::make('parent.father_education')
                                                ->label('Pendidikan Ayah')
                                                ->placeholder('-'),

                                            TextEntry::make('parent.father_job')
                                                ->label('Pekerjaan Ayah')
                                                ->placeholder('-'),

                                            TextEntry::make('parent.father_income')
                                                ->label('Penghasilan Ayah')
                                                ->placeholder('-'),

                                            TextEntry::make('parent.father_phone')
                                                ->label('No. HP Ayah')
                                                ->placeholder('-'),
                                        ]),
                                    ]),

                                Section::make('Data Ibu Kandung')
                                    ->schema([
                                        Grid::make(3)->schema([
                                            TextEntry::make('parent.mother_name')
                                                ->label('Nama Ibu')
                                                ->weight('bold')
                                                ->placeholder('-'),

                                            TextEntry::make('parent.mother_nik')
                                                ->label('NIK Ibu')
                                                ->copyable()
                                                ->placeholder('-'),

                                            TextEntry::make('parent.mother_life_status')
                                                ->label('Status Ibu')
                                                ->badge()
                                                ->color(fn (?string $state) => match ($state) {
                                                    'Masih Hidup', 'Hidup' => 'success',
                                                    'Meninggal' => 'danger',
                                                    default => 'gray',
                                                })
                                                ->placeholder('-'),

                                            TextEntry::make('parent.mother_education')
                                                ->label('Pendidikan Ibu')
                                                ->placeholder('-'),

                                            TextEntry::make('parent.mother_job')
                                                ->label('Pekerjaan Ibu')
                                                ->placeholder('-'),

                                            TextEntry::make('parent.mother_income')
                                                ->label('Penghasilan Ibu')
                                                ->placeholder('-'),

                                            TextEntry::make('parent.mother_phone')
                                                ->label('No. HP Ibu')
                                                ->placeholder('-'),
                                        ]),
                                    ]),

                                Section::make('Data Wali Siswa')
                                    ->schema([
                                        Grid::make(3)->schema([
                                            TextEntry::make('parent.guardian_name')
                                                ->label('Nama Wali')
                                                ->placeholder('-'),

                                            TextEntry::make('parent.guardian_relationship')
                                                ->label('Hubungan / Status Wali')
                                                ->placeholder('-'),

                                            TextEntry::make('parent.guardian_job')
                                                ->label('Pekerjaan Wali')
                                                ->placeholder('-'),

                                            TextEntry::make('parent.guardian_phone')
                                                ->label('No. HP Wali')
                                                ->placeholder('-'),
                                        ]),
                                    ]),
                            ]),

                        // TAB 4: DOMISILI & TRANSPORTASI
                        Tab::make('Domisili & Transportasi')
                            ->icon('heroicon-o-home')
                            ->schema([
                                Section::make('Alamat Lengkap & Wilayah')
                                    ->schema([
                                        Grid::make(3)->schema([
                                            TextEntry::make('full_address')
                                                ->label('Alamat Lengkap')
                                                ->columnSpanFull()
                                                ->placeholder('-'),

                                            TextEntry::make('rt')
                                                ->label('RT')
                                                ->placeholder('-'),

                                            TextEntry::make('rw')
                                                ->label('RW')
                                                ->placeholder('-'),

                                            TextEntry::make('postal_code')
                                                ->label('Kode Pos')
                                                ->placeholder('-'),

                                            TextEntry::make('sub_district')
                                                ->label('Kelurahan / Desa')
                                                ->placeholder('-'),

                                            TextEntry::make('district')
                                                ->label('Kecamatan')
                                                ->placeholder('-'),

                                            TextEntry::make('city')
                                                ->label('Kabupaten / Kota')
                                                ->placeholder('-'),

                                            TextEntry::make('province')
                                                ->label('Provinsi')
                                                ->placeholder('-'),

                                            TextEntry::make('residence_status')
                                                ->label('Status Tempat Tinggal')
                                                ->placeholder('-'),
                                        ]),
                                    ]),

                                Section::make('Transportasi & Aksesibilitas')
                                    ->schema([
                                        Grid::make(3)->schema([
                                            TextEntry::make('distance_to_school')
                                                ->label('Jarak ke Madrasah')
                                                ->placeholder('-'),

                                            TextEntry::make('travel_time')
                                                ->label('Waktu Tempuh')
                                                ->placeholder('-'),

                                            TextEntry::make('transportation')
                                                ->label('Transportasi')
                                                ->placeholder('-'),

                                            TextEntry::make('phone_number')
                                                ->label('No. Handphone Siswa')
                                                ->placeholder('-'),

                                            TextEntry::make('email')
                                                ->label('Email Siswa')
                                                ->placeholder('-'),

                                            TextEntry::make('coordinates')
                                                ->label('Titik Koordinat (Lat, Lng)')
                                                ->state(fn (Student $record): string => ($record->latitude && $record->longitude)
                                                    ? "{$record->latitude}, {$record->longitude}"
                                                    : '-')
                                                ->placeholder('-'),
                                        ]),
                                    ]),
                            ]),

                        // TAB 5: VAKSINASI & BANTUAN SOSIAL
                        Tab::make('Vaksinasi & Bantuan')
                            ->icon('heroicon-o-shield-check')
                            ->schema([
                                Section::make('Riwayat Imunisasi & Vaksinasi (6 Jenis)')
                                    ->description('Status riwayat imunisasi dasar lengkap siswa')
                                    ->schema([
                                        Grid::make(3)->schema([
                                            TextEntry::make('vaccine.hepatitis_b')
                                                ->label('Vaksin Hepatitis B')
                                                ->badge()
                                                ->formatStateUsing(fn ($state) => $state ? 'Ya' : 'Tidak')
                                                ->color(fn ($state) => $state ? 'success' : 'gray'),

                                            TextEntry::make('vaccine.polio')
                                                ->label('Vaksin Polio')
                                                ->badge()
                                                ->formatStateUsing(fn ($state) => $state ? 'Ya' : 'Tidak')
                                                ->color(fn ($state) => $state ? 'success' : 'gray'),

                                            TextEntry::make('vaccine.bcg')
                                                ->label('Vaksin BCG')
                                                ->badge()
                                                ->formatStateUsing(fn ($state) => $state ? 'Ya' : 'Tidak')
                                                ->color(fn ($state) => $state ? 'success' : 'gray'),

                                            TextEntry::make('vaccine.campak')
                                                ->label('Vaksin Campak')
                                                ->badge()
                                                ->formatStateUsing(fn ($state) => $state ? 'Ya' : 'Tidak')
                                                ->color(fn ($state) => $state ? 'success' : 'gray'),

                                            TextEntry::make('vaccine.dpt')
                                                ->label('Vaksin DPT')
                                                ->badge()
                                                ->formatStateUsing(fn ($state) => $state ? 'Ya' : 'Tidak')
                                                ->color(fn ($state) => $state ? 'success' : 'gray'),

                                            TextEntry::make('vaccine.covid19')
                                                ->label('Vaksin Covid-19')
                                                ->badge()
                                                ->formatStateUsing(fn ($state) => $state ? 'Ya' : 'Tidak')
                                                ->color(fn ($state) => $state ? 'success' : 'gray'),
                                        ]),
                                    ]),

                                Section::make('Bantuan Pendidikan & KIP')
                                    ->schema([
                                        Grid::make(3)->schema([
                                            TextEntry::make('aid.fund_source')
                                                ->label('Sumber Dana Pendidikan')
                                                ->badge()
                                                ->color('primary')
                                                ->placeholder('-'),

                                            TextEntry::make('aid.kip_number')
                                                ->label('Nomor KIP')
                                                ->copyable()
                                                ->placeholder('-'),

                                            TextEntry::make('aid.is_kip_sync')
                                                ->label('KIP Tersinkron')
                                                ->badge()
                                                ->formatStateUsing(fn ($state) => $state ? 'Ya' : 'Tidak')
                                                ->color(fn ($state) => $state ? 'success' : 'gray'),
                                        ]),
                                    ]),

                                Section::make('Bantuan Sosial (Kemensos / DTKS)')
                                    ->schema([
                                        Grid::make(3)->schema([
                                            TextEntry::make('aid.pkh_status')
                                                ->label('Penerima PKH')
                                                ->badge()
                                                ->formatStateUsing(fn ($state) => $state ? 'Ya' : 'Tidak')
                                                ->color(fn ($state) => $state ? 'info' : 'gray'),

                                            TextEntry::make('aid.pip_status')
                                                ->label('Penerima PIP')
                                                ->badge()
                                                ->formatStateUsing(fn ($state) => $state ? 'Ya' : 'Tidak')
                                                ->color(fn ($state) => $state ? 'info' : 'gray'),

                                            TextEntry::make('aid.dtks')
                                                ->label('DTKS Aktif')
                                                ->badge()
                                                ->formatStateUsing(fn ($state) => $state ? 'Ya' : 'Tidak')
                                                ->color(fn ($state) => $state ? 'success' : 'gray'),
                                        ]),
                                    ]),
                            ]),
                    ]),
            ]);
    }
}
