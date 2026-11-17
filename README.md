# GDSS DFU — Prioritas Evaluasi Klinis Diabetic Foot Ulcer

Group Decision Support System (GDSS) berbasis web untuk menyusun **urutan prioritas evaluasi klinis**
kasus *Diabetic Foot Ulcer* (DFU). Tiga decision maker — **dokter (ketua tim), perawat luka, dan apoteker klinis** —
menilai kasus secara terpisah, lalu sistem menggabungkannya dengan:

| Metode | Peran |
|---|---|
| **AHP** | Bobot kriteria C1–C6 dari perbandingan berpasangan, beserta uji konsistensi (CR ≤ 0,10) |
| **TOPSIS** | Peringkat kasus milik masing-masing decision maker |
| **Borda Count** | Penggabungan tiga peringkat menjadi urutan konsensus kelompok |

Kasus dengan **tanda bahaya (red flag)** selalu ditandai *Evaluasi Segera*, terlepas dari skor dan hasil voting.

> **Prototipe akademik.** Bukan perangkat medis; tidak mendiagnosis, tidak memberi resep, dan tidak menggantikan
> keputusan tenaga kesehatan. Seluruh data pasien adalah **DATA SIMULASI**.

Proyek Akhir Praktikum Sistem Informasi — Program Studi Teknik Informatika, UIN Maulana Malik Ibrahim Malang, 2026.
Tim: Muafa Hanif Prayogo, Satria Badrus Salam, Diva Zahira Aulia Salsabila, Roudhotul Janah.

---

## Daftar isi

1. [Fitur](#fitur)
2. [Kebutuhan sistem](#kebutuhan-sistem)
3. [Instalasi](#instalasi)
4. [Menjalankan aplikasi](#menjalankan-aplikasi)
5. [Akun demo](#akun-demo)
6. [Alur penggunaan](#alur-penggunaan)
7. [Mode demo cepat](#mode-demo-cepat)
8. [Menjalankan pengujian](#menjalankan-pengujian)
9. [Struktur proyek](#struktur-proyek)
10. [Pemecahan masalah](#pemecahan-masalah)
11. [Kredit](#kredit)

---

## Fitur

- Login dengan username, pembatasan 5 percobaan per menit, dan hak akses per peran (ketua / anggota).
- Alur sesi keputusan bertahap: **Persiapan → Penilaian → Voting → Menunggu Finalisasi → Final**.
- Kelola kasus simulasi (tambah, ubah, hapus, impor dataset) dan red flag otomatis maupun manual.
- Form AHP 15 pasang kriteria dengan pratinjau bobot dan CR secara langsung.
- Form skor 1–5 untuk setiap kasus dengan petunjuk rubrik per kriteria.
- Hasil individual (bobot AHP, nilai V dan peringkat TOPSIS) yang hanya terlihat oleh pemiliknya sampai hasil final.
- Voting Borda dengan pemecah seri bertingkat dan musyawarah bila masih seri.
- Halaman hasil konsensus: urutan prioritas, rincian suara, bobot tiap anggota, **kesimpulan & tindak lanjut**,
  catatan keputusan ketua, serta **cetak / simpan PDF**.
- Riwayat sesi, audit log, dan notifikasi di dashboard.

## Kebutuhan sistem

| Perangkat lunak | Versi | Keterangan |
|---|---|---|
| PHP | 8.2 atau lebih baru | Ekstensi: `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `zip` (sudah ada di XAMPP) |
| Composer | 2.x | https://getcomposer.org |
| MySQL / MariaDB | MariaDB 10.4+ / MySQL 8 | Paling mudah lewat **XAMPP** |
| Git | 2.x | Untuk clone repositori |
| Node.js | **Tidak perlu** | Bootstrap, ikon, dan font sudah tersimpan di `public/vendor` |

## Instalasi

Langkah berikut cukup dilakukan **sekali** di setiap komputer.

**1. Ambil kode**

```bash
git clone <URL-repositori> gdss-dfu
cd gdss-dfu
```

Bila memakai XAMPP, letakkan folder di `C:\xampp\htdocs\` (atau `D:\xampp\htdocs\`).

**2. Pasang dependensi PHP**

```bash
composer install
```

> Koneksi lambat? Jalankan `set COMPOSER_PROCESS_TIMEOUT=0` (CMD) atau `$env:COMPOSER_PROCESS_TIMEOUT=0` (PowerShell)
> terlebih dahulu agar unduhan paket besar tidak terputus karena batas waktu.

**3. Buat file konfigurasi**

```bash
copy .env.example .env
php artisan key:generate
```

(Di Git Bash / Linux / macOS gunakan `cp .env.example .env`.)

**4. Isi password akun demo** di file `.env` — bebas, asal tidak kosong:

```dotenv
DEMO_PASSWORD_DOKTER=isi-password-dokter
DEMO_PASSWORD_PERAWAT=isi-password-perawat
DEMO_PASSWORD_APOTEKER=isi-password-apoteker
```

File `.env` tidak ikut ter-upload ke Git, jadi setiap anggota tim mengisi password miliknya sendiri.

**5. Nyalakan MySQL dan buat database**

Buka **XAMPP Control Panel → Start** pada baris **MySQL**. Lalu buat dua database dengan salah satu cara:

- **phpMyAdmin** (perlu Apache juga dinyalakan): buka `http://localhost/phpmyadmin` → *New* → buat `gdss_dfu`
  dan `gdss_dfu_test` dengan collation `utf8mb4_unicode_ci`.
- **Terminal** — `mysql` milik XAMPP biasanya tidak ada di PATH, jadi panggil dengan path lengkap
  (sesuaikan huruf drive `C:`/`D:`):

  ```bash
  C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE gdss_dfu CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE DATABASE gdss_dfu_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
  ```

- `gdss_dfu` — database aplikasi.
- `gdss_dfu_test` — database khusus pengujian (dikosongkan otomatis setiap test berjalan).

Bila user/password MySQL Anda bukan `root` tanpa password, sesuaikan `DB_USERNAME` dan `DB_PASSWORD` di `.env`.

**6. Buat tabel dan isi data awal**

```bash
php artisan migrate:fresh --seed
```

Perintah ini membuat 3 akun demo, kriteria C1–C6 beserta rubrik, dan satu sesi **"Demo dataset_v1"** berisi
8 kasus simulasi (P01–P08) pada tahap **Persiapan**.

## Menjalankan aplikasi

1. Pastikan **MySQL di XAMPP menyala**.
2. Dari folder proyek jalankan:

   ```bash
   php artisan serve
   ```

3. Buka **http://127.0.0.1:8000** di browser.
4. Untuk menghentikan server, tekan **Ctrl + C** di terminal.

## Akun demo

| Username | Peran | Hak khusus |
|---|---|---|
| `dokter` | Dokter — **ketua tim** | Panel Ketua, kelola kasus, buka penilaian, mulai voting, finalisasi, audit log |
| `perawat` | Perawat luka | Menilai dan memberi suara |
| `apoteker` | Apoteker klinis | Menilai dan memberi suara |

Password masing-masing akun sesuai isian `DEMO_PASSWORD_*` di `.env` (langkah instalasi 4).

Satu browser hanya dapat login satu akun. Untuk mencoba tiga akun sekaligus, gunakan jendela berbeda
(mis. Chrome biasa, Chrome Incognito, dan Edge) atau keluar–masuk bergantian lewat menu nama di kanan atas.

## Alur penggunaan

| Langkah | Akun | Menu | Yang dilakukan |
|---|---|---|---|
| 1 | dokter | **Kasus** | Periksa 8 kasus simulasi; tambah/ubah kasus atau red flag manual bila perlu |
| 2 | dokter | **Panel Ketua** | Klik **Buka Penilaian** — kasus dan kriteria terkunci |
| 3 | semua | **Penilaian → 1 · Perbandingan Kriteria** | Isi 15 pasang perbandingan AHP sampai CR ≤ 0,10 |
| 4 | semua | **Penilaian → 2 · Skor Kasus** | Beri skor 1–5 untuk C1–C6 pada setiap kasus |
| 5 | semua | **Penilaian → 3 · Ringkasan & Hasil** | Klik **Kirim Final**; bobot AHP dan peringkat TOPSIS Anda tampil |
| 6 | dokter | **Panel Ketua** | Klik **Mulai Voting** (aktif setelah ketiga anggota final) |
| 7 | semua | **Voting** | Tinjau peringkat Anda, centang konfirmasi, **Kirim Suara** |
| 8 | dokter | **Panel Ketua** | Klik **Finalisasi & Publikasikan**, isi catatan kesimpulan (opsional) |
| 9 | semua | **Hasil** | Lihat urutan prioritas, rincian suara, bobot, dan kesimpulan; **Cetak / Simpan PDF** |

Dengan data `dataset_v1`, urutan akhir yang diharapkan adalah
**P06 (21) → P04 (16) → P07 (15) → P08 (14) → P05 (8) → P03 (6) → P02 (4) → P01 (0)**,
dan P07 tetap ditandai *Evaluasi Segera* (RF1, RF5).

Bila ada penilaian yang perlu dikoreksi saat voting, ketua dapat memakai **Buka Ulang Sesi** di Panel Ketua
(alasan wajib diisi; semua suara dibatalkan).

## Mode demo cepat

Untuk presentasi singkat, draf penilaian ketiga anggota dapat diisi otomatis dari dataset:

1. Ubah di `.env`:

   ```dotenv
   SEED_DEMO_ASSESSMENTS=true
   ```

2. Bangun ulang database:

   ```bash
   php artisan migrate:fresh --seed
   ```

Sesi langsung berada di tahap **Penilaian** dengan 15 pasang AHP dan 48 skor terisi untuk setiap akun.
Setiap anggota cukup membuka **Penilaian → 3 · Ringkasan & Hasil** lalu **Kirim Final**, kemudian lanjut ke langkah 6.

Untuk kembali ke kondisi awal (tahap Persiapan, penilaian kosong), ubah lagi menjadi `false` dan jalankan
`php artisan migrate:fresh --seed`. **Perhatian:** perintah ini menghapus seluruh data di database `gdss_dfu`.

## Menjalankan pengujian

Pastikan MySQL menyala dan database `gdss_dfu_test` sudah dibuat, lalu:

```bash
php artisan test
```

Pengujian mencakup unit test metode (AHP, TOPSIS, Borda, red flag) yang dibandingkan dengan perhitungan manual
(`tests/fixtures/dataset_v1_expected.json`, toleransi ±0,0001) serta feature test untuk login, hak akses,
alur status sesi, form penilaian, voting, hasil, kasus, dan audit log.

Merapikan format kode sebelum commit:

```bash
./vendor/bin/pint
```

## Struktur proyek

```text
app/
├─ Enums/                  Status sesi, penilaian, suara, peran, red flag
├─ Http/Controllers/       Controller halaman (tipis, memanggil service)
├─ Models/                 Model Eloquent (User, DecisionSession, PatientCase, Assessment, Vote, …)
├─ Services/
│  ├─ AhpService.php       Bobot AHP, uji konsistensi, agregasi kelompok
│  ├─ TopsisService.php    Peringkat TOPSIS
│  ├─ BordaService.php     Borda Count dan pemecah seri
│  ├─ RedFlagService.php   Deteksi tanda bahaya RF1–RF5
│  ├─ SessionWorkflowService.php   Semua perpindahan status sesi
│  └─ ConclusionService.php        Kesimpulan & tindak lanjut hasil
database/
├─ migrations/             Struktur tabel
└─ seeders/data/dataset_v1/   Kriteria, 8 kasus, matriks AHP, dan skor demo (JSON)
public/
├─ css/app.css             Gaya tampilan
├─ images/                 Foto ilustratif (lihat CREDITS.md)
└─ vendor/                 Bootstrap, Bootstrap Icons, font (lokal)
resources/views/           Tampilan Blade
tests/                     Unit dan feature test
```

## Pemecahan masalah

| Gejala | Penyebab & solusi |
|---|---|
| `SQLSTATE[HY000] [2002] ... connection refused` | MySQL belum menyala. Start MySQL di XAMPP Control Panel. |
| `Unknown database 'gdss_dfu'` | Database belum dibuat — lihat instalasi langkah 5. |
| Seeder berhenti: *Password akun demo belum diisi* | Isi `DEMO_PASSWORD_DOKTER`, `_PERAWAT`, `_APOTEKER` di `.env`. |
| `No application encryption key has been specified` | Jalankan `php artisan key:generate`. |
| Halaman **419 Sesi berakhir** | Sesi login kedaluwarsa atau database baru di-reset. Muat ulang halaman lalu login lagi. |
| Login ditolak *Terlalu banyak percobaan* | Salah password 5× dalam 1 menit. Tunggu sekitar satu menit. |
| `Failed to listen on 127.0.0.1:8000` | Port dipakai aplikasi lain. Jalankan `php artisan serve --port=8001`. |
| `composer install` berhenti karena *timeout* | Set `COMPOSER_PROCESS_TIMEOUT=0` lalu ulangi (lihat instalasi langkah 2). |
| Tampilan berantakan / ikon tidak muncul | Pastikan folder `public/vendor` ikut ter-clone; muat ulang dengan Ctrl + F5. |
| Test gagal karena database | Pastikan database `gdss_dfu_test` ada dan MySQL menyala. |

## Kredit

- Foto ilustratif dari [Unsplash](https://unsplash.com) di bawah Unsplash License — rincian fotografer di
  [`public/images/CREDITS.md`](public/images/CREDITS.md). Orang dalam foto bukan pengguna atau pasien sistem ini.
- [Laravel](https://laravel.com), [Bootstrap](https://getbootstrap.com), [Bootstrap Icons](https://icons.getbootstrap.com),
  dan font [Plus Jakarta Sans](https://fonts.google.com/specimen/Plus+Jakarta+Sans) — masing-masing dengan lisensinya
  (lihat file `LICENSE` di `public/vendor`).
- Rujukan klinis kriteria dan red flag: pedoman IWGDF/IDSA (2023–2024). Rubrik dalam aplikasi ini adalah rancangan
  untuk keperluan akademik dan belum divalidasi secara klinis.
