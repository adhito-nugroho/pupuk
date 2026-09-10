<?php
// proses_upload_perbaikan.php — Handler upload berkas usulan perbaikan bertahap (multi-version)
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/lib/parse_usulan.php';
require_once __DIR__ . '/lib/verify.php';

if (session_status() !== PHP_SESSION_ACTIVE) @session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$kthId = (int)($_POST['kth_id'] ?? 0);
$labelVersi = trim((string)($_POST['label_versi'] ?? ''));
$catatan = trim((string)($_POST['catatan_perbaikan'] ?? ''));

function gagal_perbaikan(int $kthId, string $msg): void {
    flash_set('error', $msg);
    header('Location: perbaikan.php?kth_id=' . $kthId);
    exit;
}

if (!$kthId) {
    flash_set('error', 'KTH ID tidak valid.');
    header('Location: index.php');
    exit;
}

$pdo = db();
$stKth = $pdo->prepare('SELECT * FROM kth WHERE id = ?');
$stKth->execute([$kthId]);
$kth = $stKth->fetch();
if (!$kth) {
    flash_set('error', 'KTH tidak ditemukan.');
    header('Location: index.php');
    exit;
}

// Validasi file upload
if (!isset($_FILES['f_usulan_perbaikan']) || $_FILES['f_usulan_perbaikan']['error'] !== UPLOAD_ERR_OK) {
    $err = $_FILES['f_usulan_perbaikan']['error'] ?? -1;
    $pesanErr = match($err) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File terlalu besar (melebihi batas server).',
        UPLOAD_ERR_NO_FILE                        => 'Pilih file Excel usulan terlebih dahulu.',
        default                                   => 'Upload file usulan gagal (kode: ' . $err . ').',
    };
    gagal_perbaikan($kthId, $pesanErr);
}

$file = $_FILES['f_usulan_perbaikan'];
$namaAsli = basename($file['name']);
$ext = strtolower(pathinfo($namaAsli, PATHINFO_EXTENSION));

if (!in_array($ext, ['xlsx', 'xls'], true)) {
    gagal_perbaikan($kthId, 'File usulan perbaikan harus berformat Excel (.xlsx atau .xls).');
}

if ($file['size'] > MAX_UPLOAD_BYTES) {
    gagal_perbaikan($kthId, 'Ukuran file melebihi batas ' . (MAX_UPLOAD_BYTES / 1024 / 1024) . ' MB.');
}

// Pastikan direktori uploads/usulan ada
$usulanDir = UPLOAD_DIR . '/usulan';
if (!is_dir($usulanDir)) {
    @mkdir($usulanDir, 0775, true);
}

$stamp = date('Ymd_His') . '_' . bin2hex(random_bytes(3));
$safeNama = preg_replace('/[^\w\.\-]+/', '_', $namaAsli);
$dstExcel = $usulanDir . '/' . $stamp . '_' . $safeNama;
$pathRel  = 'uploads/usulan/' . $stamp . '_' . $safeNama;

if (!move_uploaded_file($file['tmp_name'], $dstExcel)) {
    gagal_perbaikan($kthId, 'Gagal menyimpan file upload ke server.');
}

try {
    $pdo->beginTransaction();

    // Tentukan nomor versi berikutnya
    $stMax = $pdo->prepare('SELECT COALESCE(MAX(versi_ke), 0) + 1 FROM kth_versi_usulan WHERE kth_id = ?');
    $stMax->execute([$kthId]);
    $versiBaru = (int)$stMax->fetchColumn();
    if ($versiBaru <= 0) $versiBaru = 2;

    if ($labelVersi === '') {
        $labelVersi = 'Perbaikan Ke-' . ($versiBaru - 1) . ' (v' . $versiBaru . ')';
    }

    // Parse Excel usulan
    try {
        $ex = parse_excel_usulan($dstExcel);
    } catch (Throwable $eParse) {
        throw new RuntimeException('Gagal membaca format Excel usulan: ' . $eParse->getMessage());
    }

    if (empty($ex['rows'])) {
        throw new RuntimeException('Excel usulan tidak menghasilkan baris data. Pastikan terdapat baris header NIK dan NAMA.');
    }

    // Simpan baris usulan dengan versi_ke = $versiBaru
    $insU = $pdo->prepare('
        INSERT INTO usulan_pupuk 
        (kth_id, versi_ke, no_urut, nik, nama, jenis_kelamin, rt, rw, desa, kecamatan, pola_tanam, petak, luas_lahan, no_sk_ps, koordinat_x_raw, koordinat_y_raw, koordinat_x, koordinat_y) 
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
    ');

    foreach ($ex['rows'] as $r) {
        $insU->execute([
            $kthId,
            $versiBaru,
            $r['no'] ?? null,
            $r['nik'] ?? null,
            $r['nama'] ?? null,
            $r['jk'] ?? null,
            $r['rt'] ?? null,
            $r['rw'] ?? null,
            $r['desa'] ?? null,
            $r['kecamatan'] ?? null,
            $r['pola'] ?? null,
            $r['petak'] ?? null,
            $r['luas'] ?? null,
            $r['no_sk'] ?? null,
            $r['x_raw'] ?? null,
            $r['y_raw'] ?? null,
            $r['x'] ?? null,
            $r['y'] ?? null,
        ]);
    }

    // Insert record awal di kth_versi_usulan
    $pdo->prepare('
        INSERT INTO kth_versi_usulan 
        (kth_id, versi_ke, label_versi, nama_file_asli, path_file, total_petani, total_luas, catatan_perbaikan) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ')->execute([
        $kthId,
        $versiBaru,
        $labelVersi,
        $namaAsli,
        $pathRel,
        count($ex['rows']),
        0,
        $catatan ?: null,
    ]);

    // Jalankan verifikasi untuk versi baru
    $hasilVerif = verifikasi_satu_kth($pdo, $kthId, $versiBaru);

    // Set versi aktif pada KTH ke versi baru
    $pdo->prepare('UPDATE kth SET versi_aktif = ? WHERE id = ?')->execute([$versiBaru, $kthId]);

    // Update laporan jika sudah ada
    $lapRow = $pdo->prepare('SELECT id FROM laporan WHERE kth_id = ? ORDER BY id DESC LIMIT 1');
    $lapRow->execute([$kthId]);
    $lap = $lapRow->fetch();
    if ($lap) {
        $narasiBaru = buat_narasi_default($kth, (int)($kth['tahun_usulan'] ?? date('Y')), $hasilVerif);
        $pdo->prepare('
            UPDATE laporan 
            SET total_petani = ?, jumlah_sesuai_sk = ?, jumlah_tidak_sesuai_sk = ?, 
                jumlah_dalam_peta = ?, jumlah_luar_peta = ?, rekomendasi = ?, narasi = ? 
            WHERE id = ?
        ')->execute([
            $hasilVerif['total'],
            $hasilVerif['sesuai'],
            $hasilVerif['tidak'],
            $hasilVerif['dalam'],
            $hasilVerif['luar'],
            $hasilVerif['rekomendasi'],
            $narasiBaru,
            (int)$lap['id']
        ]);
    }

    $pdo->commit();

    flash_set('ok', '✅ Berkas usulan perbaikan ' . $labelVersi . ' berhasil diunggah dan diverifikasi. Hasil verifikasi kini menampilkan versi terbaru.');
    header('Location: hasil.php?kth_id=' . $kthId . '&v=' . $versiBaru);
    exit;

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if (is_file($dstExcel)) {
        @unlink($dstExcel);
    }
    gagal_perbaikan($kthId, 'Gagal memproses usulan perbaikan: ' . $e->getMessage());
}
