<?php
/**
 * hapus_ba.php — Hapus file Berita Acara dari laporan & disk.
 *
 * Menerima: POST { kth_id }
 * Menghapus file fisik dan me-null-kan kolom berkas_ba di laporan.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php'); exit;
}

$kthId = (int)($_POST['kth_id'] ?? 0);
if (!$kthId) {
    flash_set('error', 'Parameter tidak valid.');
    header('Location: index.php'); exit;
}

$pdo = db();

$lapRow = $pdo->prepare('SELECT id, berkas_ba FROM laporan WHERE kth_id = ? ORDER BY id DESC LIMIT 1');
$lapRow->execute([$kthId]);
$lap = $lapRow->fetch();

if ($lap && !empty($lap['berkas_ba'])) {
    // Hapus file fisik
    $filePath = __DIR__ . '/' . ltrim($lap['berkas_ba'], '/');
    if (is_file($filePath)) {
        @unlink($filePath);
    }
    // Null-kan kolom di database
    $pdo->prepare('UPDATE laporan SET berkas_ba = NULL, nama_file_ba = NULL, tgl_ba = NULL WHERE id = ?')
        ->execute([(int)$lap['id']]);

    flash_set('ok', 'File Berita Acara berhasil dihapus.');
} else {
    flash_set('error', 'File Berita Acara tidak ditemukan.');
}

header('Location: laporan.php?kth_id=' . $kthId);
exit;
