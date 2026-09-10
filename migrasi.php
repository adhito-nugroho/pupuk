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
// Pastikan folder uploads/berita_acara ada
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
