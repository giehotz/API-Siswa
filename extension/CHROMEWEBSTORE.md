# Chrome Web Store Listing — EMIS Student Exporter

> Last Updated: 2026-10-10

## Store Listing

**Extension Name**
EMIS Student Exporter

**Short Description**
Ekspor data siswa madrasah dari EMIS Kemenag ke format Excel (.xlsx) 1 sheet lengkap secara aman, cepat, dan terstruktur.

**Detailed Description**
EMIS Student Exporter mempermudah operator madrasah dalam mengekspor dan mencadangkan data kesiswaan dari portal EMIS Kemenag langsung ke format spreadsheet Excel (.xlsx) yang rapi dan siap pakai.

Fitur Utama:
- Format 1 Sheet Praktis & Multi-Sheet: Menyajikan seluruh data siswa ke dalam 1 lembar spreadsheet lengkap (NISN, NIS Lokal, Rombel, Alamat, Orang Tua, dan Status) maupun multi-sheet.
- Anti Time-Out & Resume Tanpa Pengulangan: Jika koneksi internet terputus atau server EMIS mengalami time-out di tengah jalan, seluruh data siswa yang sudah berhasil didata otomatis disimpan ke memori penyimpanan lokal dan diunduh ke berkas Excel parsial. Saat melanjutkan ekspor ("Lanjutkan Ekspor"), ekstensi menjamin TIDAK AKAN mengulang unduh siswa yang sudah ada di data.
- Pengambilan NIS Lokal & Data Pokok Lengkap: Menjamin kolom nomor identitas madrasah (NIS Lokal, NISN, NIK) terisi akurat.
- Perlindungan Privasi Bawaan: Kolom sensitif seperti NIK siswa, NIK orang tua, dan nomor Kartu Keluarga secara bawaan dinonaktifkan dan dapat diaktifkan secara sadar oleh operator dengan konfirmasi keamanan.
- Penanganan Tipe Data Akurat: Menjaga nomor identitas (NISN, NIK, NIS lokal, nomor KK) tetap bertipe teks utuh tanpa pemotongan angka nol di depan atau perubahan notasi ilmiah.
- Keamanan & Privasi 100% Lokal: Seluruh pemrosesan dan pembentukan berkas Excel dilakukan langsung di dalam browser Anda. Tidak ada data pribadi yang dikirim ke server pihak ketiga atau disimpan di penyimpanan eksternal.
- Pelaporan Ekspor Transparan: Dilengkapi lembar Log Ekspor yang mencatat jumlah data ditemukan, berhasil, gagal, dan status ringkasan tanpa membocorkan data pribadi.

Cara Penggunaan:
1. Masuk (Login) ke akun EMIS Kemenag Anda seperti biasa.
2. Buka menu Kesiswaan atau Daftar Siswa madrasah Anda.
3. Klik ikon ekstensi EMIS Student Exporter pada bilah alat peramban.
4. Sesuaikan pengaturan kolom sensitif jika diperlukan, lalu klik tombol "Mulai Ekspor".
5. File Excel (.xlsx) akan otomatis diunduh ke komputer Anda setelah proses selesai.

Catatan Keamanan & Privasi:
Ekstensi ini dibuat khusus untuk operator madrasah resmi. Ekstensi tidak pernah meminta atau menyimpan kata sandi Anda dan mematuhi prinsip privasi data siswa.

Dukungan & Bantuan:
Jika Anda memiliki pertanyaan atau masukan terkait penggunaan ekstensi, silakan hubungi pengembang melalui repositori resmi atau surel dukungan.

**Category**
Productivity

**Single Purpose**
Mengekspor data kesiswaan dari portal resmi EMIS Kemenag ke berkas Excel (.xlsx) terstruktur secara lokal di perangkat pengguna.

**Primary Language**
Indonesian

---

## Graphics & Assets

| Asset | Dimensions | Status | Filename |
|---|---|---|---|
| Store Icon | 128×128 PNG | ✅ Ready | icons/icon-128.png |
| Screenshot 1 | 1280×800 or 640×400 | ⬜ Not created | |
| Screenshot 2 | 1280×800 or 640×400 | ⬜ Not created | |
| Small Promo Tile | 440×280 | ⬜ Not created | |

### Screenshot Notes
- Screenshot 1: Tampilan popup ekstensi saat mendeteksi tab EMIS aktif dan tombol ekspor siap digunakan.
- Screenshot 2: Contoh hasil file Excel multi-sheet yang dihasilkan (Data Siswa, Data Orang Tua, Data Pembelajaran).

---

## Permissions Justification

| Permission | Type | Justification |
|---|---|---|
| `activeTab` | permissions | Digunakan untuk mendeteksi apakah operator sedang membuka halaman kesiswaan EMIS Kemenag yang valid dan menghubungkan antarmuka popup ke sesi aktif. |
| `downloads` | permissions | Digunakan untuk mengunduh berkas laporan Excel (.xlsx) yang telah dibuat secara lokal langsung ke folder unduhan pengguna. |
| `storage` | permissions | Digunakan untuk menyimpan preferensi pilihan tampilan kolom pengguna serta mempertahankan data siswa yang sudah diunduh agar tidak diulang jika terjadi time-out koneksi. |
| `unlimitedStorage` | permissions | Digunakan untuk menampung cache ratusan hingga ribuan siswa madrasah agar tidak melebihi kuota kuota penyimpanan saat melakukan pemulihan unduhan (resume). |
| `https://emis.kemenag.go.id/*` | host_permissions | Diperlukan agar content script dapat berjalan pada portal EMIS untuk mendeteksi navigasi halaman kesiswaan dan memicu pembacaan data dari konteks sesi resmi operator. |
| `https://api-emis.kemenag.go.id/*` | host_permissions | Diperlukan untuk melakukan permintaan HTTP GET detail siswa ke API resmi EMIS langsung dari peramban pengguna dengan izin sesi resmi. |
| `http://127.0.0.1:8000/*`, `http://localhost:8000/*`, `http://localhost/*`, `https://*/*` | host_permissions | Diperlukan untuk mengirimkan data hasil ekstraksi siswa ke backend server SIS (Sistem Informasi Sekolah) madrasah secara aman melalui Bearer token Sanctum. |

---

## Privacy & Data Use

### Data Collection

**Does the extension collect user data?** No

*Catatan: Seluruh data siswa diproses secara transparan di memori peramban pengguna dan hanya dikirimkan ke server SIS resmi madrasah milik pengguna jika fitur sinkronisasi diaktifkan dengan token resmi. Tidak ada data yang dikumpulkan atau dibagikan ke pihak ketiga mana pun.*

### Data Use Certification
- [x] Data is NOT sold to third parties
- [x] Data is NOT used for purposes unrelated to the extension's core functionality
- [x] Data is NOT used for creditworthiness or lending purposes

---

## Version History

| Version | Date | Changes | Status | Package |
|---|---|---|---|---|
| 1.2.0 | 2026-10-11 | Fitur Konektor SIS Backend: Modul `sync-client.js`, tombol "Kirim ke SIS", pengaturan API URL & Bearer Token, chunking idempoten 50 siswa, retry berulang dengan exponential backoff, dan resume transfer. | ✅ Packaged | `emis-student-exporter-v1.2.0.zip` |
| 1.1.2 | 2026-10-10 | Perlindungan anti time-out: auto-save ke `chrome.storage.local` dan fitur resume pintar yang menjamin tidak mengulang siswa yang sudah selesai diunduh. Ditambahkan tombol "Unduh Data Tersimpan". | Released | - |
| 1.1.1 | 2026-10-10 | Dukungan ekspor semua siswa dalam 1 sheet lengkap dengan NIS Lokal dan penarikan otomatis pagination. | Released | - |
| 1.1.0 | 2026-10-10 | Rilis awal fondasi Manifest V3, hybrid content script sniffer, normalizer 104-field, 5 sheet XLSX + Log Ekspor, dan perlindungan privasi. | Released | - |
