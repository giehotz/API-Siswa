# API-Siswa (SIS-EMIS Backend & Integration Gateway)

[![Laravel](https://img.shields.io/badge/Laravel-12.x-FF2D20?style=flat&logo=laravel&logoColor=white)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.4-777BB4?style=flat&logo=php&logoColor=white)](https://php.net)
[![Filament](https://img.shields.io/badge/Filament-v5-FFA439?style=flat)](https://filamentphp.com)
[![License](https://img.shields.io/badge/License-Proprietary-blue.svg)](LICENSE)

**API-Siswa (SIS-EMIS)** adalah Sistem Informasi Sekolah terintegrasi yang berfungsi sebagai *Single Source of Truth* (Pusat Data Tunggal) kesiswaan madrasah. Sistem ini mengekstraksi data resmi secara otomatis dari portal **EMIS 4.0 Kementerian Agama RI** via Ekstensi Browser Chrome, mengelolanya melalui antarmuka **Filament Admin Panel**, dan menyediakan **RESTful API Gateway** aman untuk dikonsumsi oleh berbagai aplikasi eksternal (seperti **Aplikasi Absensi Madrasah**, **CBT Ujian Online**, **E-Rapor Digital Kemenag**, maupun **LMS**).

---

## Daftar Isi
1. [Arsitektur & Konsep Dasar](#1-arsitektur--konsep-dasar)
2. [Fitur Utama Sistem](#2-fitur-utama-sistem)
3. [Panduan Integrasi API Eksternal](#3-panduan-integrasi-api-eksternal)
   - [Informasi Dasar Protokol](#informasi-dasar-protokol)
   - [Cara Mendapatkan Token API](#cara-mendapatkan-token-api)
   - [Tingkatan Hak Akses Token (Abilities & UU PDP)](#tingkatan-hak-akses-token-abilities--uu-pdp)
4. [Katalog Endpoint API](#4-katalog-endpoint-api)
   - [1. Cek Identitas Token (`GET /api/v1/auth/me`)](#1-cek-identitas-token-get-apiv1authme)
   - [2. Daftar Rombel Resmi (`GET /api/v1/students/rombels`)](#2-daftar-rombel-resmi-get-apiv1studentsrombels)
   - [3. Ekspor Data Siswa Datar (`GET /api/v1/students/export-json`)](#3-ekspor-data-siswa-datar-get-apiv1studentsexport-json)
   - [4. Daftar Siswa RESTful Paginasi (`GET /api/v1/students`)](#4-daftar-siswa-restful-paginasi-get-apiv1students)
   - [5. Detail Siswa Tunggal (`GET /api/v1/students/{id}`)](#5-detail-siswa-tunggal-get-apiv1studentsid)
5. [Filter & Pencarian Cerdas (Case & Space Insensitive)](#5-filter--pencarian-cerdas-case--space-insensitive)
6. [Contoh Implementasi pada Aplikasi Klien](#6-contoh-implementasi-pada-aplikasi-klien)
   - [A. Laravel Client (Studi Kasus: absensi-laravel)](#a-laravel-client-studi-kasus-absensi-laravel)
   - [B. PHP Native (cURL)](#b-php-native-curl)
   - [C. JavaScript / Node.js (Fetch API)](#c-javascript--nodejs-fetch-api)
   - [D. Python (Requests)](#d-python-requests)
   - [E. cURL Terminal (Testing Cepat)](#e-curl-terminal-testing-cepat)
7. [Standar Kode Status HTTP & Penanganan Kesalahan](#7-standar-kode-status-http--penanganan-kesalahan)
8. [Panduan Instalasi & Menjalankan Proyek](#8-panduan-instalasi--menjalankan-proyek)
9. [Troubleshooting & Solusi Masalah Umum](#9-troubleshooting--solusi-masalah-umum)

---

## 1. Arsitektur & Konsep Dasar

Aplikasi menggunakan pola arsitektur **Hybrid Extension Connector**:
- Penarikan data dari portal EMIS Kemenag dilakukan di sisi peramban browser operator melalui ekstensi Chrome (Manifest V3) untuk mencegah pemblokiran IP server madrasah.
- Token JWT portal EMIS tidak pernah disimpan di server Laravel.
- Komunikasi antara aplikasi klien dan server dilindungi oleh **Laravel Sanctum Bearer Token** per madrasah (`institution_id`).

```
┌─────────────────────────────────┐
│     Portal EMIS 4.0 Kemenag     │
└────────────────┬────────────────┘
                 │ (Ekstensi Chrome Sniffer)
                 ▼
┌─────────────────────────────────┐
│        Server SIS-EMIS          │ ◄─── Port 8000 (MySQL: sis_emis)
│  (Pusat Data Kesiswaan Madrasah)│
└────────┬───────────────┬────────┘
         │               │ (REST API + Sanctum Bearer Token)
         ▼               ▼
┌─────────────────┐  ┌──────────────────┐  ┌─────────────────┐
│ Aplikasi Absensi│  │   CBT Madrasah   │  │ E-Rapor Digital │
│ (Port 8001)     │  │   (Ujian Online) │  │ (Buku Induk)    │
└─────────────────┘  └──────────────────┘  └─────────────────┘
```

---

## 2. Fitur Utama Sistem

1. **Ekstensi Chrome (EMIS Student Exporter)**:
   - Sniffer token JWT dan `institution_id` secara transparan dari portal EMIS Kemenag.
   - Paginasi data summary siswa dan loop detail berenkripsi AES-256-CBC Opaque ID.
   - Fitur tombol *Kirim ke SIS* (sinkronisasi batch otomatis ke database server).
   - Ekspor cadangan file Excel (`.xlsx`).
2. **Backend Ingestion & Idempotensi**:
   - Endpoint `POST /api/v1/emis/sync` dengan middleware `Idempotency-Key`.
   - Pencegahan *Null-Overwrite* pada data parsial.
   - Enkripsi database untuk NIK dan Nomor KK sesuai standar UU Perlindungan Data Pribadi (UU PDP).
3. **Filament Admin Panel 5**:
   - Manajemen profil madrasah (*Institutions*).
   - Tabel siswa interaktif dengan filter rombel/kelas, pencarian, dan halaman detail komprehensif.
   - Fitur upload/import dan download/export file Excel siswa langsung dari panel.
   - Monitoring riwayat batch sinkronisasi (*Sync Batches*).
4. **API Gateway Eksternal**:
   - RESTful API terisolasi antar-madrasah (*multi-tenant*).
   - Format ekspor datar siap pakai (*1-level flattened JSON*) untuk aplikasi lain.

---

## 3. Panduan Integrasi API Eksternal

### Informasi Dasar Protokol
- **Base URL Lokal**: `http://127.0.0.1:8000/api/v1`
- **Base URL Produksi**: `https://emis.madrasah-anda.sch.id/api/v1`
- **Metode Autentikasi**: **HTTP Bearer Token (Laravel Sanctum)**
- **Header Permintaan Wajib**:
  ```http
  Authorization: Bearer <TOKEN_SANCTUM_ANDA>
  Accept: application/json
  Content-Type: application/json
  ```

---

### Cara Mendapatkan Token API

Terdapat 3 (tiga) cara untuk menerbitkan Token API:

#### Metode 1: Antarmuka Web Filament Admin (Rekomendasi UI)
1. Buka browser dan login ke dashboard admin di: `http://127.0.0.1:8000/admin`.
2. Klik menu **Lembaga** (*Institutions*) pada sidebar navigasi.
3. Pada baris madrasah Anda (misal: `MIN 2 TANGGAMUS`), klik tombol kuning berikon kunci **Buat Token**.
4. Klik **Konfirmasi** pada modal *"Terbitkan Token Sinkronisasi EMIS"*.
5. Token Sanctum otomatis disalin (*auto-copy*) ke clipboard komputer dan ditampilkan pada notifikasi layar.
6. Untuk melihat daftar token aktif atau mencabutnya, klik tombol abu-abu **Tabel Token**.

#### Metode 2: Terminal Artisan CLI (Otomasi & Server)
Jalankan perintah ini di direktori proyek:
```bash
# Format dasar:
php artisan emis:issue-token {institution_id} --name="{Nama_Label}" --abilities="{hak_akses}"

# Contoh: Token untuk Aplikasi Absensi / CBT (Standar Pembacaan)
php artisan emis:issue-token 1 --name="Integrasi-Absensi-Siswa" --abilities="students:read"

# Contoh: Token dengan Akses NIK/KK Penuh (Khusus E-Rapor)
php artisan emis:issue-token 1 --name="Integrasi-E-Rapor-Lengkap" --abilities="students:read,view-sensitive-data"
```

#### Metode 3: Login via API (Autentikasi Programatik)
- **Endpoint**: `POST /api/v1/auth/login`
- **Body (JSON)**: `{"email": "admin@madrasah.sch.id", "password": "password_rahasia"}`
- **Respon**: Mengembalikan token bearer sesi.

---

### Tingkatan Hak Akses Token (Abilities & UU PDP)

Untuk menjaga kerahasiaan data pribadi sesuai UU PDP No. 27/2022:

| Ability | Deskripsi | Rekomendasi Aplikasi |
|---|---|---|
| `students:read` | Membaca daftar dan detail siswa. **NIK dan Nomor KK otomatis disensor** (contoh: `180604********01`). | **Default** untuk Aplikasi Absensi, CBT Ujian, LMS, Perpustakaan. |
| `view-sensitive-data` | Membuka sensor NIK dan KK sehingga mengembalikan **16 digit angka asli utuh**. | Khusus aplikasi resmi pencetakan rapor / buku induk resmi Kemenag. |
| `emis:sync` | Hak akses untuk mengirim batch data dari ekstensi browser Chrome ke database SIS. | Khusus Ekstensi Chrome EMIS Exporter. |
| `*` | Hak akses penuh administratif. | User Super Admin. |

---

## 4. Katalog Endpoint API

Semua endpoint berada di bawah prefix `/api/v1/` dan memerlukan header `Authorization: Bearer <TOKEN>`.

### 1. Cek Identitas Token (`GET /api/v1/auth/me`)
Memastikan keaktifan token dan membaca profil madrasah pemilik token.
- **Method**: `GET`
- **Endpoint**: `/api/v1/auth/me`
- **Contoh Respon (HTTP 200)**:
  ```json
  {
    "type": "institution",
    "institution": {
      "id": 1,
      "name": "MIN 2 TANGGAMUS",
      "nsm": "111118060002",
      "npsn": "60705691",
      "status": "active"
    },
    "token_abilities": ["students:read"]
  }
  ```

---

### 2. Daftar Rombel Resmi (`GET /api/v1/students/rombels`)
Mengambil seluruh rombongan belajar (kelas) resmi hasil sinkronisasi EMIS beserta jenjang dan total siswa aktif.
- **Method**: `GET`
- **Endpoint**: `/api/v1/students/rombels`
- **Contoh Respon (HTTP 200)**:
  ```json
  {
    "status": "success",
    "total": 17,
    "rombels": [
      { "rombel_name": "Kelas 1 a", "level": 1, "student_count": 20 },
      { "rombel_name": "Kelas 1 b", "level": 1, "student_count": 24 },
      { "rombel_name": "Kelas 1 c", "level": 1, "student_count": 24 },
      { "rombel_name": "Kelas 6 c", "level": 6, "student_count": 25 }
    ]
  }
  ```

---

### 3. Ekspor Data Siswa Datar (`GET /api/v1/students/export-json`)
> **Format Rekomendasi Utama!** Seluruh atribut identitas siswa, kelas, orang tua, dan bantuan sosial telah diratakan (*flattened 1-level*) sehingga siap dipetakan langsung ke tabel database eksternal.

- **Method**: `GET`
- **Endpoint**: `/api/v1/students/export-json`
- **Parameter Query (Opsional)**:
  - `filter[rombel]` : Nama rombel target (contoh: `Kelas 1 a` atau `1a`).
  - `filter[level]` : Jenjang kelas (angka `1` s/d `6`).
  - `filter[status]` : Status keaktifan (`Aktif`, `Lulus`, `Mutasi`).
  - `limit` : Jumlah data maksimal (default: `2000`).

- **Contoh Respon (HTTP 200)**:
  ```json
  {
    "institution": {
      "id": 1,
      "name": "MIN 2 TANGGAMUS",
      "nsm": "111118060002",
      "npsn": "60705691"
    },
    "exported_at": "2026-10-11T16:15:00+07:00",
    "total_records": 20,
    "available_rombels": ["Kelas 1 a", "Kelas 1 b", "Kelas 6 c"],
    "students": [
      {
        "sis_student_id": 2794,
        "emis_student_id": "ST-987214-X",
        "nisn": "0151234567",
        "local_nis": "1234/MI/2026",
        "full_name": "AHMAD FAIZ ABDULLAH",
        "gender": "L",
        "birth_place": "Tanggamus",
        "birth_date": "2015-05-14",
        "rombel_name": "Kelas 1 a",
        "level": 1,
        "academic_year": "2026/2027",
        "semester": "Ganjil",
        "absent_number": 1,
        "student_status": "Aktif",
        "homeroom_teacher": "Siti Nurhaliza, S.Pd.I",
        "nik": "180604********01",
        "kk_num": "180604********99",
        "full_address": "Jl. Raya Talang Padang No. 12",
        "father_name": "Abdullah Mansur",
        "mother_name": "Fatimah Azzahra",
        "guardian_name": null,
        "phone_number": "081234567890",
        "email": "ahmadfaiz@siswa.id",
        "kip_number": "KIP-881239",
        "pkh_status": "Tidak",
        "pip_status": "Ya"
      }
    ]
  }
  ```

---

### 4. Daftar Siswa RESTful Paginasi (`GET /api/v1/students`)
Format paginasi server standar Laravel untuk pembuatan antarmuka tabel.
- **Method**: `GET`
- **Endpoint**: `/api/v1/students`
- **Parameter**: `page`, `per_page`, `filter[full_name]`, `filter[rombel]`, `sort`.

---

### 5. Detail Siswa Tunggal (`GET /api/v1/students/{id}`)
- **Method**: `GET`
- **Endpoint**: `/api/v1/students/2794`
- Mengembalikan data 1 orang siswa lengkap beserta riwayat vaksinasi dan status bantuan (KIP/PIP).

---

## 5. Filter & Pencarian Cerdas (Case & Space Insensitive)

Pencarian rombel pada API SIS-EMIS mentoleransi perbedaan spasi dan huruf besar/kecil secara otomatis:
- `Kelas 1 a` = `kelas 1 a` = `Kelas 1A` = `1a`.

Contoh URL:
```http
GET /api/v1/students/export-json?filter[rombel]=Kelas 1 a&filter[status]=Aktif&limit=100
```

---

## 6. Contoh Implementasi pada Aplikasi Klien

### A. Laravel Client (Studi Kasus: absensi-laravel)

#### 1. Konfigurasi file `.env`:
```env
SIS_EMIS_API_URL="http://127.0.0.1:8000/api/v1"
SIS_EMIS_API_TOKEN="1|k3J9FhA7s10wQz...Xy98LzPq0192AaBb"
```

#### 2. Konfigurasi `config/services.php`:
```php
'sis_emis' => [
    'url' => env('SIS_EMIS_API_URL', 'http://127.0.0.1:8000/api/v1'),
    'token' => env('SIS_EMIS_API_TOKEN'),
],
```

#### 3. Service Client (`App\Services\SisEmisSyncService.php`):
```php
namespace App\Services;

use Illuminate\Support\Facades\Http;

class SisEmisSyncService
{
    protected string $apiUrl;
    protected ?string $token;

    public function __construct()
    {
        $this->apiUrl = rtrim(config('services.sis_emis.url', 'http://127.0.0.1:8000/api/v1'), '/');
        $this->token = config('services.sis_emis.token');
    }

    /**
     * Uji Koneksi API (Healthcheck)
     */
    public function checkConnection(): array
    {
        try {
            $res = Http::withToken($this->token)->acceptJson()->timeout(10)->get("{$this->apiUrl}/students/rombels");
            return $res->successful()
                ? ['status' => 'ok', 'total_rombels' => $res->json('total', 0)]
                : ['status' => 'error', 'message' => 'HTTP ' . $res->status()];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Tarik Siswa Siap Impor
     */
    public function fetchStudents(?string $rombel = null): array
    {
        $params = ['limit' => 2000];
        if (filled($rombel)) {
            $params['filter[rombel]'] = $rombel;
        }

        $res = Http::withToken($this->token)->acceptJson()->timeout(60)->get("{$this->apiUrl}/students/export-json", $params);
        if (!$res->successful()) {
            throw new \Exception('Gagal menarik data: ' . $res->body());
        }

        return $res->json('students', []);
    }
}
```

---

### B. PHP Native (cURL)
```php
<?php
$apiUrl = 'http://127.0.0.1:8000/api/v1/students/export-json?filter[rombel]=Kelas 1 a';
$token  = '1|k3J9FhA7s10wQz...Xy98LzPq0192AaBb';

$ch = curl_init($apiUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer {$token}",
        "Accept: application/json",
    ],
]);
$res = curl_exec($ch);
curl_close($ch);

$data = json_decode($res, true);
echo "Siswa ditarik: " . count($data['students'] ?? []);
```

---

### C. JavaScript / Node.js (Fetch API)
```javascript
const API_URL = 'http://127.0.0.1:8000/api/v1';
const API_TOKEN = '1|k3J9FhA7s10wQz...Xy98LzPq0192AaBb';

async function fetchRombels() {
  const res = await fetch(`${API_URL}/students/rombels`, {
    headers: {
      'Authorization': `Bearer ${API_TOKEN}`,
      'Accept': 'application/json',
    },
  });
  const data = await res.json();
  console.log(`Ditemukan ${data.total} rombel:`, data.rombels);
}

fetchRombels();
```

---

### D. Python (Requests)
```python
import requests

API_URL = "http://127.0.0.1:8000/api/v1"
TOKEN = "1|k3J9FhA7s10wQz...Xy98LzPq0192AaBb"

headers = {
    "Authorization": f"Bearer {TOKEN}",
    "Accept": "application/json"
}

resp = requests.get(f"{API_URL}/students/export-json?filter[rombel]=Kelas 1 a", headers=headers)
if resp.status_code == 200:
    students = resp.json().get('students', [])
    print(f"Total siswa ditarik: {len(students)}")
```

---

### E. cURL Terminal (Testing Cepat)
```bash
curl -X GET "http://127.0.0.1:8000/api/v1/students/rombels" \
     -H "Authorization: Bearer 1|TOKEN_ANDA" \
     -H "Accept: application/json"
```

---

## 7. Standar Kode Status HTTP & Penanganan Kesalahan

| Kode HTTP | Status | Penjelasan & Solusi |
|---|---|---|
| `200 OK` | Berhasil | Permintaan diproses sukses dan mengembalikan payload JSON. |
| `401 Unauthorized` | Autentikasi Gagal | Header `Authorization` tidak dikirim, format token salah, atau token telah dihapus. Terbitkan token baru. |
| `403 Forbidden` | Akses Ditolak | Token tidak memiliki ability yang diminta atau mencoba mengakses data madrasah lain. |
| `404 Not Found` | Tidak Ditemukan | URL endpoint salah atau ID siswa tidak ditemukan. |
| `429 Too Many Requests` | Batas Limit Tercapai | Melebihi *rate limiting* (maksimal 60 request/menit). Tunggu 1 menit sebelum mengirim ulang. |
| `500 Server Error` | Kesalahan Server | Galat internal server SIS-EMIS. Periksa log Laravel di `storage/logs/laravel.log`. |

---

## 8. Panduan Instalasi & Menjalankan Proyek

### Kebutuhan Sistem:
- PHP >= 8.3 / 8.4
- Composer 2.x
- MySQL >= 8.0 / MariaDB
- Node.js & NPM (opsional untuk asset build)

### Langkah Pemasangan:
```bash
# 1. Kloning repositori (atau buka folder direktori)
git clone https://github.com/giehotz/API-Siswa.git
cd API-Siswa

# 2. Pasang dependensi PHP
composer install

# 3. Konfigurasi Environment
cp .env.example .env
php artisan key:generate

# 4. Atur koneksi database MySQL di file .env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sis_emis
DB_USERNAME=root
DB_PASSWORD=

# 5. Jalankan migrasi dan seeder
php artisan migrate --seed

# 6. Jalankan server lokal
php artisan serve --port=8000
```

### Pemasangan Ekstensi Chrome:
1. Buka peramban Google Chrome dan navigasikan ke `chrome://extensions`.
2. Aktifkan **Developer mode** di pojok kanan atas.
3. Klik tombol **Load unpacked** dan pilih folder `extension/` dari repositori ini.
4. Buka portal EMIS 4.0 Kemenag (`https://emis.kemenag.go.id`), login, dan gunakan ekstensi untuk sinkronisasi.

---

## 9. Troubleshooting & Solusi Masalah Umum

1. **`cURL error 7: Connection refused`**:
   - Pastikan server backend SIS-EMIS aktif berjalan di port 8000 (`php artisan serve --port=8000`).
2. **Hasil penarikan siswa kosong (`total_records: 0`)**:
   - Panggil `GET /api/v1/students/rombels` terlebih dahulu untuk memastikan nama rombel yang tersimpan di database.
3. **Angka '0' di awal NISN / NIK terpotong pada database aplikasi klien**:
   - Pastikan kolom `nisn`, `nik`, `kk_num`, dan `local_nis` pada database klien bertipe data `VARCHAR` / `STRING`, **bukan integer**.

---

*Dikembangkan untuk digitalisasi data madrasah Indonesia yang aman, terintegrasi, dan berkesinambungan.*
