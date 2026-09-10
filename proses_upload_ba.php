<?php
/**
 * proses_upload_ba.php — Handler AJAX upload Berita Acara (Word, Excel, PDF per KTH).
 *
 * Menerima: POST multipart { kth_id, tgl_ba } + FILE { file_ba }
 * Menyimpan file ke uploads/berita_acara/ dan mencatat path di tabel laporan.
 * Satu BA per KTH — jika sudah ada, file lama diganti.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/helpers.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'msg' => 'Method tidak diizinkan.']);
        exit;
    }

    $kthId = (int)($_POST['kth_id'] ?? 0);
    $tglBa = trim((string)($_POST['tgl_ba'] ?? ''));

    if (!$kthId) {
        echo json_encode(['ok' => false, 'msg' => 'kth_id tidak valid.']);
        exit;
    }

    // Validasi file
    if (empty($_FILES['file_ba']) || $_FILES['file_ba']['error'] !== UPLOAD_ERR_OK) {
        $err = $_FILES['file_ba']['error'] ?? -1;
        $pesanErr = match($err) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File terlalu besar (melebihi batas maksimal server).',
            UPLOAD_ERR_NO_FILE                        => 'Tidak ada file yang diunggah.',
            UPLOAD_ERR_PARTIAL                        => 'File hanya terunggah sebagian.',
            default                                   => 'Upload gagal (kode error: ' . $err . ').',
        };
        echo json_encode(['ok' => false, 'msg' => $pesanErr]);
        exit;
    }

    $file     = $_FILES['file_ba'];
    $ukuran   = (int)$file['size'];
    $namaAsli = basename($file['name']);
    $ext      = strtolower(pathinfo($namaAsli, PATHINFO_EXTENSION));

    $allowedExts = ['doc', 'docx', 'xls', 'xlsx', 'pdf'];
    if (!in_array($ext, $allowedExts, true)) {
        echo json_encode(['ok' => false, 'msg' => 'Hanya file Word (.doc, .docx), Excel (.xls, .xlsx), atau PDF (.pdf) yang diizinkan.']);
        exit;
    }

    if ($ukuran > 10 * 1024 * 1024) {
        echo json_encode(['ok' => false, 'msg' => 'Ukuran file melebihi batas 10 MB.']);
        exit;
    }

    $pdo = db();

    // Auto-migration fallback jika kolom belum ada di database server
    try {
        $pdo->query('SELECT berkas_ba, nama_file_ba, tgl_ba FROM laporan LIMIT 0');
    } catch (Throwable $eMissing) {
        $cols = [
            'berkas_ba'    => 'VARCHAR(512) DEFAULT NULL',
            'nama_file_ba' => 'VARCHAR(255) DEFAULT NULL',
            'tgl_ba'       => 'DATE DEFAULT NULL'
        ];
        foreach ($cols as $colName => $colDef) {
            try {
                $pdo->exec("ALTER TABLE `laporan` ADD COLUMN `$colName` $colDef");
            } catch (Throwable $ignored) {}
        }
    }

    // Pastikan KTH ada
    $kthRow = $pdo->prepare('SELECT id FROM kth WHERE id = ?');
    $kthRow->execute([$kthId]);
    if (!$kthRow->fetch()) {
        echo json_encode(['ok' => false, 'msg' => 'KTH tidak ditemukan.']);
        exit;
    }

    // Ambil laporan terbaru untuk kth_id ini
    $lapRow = $pdo->prepare('SELECT id, berkas_ba FROM laporan WHERE kth_id = ? ORDER BY id DESC LIMIT 1');
    $lapRow->execute([$kthId]);
    $lap = $lapRow->fetch();

    // Hapus file lama jika ada
    if ($lap && !empty($lap['berkas_ba'])) {
        $fileLama = __DIR__ . '/' . ltrim($lap['berkas_ba'], '/');
        if (is_file($fileLama)) {
            @unlink($fileLama);
        }
    }

    // Simpan file baru
    $baDir = __DIR__ . '/uploads/berita_acara';
    if (!is_dir($baDir)) {
        @mkdir($baDir, 0775, true);
    }

    $cleanExt = preg_replace('/[^a-z0-9]/', '', $ext);
    $namaFile = 'kth_' . $kthId . '_' . time() . '.' . $cleanExt;
    $pathFull = $baDir . '/' . $namaFile;
    $pathRel  = 'uploads/berita_acara/' . $namaFile;

    if (!move_uploaded_file($file['tmp_name'], $pathFull)) {
        echo json_encode(['ok' => false, 'msg' => 'Gagal menyimpan file ke server. Periksa izin folder uploads/berita_acara.']);
        exit;
    }

    // Update atau insert laporan
    $tglSimpan = ($tglBa !== '' && strtotime($tglBa)) ? $tglBa : null;

    if ($lap) {
        $pdo->prepare(
            'UPDATE laporan SET berkas_ba = ?, nama_file_ba = ?, tgl_ba = ? WHERE id = ?'
        )->execute([$pathRel, $namaAsli, $tglSimpan, (int)$lap['id']]);
    } else {
        // Belum ada laporan — buat record laporan minimal
        $h = $pdo->prepare(
            'SELECT COUNT(*) total, SUM(status_sk="Sesuai SK PS") sesuai, SUM(status_sk!="Sesuai SK PS") tidak,
                    SUM(status_koordinat="Dalam Peta PS") dalam, SUM(status_koordinat!="Dalam Peta PS") luar
             FROM hasil_verifikasi WHERE kth_id = ?'
        );
        $h->execute([$kthId]);
        $hitung = $h->fetch() ?: [];

        $pdo->prepare(
            'INSERT INTO laporan (kth_id, tahun, total_petani, jumlah_sesuai_sk, jumlah_tidak_sesuai_sk,
                                  jumlah_dalam_peta, jumlah_luar_peta, narasi, rekomendasi, berkas_ba, nama_file_ba, tgl_ba)
             VALUES (?, YEAR(NOW()), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $kthId,
            (int)($hitung['total'] ?? 0),
            (int)($hitung['sesuai'] ?? 0),
            (int)($hitung['tidak'] ?? 0),
            (int)($hitung['dalam'] ?? 0),
            (int)($hitung['luar'] ?? 0),
            '',
            'Perlu Revisi',
            $pathRel,
            $namaAsli,
            $tglSimpan,
        ]);
    }

    echo json_encode([
        'ok'       => true,
        'msg'      => 'Berita Acara berhasil diunggah.',
        'path'     => $pathRel,
        'filename' => $namaAsli,
        'tgl_ba'   => $tglSimpan,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok'  => false,
        'msg' => 'Terjadi kesalahan sistem: ' . $e->getMessage()
    ]);
}

