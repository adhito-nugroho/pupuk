<?php
// hapus_versi.php — Hapus satu putaran versi usulan perbaikan beserta data turunannya
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/verify.php';

if (session_status() !== PHP_SESSION_ACTIVE) @session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    flash_set('error', 'Penghapusan versi harus via tombol Hapus (POST).');
    header('Location: index.php');
    exit;
}

$kthId   = (int)($_POST['kth_id'] ?? 0);
$versiKe = (int)($_POST['versi_ke'] ?? 0);
$from    = (string)($_POST['from'] ?? 'index');

if (!$kthId || $versiKe <= 1) {
    flash_set('error', 'Versi usulan awal (v1) tidak dapat dihapus. Anda hanya dapat menghapus putaran usulan perbaikan (v2, v3, dst.).');
    header('Location: ' . ($from === 'perbaikan' ? "perbaikan.php?kth_id={$kthId}" : 'index.php'));
    exit;
}

$pdo = db();
$stKth = $pdo->prepare('SELECT * FROM kth WHERE id = ?');
$stKth->execute([$kthId]);
$kth = $stKth->fetch();
if (!$kth) {
    flash_set('error', 'Data KTH tidak ditemukan.');
    header('Location: index.php');
    exit;
}

// Ambil info file versi yang akan dihapus
$stV = $pdo->prepare('SELECT * FROM kth_versi_usulan WHERE kth_id = ? AND versi_ke = ?');
$stV->execute([$kthId, $versiKe]);
$ver = $stV->fetch();

if (!$ver) {
    flash_set('error', 'Data versi yang dimaksud tidak ditemukan.');
    header('Location: ' . ($from === 'perbaikan' ? "perbaikan.php?kth_id={$kthId}" : 'index.php'));
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Hapus hasil_verifikasi untuk versi ini
    $pdo->prepare('DELETE FROM hasil_verifikasi WHERE kth_id = ? AND versi_ke = ?')->execute([$kthId, $versiKe]);

    // 2. Hapus usulan_pupuk untuk versi ini
    $pdo->prepare('DELETE FROM usulan_pupuk WHERE kth_id = ? AND versi_ke = ?')->execute([$kthId, $versiKe]);

    // 3. Hapus record dari kth_versi_usulan
    $pdo->prepare('DELETE FROM kth_versi_usulan WHERE kth_id = ? AND versi_ke = ?')->execute([$kthId, $versiKe]);

    // 4. Hapus laporan khusus versi ini jika ada
    try {
        $stLapDel = $pdo->prepare('SELECT berkas_ba FROM laporan WHERE kth_id = ? AND versi_ke = ?');
        $stLapDel->execute([$kthId, $versiKe]);
        foreach ($stLapDel->fetchAll() as $rLapDel) {
            if (!empty($rLapDel['berkas_ba'])) {
                $fullPathBa = __DIR__ . '/' . ltrim($rLapDel['berkas_ba'], '/');
                if (is_file($fullPathBa)) @unlink($fullPathBa);
            }
        }
        $pdo->prepare('DELETE FROM laporan WHERE kth_id = ? AND versi_ke = ?')->execute([$kthId, $versiKe]);
    } catch (Throwable $eDelLap) {}

    // 5. Hapus file fisik usulan perbaikan jika ada
    if (!empty($ver['path_file'])) {
        $fullPath = __DIR__ . '/' . ltrim($ver['path_file'], '/');
        if (is_file($fullPath)) {
            @unlink($fullPath);
        }
    }

    // 5. Tentukan versi aktif terbaru yang masih tersisa
    $stRem = $pdo->prepare('SELECT COALESCE(MAX(versi_ke), 1) FROM kth_versi_usulan WHERE kth_id = ?');
    $stRem->execute([$kthId]);
    $newAktif = (int)$stRem->fetchColumn();
    if ($newAktif <= 0) $newAktif = 1;

    $pdo->prepare('UPDATE kth SET versi_aktif = ? WHERE id = ?')->execute([$newAktif, $kthId]);

    // 6. Sinkronkan laporan dengan versi aktif yang baru
    // (utamakan baris laporan versi tersebut; fallback ke terakhir utk data lama)
    $hasilTerbaru = verifikasi_satu_kth($pdo, $kthId, $newAktif);
    $lap = false;
    try {
        $cekV = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'laporan' AND COLUMN_NAME = 'versi_ke'");
        $cekV->execute();
        if ((int)$cekV->fetchColumn() > 0) {
            $lapRowV = $pdo->prepare('SELECT id FROM laporan WHERE kth_id = ? AND versi_ke = ? ORDER BY id DESC LIMIT 1');
            $lapRowV->execute([$kthId, $newAktif]);
            $lap = $lapRowV->fetch();
        }
    } catch (Throwable $eLV) { $lap = false; }
    if (!$lap) {
        $lapRow = $pdo->prepare('SELECT id FROM laporan WHERE kth_id = ? ORDER BY id DESC LIMIT 1');
        $lapRow->execute([$kthId]);
        $lap = $lapRow->fetch();
    }
    if ($lap) {
        $narasiBaru = buat_narasi_default($kth, (int)($kth['tahun_usulan'] ?? date('Y')), $hasilTerbaru);
        $pdo->prepare('
            UPDATE laporan 
            SET total_petani = ?, jumlah_sesuai_sk = ?, jumlah_tidak_sesuai_sk = ?, 
                jumlah_dalam_peta = ?, jumlah_luar_peta = ?, rekomendasi = ?, narasi = ? 
            WHERE id = ?
        ')->execute([
            $hasilTerbaru['total'],
            $hasilTerbaru['sesuai'],
            $hasilTerbaru['tidak'],
            $hasilTerbaru['dalam'],
            $hasilTerbaru['luar'],
            $hasilTerbaru['rekomendasi'],
            $narasiBaru,
            (int)$lap['id']
        ]);
    }

    $pdo->commit();

    @file_put_contents(__DIR__ . '/hapus.log',
        date('Y-m-d H:i:s') . ' | HAPUS VERSI | kth_id=' . $kthId
        . ' | versi=' . $versiKe . ' | label=' . ($ver['label_versi'] ?? '-')
        . ' | ip=' . ($_SERVER['REMOTE_ADDR'] ?? '-') . PHP_EOL, FILE_APPEND);

    flash_set('ok', '✅ Versi perbaikan ' . ($ver['label_versi'] ?: ('v' . $versiKe)) . ' berhasil dihapus. Versi aktif dialihkan ke Versi ' . $newAktif . '.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    flash_set('error', 'Gagal menghapus versi: ' . $e->getMessage());
}

$dest = ($from === 'perbaikan') ? "perbaikan.php?kth_id={$kthId}" : "index.php";
header('Location: ' . $dest);
exit;
