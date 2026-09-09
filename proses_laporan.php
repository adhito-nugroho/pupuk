<?php
// Simpan laporan (versi baru = riwayat) + reset template narasi.
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/verify.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: index.php'); exit; }
$kthId = (int)($_POST['kth_id'] ?? 0);
$pdo = db();
$kth = $pdo->prepare('SELECT * FROM kth WHERE id = ?');
$kth->execute([$kthId]);
$k = $kth->fetch();
if (!$k) { flash_set('error', 'Kasus tidak ditemukan.'); header('Location: index.php'); exit; }

$h = $pdo->prepare('SELECT COUNT(*) total, SUM(status_sk="Sesuai SK PS") sesuai, SUM(status_sk!="Sesuai SK PS") tidak, SUM(status_koordinat="Dalam Peta PS") dalam, SUM(status_koordinat!="Dalam Peta PS") luar FROM hasil_verifikasi WHERE kth_id = ?');
$h->execute([$kthId]);
$live = $h->fetch();

if (!empty($_POST['reset_template'])) {
    $qLuas = $pdo->prepare('SELECT SUM(luas_lahan) AS total_luas, COUNT(CASE WHEN luas_lahan > 2.0 THEN 1 END) AS lebih_2ha FROM usulan_pupuk WHERE kth_id = ?');
    $qLuas->execute([$kthId]);
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
    $pdo->prepare('INSERT INTO laporan (kth_id, tahun, total_petani, jumlah_sesuai_sk, jumlah_tidak_sesuai_sk, jumlah_dalam_peta, jumlah_luar_peta, narasi, rekomendasi) VALUES (?,?,?,?,?,?,?,?,?)')
        ->execute([$kthId, (string)($k['tahun_usulan'] ?? date('Y')), $hitung['total'], $hitung['sesuai'], $hitung['tidak'], $hitung['dalam'], $hitung['luar'], $narasi, $rekom]);
    flash_set('ok', 'Narasi dikembalikan ke template otomatis (versi baru tersimpan).');
    header('Location: laporan.php?kth_id=' . $kthId);
    exit;
}

$tahun = trim((string)($_POST['tahun'] ?? ''));
$narasi = (string)($_POST['narasi'] ?? '');
$rekom = trim((string)($_POST['rekomendasi'] ?? 'Perlu Revisi'));
if (!in_array($rekom, ['Dapat Ditindaklanjuti', 'Perlu Revisi'], true)) $rekom = 'Perlu Revisi';
if ($narasi === '') { flash_set('error', 'Narasi tidak boleh kosong.'); header('Location: laporan.php?kth_id=' . $kthId); exit; }

$pdo->prepare('INSERT INTO laporan (kth_id, tahun, total_petani, jumlah_sesuai_sk, jumlah_tidak_sesuai_sk, jumlah_dalam_peta, jumlah_luar_peta, narasi, rekomendasi) VALUES (?,?,?,?,?,?,?,?,?)')
    ->execute([$kthId, $tahun ?: null, (int)$live['total'], (int)$live['sesuai'], (int)$live['tidak'], (int)$live['dalam'], (int)$live['luar'], $narasi, $rekom]);
flash_set('ok', 'Laporan tersimpan sebagai versi baru. Siap export Excel.');
header('Location: laporan.php?kth_id=' . $kthId);
exit;
