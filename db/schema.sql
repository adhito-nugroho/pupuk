CREATE DATABASE IF NOT EXISTS verif_pupuk CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE verif_pupuk;

CREATE TABLE IF NOT EXISTS kth (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_kth VARCHAR(255) NOT NULL,
    nomor_sk VARCHAR(255) DEFAULT NULL,
    nama_kph VARCHAR(255) DEFAULT NULL,
    luas_areal DECIMAL(14,2) DEFAULT NULL,
    tanggal_sk DATE DEFAULT NULL,
    tahun_usulan YEAR DEFAULT NULL,
    versi_aktif INT NOT NULL DEFAULT 1,
    dibuat_pada TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS kth_versi_usulan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kth_id INT NOT NULL,
    versi_ke INT NOT NULL DEFAULT 1,
    label_versi VARCHAR(100) NOT NULL,
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
    FOREIGN KEY (kth_id) REFERENCES kth(id) ON DELETE CASCADE,
    INDEX idx_kth_versi (kth_id, versi_ke)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS sk_anggota (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kth_id INT NOT NULL,
    nama VARCHAR(255) NOT NULL,
    nik VARCHAR(32) NOT NULL,
    jenis_kelamin VARCHAR(8) DEFAULT NULL,
    desa VARCHAR(255) DEFAULT NULL,
    kecamatan VARCHAR(255) DEFAULT NULL,
    FOREIGN KEY (kth_id) REFERENCES kth(id) ON DELETE CASCADE,
    INDEX idx_sk_nik (nik),
    INDEX idx_sk_kth (kth_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS poligon_ps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kth_id INT NOT NULL,
    nama_layer VARCHAR(255) DEFAULT NULL,
    geometry_json LONGTEXT NOT NULL,
    jumlah_ring INT DEFAULT 0,
    sumber_file VARCHAR(255) DEFAULT NULL,
    dibuat_pada TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (kth_id) REFERENCES kth(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS usulan_pupuk (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kth_id INT NOT NULL,
    versi_ke INT NOT NULL DEFAULT 1,
    no_urut INT DEFAULT NULL,
    nik VARCHAR(32) DEFAULT NULL,
    nama VARCHAR(255) DEFAULT NULL,
    jenis_kelamin VARCHAR(8) DEFAULT NULL,
    rt VARCHAR(16) DEFAULT NULL,
    rw VARCHAR(16) DEFAULT NULL,
    desa VARCHAR(255) DEFAULT NULL,
    kecamatan VARCHAR(255) DEFAULT NULL,
    pola_tanam VARCHAR(255) DEFAULT NULL,
    petak VARCHAR(64) DEFAULT NULL,
    luas_lahan DECIMAL(10,4) DEFAULT NULL,
    no_sk_ps VARCHAR(255) DEFAULT NULL,
    koordinat_x_raw VARCHAR(64) DEFAULT NULL,
    koordinat_y_raw VARCHAR(64) DEFAULT NULL,
    koordinat_x DOUBLE DEFAULT NULL,
    koordinat_y DOUBLE DEFAULT NULL,
    FOREIGN KEY (kth_id) REFERENCES kth(id) ON DELETE CASCADE,
    INDEX idx_usulan_kth (kth_id),
    INDEX idx_usulan_versi (kth_id, versi_ke),
    INDEX idx_usulan_nik (nik)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS hasil_verifikasi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usulan_id INT NOT NULL,
    kth_id INT NOT NULL,
    versi_ke INT NOT NULL DEFAULT 1,
    status_sk VARCHAR(32) NOT NULL DEFAULT 'Belum Sesuai SK PS',
    status_koordinat VARCHAR(32) NOT NULL DEFAULT 'Luar Peta PS',
    catatan TEXT DEFAULT NULL,
    kemiripan_nama DECIMAL(5,2) DEFAULT NULL,
    nama_mirip_sk VARCHAR(255) DEFAULT NULL,
    -- Koreksi spasial manual (koordinat asli tetap di usulan_pupuk)
    koordinat_koreksi_x DOUBLE DEFAULT NULL,
    koordinat_koreksi_y DOUBLE DEFAULT NULL,
    dikoreksi_pada TIMESTAMP NULL DEFAULT NULL,
    dibuat_pada TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_hasil_usulan (usulan_id),
    FOREIGN KEY (usulan_id) REFERENCES usulan_pupuk(id) ON DELETE CASCADE,
    FOREIGN KEY (kth_id) REFERENCES kth(id) ON DELETE CASCADE,
    INDEX idx_hasil_versi (kth_id, versi_ke)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS laporan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kth_id INT NOT NULL,
    tahun VARCHAR(8) DEFAULT NULL,
    total_petani INT DEFAULT 0,
    jumlah_sesuai_sk INT DEFAULT 0,
    jumlah_tidak_sesuai_sk INT DEFAULT 0,
    jumlah_dalam_peta INT DEFAULT 0,
    jumlah_luar_peta INT DEFAULT 0,
    narasi TEXT DEFAULT NULL,
    rekomendasi VARCHAR(64) DEFAULT 'Perlu Revisi',
    -- Berita Acara perbaikan (satu BA per KTH)
    berkas_ba VARCHAR(512) DEFAULT NULL,
    nama_file_ba VARCHAR(255) DEFAULT NULL,
    tgl_ba DATE DEFAULT NULL,
    dibuat_pada TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (kth_id) REFERENCES kth(id) ON DELETE CASCADE
) ENGINE=InnoDB;
