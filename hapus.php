<?php
// Hapus satu kasus + seluruh data turunannya + file fisik terkait.
// Hanya via POST (anti klik tak sengaja / crawler). Mencatat ke hapus.log.
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    flash_set('error', 'Penghapusan harus via tombol Hapus (POST).');
    header('Location: index.php');
    exit;
}

$kthId = (int)($_POST['kth_id'] ?? 0);
if (!$kthId) {
    flash_set('error', 'Parameter tidak valid.');
    header('Location: index.php');
    exit;
}

$pdo = db();
$stKth = $pdo->prepare('SELECT id, nama_kth FROM kth WHERE id = ?');
$stKth->execute([$kthId]);
$kth = $stKth->fetch();
if (!$kth) {
    flash_set('error', 'Data kasus tidak ditemukan.');
    header('Location: index.php');
    exit;
}

// Kumpulkan file fisik terkait sebelum baris DB dihapus
$files = [];
try {
    $stV = $pdo->prepare('SELECT path_file FROM kth_versi_usulan WHERE kth_id = ?');
    $stV->execute([$kthId]);
    foreach ($stV->fetchAll() as $vr) {
        if (!empty($vr['path_file'])) $files[] = $vr['path_file'];
    }
    $stL = $pdo->prepare('SELECT berkas_ba FROM laporan WHERE kth_id = ?');
    $stL->execute([$kthId]);
    foreach ($stL->fetchAll() as $lr) {
        if (!empty($lr['berkas_ba'])) $files[] = $lr['berkas_ba'];
    }
} catch (Throwable $eFiles) { /* best-effort */ }

$pdo->prepare('DELETE FROM kth WHERE id = ?')->execute([$kthId]);

$deleted = 0;
foreach (array_unique($files) as $rel) {
    $full = __DIR__ . '/' . ltrim($rel, '/');
    if (is_file($full) && strpos(realpath($full) ?: $full, realpath(__DIR__) ?: __DIR__) === 0) {
        if (@unlink($full)) $deleted++;
    }
}

// Audit trail penghapusan
@file_put_contents(__DIR__ . '/hapus.log',
    date('Y-m-d H:i:s') . ' | HAPUS KASUS | id=' . $kthId
    . ' | nama=' . ($kth['nama_kth'] ?? '-') . ' | ip=' . ($_SERVER['REMOTE_ADDR'] ?? '-')
    . ' | file_dihapus=' . $deleted . PHP_EOL, FILE_APPEND);

flash_set('ok', "Kasus #{$kthId} (" . ($kth['nama_kth'] ?? '-') . ") dihapus beserta {$deleted} file terkait.");
header('Location: index.php');
exit;
