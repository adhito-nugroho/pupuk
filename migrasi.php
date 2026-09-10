<?php
/**
 * migrasi.php — Skrip migrasi satu-kali untuk fitur Perbaikan Rekomendasi.
 * Aman dijalankan berulang kali (cek kolom sebelum ALTER).
 *
 * Akses: http://localhost/pupuk/migrasi.php
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/db.php';

$pdo = db();
$hasil = [];
$ada_error = false;

/**
 * Cek apakah kolom sudah ada di tabel.
 */
function kolom_ada(PDO $pdo, string $tabel, string $kolom): bool {
    $st = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
    );
    $st->execute([$tabel, $kolom]);
    return (int)$st->fetchColumn() > 0;
}

/**
 * Jalankan satu ALTER TABLE jika kolom belum ada.
 */
function tambah_kolom(PDO $pdo, string $tabel, string $kolom, string $definisi, array &$hasil, bool &$ada_error): void {
    if (kolom_ada($pdo, $tabel, $kolom)) {
        $hasil[] = ['status' => 'skip', 'msg' => "Kolom `{$tabel}`.`{$kolom}` sudah ada — dilewati."];
        return;
    }
    try {
        $pdo->exec("ALTER TABLE `{$tabel}` ADD COLUMN `{$kolom}` {$definisi}");
        $hasil[] = ['status' => 'ok', 'msg' => "Kolom `{$tabel}`.`{$kolom}` berhasil ditambahkan."];
    } catch (PDOException $e) {
        $hasil[] = ['status' => 'error', 'msg' => "GAGAL menambah `{$tabel}`.`{$kolom}`: " . $e->getMessage()];
        $ada_error = true;
    }
}

// ═══════════════════════════════════════════════════════
// Migrasi: tabel hasil_verifikasi
// ═══════════════════════════════════════════════════════
tambah_kolom($pdo, 'hasil_verifikasi', 'koordinat_koreksi_x',
    "DOUBLE DEFAULT NULL COMMENT 'Longitude koreksi manual (koordinat asli tetap di usulan_pupuk.koordinat_x)'",
    $hasil, $ada_error);

tambah_kolom($pdo, 'hasil_verifikasi', 'koordinat_koreksi_y',
    "DOUBLE DEFAULT NULL COMMENT 'Latitude koreksi manual (koordinat asli tetap di usulan_pupuk.koordinat_y)'",
    $hasil, $ada_error);

tambah_kolom($pdo, 'hasil_verifikasi', 'dikoreksi_pada',
    "TIMESTAMP NULL DEFAULT NULL COMMENT 'Waktu koreksi koordinat dilakukan'",
    $hasil, $ada_error);

// ═══════════════════════════════════════════════════════
// Migrasi: tabel laporan
// ═══════════════════════════════════════════════════════
tambah_kolom($pdo, 'laporan', 'berkas_ba',
    "VARCHAR(512) DEFAULT NULL COMMENT 'Path file Berita Acara (Word/Excel/PDF) yang diupload'",
    $hasil, $ada_error);

tambah_kolom($pdo, 'laporan', 'nama_file_ba',
    "VARCHAR(255) DEFAULT NULL COMMENT 'Nama file BA asli untuk ditampilkan'",
    $hasil, $ada_error);

tambah_kolom($pdo, 'laporan', 'tgl_ba',
    "DATE DEFAULT NULL COMMENT 'Tanggal Berita Acara'",
    $hasil, $ada_error);

// ═══════════════════════════════════════════════════════
// Migrasi: tabel kth_versi_usulan (Riwayat Versi Usulan)
// ═══════════════════════════════════════════════════════
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `kth_versi_usulan` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `kth_id` INT NOT NULL,
            `versi_ke` INT NOT NULL DEFAULT 1,
            `label_versi` VARCHAR(100) NOT NULL,
            `nama_file_asli` VARCHAR(255) NOT NULL,
            `path_file` VARCHAR(512) NOT NULL,
            `total_petani` INT DEFAULT 0,
            `total_luas` DOUBLE DEFAULT 0,
            `jumlah_sesuai_sk` INT DEFAULT 0,
            `jumlah_tidak_sesuai_sk` INT DEFAULT 0,
            `jumlah_dalam_peta` INT DEFAULT 0,
            `jumlah_luar_peta` INT DEFAULT 0,
            `rekomendasi` VARCHAR(64) DEFAULT 'Perlu Revisi',
            `catatan_perbaikan` TEXT NULL,
            `dibuat_pada` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY `idx_kth_versi` (`kth_id`, `versi_ke`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $hasil[] = ['status' => 'ok', 'msg' => "Tabel `kth_versi_usulan` siap."];
} catch (PDOException $e) {
    $hasil[] = ['status' => 'error', 'msg' => "Gagal membuat tabel `kth_versi_usulan`: " . $e->getMessage()];
    $ada_error = true;
}

// Kolom versi di tabel kth, usulan_pupuk, dan hasil_verifikasi
tambah_kolom($pdo, 'kth', 'versi_aktif',
    "INT NOT NULL DEFAULT 1 COMMENT 'Nomor versi usulan yang aktif'",
    $hasil, $ada_error);

tambah_kolom($pdo, 'usulan_pupuk', 'versi_ke',
    "INT NOT NULL DEFAULT 1 COMMENT 'Nomor versi usulan'",
    $hasil, $ada_error);

tambah_kolom($pdo, 'hasil_verifikasi', 'versi_ke',
    "INT NOT NULL DEFAULT 1 COMMENT 'Nomor versi hasil verifikasi'",
    $hasil, $ada_error);

// Backfill data versi 1 untuk KTH yang sudah ada jika tabel kth_versi_usulan masih kosong
try {
    $stKth = $pdo->query('SELECT id, nama_kth, dibuat_pada FROM kth');
    $backfillCount = 0;
    while ($rowKth = $stKth->fetch()) {
        $kId = (int)$rowKth['id'];
        $cek = $pdo->prepare('SELECT COUNT(*) FROM kth_versi_usulan WHERE kth_id = ?');
        $cek->execute([$kId]);
        if ((int)$cek->fetchColumn() === 0) {
            $h = $pdo->prepare('SELECT COUNT(*) total, SUM(luas_lahan) luas FROM usulan_pupuk WHERE kth_id = ?');
            $h->execute([$kId]);
            $uInfo = $h->fetch() ?: [];
            $totPetani = (int)($uInfo['total'] ?? 0);
            if ($totPetani > 0) {
                $h2 = $pdo->prepare('SELECT COUNT(*) total, SUM(status_sk="Sesuai SK PS") sesuai, SUM(status_sk!="Sesuai SK PS") tidak, SUM(status_koordinat="Dalam Peta PS") dalam, SUM(status_koordinat!="Dalam Peta PS") luar FROM hasil_verifikasi WHERE kth_id = ?');
                $h2->execute([$kId]);
                $res = $h2->fetch() ?: [];
                $rekom = ((int)($res['tidak'] ?? 0) === 0 && (int)($res['luar'] ?? 0) === 0) ? 'Dapat Ditindaklanjuti' : 'Perlu Revisi';
                $pdo->prepare('INSERT INTO kth_versi_usulan (kth_id, versi_ke, label_versi, nama_file_asli, path_file, total_petani, total_luas, jumlah_sesuai_sk, jumlah_tidak_sesuai_sk, jumlah_dalam_peta, jumlah_luar_peta, rekomendasi, catatan_perbaikan, dibuat_pada) VALUES (?, 1, "Usulan Awal (v1)", "usulan_awal.xlsx", "", ?, ?, ?, ?, ?, ?, ?, "Data usulan awal.", ?)')->execute([
                    $kId,
                    $totPetani,
                    (float)($uInfo['luas'] ?? 0),
                    (int)($res['sesuai'] ?? 0),
                    (int)($res['tidak'] ?? 0),
                    (int)($res['dalam'] ?? 0),
                    (int)($res['luar'] ?? 0),
                    $rekom,
                    $rowKth['dibuat_pada'] ?: date('Y-m-d H:i:s')
                ]);
                $backfillCount++;
            }
        }
    }
    if ($backfillCount > 0) {
        $hasil[] = ['status' => 'ok', 'msg' => "Berhasil mem-backfill {$backfillCount} data versi 1 di `kth_versi_usulan`."];
    }
} catch (Throwable $eBf) {
    $hasil[] = ['status' => 'skip', 'msg' => "Backfill dilewati/ada catatan: " . $eBf->getMessage()];
}

// ═══════════════════════════════════════════════════════
// Pastikan folder uploads/berita_acara dan uploads/usulan ada
// ═══════════════════════════════════════════════════════
$baDir = __DIR__ . '/uploads/berita_acara';
if (!is_dir($baDir)) {
    if (@mkdir($baDir, 0775, true)) {
        $hasil[] = ['status' => 'ok', 'msg' => "Folder uploads/berita_acara berhasil dibuat."];
    } else {
        $hasil[] = ['status' => 'error', 'msg' => "Gagal membuat folder uploads/berita_acara."];
        $ada_error = true;
    }
} else {
    $hasil[] = ['status' => 'skip', 'msg' => "Folder uploads/berita_acara sudah ada — dilewati."];
}

$usulanDir = __DIR__ . '/uploads/usulan';
if (!is_dir($usulanDir)) {
    if (@mkdir($usulanDir, 0775, true)) {
        $hasil[] = ['status' => 'ok', 'msg' => "Folder uploads/usulan berhasil dibuat."];
    }
}

// Jika dijalankan dari CLI (misalnya saat git deploy)
if (php_sapi_name() === 'cli') {
    echo "\n=== [SERVER] MENJALANKAN MIGRASI DATABASE ===\n";
    foreach ($hasil as $item) {
        $badge = match($item['status']) {
            'ok'    => '  [OK]   ',
            'skip'  => '  [SKIP] ',
            default => '  [ERR]  ',
        };
        echo $badge . $item['msg'] . "\n";
    }
    if ($ada_error) {
        echo ">>> Migrasi selesai dengan beberapa error.\n\n";
        exit(1);
    } else {
        echo ">>> Migrasi database sukses!\n\n";
        exit(0);
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Migrasi Database — Fitur Perbaikan Rekomendasi</title>
<style>
body { font-family: 'Segoe UI', sans-serif; background: #f8fafc; color: #1e293b; padding: 40px; max-width: 720px; margin: 0 auto; }
h1 { font-size: 22px; font-weight: 700; margin-bottom: 4px; }
p.sub { color: #64748b; font-size: 13px; margin-bottom: 28px; }
.item { display: flex; align-items: flex-start; gap: 12px; padding: 10px 14px; border-radius: 6px; margin-bottom: 8px; font-size: 13px; }
.item.ok    { background: #f0fdf4; border: 1px solid #bbf7d0; }
.item.skip  { background: #f8fafc; border: 1px solid #e2e8f0; }
.item.error { background: #fef2f2; border: 1px solid #fecaca; }
.dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; margin-top: 3px; }
.dot.ok    { background: #22c55e; }
.dot.skip  { background: #94a3b8; }
.dot.error { background: #ef4444; }
.summary { margin-top: 24px; padding: 16px 20px; border-radius: 8px; font-size: 14px; font-weight: 600; }
.summary.ok    { background: #f0fdf4; border: 1px solid #86efac; color: #15803d; }
.summary.error { background: #fef2f2; border: 1px solid #f87171; color: #b91c1c; }
a.btn { display: inline-block; margin-top: 20px; padding: 10px 20px; background: #1e3a2b; color: white; border-radius: 6px; text-decoration: none; font-size: 13px; font-weight: 600; }
</style>
</head>
<body>
<h1>🔧 Migrasi Database</h1>
<p class="sub">Menambahkan kolom untuk fitur Koreksi Koordinat & Upload Berita Acara Perbaikan.</p>

<?php foreach ($hasil as $item): ?>
<div class="item <?= $item['status'] ?>">
  <div class="dot <?= $item['status'] ?>"></div>
  <div><?= htmlspecialchars($item['msg']) ?></div>
</div>
<?php endforeach; ?>

<div class="summary <?= $ada_error ? 'error' : 'ok' ?>">
  <?= $ada_error
    ? '⚠️ Migrasi selesai dengan beberapa error. Periksa pesan di atas.'
    : '✅ Migrasi berhasil! Semua kolom sudah tersedia.' ?>
</div>

<a class="btn" href="index.php">← Kembali ke Buku Register</a>
</body>
</html>
