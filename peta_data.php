<?php
/**
 * peta_data.php — GeoJSON endpoint untuk peta interaktif hasil verifikasi.
 * Output: { polygon: GeoJSON|null, titik: GeoJSON FeatureCollection }
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/parse_shp.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$kthId = (int)($_GET['kth_id'] ?? 0);
$vParam = isset($_GET['v']) ? (int)$_GET['v'] : null;
if (!$kthId) { http_response_code(400); echo json_encode(['error'=>'kth_id diperlukan']); exit; }

$pdo = db();

$versiAktif = 1;
if ($vParam && $vParam > 0) {
    $versiAktif = $vParam;
} else {
    $stV = $pdo->prepare('SELECT versi_aktif FROM kth WHERE id = ?');
    $stV->execute([$kthId]);
    $versiAktif = (int)($stV->fetchColumn() ?: 1);
}

// ─── 1. Polygon PS ──────────────────────────────────────────────────────────
$polyRow = $pdo->prepare('SELECT geometry_json FROM poligon_ps WHERE kth_id = ? ORDER BY id DESC LIMIT 1');
$polyRow->execute([$kthId]);
$pr = $polyRow->fetch();

$polygonGeoJSON = null;
if ($pr) {
    $rings = rings_dari_geometry_json((string)$pr['geometry_json']);
    if ($rings) {
        $coords = array_map(fn($r) => [$r], $rings);
        $polygonGeoJSON = [
            'type' => 'Feature',
            'properties' => ['label' => 'Areal PS'],
            'geometry' => [
                'type'        => 'MultiPolygon',
                'coordinates' => $coords,
            ],
        ];
    }
}

// ─── 2. Titik petani + status verifikasi ────────────────────────────────────
$q = $pdo->prepare(
    'SELECT u.id, u.no_urut, u.nama, u.nik, u.desa, u.kecamatan,
            u.koordinat_x_raw, u.koordinat_y_raw, u.koordinat_x, u.koordinat_y,
            h.status_sk, h.status_koordinat, h.catatan, h.kemiripan_nama, h.nama_mirip_sk,
            h.koordinat_koreksi_x, h.koordinat_koreksi_y
     FROM usulan_pupuk u
     LEFT JOIN hasil_verifikasi h ON (h.usulan_id = u.id AND h.versi_ke = u.versi_ke)
     WHERE u.kth_id = ? AND u.versi_ke = ?
     ORDER BY COALESCE(u.no_urut, u.id)'
);
$q->execute([$kthId, $versiAktif]);
$rows = $q->fetchAll(PDO::FETCH_ASSOC);

$titikByUsulan = ambil_titik_usulan($pdo, $kthId, $versiAktif);

$features = [];
foreach ($rows as $r) {
    $usulanId = (int)$r['id'];
    $statusSK    = (string)($r['status_sk']         ?? 'Belum Sesuai SK PS');
    $statusKoord = (string)($r['status_koordinat']  ?? 'Luar Peta PS');
    $sesuaiSK    = $statusSK   === 'Sesuai SK PS';

    $korX = $r['koordinat_koreksi_x'] !== null ? (float)$r['koordinat_koreksi_x'] : null;
    $korY = $r['koordinat_koreksi_y'] !== null ? (float)$r['koordinat_koreksi_y'] : null;

    $titikPetani = $titikByUsulan[$usulanId] ?? [];
    if (empty($titikPetani)) {
        $titikPetani = [[
            'id' => null,
            'urutan' => 1,
            'x_raw' => $r['koordinat_x_raw'] ?? null,
            'y_raw' => $r['koordinat_y_raw'] ?? null,
            'x' => $r['koordinat_x'],
            'y' => $r['koordinat_y'],
            'status_koordinat' => $statusKoord,
        ]];
    }
    $jumlahTitik = count($titikPetani);
    $adaTitikValid = false;

    foreach ($titikPetani as $t) {
        $urutan = (int)($t['urutan'] ?? 1);
        $utama = $urutan === 1;
        $lng = $t['x'] !== null ? (float)$t['x'] : null;
        $lat = $t['y'] !== null ? (float)$t['y'] : null;
        if ($utama && $korX !== null && $korY !== null) {
            $lng = $korX;
            $lat = $korY;
        }
        if ($lng === null || $lat === null) continue;

        $adaTitikValid = true;
        $statusTitik = (string)($t['status_koordinat'] ?? '');
        if ($statusTitik === '') $statusTitik = $utama ? $statusKoord : 'Luar Peta PS';
        $dalamTitik = $statusTitik === 'Dalam Peta PS';

        if ($sesuaiSK && $dalamTitik)        { $warna = 'hijau';  $ikon = 'ok'; }
        elseif (!$sesuaiSK && !$dalamTitik)  { $warna = 'merah';  $ikon = 'dua'; }
        elseif (!$sesuaiSK)                 { $warna = 'merah';  $ikon = 'sk'; }
        else                                { $warna = 'oranye'; $ikon = 'koord'; }

        $features[] = [
            'type' => 'Feature',
            'geometry' => ['type' => 'Point', 'coordinates' => [$lng, $lat]],
            'properties' => [
                'id'             => $usulanId,
                'titik_id'       => isset($t['id']) ? (int)$t['id'] : null,
                'urutan'         => $urutan,
                'utama'          => $utama,
                'jumlah_titik'   => $jumlahTitik,
                'no'             => $r['no_urut'],
                'nama'           => $r['nama'],
                'nik'            => $r['nik'],
                'desa'           => $r['desa'],
                'kecamatan'      => $r['kecamatan'],
                'status_sk'      => $statusSK,
                'status_koord'   => $statusTitik,
                'status_petani'  => $statusKoord,
                'catatan'        => $r['catatan'],
                'nama_mirip'     => $r['nama_mirip_sk'],
                'kemiripan'      => $r['kemiripan_nama'],
                'warna'          => $warna,
                'ikon'           => $ikon,
                'no_koordinat'   => false,
            ],
        ];
    }

    if (!$adaTitikValid) {
        $features[] = [
            'type' => 'Feature',
            'geometry' => null,
            'properties' => [
                'id'             => $usulanId,
                'titik_id'       => null,
                'urutan'         => 1,
                'utama'          => true,
                'jumlah_titik'   => $jumlahTitik,
                'no'             => $r['no_urut'],
                'nama'           => $r['nama'],
                'nik'            => $r['nik'],
                'desa'           => $r['desa'],
                'kecamatan'      => $r['kecamatan'],
                'status_sk'      => $statusSK,
                'status_koord'   => $statusKoord,
                'status_petani'  => $statusKoord,
                'catatan'        => $r['catatan'],
                'nama_mirip'     => $r['nama_mirip_sk'],
                'kemiripan'      => $r['kemiripan_nama'],
                'warna'          => 'merah',
                'ikon'           => 'dua',
                'no_koordinat'   => true,
            ],
        ];
    }
}

echo json_encode([
    'polygon' => $polygonGeoJSON,
    'titik'   => [
        'type'     => 'FeatureCollection',
        'features' => $features,
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
