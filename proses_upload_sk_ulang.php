<?php
// proses_upload_sk_ulang.php — Handler upload ulang berkas SK dari halaman konfirmasi_sk.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/lib/parse_sk.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$kthId = (int)($_POST['kth_id'] ?? 0);
if (!$kthId) {
    flash_set('error', 'KTH ID tidak valid.');
    header('Location: index.php');
    exit;
}

$pdo = db();
$stKth = $pdo->prepare('SELECT id, nama_kth FROM kth WHERE id = ?');
$stKth->execute([$kthId]);
$kth = $stKth->fetch();
if (!$kth) {
    flash_set('error', 'KTH tidak ditemukan.');
    header('Location: index.php');
    exit;
}

if (!isset($_FILES['f_sk_ulang']) || $_FILES['f_sk_ulang']['error'] !== UPLOAD_ERR_OK) {
    $errCode = $_FILES['f_sk_ulang']['error'] ?? -1;
    flash_set('error', 'Gagal mengunggah berkas SK (kode: ' . $errCode . ').');
    header('Location: konfirmasi_sk.php?kth_id=' . $kthId);
    exit;
}

$file = $_FILES['f_sk_ulang'];
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, ['xlsx', 'xls', 'csv'], true)) {
    flash_set('error', 'Berkas SK harus berformat Excel (.xlsx, .xls) atau CSV (.csv).');
    header('Location: konfirmasi_sk.php?kth_id=' . $kthId);
    exit;
}

if ($file['size'] > MAX_UPLOAD_BYTES) {
    flash_set('error', 'Ukuran berkas melebihi batas ' . (MAX_UPLOAD_BYTES / 1024 / 1024) . ' MB.');
    header('Location: konfirmasi_sk.php?kth_id=' . $kthId);
    exit;
}

$stamp = date('Ymd_His') . '_' . bin2hex(random_bytes(3));
$safeName = preg_replace('/[^\w\.\-]+/', '_', basename($file['name']));
$dstSk = UPLOAD_DIR . '/' . $stamp . '_' . $safeName;

if (!move_uploaded_file($file['tmp_name'], $dstSk)) {
    flash_set('error', 'Gagal menyimpan berkas SK di server.');
    header('Location: konfirmasi_sk.php?kth_id=' . $kthId);
    exit;
}

try {
    $parsed = parse_excel_sk($dstSk);
    if (empty($parsed['rows'])) {
        throw new RuntimeException('Berkas SK tidak memuat baris data anggota yang valid (pastikan kolom NIK dan NAMA tersedia di berkas).');
    }

    // Masukkan ke staging session agar langsung tampil di tabel konfirmasi_sk
    $_SESSION['sk_parse'][$kthId] = [
        'rows' => $parsed['rows'],
        'info' => [
            'total' => $parsed['total'],
            'perlu_dicek' => $parsed['perlu_dicek_count'],
            'file' => basename($file['name']),
        ]
    ];

    flash_set('ok', 'Berhasil membaca seluruh ' . $parsed['total'] . ' data anggota dari berkas SK (' . basename($file['name']) . '). Silakan periksa dan klik tombol "Simpan & Lanjut" di bawah tabel.');
} catch (Throwable $e) {
    flash_set('error', 'Gagal membaca berkas SK: ' . $e->getMessage());
}

header('Location: konfirmasi_sk.php?kth_id=' . $kthId);
exit;
