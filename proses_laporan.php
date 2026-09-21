<?php
// Simpan laporan (versi baru = riwayat) + reset template narasi.
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/verify.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: index.php'); exit; }
$kthId = (int)($_POST['kth_id'] ?? 0);
$vParam = (int)($_POST['v'] ?? $_POST['versi_ke'] ?? 0);
$pdo = db();
$kth = $pdo->prepare('SELECT * FROM kth WHERE id = ?');
$kth->execute([$kthId]);
$k = $kth->fetch();
if (!$k) { flash_set('error', 'Kasus tidak ditemukan.'); header('Location: index.php'); exit; }

// Versi yang dilaporkan: parameter > versi aktif KTH > 1
$versiLap = $vParam > 0 ? $vParam : (int)($k['versi_aktif'] ?? 1);
if ($versiLap <= 0) $versiLap = 1;

// Kolom versi_ke mungkin belum ada di server lama — deteksi dinamis
$adaVersiLap = true;
try {
    $cek = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'laporan' AND COLUMN_NAME = 'versi_ke'");
    $cek->execute();
    $adaVersiLap = (int)$cek->fetchColumn() > 0;
} catch (Throwable $eCek) { $adaVersiLap = false; }
$filterVersiHasil = 'WHERE kth_id = ? AND versi_ke = ?';
$filterVersiUsulan = 'WHERE kth_id = ? AND versi_ke = ?';

$h = $pdo->prepare('SELECT COUNT(*) total, SUM(status_sk="Sesuai SK PS") sesuai, SUM(status_sk!="Sesuai SK PS") tidak, SUM(status_koordinat="Dalam Peta PS") dalam, SUM(status_koordinat!="Dalam Peta PS") luar FROM hasil_verifikasi ' . $filterVersiHasil);
$h->execute([$kthId, $versiLap]);
$live = $h->fetch();

if (!empty($_POST['reset_template'])) {
    $qLuas = $pdo->prepare('SELECT SUM(luas_lahan) AS total_luas, COUNT(CASE WHEN luas_lahan > 2.0 THEN 1 END) AS lebih_2ha FROM usulan_pupuk ' . $filterVersiUsulan);
    $qLuas->execute([$kthId, $versiLap]);
    $rowLuas = $qLuas->fetch() ?: [];

    $hitung = [
        'total' => (int)$live['total'],
        'sesuai' => (int)$live['sesuai'],
        'tidak' => (int)$live['tidak'],
        'dalam' => (int)$live['dalam'],
        'luar' => (int)$live['luar'],
        'lebih_luas' => (int)($rowLuas['lebih_2ha'] ?? 0),
        'total_luas' => (float)($rowLuas['total_luas'] ?? 0.0),
    ];
    $narasi = buat_narasi_default($k, (int)($k['tahun_usulan'] ?? date('Y')), $hitung);
    $rekom = ($hitung['tidak'] === 0 && $hitung['luar'] === 0 && $hitung['lebih_luas'] === 0 && $hitung['total'] > 0) ? 'Dapat Ditindaklanjuti' : 'Perlu Revisi';
    if ($adaVersiLap) {
        $pdo->prepare('INSERT INTO laporan (kth_id, versi_ke, tahun, total_petani, jumlah_sesuai_sk, jumlah_tidak_sesuai_sk, jumlah_dalam_peta, jumlah_luar_peta, narasi, rekomendasi) VALUES (?,?,?,?,?,?,?,?,?,?)')
            ->execute([$kthId, $versiLap, (string)($k['tahun_usulan'] ?? date('Y')), $hitung['total'], $hitung['sesuai'], $hitung['tidak'], $hitung['dalam'], $hitung['luar'], $narasi, $rekom]);
    } else {
        $pdo->prepare('INSERT INTO laporan (kth_id, tahun, total_petani, jumlah_sesuai_sk, jumlah_tidak_sesuai_sk, jumlah_dalam_peta, jumlah_luar_peta, narasi, rekomendasi) VALUES (?,?,?,?,?,?,?,?,?)')
            ->execute([$kthId, (string)($k['tahun_usulan'] ?? date('Y')), $hitung['total'], $hitung['sesuai'], $hitung['tidak'], $hitung['dalam'], $hitung['luar'], $narasi, $rekom]);
    }
    flash_set('ok', 'Narasi dikembalikan ke template otomatis (versi baru tersimpan).');
    header('Location: laporan.php?kth_id=' . $kthId . ($versiLap > 1 ? '&v=' . $versiLap : ''));
    exit;
}

$tahun = trim((string)($_POST['tahun'] ?? ''));
$narasi = (string)($_POST['narasi'] ?? '');
$rekom = trim((string)($_POST['rekomendasi'] ?? 'Perlu Revisi'));
if (!in_array($rekom, ['Dapat Ditindaklanjuti', 'Perlu Revisi'], true)) $rekom = 'Perlu Revisi';
if ($narasi === '') { flash_set('error', 'Narasi tidak boleh kosong.'); header('Location: laporan.php?kth_id=' . $kthId); exit; }

if ($adaVersiLap) {
    $pdo->prepare('INSERT INTO laporan (kth_id, versi_ke, tahun, total_petani, jumlah_sesuai_sk, jumlah_tidak_sesuai_sk, jumlah_dalam_peta, jumlah_luar_peta, narasi, rekomendasi) VALUES (?,?,?,?,?,?,?,?,?,?)')
        ->execute([$kthId, $versiLap, $tahun ?: null, (int)$live['total'], (int)$live['sesuai'], (int)$live['tidak'], (int)$live['dalam'], (int)$live['luar'], $narasi, $rekom]);
} else {
    $pdo->prepare('INSERT INTO laporan (kth_id, tahun, total_petani, jumlah_sesuai_sk, jumlah_tidak_sesuai_sk, jumlah_dalam_peta, jumlah_luar_peta, narasi, rekomendasi) VALUES (?,?,?,?,?,?,?,?,?)')
        ->execute([$kthId, $tahun ?: null, (int)$live['total'], (int)$live['sesuai'], (int)$live['tidak'], (int)$live['dalam'], (int)$live['luar'], $narasi, $rekom]);
}
flash_set('ok', 'Laporan tersimpan sebagai versi baru. Siap export Excel.');
header('Location: laporan.php?kth_id=' . $kthId . ($versiLap > 1 ? '&v=' . $versiLap : ''));
exit;
