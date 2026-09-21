<?php
/**
 * proses_koreksi_koordinat.php — Handler AJAX untuk koreksi posisi koordinat petani.
 *
 * Menerima: POST { usulan_id, lat, lng }
 * Menyimpan koordinat koreksi ke hasil_verifikasi (kolom baru).
 * TIDAK mengubah koordinat asli di usulan_pupuk.
 * Memperbarui status_koordinat = 'Dalam Peta PS' setelah validasi server-side.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/parse_shp.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'msg' => 'Method tidak diizinkan.']);
    exit;
}

$usulanId = (int)($_POST['usulan_id'] ?? 0);
$lat      = isset($_POST['lat']) ? (float)$_POST['lat'] : null;
$lng      = isset($_POST['lng']) ? (float)$_POST['lng'] : null;

if (!$usulanId || $lat === null || $lng === null) {
    echo json_encode(['ok' => false, 'msg' => 'Parameter tidak lengkap (usulan_id, lat, lng).']);
    exit;
}

// Validasi koordinat: latitude -90..90, longitude 90..140 (wilayah Indonesia)
if ($lat < -15 || $lat > 10 || $lng < 90 || $lng > 145) {
    echo json_encode(['ok' => false, 'msg' => 'Koordinat di luar rentang wilayah Indonesia.']);
    exit;
}

$pdo = db();

// Ambil kth_id + versi dari usulan
$qu = $pdo->prepare('SELECT kth_id, versi_ke FROM usulan_pupuk WHERE id = ?');
$qu->execute([$usulanId]);
$u = $qu->fetch();
if (!$u) {
    echo json_encode(['ok' => false, 'msg' => 'Data usulan tidak ditemukan.']);
    exit;
}
$kthId = (int)$u['kth_id'];
$versiKe = (int)($u['versi_ke'] ?? 1);

// Validasi server-side: titik harus masuk poligon PS
$polyRow = $pdo->prepare('SELECT geometry_json FROM poligon_ps WHERE kth_id = ? ORDER BY id DESC LIMIT 1');
$polyRow->execute([$kthId]);
$pr = $polyRow->fetch();

if ($pr) {
    $rings = rings_dari_geometry_json((string)$pr['geometry_json']);
    if ($rings && !point_in_geometry($lng, $lat, $rings)) {
        echo json_encode(['ok' => false, 'msg' => 'Koordinat baru masih berada di luar poligon areal PS. Geser marker ke dalam batas area PS.']);
        exit;
    }
}
// Jika tidak ada poligon tersimpan, tetap izinkan koreksi (poligon belum diupload)

// Simpan koreksi ke hasil_verifikasi (koordinat asli di usulan_pupuk TIDAK diubah)
$upd = $pdo->prepare(
    'UPDATE hasil_verifikasi
     SET koordinat_koreksi_x = ?,
         koordinat_koreksi_y = ?,
         dikoreksi_pada = NOW(),
         status_koordinat = "Dalam Peta PS"
     WHERE usulan_id = ?'
);
$upd->execute([$lng, $lat, $usulanId]);

if ($upd->rowCount() === 0) {
    // Mungkin belum ada record hasil_verifikasi, buat dulu
    // (seharusnya sudah ada dari proses verifikasi normal)
    echo json_encode(['ok' => false, 'msg' => 'Record hasil verifikasi tidak ditemukan. Jalankan Uji Ulang terlebih dahulu.']);
    exit;
}

// Sinkronkan ringkasan versi + laporan agar angka tidak basi.
// (Sebelumnya koreksi hanya update satu baris hasil sehingga
// Buku Register, histori versi, dan laporan tetap tampil angka lama.)
try {
    $hAgg = $pdo->prepare('SELECT COUNT(*) total, SUM(status_sk="Sesuai SK PS") sesuai, SUM(status_sk!="Sesuai SK PS") tidak, SUM(status_koordinat="Dalam Peta PS") dalam, SUM(status_koordinat!="Dalam Peta PS") luar FROM hasil_verifikasi WHERE kth_id = ? AND versi_ke = ?');
    $hAgg->execute([$kthId, $versiKe]);
    $agg = $hAgg->fetch() ?: [];
    $qLuas = $pdo->prepare('SELECT COALESCE(SUM(luas_lahan),0) total_luas FROM usulan_pupuk WHERE kth_id = ? AND versi_ke = ?');
    $qLuas->execute([$kthId, $versiKe]);
    $totalLuas = (float)$qLuas->fetchColumn();
    $rekom = ((int)($agg['tidak'] ?? 0) === 0 && (int)($agg['luar'] ?? 0) === 0) ? 'Dapat Ditindaklanjuti' : 'Perlu Revisi';
    $pdo->prepare('UPDATE kth_versi_usulan SET total_petani=?, total_luas=?, jumlah_sesuai_sk=?, jumlah_tidak_sesuai_sk=?, jumlah_dalam_peta=?, jumlah_luar_peta=?, rekomendasi=? WHERE kth_id=? AND versi_ke=?')
        ->execute([(int)($agg['total'] ?? 0), $totalLuas, (int)($agg['sesuai'] ?? 0), (int)($agg['tidak'] ?? 0), (int)($agg['dalam'] ?? 0), (int)($agg['luar'] ?? 0), $rekom, $kthId, $versiKe]);
    $stLap = $pdo->prepare('SELECT id FROM laporan WHERE kth_id = ? ORDER BY id DESC LIMIT 1');
    $stLap->execute([$kthId]);
    if ($idLap = $stLap->fetchColumn()) {
        $pdo->prepare('UPDATE laporan SET total_petani=?, jumlah_sesuai_sk=?, jumlah_tidak_sesuai_sk=?, jumlah_dalam_peta=?, jumlah_luar_peta=?, rekomendasi=? WHERE id=?')
            ->execute([(int)($agg['total'] ?? 0), (int)($agg['sesuai'] ?? 0), (int)($agg['tidak'] ?? 0), (int)($agg['dalam'] ?? 0), (int)($agg['luar'] ?? 0), $rekom, (int)$idLap]);
    }
} catch (Throwable $eSync) { /* sinkronisasi best-effort, koreksi tetap tersimpan */ }

echo json_encode([
    'ok'  => true,
    'msg' => 'Koreksi koordinat berhasil disimpan. Status diperbarui ke "Dalam Peta PS".',
    'lat' => $lat,
    'lng' => $lng,
]);
