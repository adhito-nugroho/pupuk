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
$kasiNama = trim((string)($_POST['nama_kasi_rlpm'] ?? ''));
$kasiNip  = trim((string)($_POST['nip_kasi_rlpm'] ?? ''));
$luNama   = trim((string)($_POST['nama_penyuluh'] ?? ''));
$luNip    = trim((string)($_POST['nip_penyuluh'] ?? ''));
$kembali = trim((string)($_POST['kembali'] ?? ''));

if (mb_strlen($nama, 'UTF-8') > 120) $nama = mb_substr($nama, 0, 120, 'UTF-8');
if (mb_strlen($nip, 'UTF-8') > 64) $nip = mb_substr($nip, 0, 64, 'UTF-8');
if (mb_strlen($jabatan, 'UTF-8') > 120) $jabatan = mb_substr($jabatan, 0, 120, 'UTF-8');
if (mb_strlen($kasiNama, 'UTF-8') > 120) $kasiNama = mb_substr($kasiNama, 0, 120, 'UTF-8');
if (mb_strlen($kasiNip, 'UTF-8') > 64) $kasiNip = mb_substr($kasiNip, 0, 64, 'UTF-8');
if (mb_strlen($luNama, 'UTF-8') > 120) $luNama = mb_substr($luNama, 0, 120, 'UTF-8');
if (mb_strlen($luNip, 'UTF-8') > 64) $luNip = mb_substr($luNip, 0, 64, 'UTF-8');

pengaturan_set($pdo, 'nama_verifikator', $nama);
pengaturan_set($pdo, 'nip_verifikator', $nip);
pengaturan_set($pdo, 'jabatan_verifikator', $jabatan);
pengaturan_set($pdo, 'nama_kasi_rlpm', $kasiNama);
pengaturan_set($pdo, 'nip_kasi_rlpm', $kasiNip);
pengaturan_set($pdo, 'nama_penyuluh', $luNama);
pengaturan_set($pdo, 'nip_penyuluh', $luNip);

flash_set('ok', 'Pengaturan penandatangan tersimpan.');

if ($kembali !== '') {
    header('Location: ' . $kembali);
} else {
    header('Location: pengaturan.php');
}
exit;
