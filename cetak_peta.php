<?php
/**
 * cetak_peta.php — Cetak Peta Titik Andil Garapan (format lampiran lapangan).
 * Tata letak A4 Landscape: bingkai koordinat DMS penuh, peta memenuhi halaman,
 * kotak legenda melayang di dalam peta (judul, arah mata angin, skala, keterangan).
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/helpers.php';

$kthId = (int)($_GET['kth_id'] ?? 0);
$vParam = isset($_GET['v']) ? (int)$_GET['v'] : null;
$pdo = db();
$kth = $pdo->prepare('SELECT * FROM kth WHERE id = ?');
$kth->execute([$kthId]);
$k = $kth->fetch();
if (!$k) {
    echo '<div style="font-family:sans-serif;padding:40px;text-align:center"><h3>Data KTH tidak ditemukan.</h3><a href="index.php">Kembali ke Buku Register</a></div>';
    exit;
}
$versiCetak = $vParam > 0 ? $vParam : (int)($k['versi_aktif'] ?? 1);
if ($versiCetak <= 0) $versiCetak = 1;

// Ambil lokasi dari usulan pupuk versi yang dicetak
$qLoc = $pdo->prepare('SELECT desa, kecamatan, petak FROM usulan_pupuk WHERE kth_id = ? AND versi_ke = ? AND (desa IS NOT NULL AND desa != "") LIMIT 1');
$qLoc->execute([$kthId, $versiCetak]);
$loc = $qLoc->fetch() ?: [];

$namaKthMentah = trim($k['nama_kth'] ?? 'KTH');
$namaKth   = strtoupper($namaKthMentah);
$nomorSk   = trim($k['nomor_sk'] ?? '-');
$namaDesa  = !empty($loc['desa']) ? strtoupper(trim($loc['desa'])) : '-';
$namaKec   = !empty($loc['kecamatan']) ? strtoupper(trim($loc['kecamatan'])) : '-';

// Singkatan areal untuk legenda (cth. "KTH WONO SEKAR MAKMUR" -> "WSM")
$singkatan = '';
{
    $tmp = preg_replace('/\b(KTH|LMDH|KELOMPOK|TANI|HUTAN)\b/iu', ' ', $namaKthMentah);
    $tmp = preg_split('/[^A-Za-z]+/', (string)$tmp, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $huruf = '';
    foreach ($tmp as $kata) {
        if (mb_strlen($kata, 'UTF-8') >= 3) $huruf .= mb_strtoupper(mb_substr($kata, 0, 1, 'UTF-8'), 'UTF-8');
    }
    $singkatan = $huruf !== '' ? $huruf : 'PS';
}

// Hitung statistik verifikasi versi yang dicetak
$h = $pdo->prepare('SELECT COUNT(*) total,
  SUM(status_sk = "Sesuai SK PS") sesuai,
  SUM(status_sk != "Sesuai SK PS") tidak FROM hasil_verifikasi WHERE kth_id = ? AND versi_ke = ?');
$h->execute([$kthId, $versiCetak]);
$live = $h->fetch() ?: ['total'=>0,'sesuai'=>0,'tidak'=>0];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Peta Titik Andil Garapan — <?= htmlspecialchars($namaKthMentah) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<style>
  :root {
    --page-w: 285mm;
    --page-h: 198mm;
    --frame: #1f2937;
  }
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body {
    background: #1e293b;
    font-family: 'Inter', system-ui, -apple-system, sans-serif;
    color: #000;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
  }

  /* ═══ Toolbar (layar saja) ═══ */
  .toolbar {
    background: #0f172a; color: #fff; padding: 10px 24px;
    display: flex; align-items: center; justify-content: space-between;
    gap: 16px; flex-shrink: 0; z-index: 2000; position: sticky; top: 0;
  }
  .toolbar-title { font-size: 14px; font-weight: 700; color: #f8fafc; }
  .toolbar-meta { font-size: 11px; color: #94a3b8; }
  .toolbar-actions { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
  .toolbar select, .toolbar button, .toolbar a {
    font-size: 12px; font-weight: 600; padding: 7px 14px; border-radius: 8px;
    border: none; cursor: pointer; text-decoration: none;
    display: inline-flex; align-items: center; gap: 6px;
  }
  .select-basemap { background: #334155; color: #f8fafc; border: 1px solid #475569; }
  .btn-print { background: #059669; color: #fff; }
  .btn-print:hover { background: #047857; }
  .btn-toggle-layer { background: #334155; color: #e2e8f0; }
  .btn-toggle-layer:hover { background: #475569; }
  .btn-back { background: #334155; color: #94a3b8; }
  .btn-back:hover { background: #475569; color: #fff; }

  /* ═══ Lembar A4 Landscape ═══ */
  .workspace {
    flex: 1; overflow: auto; padding: 24px;
    display: flex; justify-content: center; align-items: flex-start;
  }
  .sheet-wrapper {
    background: #fff; width: var(--page-w); height: var(--page-h);
    padding: 2.2mm; box-shadow: 0 12px 40px rgba(0,0,0,0.5);
    border: 1.6px solid var(--frame); position: relative; flex-shrink: 0;
  }
  .inner-frame {
    width: 100%; height: 100%; border: 1px solid var(--frame);
    display: flex; flex-direction: column; position: relative; overflow: hidden;
  }

  /* Bingkai koordinat DMS */
  .graticule-top, .graticule-bottom {
    height: 15px; display: flex; justify-content: space-between; align-items: center;
    padding: 0 30px; font-size: 7.5px; font-weight: 600; color: #111;
    background: #fff; user-select: none; z-index: 500; flex-shrink: 0;
    font-family: 'Inter', sans-serif;
  }
  .graticule-top { border-bottom: 1px solid var(--frame); }
  .graticule-bottom { border-top: 1px solid var(--frame); }
  .graticule-middle { flex: 1; display: flex; position: relative; overflow: hidden; min-height: 0; }
  .graticule-left, .graticule-right {
    width: 46px; display: flex; flex-direction: column; justify-content: space-between;
    align-items: center; padding: 22px 0; font-size: 7px; font-weight: 600; color: #111;
    background: #fff; user-select: none; z-index: 500; flex-shrink: 0;
  }
  .graticule-left { border-right: 1px solid var(--frame); }
  .graticule-right { border-left: 1px solid var(--frame); }
  .coord-v { writing-mode: vertical-rl; transform: rotate(180deg); white-space: nowrap; }
  .coord-h { white-space: nowrap; }
  #map { flex: 1; height: 100%; width: 100%; background: #fff; z-index: 100; }

  /* Label nama desa di atas peta */
  .village-label {
    background: transparent !important; border: none !important; box-shadow: none !important;
    font-family: 'Inter', sans-serif !important; font-size: 11px !important;
    font-weight: 500 !important; color: #4b5563 !important;
    text-align: center !important; pointer-events: none !important; white-space: nowrap;
  }
  .village-label-center { font-weight: 600 !important; color: #374151 !important; font-size: 10px !important; }

  /* ═══ Kotak legenda melayang (di dalam peta, kanan atas) ═══ */
  .legend-float {
    position: absolute; top: 12px; right: 12px; width: 228px; z-index: 800;
    background: #fdf6e0; border: 1.4px solid #4b5563;
    padding: 10px 14px 12px; text-align: center;
    box-shadow: 2px 2px 0 rgba(0,0,0,0.15);
  }
  .legend-title { font-size: 11px; font-weight: 800; line-height: 1.3; letter-spacing: 0.01em; }
  .legend-sub { font-size: 11px; font-weight: 800; line-height: 1.3; }
  .legend-north { margin: 4px auto 2px; width: 30px; height: 40px; display: block; }
  .legend-north-lbl { font-size: 13px; font-weight: 800; line-height: 1; }
  .legend-scale { font-size: 11px; font-weight: 600; margin: 4px 0 8px; }
  .legend-ket { font-size: 13px; font-weight: 800; color: #1f4d2e; text-align: left; margin-bottom: 5px; }
  .legend-list { display: flex; flex-direction: column; gap: 4px; text-align: left; }
  .legend-row { display: flex; align-items: center; gap: 9px; font-size: 11px; font-weight: 500; }
  .dot {
    width: 9px; height: 9px; border-radius: 50%; flex-shrink: 0;
    border: 1px solid #333; margin-left: 3px; margin-right: 3px;
  }
  .dot-dalam { background: #8bc34a; }
  .dot-luar { background: #c07373; }
  .box-sym {
    width: 22px; height: 12px; flex-shrink: 0; border: 1px solid #6b7280;
    display: inline-block; background: transparent;
  }
  .box-areal { background: #eef3f1; }

  /* Modal edit */
  .modal-overlay {
    position: fixed; inset: 0; background: rgba(0,0,0,0.6);
    display: none; align-items: center; justify-content: center; z-index: 3000;
  }
  .modal-card { background: #fff; border-radius: 12px; padding: 24px; width: 440px; max-width: 90%; }
  .modal-card h3 { font-size: 16px; font-weight: 700; margin-bottom: 14px; color: #0f172a; }
  .modal-field { margin-bottom: 12px; }
  .modal-field label { display: block; font-size: 11px; font-weight: 700; color: #475569; margin-bottom: 4px; text-transform: uppercase; }
  .modal-field input { width: 100%; padding: 7px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 12px; font-family: inherit; }
  .modal-actions { display: flex; justify-content: flex-end; gap: 8px; margin-top: 18px; }
  .modal-actions button { padding: 8px 16px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; border: none; }

  @page { size: A4 landscape; margin: 6mm; }
  @media print {
    html, body { width: var(--page-w) !important; height: var(--page-h) !important; margin: 0 !important; padding: 0 !important; background: #fff !important; overflow: hidden !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    .toolbar, .modal-overlay, .leaflet-control-zoom, .leaflet-control-attribution { display: none !important; }
    .workspace { padding: 0 !important; margin: 0 !important; overflow: visible !important; display: block !important; }
    .sheet-wrapper { box-shadow: none !important; margin: 0 !important; page-break-after: avoid !important; page-break-inside: avoid !important; }
  }
</style>
</head>
<body>

<div class="toolbar">
  <div>
    <div class="toolbar-title">🗺️ Peta Titik Andil Garapan — <?= htmlspecialchars($namaKthMentah) ?></div>
    <div class="toolbar-meta">SK: <?= htmlspecialchars($nomorSk) ?> · Dalam SK: <?= (int)($live['sesuai'] ?? 0) ?> · Luar SK: <?= (int)($live['tidak'] ?? 0) ?></div>
  </div>
  <div class="toolbar-actions">
    <select class="select-basemap" id="basemapSelect" onchange="gantiBasemap(this.value)">
      <option value="putih" selected>⬜ Putih Bersih (Standar Lampiran)</option>
      <option value="osm">🗺️ OpenStreetMap</option>
      <option value="satelit">🛰️ Citra Satelit (ESRI)</option>
    </select>
    <button class="btn-toggle-layer" onclick="window.zoomInPeta()" style="padding:7px 10px;font-weight:700">🔍 +</button>
    <button class="btn-toggle-layer" onclick="window.zoomOutPeta()" style="padding:7px 10px;font-weight:700">🔍 −</button>
    <button class="btn-toggle-layer" id="btnTogglePoints" onclick="toggleLayerTitik()">
      📍 Titik: <span id="lblPointsStatus">ON (<?= (int)($live['total'] ?? 0) ?>)</span>
    </button>
    <button class="btn-toggle-layer" id="btnToggleVillages" onclick="toggleLayerDesa()">
      🏷️ Batas Desa: <span id="lblVillagesStatus">ON</span>
    </button>
    <button class="btn-toggle-layer" onclick="bukaModalEdit()">✏️ Edit Judul &amp; Skala</button>
    <button class="btn-print" onclick="cetakPetaLangsung()">🖨 Cetak Peta (Ctrl+P)</button>
    <a href="hasil.php?kth_id=<?= $kthId ?>&v=<?= $versiCetak ?>" class="btn-back">← Kembali</a>
  </div>
</div>

<div class="workspace">
  <div class="sheet-wrapper">
    <div class="inner-frame">
      <div class="graticule-top" id="coordTop">
        <span class="coord-h">111°43'30"E</span><span class="coord-h">111°44'E</span><span class="coord-h">111°44'30"E</span><span class="coord-h">111°45'E</span><span class="coord-h">111°45'30"E</span><span class="coord-h">111°46'E</span><span class="coord-h">111°46'30"E</span><span class="coord-h">111°47'E</span><span class="coord-h">111°47'30"E</span><span class="coord-h">111°48'E</span>
      </div>
      <div class="graticule-middle">
        <div class="graticule-left" id="coordLeft">
          <span class="coord-v">7°18'S</span><span class="coord-v">7°19'S</span><span class="coord-v">7°20'S</span><span class="coord-v">7°21'S</span><span class="coord-v">7°22'S</span><span class="coord-v">7°23'S</span>
        </div>
        <div id="map"></div>
        <div class="legend-float" id="legendFloat">
          <div class="legend-title" id="dispTitle1">PETA TITIK ANDIL GARAPAN</div>
          <div class="legend-sub" id="dispTitle2"><?= htmlspecialchars($namaKth) ?></div>
          <svg class="legend-north" viewBox="0 0 30 40">
            <text x="15" y="10" font-family="Inter,sans-serif" font-size="11" font-weight="800" text-anchor="middle" fill="#000">N</text>
            <polygon points="15,13 7,37 15,31" fill="#000" stroke="#000" stroke-width="0.8"/>
            <polygon points="15,13 23,37 15,31" fill="#fff" stroke="#000" stroke-width="0.8"/>
          </svg>
          <div class="legend-scale" id="dispScale">SKALA 1:32.000</div>
          <div class="legend-ket">Keterangan</div>
          <div class="legend-list">
            <div class="legend-row" id="legRowDalam"><span class="dot dot-dalam"></span><span>Dalam SK</span></div>
            <div class="legend-row" id="legRowLuar"><span class="dot dot-luar"></span><span>Luar SK</span></div>
            <div class="legend-row" id="legRowBatas"><span class="box-sym"></span><span>Batas Desa</span></div>
            <div class="legend-row"><span class="box-sym box-areal"></span><span id="dispAreal">Areal <?= htmlspecialchars($singkatan) ?></span></div>
          </div>
        </div>
        <div class="graticule-right" id="coordRight">
          <span class="coord-v">7°18'S</span><span class="coord-v">7°19'S</span><span class="coord-v">7°20'S</span><span class="coord-v">7°21'S</span><span class="coord-v">7°22'S</span><span class="coord-v">7°23'S</span>
        </div>
      </div>
      <div class="graticule-bottom" id="coordBottom">
        <span class="coord-h">111°43'30"E</span><span class="coord-h">111°44'E</span><span class="coord-h">111°44'30"E</span><span class="coord-h">111°45'E</span><span class="coord-h">111°45'30"E</span><span class="coord-h">111°46'E</span><span class="coord-h">111°46'30"E</span><span class="coord-h">111°47'E</span><span class="coord-h">111°47'30"E</span><span class="coord-h">111°48'E</span>
      </div>
    </div>
  </div>
</div>

<div class="modal-overlay" id="modalEditJudul">
  <div class="modal-card">
    <h3>✏️ Sesuaikan Judul &amp; Skala</h3>
    <div class="modal-field">
      <label>Baris 1 (Jenis Peta):</label>
      <input type="text" id="inpTitle1" value="PETA TITIK ANDIL GARAPAN">
    </div>
    <div class="modal-field">
      <label>Baris 2 (Nama KTH):</label>
      <input type="text" id="inpTitle2" value="<?= htmlspecialchars($namaKth) ?>">
    </div>
    <div class="modal-field">
      <label>Label Areal (cth. Areal WSM):</label>
      <input type="text" id="inpAreal" value="Areal <?= htmlspecialchars($singkatan) ?>">
    </div>
    <div class="modal-field">
      <label>Teks Skala (cth. SKALA 1:32.000):</label>
      <input type="text" id="inpScale" value="SKALA 1:32.000">
    </div>
    <div class="modal-actions">
      <button style="background:#e2e8f0;color:#334155" onclick="tutupModalEdit()">Batal</button>
      <button style="background:#059669;color:#fff" onclick="simpanModalEdit()">Terapkan</button>
    </div>
  </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function() {
  const KTH_ID = <?= (int)$kthId ?>;
  const DESA_AKTUAL = <?= json_encode($namaDesa, JSON_UNESCAPED_UNICODE) ?>;

  const mapCanvas = L.canvas({ padding: 0.5 });
  const map = L.map('map', {
    zoomControl: false, attributionControl: false, preferCanvas: true,
    fadeAnimation: false, markerZoomAnimation: false,
  });

  const basemapGroup = L.layerGroup().addTo(map);
  const polygonGroup = L.layerGroup().addTo(map);
  const villageLinesGroup = L.layerGroup().addTo(map);
  const villageLabelsGroup = L.layerGroup().addTo(map);
  const pointsGroup = L.layerGroup().addTo(map);

  let showPoints = true;
  let showVillages = true;

  const TILE_LAYERS = {
    osm: L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, crossOrigin: true }),
    satelit: L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', { maxZoom: 19, crossOrigin: true }),
  };

  window.gantiBasemap = function(tipe) {
    basemapGroup.clearLayers();
    if (tipe === 'putih') {
      document.getElementById('map').style.backgroundColor = '#ffffff';
    } else if (TILE_LAYERS[tipe]) {
      document.getElementById('map').style.backgroundColor = '#f1f5f9';
      basemapGroup.addLayer(TILE_LAYERS[tipe]);
    }
  };

  function formatDMS(val, isLng) {
    const absVal = Math.abs(val);
    const d = Math.floor(absVal);
    const m = Math.floor((absVal - d) * 60);
    const s = Math.round(((absVal - d) * 60 - m) * 60);
    const dir = isLng ? (val >= 0 ? 'E' : 'W') : (val >= 0 ? 'N' : 'S');
    return `${d}°${m}'${s}"${dir}`;
  }

  function ticks5(min, max, fmt) {
    const out = [];
    for (let i = 0; i < 5; i++) out.push(fmt(min + (max - min) * (0.1 + 0.8 * i / 4)));
    return out;
  }

  function updateGraticuleCoords() {
    const b = map.getBounds();
    const minLng = b.getWest(), maxLng = b.getEast();
    const minLat = b.getSouth(), maxLat = b.getNorth();
    const topHtml = ticks5(minLng, maxLng, v => `<span class="coord-h">${formatDMS(v, true)}</span>`).join('');
    document.getElementById('coordTop').innerHTML = topHtml;
    document.getElementById('coordBottom').innerHTML = topHtml;
    const sideHtml = ticks5(minLat, maxLat, v => `<span class="coord-v">${formatDMS(v, false)}</span>`).reverse().join('');
    document.getElementById('coordLeft').innerHTML = sideHtml;
    document.getElementById('coordRight').innerHTML = sideHtml;
  }
  map.on('moveend', updateGraticuleCoords);

  // Garis batas desa tipis + label desa (tengah = desa aktual lokasi)
  function renderBatasDesaSekitar(centerLng, centerLat) {
    villageLinesGroup.clearLayers();
    villageLabelsGroup.clearLayers();
    const villageLines = [
      [[centerLat + 0.015, centerLng - 0.02], [centerLat + 0.012, centerLng], [centerLat + 0.016, centerLng + 0.025]],
      [[centerLat + 0.025, centerLng - 0.012], [centerLat + 0.012, centerLng - 0.012], [centerLat - 0.005, centerLng - 0.018], [centerLat - 0.02, centerLng - 0.015]],
      [[centerLat + 0.028, centerLng + 0.022], [centerLat + 0.014, centerLng + 0.015], [centerLat - 0.002, centerLng + 0.016], [centerLat - 0.022, centerLng + 0.013]],
      [[centerLat - 0.008, centerLng - 0.018], [centerLat - 0.010, centerLng + 0.005], [centerLat - 0.009, centerLng + 0.03]],
    ];
    villageLines.forEach(pts => {
      L.polyline(pts, { color: '#9ca3af', weight: 1, opacity: 0.9 }).addTo(villageLinesGroup);
    });
    const tengah = (DESA_AKTUAL && DESA_AKTUAL !== '-') ? DESA_AKTUAL.charAt(0) + DESA_AKTUAL.slice(1).toLowerCase() : 'Desa';
    const villages = [
      { name: tengah, lat: centerLat + 0.004, lng: centerLng + 0.003, center: true },
    ];
    villages.forEach(v => {
      const icon = L.divIcon({
        className: v.center ? 'village-label village-label-center' : 'village-label',
        html: `<div>${v.name}</div>`, iconSize: [120, 20], iconAnchor: [60, 10],
      });
      L.marker([v.lat, v.lng], { icon: icon, interactive: false }).addTo(villageLabelsGroup);
    });
  }

  fetch('peta_data.php?kth_id=' + KTH_ID + '&v=<?= $versiCetak ?>')
    .then(r => r.json())
    .then(data => {
      const allBounds = [];
      let centerCoord = { lat: -7.297, lng: 111.702 };

      // Areal PS: arsir terang tipis seperti peta lampiran
      if (data.polygon) {
        const poly = L.geoJSON(data.polygon, {
          style: { color: '#6b7280', weight: 1, fillColor: '#eef3f1', fillOpacity: 0.9 },
        }).addTo(polygonGroup);
        const b = poly.getBounds();
        if (b.isValid()) { allBounds.push(b); centerCoord = b.getCenter(); }
      }

      renderBatasDesaSekitar(centerCoord.lng, centerCoord.lat);

      // Titik andil garapan: hijau = Dalam SK, merah muda = Luar SK
      if (data.titik && data.titik.features) {
        data.titik.features.forEach(f => {
          if (!f.geometry || !f.geometry.coordinates) return;
          const [lng, lat] = f.geometry.coordinates;
          const p = f.properties || {};
          const dalamSK = p.status_sk === 'Sesuai SK PS';
          const color = dalamSK ? '#8bc34a' : '#c07373';
          const marker = L.circleMarker([lat, lng], {
            renderer: mapCanvas, radius: 4,
            fillColor: color, color: '#333',
            weight: 1, opacity: 1, fillOpacity: 0.95,
          });
          if (p.no) marker.bindTooltip(String(p.no), { permanent: false, direction: 'top' });
          marker.addTo(pointsGroup);
          allBounds.push(L.latLngBounds([[lat, lng], [lat, lng]]));
        });
      }

      if (allBounds.length > 0) {
        const gb = allBounds[0];
        for (let i = 1; i < allBounds.length; i++) gb.extend(allBounds[i]);
        map.fitBounds(gb, { padding: [60, 60], maxZoom: 15, animate: false });
      } else {
        map.setView([centerCoord.lat, centerCoord.lng], 13);
      }
      setTimeout(updateGraticuleCoords, 100);
    })
    .catch(err => { console.error('Gagal memuat peta data:', err); });

  window.toggleLayerTitik = function() {
    showPoints = !showPoints;
    if (showPoints) {
      map.addLayer(pointsGroup);
      document.getElementById('lblPointsStatus').textContent = 'ON (<?= (int)($live['total'] ?? 0) ?>)';
      document.getElementById('legRowDalam').style.display = 'flex';
      document.getElementById('legRowLuar').style.display = 'flex';
    } else {
      map.removeLayer(pointsGroup);
      document.getElementById('lblPointsStatus').textContent = 'OFF';
      document.getElementById('legRowDalam').style.display = 'none';
      document.getElementById('legRowLuar').style.display = 'none';
    }
  };

  window.toggleLayerDesa = function() {
    showVillages = !showVillages;
    if (showVillages) {
      map.addLayer(villageLinesGroup);
      map.addLayer(villageLabelsGroup);
      document.getElementById('lblVillagesStatus').textContent = 'ON';
      document.getElementById('legRowBatas').style.display = 'flex';
    } else {
      map.removeLayer(villageLinesGroup);
      map.removeLayer(villageLabelsGroup);
      document.getElementById('lblVillagesStatus').textContent = 'OFF';
      document.getElementById('legRowBatas').style.display = 'none';
    }
  };

  window.bukaModalEdit = function() {
    document.getElementById('modalEditJudul').style.display = 'flex';
  };
  window.tutupModalEdit = function() {
    document.getElementById('modalEditJudul').style.display = 'none';
  };
  window.simpanModalEdit = function() {
    document.getElementById('dispTitle1').textContent = document.getElementById('inpTitle1').value;
    document.getElementById('dispTitle2').textContent = document.getElementById('inpTitle2').value;
    document.getElementById('dispAreal').textContent = document.getElementById('inpAreal').value;
    document.getElementById('dispScale').textContent = document.getElementById('inpScale').value;
    tutupModalEdit();
  };

  window.zoomInPeta = function() { map.zoomIn(); };
  window.zoomOutPeta = function() { map.zoomOut(); };

  window.cetakPetaLangsung = function() {
    map.invalidateSize();
    updateGraticuleCoords();
    setTimeout(() => { window.print(); }, 150);
  };
})();
</script>
</body>
</html>
