<?php
// Simpan pengaturan global verifikator.
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: pengaturan.php'); exit; }

$pdo = db();
$nama    = trim((string)($_POST['nama_verifikator'] ?? ''));
$nip     = trim((string)($_POST['nip_verifikator'] ?? ''));
$jabatan = trim((string)($_POST['jabatan_verifikator'] ?? ''));
$kembali = trim((string)($_POST['kembali'] ?? ''));

if (mb_strlen($nama, 'UTF-8') > 120) $nama = mb_substr($nama, 0, 120, 'UTF-8');
if (mb_strlen($nip, 'UTF-8') > 64) $nip = mb_substr($nip, 0, 64, 'UTF-8');
if (mb_strlen($jabatan, 'UTF-8') > 120) $jabatan = mb_substr($jabatan, 0, 120, 'UTF-8');

pengaturan_set($pdo, 'nama_verifikator', $nama);
pengaturan_set($pdo, 'nip_verifikator', $nip);
pengaturan_set($pdo, 'jabatan_verifikator', $jabatan);

flash_set('ok', 'Pengaturan verifikator tersimpan. Lembar Hasil kini memakai nama "' . ($nama !== '' ? $nama : '(kosong)') . '".');

if ($kembali !== '') {
    header('Location: ' . $kembali);
} else {
    header('Location: pengaturan.php');
}
exit;
