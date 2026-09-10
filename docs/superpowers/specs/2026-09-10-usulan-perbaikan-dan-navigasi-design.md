# Rancang Bangun Fitur Usulan Perbaikan Bertahap (Versioning) & Navigasi Cepat KTH Terpadu

**Tanggal:** 2026-09-10  
**Status:** Menunggu Persetujuan Pengguna  

---

## 1. Latar Belakang & Tujuan
1. **Fitur Upload Perbaikan Bertahap (Versioning)**:
   - Seringkali data usulan pupuk yang diajukan oleh kelompok tani memiliki ketidaksesuaian (misal salah NIK, nama belum pas, atau titik koordinat melenceng).
   - Pengguna membutuhkan fasilitas untuk mengunggah file Excel usulan revisi/perbaikan yang kemudian diverifikasi ulang secara otomatis terhadap SK dan Peta SHP yang sudah ada.
   - Seluruh data historis versi usulan awal (v1) dan hasil verifikasi sebelumnya harus tetap tersimpan rapi dan dapat dibuka kembali kapan saja sebagai bukti audit (*audit trail*).
2. **Navigasi Cepat KTH Terpadu**:
   - Mempermudah pengguna saat menelaah suatu kasus KTH untuk berpindah antar modul: **Hasil Verifikasi**, **Daftar SK & Anggota**, **Peta Spasial**, **Berita Acara & Laporan**, **Cetak Lembar Hasil**, dan **Upload Perbaikan** tanpa harus kembali ke halaman awal.

---

## 2. Rencana Arsitektur & Perubahan Database

### 2.1 Perubahan Tabel Database (Melalui `migrasi.php`)

1. **Tabel Baru: `kth_versi_usulan`** (Mencatat rekam jejak setiap file usulan yang diunggah):
   ```sql
   CREATE TABLE IF NOT EXISTS kth_versi_usulan (
       id INT AUTO_INCREMENT PRIMARY KEY,
       kth_id INT NOT NULL,
       versi_ke INT NOT NULL DEFAULT 1,
       label_versi VARCHAR(100) NOT NULL, -- Contoh: 'Usulan Awal (v1)', 'Perbaikan Ke-1 (v2)'
       nama_file_asli VARCHAR(255) NOT NULL,
       path_file VARCHAR(512) NOT NULL,
       total_petani INT DEFAULT 0,
       total_luas DOUBLE DEFAULT 0,
       jumlah_sesuai_sk INT DEFAULT 0,
       jumlah_tidak_sesuai_sk INT DEFAULT 0,
       jumlah_dalam_peta INT DEFAULT 0,
       jumlah_luar_peta INT DEFAULT 0,
       rekomendasi VARCHAR(64) DEFAULT 'Perlu Revisi',
       catatan_perbaikan TEXT NULL,
       dibuat_pada TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
       KEY idx_kth_versi (kth_id, versi_ke)
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
   ```

2. **Tabel `usulan_pupuk`**:
   - Menambahkan kolom `versi_ke INT NOT NULL DEFAULT 1`.
   - Index `(kth_id, versi_ke)`.

3. **Tabel `hasil_verifikasi`**:
   - Menambahkan kolom `versi_ke INT NOT NULL DEFAULT 1`.
   - Index `(kth_id, versi_ke)`.

4. **Tabel `kth`**:
   - Menambahkan kolom `versi_aktif INT NOT NULL DEFAULT 1`.

---

## 3. Alur Kerja Modul Perbaikan (Upload & Verifikasi Revisi)

```
[Pengguna di Hasil / Laporan]
       │
       ▼
[Klik Tab "Upload Perbaikan" / Tombol Revisi Usulan]
       │
       ▼
[Pilih File Excel Usulan Baru (.xlsx / .xls) + Catatan Perubahan]
       │
       ▼
[Backend: proses_upload_perbaikan.php]
  1. Hitung versi berikutnya: $versiBaru = versi_terakhir + 1
  2. Parse baris data usulan baru dari Excel
  3. Simpan baris ke tabel `usulan_pupuk` dengan `versi_ke = $versiBaru`
  4. Jalankan `verifikasi_satu_kth($pdo, $kthId, $versiBaru)`
  5. Catat metadata di `kth_versi_usulan`
  6. Set `kth.versi_aktif = $versiBaru`
       │
       ▼
[Redirect ke Hasil Verifikasi versi terbaru dengan Pemilih Versi (Version Switcher)]
```

---

## 4. Rancang Bangun Tampilan (UI / UX)

### 4.1 Sub-Navbar Tab KTH Terpadu (`layout_kth_nav($kth, $aktif, $versiAktif)`)
Diletakkan di bagian atas halaman kerja kasus (`hasil.php`, `konfirmasi_sk.php`, `peta.php`, `laporan.php`, `cetak.php`, `perbaikan.php`):
- **Info KTH**: Nama KTH, Nomor SK, Status Rekomendasi Terkini.
- **Tab Navigasi**:
  1. 📊 **Hasil Verifikasi** (`hasil.php?kth_id=...&v=...`)
  2. 👥 **Anggota SK** (`konfirmasi_sk.php?kth_id=...`)
  3. 🗺️ **Peta Spasial** (`peta.php?kth_id=...&v=...`)
  4. 📝 **Berita Acara & Laporan** (`laporan.php?kth_id=...&v=...`)
  5. 🖨️ **Cetak Lembar Hasil** (`cetak.php?kth_id=...&v=...`)
  6. 🔄 **+ Upload Perbaikan** (`perbaikan.php?kth_id=...`)

### 4.2 Dropdown / Pill Switcher Versi Usulan
- Di halaman Hasil, Laporan, Peta, dan Cetak, disediakan pilihan versi:
  - `🟢 Versi 2 (Perbaikan) — 10 Sep 2026 [Aktif]`
  - `⚪ Versi 1 (Usulan Awal) — 08 Sep 2026`
- Menampilkan perbandingan ringkas (misal: *Versi 1: 5 Belum Sesuai SK ➔ Versi 2: 0 Belum Sesuai SK*).

---

## 5. Rencana Pengujian & Verifikasi
1. **Pengujian Migrasi**: Jalankan `php migrasi.php` dan pastikan tabel serta kolom baru terpasang tanpa merusak data yang sudah ada (data lama otomatis diberi `versi_ke = 1`).
2. **Pengujian Upload Perbaikan**: Mengunggah file Excel perbaikan pada kasus uji, memastikan baris usulan lama tetap utuh, data baru terverifikasi dengan benar, dan switcher versi berfungsi mulus.
3. **Pengujian Navigasi**: Memastikan setiap tab KTH dapat diakses dengan cepat dan mempertahankan parameter `kth_id` serta `v` (versi) yang sedang aktif.
4. **Deploy Server**: Jalankan `./deploy-git.bat` untuk sinkronisasi otomatis ke server.
