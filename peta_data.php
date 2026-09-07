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
if (!$kthId) { http_response_code(400); echo json_encode(['error'=>'kth_id diperlukan']); exit; }

$pdo = db();

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
            u.koordinat_x, u.koordinat_y,
            h.status_sk, h.status_koordinat, h.catatan, h.kemiripan_nama, h.nama_mirip_sk
     FROM usulan_pupuk u
     LEFT JOIN hasil_verifikasi h ON h.usulan_id = u.id
     WHERE u.kth_id = ?
     ORDER BY COALESCE(u.no_urut, u.id)'
);
$q->execute([$kthId]);
$rows = $q->fetchAll(PDO::FETCH_ASSOC);

$features = [];
foreach ($rows as $r) {
    $lng = $r['koordinat_x'] !== null ? (float)$r['koordinat_x'] : null;
    $lat = $r['koordinat_y'] !== null ? (float)$r['koordinat_y'] : null;

    $statusSK    = (string)($r['status_sk']         ?? 'Belum Sesuai SK PS');
    $statusKoord = (string)($r['status_koordinat']  ?? 'Luar Peta PS');
    $sesuaiSK    = $statusSK   === 'Sesuai SK PS';
    $dalamPeta   = $statusKoord === 'Dalam Peta PS';

    if ($sesuaiSK && $dalamPeta)        { $warna = 'hijau';  $ikon = 'ok'; }
    elseif (!$sesuaiSK && !$dalamPeta)  { $warna = 'merah';  $ikon = 'dua'; }
    elseif (!$sesuaiSK)                 { $warna = 'merah';  $ikon = 'sk'; }
    else                                { $warna = 'oranye'; $ikon = 'koord'; }

    $features[] = [
        'type' => 'Feature',
        'geometry' => ($lng !== null && $lat !== null)
            ? ['type' => 'Point', 'coordinates' => [$lng, $lat]]
            : null,
        'properties' => [
            'id'           => (int)$r['id'],
            'no'           => $r['no_urut'],
            'nama'         => $r['nama'],
            'nik'          => $r['nik'],
            'desa'         => $r['desa'],
            'kecamatan'    => $r['kecamatan'],
            'status_sk'    => $statusSK,
            'status_koord' => $statusKoord,
            'catatan'      => $r['catatan'],
            'nama_mirip'   => $r['nama_mirip_sk'],
            'kemiripan'    => $r['kemiripan_nama'],
            'warna'        => $warna,
            'ikon'         => $ikon,
            'no_koordinat' => ($lng === null || $lat === null),
        ],
    ];
}

echo json_encode([
    'polygon' => $polygonGeoJSON,
    'titik'   => [
        'type'     => 'FeatureCollection',
        'features' => $features,
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
