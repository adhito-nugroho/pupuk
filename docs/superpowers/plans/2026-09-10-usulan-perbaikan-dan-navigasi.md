# Implementasi Usulan Perbaikan Bertahap (Versioning) & Navigasi Terpadu KTH

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Membangun fasilitas upload berkas perbaikan usulan pupuk bertahap dengan riwayat versi (audit trail) serta sub-navbar navigasi terpadu untuk akses cepat antar modul SK, Peta, Cetak, Laporan, dan Perbaikan.

**Architecture:** 
1. Database schema migration menambahkan tabel `kth_versi_usulan` serta kolom `versi_ke` di `usulan_pupuk` dan `hasil_verifikasi`.
2. Mesin verifikasi `lib/verify.php` mendukung eksekusi multi-versi per KTH.
3. Sub-navbar `layout_kth_subnav()` disematkan di semua halaman KTH dengan dukungan switcher versi aktif (`&v=N`).
4. Modul `perbaikan.php` dan `proses_upload_perbaikan.php` menangani unggah file Excel revisi, parsing, kalkulasi hasil verifikasi versi baru, dan pencatatan riwayat.

**Tech Stack:** PHP 8+, MySQL/MariaDB (PDO), TailwindCSS, Alpine.js, PhpSpreadsheet / Python SK converter.

## Global Constraints
- Seluruh query database harus kompatibel ke belakang (data lama otomatis terdata sebagai Versi 1).
- Eksekusi migrasi database harus otomatis berjalan via `migrasi.php` dan terintegrasi di `deploy-git.bat`.
- Desain visual mengikuti sistem desain resmi Kehutanan (Palet Forest Green, Kadaster Paper, dan Newsreader Serif).

---

### Task 1: Database Migration & Schema Update

**Files:**
- Modify: `migrasi.php`
- Modify: `db/schema.sql`

**Interfaces:**
- Produces: Tabel `kth_versi_usulan`, kolom `versi_ke` di `usulan_pupuk` & `hasil_verifikasi`, kolom `versi_aktif` di `kth`.

- [ ] **Step 1: Tambahkan skrip migrasi tabel dan kolom baru di `migrasi.php`**
Tambahkan pembuatan tabel `kth_versi_usulan` dan alter kolom `versi_ke` & `versi_aktif`. Pastikan data lama otomatis di-backfill ke versi 1 jika `kth_versi_usulan` masih kosong.

- [ ] **Step 2: Jalankan `php migrasi.php` di terminal lokal**
Verifikasi output CLI:
```bash
php migrasi.php
```
Harus menampilkan `[OK]` atau `[SKIP]` untuk semua tabel dan kolom baru tanpa error.

- [ ] **Step 3: Update file `db/schema.sql`**
Tambahkan tabel `kth_versi_usulan` dan kolom versi ke schema dasar.

- [ ] **Step 4: Commit**
```bash
git add migrasi.php db/schema.sql
git commit -m "feat(db): tambahkan tabel kth_versi_usulan dan kolom versioning usulan"
```

---

### Task 2: Update Core Engine Multi-Versi di `lib/verify.php` & Helper Data

**Files:**
- Modify: `lib/verify.php`
- Modify: `lib/helpers.php`

**Interfaces:**
- Consumes: `kth_id`, `versi_ke` (opsional, default versi aktif/tertinggi)
- Produces: `verifikasi_satu_kth(PDO $pdo, int $kthId, int $versiKe = 0): array`

- [ ] **Step 1: Modifikasi fungsi `verifikasi_satu_kth` di `lib/verify.php`**
Perbarui fungsi agar menerima parameter `$versiKe = 0`. Jika `0`, ambil `versi_aktif` dari tabel `kth` (atau `MAX(versi_ke)`).
Ambil `usulan_pupuk` dengan `versi_ke = $versiKe`.
Hapus dan insert `hasil_verifikasi` khusus untuk `kth_id = ? AND versi_ke = ?`.
Perbarui ringkasan di tabel `kth_versi_usulan`.

- [ ] **Step 2: Tambahkan fungsi pembantu `ambil_daftar_versi(PDO $pdo, int $kthId): array` di `lib/helpers.php`**
Fungsi untuk mengambil seluruh riwayat versi untuk KTH tertentu guna ditampilkan di dropdown switcher.

- [ ] **Step 3: Uji verifikasi via PHP CLI**
```bash
php -r "require 'config.php'; require 'lib/db.php'; require 'lib/verify.php'; var_dump(verifikasi_satu_kth(db(), 1));"
```

- [ ] **Step 4: Commit**
```bash
git add lib/verify.php lib/helpers.php
git commit -m "feat(verify): dukung verifikasi per nomor versi usulan"
```

---

### Task 3: Komponen Sub-Navbar Navigasi Terpadu & Version Switcher

**Files:**
- Modify: `lib/layout.php`

**Interfaces:**
- Produces: `layout_kth_subnav(array $kth, string $aktif, int $versiAktif = 1, array $daftarVersi = []): void`

- [ ] **Step 1: Buat fungsi `layout_kth_subnav()` di `lib/layout.php`**
Rancang sub-navbar dengan header identitas KTH (Nama KTH, Nomor SK, Status Rekomendasi, Luas) dan 6 tab aksi:
1. 📊 Hasil Verifikasi (`hasil.php?kth_id=...&v=...`)
2. 👥 Anggota SK (`konfirmasi_sk.php?kth_id=...&v=...`)
3. 🗺️ Peta Spasial (`peta.php?kth_id=...&v=...`)
4. 📝 Berita Acara & Laporan (`laporan.php?kth_id=...&v=...`)
5. 🖨️ Cetak Lembar Hasil (`cetak.php?kth_id=...&v=...`)
6. 🔄 + Upload Perbaikan (`perbaikan.php?kth_id=...`)
Sertakan selector versi jika terdapat lebih dari 1 versi usulan.

- [ ] **Step 2: Commit**
```bash
git add lib/layout.php
git commit -m "feat(ui): tambahkan komponen sub-navbar terpadu dan version switcher"
```

---

### Task 4: Modul Upload & Proses Perbaikan (`perbaikan.php` & `proses_upload_perbaikan.php`)

**Files:**
- Create: `perbaikan.php`
- Create: `proses_upload_perbaikan.php`

**Interfaces:**
- Consumes: POST multipart `{ kth_id, catatan_perbaikan }` + FILE `{ file_usulan }`
- Produces: Record versi baru di `kth_versi_usulan`, baris di `usulan_pupuk (versi_ke = N)`, hasil di `hasil_verifikasi (versi_ke = N)`.

- [ ] **Step 1: Buat halaman `perbaikan.php`**
Menampilkan:
- Sub-navbar KTH aktif
- Riwayat perbaikan terdahulu (tabel timeline versi: Tanggal, Nama File, Jumlah Petani Sesuai/Tidak Sesuai)
- Form upload file Excel revisi/perbaikan baru dengan panduan format dan input catatan perbaikan.

- [ ] **Step 2: Buat handler backend `proses_upload_perbaikan.php`**
- Validasi file Excel (`.xlsx`, `.xls`).
- Simpan file ke `uploads/usulan/`.
- Hitung `$versiBaru = versi_terakhir + 1`.
- Ekstrak baris usulan dari Excel menggunakan PhpSpreadsheet (atau parser excel yang sudah ada di `proses_baru.php`).
- Insert baris ke `usulan_pupuk` dengan `versi_ke = $versiBaru`.
- Jalankan `verifikasi_satu_kth($pdo, $kthId, $versiBaru)`.
- Set `kth.versi_aktif = $versiBaru`.
- Redirect ke `hasil.php?kth_id=$kthId&v=$versiBaru` dengan pesan sukses.

- [ ] **Step 3: Uji lint PHP**
```bash
php -l perbaikan.php
php -l proses_upload_perbaikan.php
```

- [ ] **Step 4: Commit**
```bash
git add perbaikan.php proses_upload_perbaikan.php
git commit -m "feat(perbaikan): buat modul dan handler upload usulan perbaikan bertahap"
```

---

### Task 5: Integrasi Sub-Navbar dan Dukungan Versi di Semua Modul KTH

**Files:**
- Modify: `hasil.php`
- Modify: `konfirmasi_sk.php`
- Modify: `peta.php`
- Modify: `laporan.php`
- Modify: `cetak.php`

**Interfaces:**
- Membaca parameter `$_GET['v']` atau fallback ke versi aktif KTH.
- Memanggil `layout_kth_subnav($kth, 'modul_name', $versiAktif, $daftarVersi)` di bagian atas halaman.

- [ ] **Step 1: Update `hasil.php`**
Semprotkan sub-navbar terpadu, baca data `usulan_pupuk` & `hasil_verifikasi` sesuai versi yang dipilih (`v`), dan tampilkan lencana komparasi versi jika sedang melihat versi lama.

- [ ] **Step 2: Update `konfirmasi_sk.php`**
Semprotkan sub-navbar terpadu.

- [ ] **Step 3: Update `peta.php`**
Semprotkan sub-navbar terpadu, render titik koordinat petani sesuai versi yang dipilih.

- [ ] **Step 4: Update `laporan.php`**
Semprotkan sub-navbar terpadu, hitung angka rekapitulasi narasi dan neraca verifikasi sesuai versi yang dipilih.

- [ ] **Step 5: Update `cetak.php`**
Dukung pencetakan berdasarkan versi usulan yang dipilih dengan informasi nomor versi di header cetak.

- [ ] **Step 6: Uji lint seluruh file**
```bash
php -l hasil.php
php -l konfirmasi_sk.php
php -l peta.php
php -l laporan.php
php -l cetak.php
```

- [ ] **Step 7: Commit**
```bash
git add hasil.php konfirmasi_sk.php peta.php laporan.php cetak.php
git commit -m "feat: integrasikan sub-navbar terpadu dan dukungan multi-versi di seluruh modul KTH"
```

---

### Task 6: Verifikasi Menyeluruh & Testing

**Files:**
- Test end-to-end

- [ ] **Step 1: Jalankan migrasi lokal**
```bash
php migrasi.php
```

- [ ] **Step 2: Jalankan browser/manual testing**
Pastikan alur: Buka Kasus -> Lihat Hasil -> Buka Anggota SK -> Buka Peta -> Upload Perbaikan -> Muncul Versi 2 -> Switcher antar versi berjalan mulus.

- [ ] **Step 3: Commit final & siapkan deploy**
```bash
git status
```
