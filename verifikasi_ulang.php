<?php
// Hitung ulang verifikasi (mis. setelah edit SK / data berubah).
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/lib/verify.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: index.php'); exit; }
$kthId = (int)($_POST['kth_id'] ?? 0);
$pdo = db();
$hitung = verifikasi_satu_kth($pdo, $kthId);
// Sinkronkan angka ringkasan di draf laporan terakhir (narasi & rekomendasi manual tidak ditimpa)
$st = $pdo->prepare('SELECT id FROM laporan WHERE kth_id = ? ORDER BY id DESC LIMIT 1');
$st->execute([$kthId]);
if ($id = $st->fetchColumn()) {
    $pdo->prepare('UPDATE laporan SET total_petani=?, jumlah_sesuai_sk=?, jumlah_tidak_sesuai_sk=?, jumlah_dalam_peta=?, jumlah_luar_peta=? WHERE id=?')
        ->execute([$hitung['total'], $hitung['sesuai'], $hitung['tidak'], $hitung['dalam'], $hitung['luar'], $id]);
}
$pesanFlash = 'Verifikasi dihitung ulang: ' . $hitung['sesuai'] . ' sesuai SK, ' . $hitung['tidak'] . ' belum; ' . $hitung['dalam'] . ' dalam peta, ' . $hitung['luar'] . ' luar peta.';
if (!empty($hitung['lebih_luas'])) {
    $pesanFlash .= ' (⚠️ Terdapat ' . $hitung['lebih_luas'] . ' petani dengan luas usulan melebihi 2 Ha).';
}
flash_set('ok', $pesanFlash);
header('Location: hasil.php?kth_id=' . $kthId);
exit;
