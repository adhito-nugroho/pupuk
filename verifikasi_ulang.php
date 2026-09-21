<?php
// Hitung ulang verifikasi (mis. setelah edit SK / data berubah).
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/lib/verify.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: index.php'); exit; }
$kthId = (int)($_POST['kth_id'] ?? 0);
$versiKe = (int)($_POST['v'] ?? 0);
$pdo = db();

// Sinkronkan koordinat jika ada format UTM di database sebelum verifikasi ulang
$syncUtm = sinkronkan_koordinat_utm_ke_wgs84($pdo, $kthId, false);

$hitung = verifikasi_satu_kth($pdo, $kthId, $versiKe);
$vAktif = $hitung['versi_ke'] ?? 1;

// Sinkronkan angka ringkasan di draf laporan versi tersebut
// (narasi & rekomendasi manual tidak ditimpa); fallback ke terakhir utk data lama
$lapId = false;
try {
    $cekV = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'laporan' AND COLUMN_NAME = 'versi_ke'");
    $cekV->execute();
    if ((int)$cekV->fetchColumn() > 0) {
        $stV = $pdo->prepare('SELECT id FROM laporan WHERE kth_id = ? AND versi_ke = ? ORDER BY id DESC LIMIT 1');
        $stV->execute([$kthId, $vAktif]);
        $lapId = $stV->fetchColumn();
    }
} catch (Throwable $eLV) { $lapId = false; }
if (!$lapId) {
    $st = $pdo->prepare('SELECT id FROM laporan WHERE kth_id = ? ORDER BY id DESC LIMIT 1');
    $st->execute([$kthId]);
    $lapId = $st->fetchColumn();
}
if ($lapId) {
    $pdo->prepare('UPDATE laporan SET total_petani=?, jumlah_sesuai_sk=?, jumlah_tidak_sesuai_sk=?, jumlah_dalam_peta=?, jumlah_luar_peta=? WHERE id=?')
        ->execute([$hitung['total'], $hitung['sesuai'], $hitung['tidak'], $hitung['dalam'], $hitung['luar'], (int)$lapId]);
}
$pesanFlash = 'Verifikasi versi ' . $vAktif . ' dihitung ulang: ' . $hitung['sesuai'] . ' sesuai SK, ' . $hitung['tidak'] . ' belum; ' . $hitung['dalam'] . ' dalam peta, ' . $hitung['luar'] . ' luar peta.';
if (!empty($syncUtm['total_diupdate'])) {
    $pesanFlash .= ' (🌐 ' . $syncUtm['total_diupdate'] . ' titik koordinat UTM otomatis dikonversi ke Geografis WGS84).';
}
if (!empty($hitung['lebih_luas'])) {
    $pesanFlash .= ' (⚠️ Terdapat ' . $hitung['lebih_luas'] . ' petani dengan luas usulan melebihi 2 Ha).';
}
flash_set('ok', $pesanFlash);
header('Location: hasil.php?kth_id=' . $kthId . ($vAktif > 1 ? '&v=' . $vAktif : ''));
exit;
