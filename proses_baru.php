<?php
// Proses upload 3 file + parsing awal (Excel usulan, Excel/CSV SK anggota, ZIP shapefile).
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/lib/parse_usulan.php';
require_once __DIR__ . '/lib/parse_sk.php';
require_once __DIR__ . '/lib/parse_shp.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: baru.php'); exit; }

function gagal(string $msg): void {
    flash_set('error', $msg);
    header('Location: baru.php');
    exit;
}

function proses_db_simpan(string $namaKth, int $tahun, string $namaKph, string $nomorSk, string $luasAreal, string $tanggalSk, string $dstExcel, string $dstSk, string $dstZip, ?string $sheetSk, bool $isConfirmSheet): void {
    $pdo = db();
    try {
        // 1) Validasi & parsing semua file SEBELUM menghapus DB — jika gagal, data lama tetap aman
        try {
            $ex = parse_excel_usulan($dstExcel);
        } catch (Throwable $e) {
            throw new RuntimeException('Gagal parsing Excel usulan pupuk: ' . $e->getMessage());
        }
        if (!count($ex['rows'])) {
            throw new RuntimeException('Excel usulan tidak menghasilkan baris data (pastikan terdapat baris header NIK + NAMA).');
        }
        try {
            $skParsed = parse_excel_sk($dstSk, $sheetSk);
        } catch (Throwable $e) {
            throw new RuntimeException('Gagal membaca file Excel/CSV anggota SK' . ($sheetSk ? " (sheet '{$sheetSk}')" : '') . ': ' . $e->getMessage());
        }
        if (!count($skParsed['rows'])) {
            throw new RuntimeException('File SK anggota tidak menghasilkan baris data pada sheet ' . ($sheetSk ? "'{$sheetSk}'" : 'aktif') . ' (pastikan header NIK + NAMA ada). Coba pilih sheet lain.');
        }
        try {
            $shp = parse_shapefile_zip($dstZip, $namaKth);
        } catch (Throwable $e) {
            throw new RuntimeException('Gagal parsing shapefile: ' . $e->getMessage());
        }
        if (!$shp['total_fitur']) {
            throw new RuntimeException('Shapefile tidak berisi fitur poligon yang valid.');
        }
        $pdo->beginTransaction();
        $st = $pdo->prepare('SELECT id FROM kth WHERE LOWER(nama_kth) = LOWER(?) LIMIT 1');
        $st->execute([$namaKth]);
        $kthId = $st->fetchColumn();
        $luasVal = ($luasAreal !== '' && is_numeric($luasAreal)) ? (float)$luasAreal : null;
        $tglVal = ($tanggalSk !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggalSk)) ? $tanggalSk : null;
        if ($kthId) {
            $kthId = (int)$kthId;
            $pdo->prepare('DELETE FROM hasil_verifikasi WHERE kth_id = ?')->execute([$kthId]);
            $pdo->prepare('DELETE FROM laporan WHERE kth_id = ?')->execute([$kthId]);
            $pdo->prepare('DELETE FROM usulan_pupuk WHERE kth_id = ?')->execute([$kthId]);
            $pdo->prepare('DELETE FROM sk_anggota WHERE kth_id = ?')->execute([$kthId]);
            $pdo->prepare('DELETE FROM poligon_ps WHERE kth_id = ?')->execute([$kthId]);
            $pdo->prepare('UPDATE kth SET nomor_sk = ?, nama_kph = ?, luas_areal = ?, tanggal_sk = ?, tahun_usulan = ?, nama_kth = ? WHERE id = ?')
                ->execute([$nomorSk ?: null, $namaKph ?: null, $luasVal, $tglVal, $tahun ?: null, $namaKth, $kthId]);
        } else {
            $pdo->prepare('INSERT INTO kth (nama_kth, nomor_sk, nama_kph, luas_areal, tanggal_sk, tahun_usulan) VALUES (?,?,?,?,?,?)')
                ->execute([$namaKth, $nomorSk ?: null, $namaKph ?: null, $luasVal, $tglVal, $tahun ?: null]);
            $kthId = (int)$pdo->lastInsertId();
        }
        $insU = $pdo->prepare('INSERT INTO usulan_pupuk (kth_id, no_urut, nik, nama, jenis_kelamin, rt, rw, desa, kecamatan, pola_tanam, petak, luas_lahan, no_sk_ps, koordinat_x_raw, koordinat_y_raw, koordinat_x, koordinat_y) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        foreach ($ex['rows'] as $r) {
            $insU->execute([
                $kthId, $r['no'], $r['nik'], $r['nama'], $r['jk'] ?: null, $r['rt'] ?: null, $r['rw'] ?: null,
                $r['desa'] ?: null, $r['kecamatan'] ?: null, $r['pola'] ?: null, $r['petak'] ?: null,
                $r['luas'], $r['no_sk'] ?: null, $r['x_raw'] ?: null, $r['y_raw'] ?: null, $r['x'], $r['y']
            ]);
        }
        $_SESSION['sk_parse'][$kthId] = [
            'rows' => $skParsed['rows'],
            'info' => [
                'total' => $skParsed['total'],
                'perlu_dicek' => $skParsed['perlu_dicek_count'],
                'file' => basename($dstSk) . ($sheetSk ? " — Sheet: {$sheetSk}" : ''),
                'sheet' => $sheetSk,
            ],
        ];
        $namaLayer = $shp['features'][0]['lembaga'] ?? basename($dstZip);
        $noSkDbf = '';
        foreach ($shp['features'] as $f) {
            if (trim((string)$f['no_sk']) !== '') { $noSkDbf = (string)$f['no_sk']; break; }
        }
        if ($nomorSk === '' && $noSkDbf !== '') {
            $nomorSk = $noSkDbf;
            $pdo->prepare('UPDATE kth SET nomor_sk = ? WHERE id = ?')->execute([$nomorSk, $kthId]);
        }
        $pdo->prepare('INSERT INTO poligon_ps (kth_id, nama_layer, geometry_json, jumlah_ring, sumber_file) VALUES (?,?,?,?,?)')
            ->execute([$kthId, $namaLayer, json_encode(['features' => $shp['features']], JSON_UNESCAPED_UNICODE), $shp['total_ring'], basename($dstZip)]);
        $pdo->commit();
        unset($_SESSION['pending_baru']);
        $pesan = 'Upload berhasil: ' . count($ex['rows']) . ' baris usulan pupuk, ' . count($_SESSION['sk_parse'][$kthId]['rows']) . ' baris daftar anggota SK' . ($sheetSk ? " (sheet: {$sheetSk})" : '') . ', ' . $shp['total_fitur'] . ' fitur poligon (' . $shp['total_ring'] . ' ring). Silakan tinjau dan konfirmasi anggota SK.';
        if ($skParsed['perlu_dicek_count'] > 0) {
            $pesan .= " (Perhatian: ada {$skParsed['perlu_dicek_count']} baris yang ditandai kuning dan perlu dicek manual).";
        }
        flash_set('ok', $pesan);
        header('Location: konfirmasi_sk.php?kth_id=' . $kthId);
        exit;
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
        if ($isConfirmSheet) {
            flash_set('error', $e->getMessage());
            header('Location: pilih_sheet_sk.php');
            exit;
        }
        // cleanup file upload jika gagal dan bukan pending
        if (!isset($_SESSION['pending_baru'])) {
            @unlink($dstExcel ?? '');
            @unlink($dstSk ?? '');
            @unlink($dstZip ?? '');
        }
        gagal($e->getMessage());
    }
}

// ============================================================
// MODE 2: Lanjutan dari halaman pilih_sheet_sk.php
// ============================================================
$isConfirmSheet = isset($_POST['confirm_sheet']) && isset($_SESSION['pending_baru']);
if ($isConfirmSheet) {
    $pending = $_SESSION['pending_baru'];
    $sheetSk = trim((string)($_POST['sheet_sk'] ?? ''));
    if ($sheetSk === '') {
        flash_set('error', 'Silakan pilih salah satu sheet terlebih dahulu.');
        header('Location: pilih_sheet_sk.php');
        exit;
    }
    $dstSkPending = $pending['dstSk'] ?? '';
    if (!is_file($dstSkPending)) {
        unset($_SESSION['pending_baru']);
        gagal('File SK pending tidak ditemukan, silakan upload ulang dari awal.');
    }
    $sheetNamesCheck = get_sheet_names_sk($dstSkPending);
    $found = false;
    foreach ($sheetNamesCheck as $nm) {
        if (strcasecmp($nm, $sheetSk) === 0) { $found = true; $sheetSk = $nm; break; }
    }
    if (!$found) {
        flash_set('error', "Sheet '{$sheetSk}' tidak ditemukan di file.");
        header('Location: pilih_sheet_sk.php');
        exit;
    }
    $namaKth = trim((string)($pending['post']['nama_kth_baru'] ?? ''));
    $tahun = (int)($pending['post']['tahun'] ?? date('Y'));
    $namaKph = trim((string)($pending['post']['nama_kph'] ?? ''));
    $nomorSk = trim((string)($pending['post']['nomor_sk'] ?? ''));
    $luasAreal = trim((string)($pending['post']['luas_areal'] ?? ''));
    $tanggalSk = trim((string)($pending['post']['tanggal_sk'] ?? ''));
    $dstExcel = $pending['dstExcel'];
    $dstSk = $pending['dstSk'];
    $dstZip = $pending['dstZip'];
    if (!is_file($dstExcel) || !is_file($dstZip)) {
        unset($_SESSION['pending_baru']);
        gagal('File upload pending tidak lengkap, silakan upload ulang.');
    }
    proses_db_simpan($namaKth, $tahun, $namaKph, $nomorSk, $luasAreal, $tanggalSk, $dstExcel, $dstSk, $dstZip, $sheetSk, true);
    exit;
}

// ============================================================
// MODE 1: Upload awal 3 file dari baru.php
// ============================================================
$namaKth = trim((string)($_POST['nama_kth_baru'] ?? ''));
$tahun = (int)($_POST['tahun'] ?? date('Y'));
$namaKph = trim((string)($_POST['nama_kph'] ?? ''));
$nomorSk = trim((string)($_POST['nomor_sk'] ?? ''));
$luasAreal = trim((string)($_POST['luas_areal'] ?? ''));
$tanggalSk = trim((string)($_POST['tanggal_sk'] ?? ''));

if ($namaKth === '') gagal('Nama KTH/LMDH wajib diisi.');

$uploadMap = [
    'f_excel' => 'Excel usulan pupuk',
    'f_sk'    => 'Excel/CSV daftar anggota SK',
    'f_zip'   => 'ZIP shapefile areal PS'
];
foreach ($uploadMap as $k => $label) {
    if (!isset($_FILES[$k]) || $_FILES[$k]['error'] !== UPLOAD_ERR_OK) {
        gagal("Upload $label gagal (kode error " . ($_FILES[$k]['error'] ?? '?') . ').');
    }
    if ($_FILES[$k]['size'] > MAX_UPLOAD_BYTES) {
        gagal("$label melebihi batas " . (MAX_UPLOAD_BYTES / 1024 / 1024) . ' MB.');
    }
    if ($_FILES[$k]['size'] === 0) {
        gagal("$label kosong.");
    }
}
$ext = fn($n) => strtolower(pathinfo((string)$n, PATHINFO_EXTENSION));
if (!in_array($ext($_FILES['f_excel']['name']), ['xlsx', 'xls'], true)) {
    gagal('File usulan pupuk harus bertipe .xlsx atau .xls.');
}
if (!in_array($ext($_FILES['f_sk']['name']), ['xlsx', 'xls', 'csv'], true)) {
    gagal('File daftar anggota SK harus bertipe .xlsx, .xls, atau .csv.');
}
if ($ext($_FILES['f_zip']['name']) !== 'zip') {
    gagal('File shapefile harus bertipe .zip.');
}
$zip = new ZipArchive();
if ($zip->open($_FILES['f_zip']['tmp_name']) !== true) {
    gagal('File ZIP shapefile corrupt / tidak bisa dibuka.');
}
$bases = [];
for ($i = 0; $i < $zip->numFiles; $i++) {
    $nm = $zip->getNameIndex($i);
    if (substr($nm, -1) === '/') continue;
    $low = strtolower($nm);
    foreach (['.shp', '.dbf', '.shx'] as $e) {
        if (str_ends_with($low, $e)) {
            $bases[strtolower(substr($nm, 0, -4))][$e] = true;
        }
    }
}
$zip->close();
$okBase = null;
foreach ($bases as $b => $have) {
    if (isset($have['.shp'], $have['.dbf'], $have['.shx'])) { $okBase = $b; break; }
}
if ($okBase === null) {
    gagal('ZIP shapefile tidak lengkap: wajib memuat minimal file .shp, .dbf, dan .shx dengan nama base file yang sama.');
}
$stamp = date('Ymd_His') . '_' . bin2hex(random_bytes(4));
$safe = fn($n) => preg_replace('/[^\w\.\-]+/', '_', (string)$n);
$dstExcel = UPLOAD_DIR . '/' . $stamp . '_' . $safe($_FILES['f_excel']['name']);
$dstSk    = UPLOAD_DIR . '/' . $stamp . '_' . $safe($_FILES['f_sk']['name']);
$dstZip   = UPLOAD_DIR . '/' . $stamp . '_' . $safe($_FILES['f_zip']['name']);
foreach ([['f_excel', $dstExcel], ['f_sk', $dstSk], ['f_zip', $dstZip]] as [$k, $dst]) {
    if (!move_uploaded_file($_FILES[$k]['tmp_name'], $dst)) {
        gagal('Gagal menyimpan file upload ke server.');
    }
}
$extSk = strtolower(pathinfo($dstSk, PATHINFO_EXTENSION));
$sheetSk = null;
if ($extSk !== 'csv') {
    $requestedSheet = trim((string)($_POST['sheet_sk'] ?? ''));
    if ($requestedSheet !== '') {
        $sheetSk = $requestedSheet;
    } else {
        try {
            $sheetNames = get_sheet_names_sk($dstSk);
        } catch (Throwable $e) {
            $sheetNames = [];
        }
        if (count($sheetNames) > 1) {
            $_SESSION['pending_baru'] = [
                'post' => $_POST,
                'dstExcel' => $dstExcel,
                'dstSk' => $dstSk,
                'dstZip' => $dstZip,
                'stamp' => $stamp,
                'sheetNames' => $sheetNames,
                'origSkName' => $_FILES['f_sk']['name'],
                'origExcelName' => $_FILES['f_excel']['name'],
                'origZipName' => $_FILES['f_zip']['name'],
            ];
            header('Location: pilih_sheet_sk.php');
            exit;
        }
        $sheetSk = null;
    }
}
proses_db_simpan($namaKth, $tahun, $namaKph, $nomorSk, $luasAreal, $tanggalSk, $dstExcel, $dstSk, $dstZip, $sheetSk, false);
