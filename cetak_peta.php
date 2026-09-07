<?php
/**
 * cetak_peta.php — Halaman cetak layout peta standar kartografi GIS (format BPKH / Kehutanan).
 * Menggunakan tata letak presisi A4 Landscape, bingkai koordinat derajat-menit-detik,
 * kop judul resmi, skala grafis, legenda kartografi, dan inset peta situasi Bojonegoro.
 * Dioptimasi agar print preview instan tanpa lag.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/helpers.php';

$kthId = (int)($_GET['kth_id'] ?? 0);
$pdo = db();
$kth = $pdo->prepare('SELECT * FROM kth WHERE id = ?');
$kth->execute([$kthId]);
$k = $kth->fetch();
if (!$k) {
    echo '<div style="font-family:sans-serif;padding:40px;text-align:center"><h3>Data KTH tidak ditemukan.</h3><a href="index.php">Kembali ke Beranda</a></div>';
    exit;
}

// Ambil lokasi dari usulan pupuk (Desa, Kecamatan, Petak)
$qLoc = $pdo->prepare('SELECT desa, kecamatan, petak FROM usulan_pupuk WHERE kth_id = ? AND (desa IS NOT NULL AND desa != "") LIMIT 1');
$qLoc->execute([$kthId]);
$loc = $qLoc->fetch() ?: [];

$namaKth   = strtoupper(trim($k['nama_kth'] ?? 'KTH'));
$nomorSk   = trim($k['nomor_sk'] ?? '-');
$tahunSk   = trim((string)($k['tahun_usulan'] ?? date('Y')));
$namaDesa  = !empty($loc['desa']) ? strtoupper(trim($loc['desa'])) : 'BONDOL';
$namaKec   = !empty($loc['kecamatan']) ? strtoupper(trim($loc['kecamatan'])) : 'NGAMBON';
$petakNo   = !empty($loc['petak']) ? trim($loc['petak']) : '18';
$namaKab   = 'BOJONEGORO';

// Hitung statistik verifikasi
$h = $pdo->prepare('SELECT COUNT(*) total,
  SUM(status_sk = "Sesuai SK PS") sesuai,
  SUM(status_sk != "Sesuai SK PS") tidak,
  SUM(status_koordinat = "Dalam Peta PS") dalam,
  SUM(status_koordinat != "Dalam Peta PS") luar FROM hasil_verifikasi WHERE kth_id = ?');
$h->execute([$kthId]);
$live = $h->fetch() ?: ['total'=>0,'sesuai'=>0,'tidak'=>0,'dalam'=>0,'luar'=>0];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Cetak Peta — <?= htmlspecialchars($k['nama_kth']) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Roboto+Mono:wght@500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<style>
  :root {
    --page-w: 285mm;
    --page-h: 198mm;
    --border-color: #000000;
  }
  * {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
  }
  body {
    background: #1e293b;
    font-family: 'Inter', system-ui, -apple-system, sans-serif;
    color: #000;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
  }

  /* ═══ Top Toolbar (Screen Only) ═══ */
  .toolbar {
    background: #0f172a;
    color: #fff;
    padding: 10px 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    flex-shrink: 0;
    box-shadow: 0 4px 12px rgba(0,0,0,0.3);
    z-index: 2000;
    position: sticky;
    top: 0;
  }
  .toolbar-left {
    display: flex;
    align-items: center;
    gap: 12px;
  }
  .toolbar-title {
    font-size: 14px;
    font-weight: 700;
    letter-spacing: 0.02em;
    color: #f8fafc;
  }
  .toolbar-meta {
    font-size: 11px;
    color: #94a3b8;
  }
  .toolbar-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
  }
  .toolbar select, .toolbar button, .toolbar a {
    font-size: 12px;
    font-weight: 600;
    padding: 7px 14px;
    border-radius: 8px;
    border: none;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.15s ease;
  }
  .select-basemap {
    background: #334155;
    color: #f8fafc;
    border: 1px solid #475569;
    padding: 6px 12px;
  }
  .select-basemap:focus {
    outline: 2px solid #10b981;
  }
  .btn-print {
    background: #059669;
    color: #fff;
  }
  .btn-print:hover {
    background: #047857;
  }
  .btn-toggle-layer {
    background: #334155;
    color: #e2e8f0;
  }
  .btn-toggle-layer:hover {
    background: #475569;
  }
  .btn-back {
    background: #334155;
    color: #94a3b8;
  }
  .btn-back:hover {
    background: #475569;
    color: #fff;
  }

  /* ═══ Workspace & Sheet Container ═══ */
  .workspace {
    flex: 1;
    overflow: auto;
    padding: 24px;
    display: flex;
    justify-content: center;
    align-items: flex-start;
  }
  .sheet-wrapper {
    background: #ffffff;
    width: var(--page-w);
    height: var(--page-h);
    padding: 3.5mm;
    box-shadow: 0 12px 40px rgba(0,0,0,0.5);
    border: 2px solid #000;
    position: relative;
    flex-shrink: 0;
  }

  /* Bingkai Ganda (Inner Neatline Frame) */
  .inner-frame {
    width: 100%;
    height: 100%;
    border: 1.2px solid #000;
    display: flex;
    position: relative;
    overflow: hidden;
  }

  /* ═══ Bagian Kiri: Peta Utama (~73% Lebar) ═══ */
  .main-map-section {
    flex: 1;
    height: 100%;
    border-right: 1.5px solid #000;
    display: flex;
    flex-direction: column;
    position: relative;
    background: #fff;
  }

  /* Bingkai Grid Koordinat (Graticule Neatline) */
  .graticule-container {
    position: relative;
    flex: 1;
    display: flex;
    flex-direction: column;
    overflow: hidden;
  }
  .graticule-top, .graticule-bottom {
    height: 16px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0 28px;
    font-size: 8.5px;
    font-family: 'Roboto Mono', monospace;
    font-weight: 700;
    color: #000;
    background: #fff;
    user-select: none;
    z-index: 500;
  }
  .graticule-top {
    border-bottom: 1px solid #000;
  }
  .graticule-bottom {
    border-top: 1px solid #000;
  }
  .graticule-middle {
    flex: 1;
    display: flex;
    position: relative;
    overflow: hidden;
  }
  .graticule-left, .graticule-right {
    width: 20px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    align-items: center;
    padding: 24px 0;
    font-size: 8px;
    font-family: 'Roboto Mono', monospace;
    font-weight: 700;
    color: #000;
    background: #fff;
    user-select: none;
    z-index: 500;
  }
  .graticule-left {
    border-right: 1px solid #000;
  }
  .graticule-right {
    border-left: 1px solid #000;
  }
  .coord-v {
    writing-mode: vertical-rl;
    transform: rotate(180deg);
    white-space: nowrap;
  }
  .coord-h {
    white-space: nowrap;
  }

  /* Wadah Peta Leaflet */
  #map {
    flex: 1;
    height: 100%;
    width: 100%;
    background: #ffffff;
    z-index: 100;
  }

  /* Poligon label overlay */
  .poly-name-label {
    background: transparent !important;
    border: none !important;
    box-shadow: none !important;
    font-family: 'Inter', sans-serif !important;
    font-size: 10px !important;
    font-weight: 800 !important;
    color: #000000 !important;
    text-shadow: -1px -1px 0 #fff, 1px -1px 0 #fff, -1px 1px 0 #fff, 1px 1px 0 #fff;
    text-align: center !important;
    pointer-events: none !important;
  }
  .village-label {
    background: transparent !important;
    border: none !important;
    box-shadow: none !important;
    font-family: 'Inter', sans-serif !important;
    font-size: 11px !important;
    font-weight: 600 !important;
    color: #1e293b !important;
    text-align: center !important;
    letter-spacing: 0.05em;
    pointer-events: none !important;
  }

  /* ═══ Bagian Kanan: Kolom Kartografi (~27% Lebar) ═══ */
  .carto-sidebar {
    width: 76mm;
    height: 100%;
    display: flex;
    flex-direction: column;
    background: #ffffff;
    overflow: hidden;
  }

  /* Kompartemen Kartografi dengan Garis Pembatas Hitam Tebal */
  .carto-box {
    border-bottom: 1.5px solid #000;
    padding: 6px 10px;
    position: relative;
    flex-shrink: 0;
  }

  /* ── 1. Kop Judul Peta ── */
  .box-title {
    text-align: center;
    padding: 8px 6px;
  }
  .title-line-1 {
    font-size: 11.5px;
    font-weight: 800;
    line-height: 1.25;
    letter-spacing: 0.02em;
  }
  .title-line-2 {
    font-size: 10px;
    font-weight: 700;
    line-height: 1.25;
    margin-top: 1px;
  }
  .title-line-3 {
    font-size: 9.5px;
    font-weight: 700;
    line-height: 1.25;
    margin-top: 2px;
  }
  .title-line-4 {
    font-size: 9.5px;
    font-weight: 700;
    line-height: 1.25;
  }

  /* ── 2. Skala & Arah Mata Angin ── */
  .box-scale {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 6px 8px;
  }
  .scale-ratio {
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 0.04em;
    margin-bottom: 4px;
  }
  .north-arrow-svg {
    width: 28px;
    height: 42px;
    margin-bottom: 4px;
  }
  .graphic-scale-wrap {
    display: flex;
    flex-direction: column;
    align-items: center;
    width: 100%;
    max-width: 200px;
  }
  .scale-numbers {
    display: flex;
    justify-content: space-between;
    width: 100%;
    font-size: 7.5px;
    font-family: 'Roboto Mono', monospace;
    font-weight: 700;
    margin-bottom: 1px;
    padding: 0 1px;
  }
  .scale-bar-svg {
    width: 100%;
    height: 6px;
  }
  .scale-unit {
    align-self: flex-end;
    font-size: 7.5px;
    font-family: 'Roboto Mono', monospace;
    font-weight: 700;
    margin-top: 1px;
  }

  /* ── 3. Keterangan / Legenda ── */
  .box-legend {
    padding: 6px 10px;
  }
  .legend-header {
    font-size: 11px;
    font-weight: 800;
    margin-bottom: 5px;
  }
  .legend-list {
    display: flex;
    flex-direction: column;
    gap: 4.5px;
  }
  .legend-row {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 9.5px;
    font-weight: 600;
    color: #000;
  }
  .symbol-box {
    width: 24px;
    height: 12px;
    border: 1px solid #000;
    flex-shrink: 0;
    display: inline-block;
  }
  .sym-batas-desa {
    background: #fff;
    border: 1.5px dashed #000;
  }
  .sym-khdpk-ps {
    background-image: repeating-linear-gradient(45deg, #ef4444, #ef4444 1.5px, #fff 1.5px, #fff 4px);
    border: 1px solid #dc2626;
  }
  .sym-petak {
    background: #fff;
    border: 1px solid #94a3b8;
  }
  .sym-kps-yellow {
    background: #ffeb3b;
    border: 1.2px solid #000;
  }
  .symbol-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    flex-shrink: 0;
    border: 1.2px solid #000;
    margin-left: 7px;
    margin-right: 7px;
  }
  .dot-green { background: #16a34a; }
  .dot-red { background: #dc2626; }
  .dot-orange { background: #ea580c; }

  /* ── 4. Sumber Peta ── */
  .box-sources {
    padding: 5px 9px;
    font-size: 8px;
    line-height: 1.3;
  }
  .sources-header {
    font-size: 9px;
    font-weight: 800;
    margin-bottom: 3px;
  }
  .sources-list {
    padding-left: 13px;
    margin: 0;
  }
  .sources-list li {
    margin-bottom: 1.5px;
    font-weight: 500;
  }

  /* ── 5. Peta Situasi (Inset Map) ── */
  .box-inset {
    flex: 1;
    min-height: 0;
    border-bottom: none;
    display: flex;
    flex-direction: column;
    padding: 5px 8px;
    background: #fff;
  }
  .inset-header {
    text-align: center;
    font-size: 9.5px;
    font-weight: 800;
    line-height: 1.15;
  }
  .inset-scale {
    text-align: center;
    font-size: 8px;
    font-weight: 700;
    letter-spacing: 0.03em;
    margin-bottom: 3px;
  }
  .inset-map-box {
    flex: 1;
    min-height: 0;
    border: 1px solid #000;
    position: relative;
    overflow: hidden;
    background: #f8fafc;
  }
  .inset-svg {
    width: 100%;
    height: 100%;
    display: block;
  }
  .inset-legend {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-top: 3px;
    font-size: 7.5px;
    font-weight: 700;
    flex-shrink: 0;
  }
  .sym-loc-box {
    width: 13px;
    height: 8px;
    border: 1.5px solid #dc2626;
    background: rgba(220,38,38,0.2);
    flex-shrink: 0;
  }

  /* Modal Edit Teks */
  .modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.6);
    backdrop-filter: blur(4px);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 3000;
  }
  .modal-card {
    background: #fff;
    border-radius: 12px;
    padding: 24px;
    width: 440px;
    max-width: 90%;
    box-shadow: 0 20px 40px rgba(0,0,0,0.4);
  }
  .modal-card h3 {
    font-size: 16px;
    font-weight: 700;
    margin-bottom: 14px;
    color: #0f172a;
  }
  .modal-field {
    margin-bottom: 12px;
  }
  .modal-field label {
    display: block;
    font-size: 11px;
    font-weight: 700;
    color: #475569;
    margin-bottom: 4px;
    text-transform: uppercase;
  }
  .modal-field input {
    width: 100%;
    padding: 7px 10px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    font-size: 12px;
    font-family: inherit;
  }
  .modal-field input:focus {
    outline: 2px solid #059669;
  }
  .modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    margin-top: 18px;
  }
  .modal-actions button {
    padding: 8px 16px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    border: none;
  }

  /* ═══ Print Stylesheet (Khusus Cetak A4 Landscape) ═══ */
  @page {
    size: A4 landscape;
    margin: 6mm;
  }
  @media print {
    html, body {
      width: var(--page-w) !important;
      height: var(--page-h) !important;
      margin: 0 !important;
      padding: 0 !important;
      background: #ffffff !important;
      overflow: hidden !important;
      -webkit-print-color-adjust: exact !important;
      print-color-adjust: exact !important;
    }
    .toolbar, .modal-overlay, .leaflet-control-zoom, .leaflet-control-attribution {
      display: none !important;
    }
    .workspace {
      padding: 0 !important;
      margin: 0 !important;
      overflow: visible !important;
      display: block !important;
    }
    .sheet-wrapper {
      box-shadow: none !important;
      margin: 0 !important;
      border: 1.8px solid #000 !important;
      page-break-after: avoid !important;
      page-break-inside: avoid !important;
      page-break-before: avoid !important;
    }
    .inner-frame {
      border: 1.2px solid #000 !important;
    }
    .carto-sidebar, .main-map-section, .carto-box {
      border-color: #000 !important;
    }
  }
</style>
</head>
<body>

<!-- Toolbar Atas (Layar Saja) -->
<div class="toolbar">
  <div class="toolbar-left">
    <div>
      <div class="toolbar-title">🗺️ Format Cetak Peta Standar Kartografi Kehutanan</div>
      <div class="toolbar-meta">KTH: <?= htmlspecialchars($k['nama_kth']) ?> · SK: <?= htmlspecialchars($nomorSk) ?> · Petak: <?= htmlspecialchars($petakNo) ?></div>
    </div>
  </div>

  <div class="toolbar-actions">
    <!-- Pilihan Basemap -->
    <select class="select-basemap" id="basemapSelect" onchange="gantiBasemap(this.value)">
      <option value="kartografis" selected>🏛️ Kartografis Bersih (Standar BPKH - Cepat)</option>
      <option value="osm">🗺️ OpenStreetMap</option>
      <option value="satelit">🛰️ Citra Satelit (ESRI)</option>
      <option value="positron">🌫️ CartoDB Positron</option>
    </select>

    <!-- Tombol Zoom Peta -->
    <button class="btn-toggle-layer" onclick="window.zoomInPeta()" title="Perbesar Peta" style="padding:7px 10px;font-weight:700">🔍 +</button>
    <button class="btn-toggle-layer" onclick="window.zoomOutPeta()" title="Perkecil Peta" style="padding:7px 10px;font-weight:700">🔍 −</button>

    <!-- Toggle Layer Titik Petani -->
    <button class="btn-toggle-layer" id="btnTogglePoints" onclick="toggleLayerTitik()">
      📍 Titik Petani: <span id="lblPointsStatus">ON (<?= (int)$live['total'] ?>)</span>
    </button>

    <!-- Toggle Layer Label Desa -->
    <button class="btn-toggle-layer" id="btnToggleVillages" onclick="toggleLayerDesa()">
      🏷️ Label Desa: <span id="lblVillagesStatus">ON</span>
    </button>

    <!-- Sesuaikan Teks Kop -->
    <button class="btn-toggle-layer" onclick="bukaModalEdit()">
      ✏️ Edit Kop Judul
    </button>

    <!-- Tombol Cetak Langsung -->
    <button class="btn-print" onclick="cetakPetaLangsung()">
      <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
      Cetak Peta (Ctrl+P)
    </button>

    <!-- Kembali -->
    <a href="hasil.php?kth_id=<?= $kthId ?>" class="btn-back">
      <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
      Kembali
    </a>
  </div>
</div>

<!-- Workspace Lembar A4 Landscape -->
<div class="workspace">
  <div class="sheet-wrapper" id="printSheet">
    <div class="inner-frame">

      <!-- ══════════════════════════════════════════════════════════════════════ -->
      <!-- KOLOM KIRI: PETA UTAMA DENGAN BINGKAI GRID KOORDINAT D-M-S             -->
      <!-- ══════════════════════════════════════════════════════════════════════ -->
      <div class="main-map-section">
        <div class="graticule-container">
          <!-- Koordinat Bujur Atas -->
          <div class="graticule-top" id="coordTop">
            <span class="coord-h">111°42'0"E</span>
            <span class="coord-h">111°42'30"E</span>
            <span class="coord-h">111°43'0"E</span>
          </div>

          <div class="graticule-middle">
            <!-- Koordinat Lintang Kiri -->
            <div class="graticule-left" id="coordLeft">
              <span class="coord-v">7°16'30"S</span>
              <span class="coord-v">7°17'0"S</span>
              <span class="coord-v">7°17'30"S</span>
            </div>

            <!-- Wadah Peta Utama -->
            <div id="map"></div>

            <!-- Koordinat Lintang Kanan -->
            <div class="graticule-right" id="coordRight">
              <span class="coord-v">7°16'30"S</span>
              <span class="coord-v">7°17'0"S</span>
              <span class="coord-v">7°17'30"S</span>
            </div>
          </div>

          <!-- Koordinat Bujur Bawah -->
          <div class="graticule-bottom" id="coordBottom">
            <span class="coord-h">111°42'0"E</span>
            <span class="coord-h">111°42'30"E</span>
            <span class="coord-h">111°43'0"E</span>
          </div>
        </div>
      </div>

      <!-- ══════════════════════════════════════════════════════════════════════ -->
      <!-- KOLOM KANAN: MARGIN INFORMASI KARTOGRAFI                               -->
      <!-- ══════════════════════════════════════════════════════════════════════ -->
      <div class="carto-sidebar">

        <!-- 1. KOP / JUDUL PETA -->
        <div class="carto-box box-title">
          <div class="title-line-1" id="dispTitle1">PETA KAWASAN HUTAN</div>
          <div class="title-line-2" id="dispTitle2">DENGAN PENGELOLAAN KHUSUS (KHDPK)</div>
          <div class="title-line-3" id="dispTitle3">DESA <?= htmlspecialchars($namaDesa) ?> KEC. <?= htmlspecialchars($namaKec) ?></div>
          <div class="title-line-4" id="dispTitle4">KABUPATEN <?= htmlspecialchars($namaKab) ?></div>
        </div>

        <!-- 2. SKALA & ARAH MATA ANGIN -->
        <div class="carto-box box-scale">
          <div class="scale-ratio" id="scaleRatioText">SKALA 1:15.000</div>

          <!-- Arah Mata Angin (North Arrow Standard GIS) -->
          <svg class="north-arrow-svg" viewBox="0 0 40 60">
            <!-- N text -->
            <text x="20" y="11" font-family="'Inter', sans-serif" font-size="11" font-weight="900" text-anchor="middle" fill="#000">N</text>
            <!-- Panah Utara Kiri (Hitam) -->
            <polygon points="20,15 9,54 20,44" fill="#000000" stroke="#000000" stroke-width="0.8"/>
            <!-- Panah Utara Kanan (Putih) -->
            <polygon points="20,15 31,54 20,44" fill="#ffffff" stroke="#000000" stroke-width="0.8"/>
            <!-- Garis Tengah -->
            <line x1="20" y1="15" x2="20" y2="52" stroke="#000000" stroke-width="0.8"/>
          </svg>

          <!-- Skala Grafis (Alternating Checkered Bar) -->
          <div class="graphic-scale-wrap">
            <div class="scale-numbers">
              <span>0</span>
              <span>0.125</span>
              <span>0.25</span>
              <span>0.5</span>
              <span>0.75</span>
              <span>1</span>
            </div>
            <svg class="scale-bar-svg" viewBox="0 0 200 8" preserveAspectRatio="none">
              <!-- Segmen 1 (0 - 0.125) Hitam -->
              <rect x="0" y="0" width="25" height="8" fill="#000000" stroke="#000" stroke-width="0.5"/>
              <!-- Segmen 2 (0.125 - 0.25) Putih -->
              <rect x="25" y="0" width="25" height="8" fill="#ffffff" stroke="#000" stroke-width="0.5"/>
              <!-- Segmen 3 (0.25 - 0.5) Hitam -->
              <rect x="50" y="0" width="50" height="8" fill="#000000" stroke="#000" stroke-width="0.5"/>
              <!-- Segmen 4 (0.5 - 0.75) Putih -->
              <rect x="100" y="0" width="50" height="8" fill="#ffffff" stroke="#000" stroke-width="0.5"/>
              <!-- Segmen 5 (0.75 - 1.0) Hitam -->
              <rect x="150" y="0" width="50" height="8" fill="#000000" stroke="#000" stroke-width="0.5"/>
            </svg>
            <div class="scale-unit">Km</div>
          </div>
        </div>

        <!-- 3. KETERANGAN / LEGENDA -->
        <div class="carto-box box-legend">
          <div class="legend-header">Keterangan :</div>
          <div class="legend-list">
            <div class="legend-row">
              <span class="symbol-box sym-batas-desa"></span>
              <span>Batas Desa</span>
            </div>
            <div class="legend-row">
              <span class="symbol-box sym-khdpk-ps"></span>
              <span>Areal KHDPK PS</span>
            </div>
            <div class="legend-row">
              <span class="symbol-box sym-petak"></span>
              <span>Petak Perhutani</span>
            </div>
            <div class="legend-row">
              <span class="symbol-box sym-kps-yellow"></span>
              <span>KPS Kulin KK/IPHPS</span>
            </div>
            <!-- Titik verifikasi pupuk -->
            <div class="legend-row" id="legRowSesuai">
              <span class="symbol-dot dot-green"></span>
              <span>Sesuai SK &amp; Dalam Peta</span>
            </div>
            <div class="legend-row" id="legRowBelum">
              <span class="symbol-dot dot-red"></span>
              <span>Belum Sesuai SK PS</span>
            </div>
            <div class="legend-row" id="legRowLuar">
              <span class="symbol-dot dot-orange"></span>
              <span>Luar Peta PS</span>
            </div>
          </div>
        </div>

        <!-- 4. SUMBER PETA -->
        <div class="carto-box box-sources">
          <div class="sources-header">Sumber Peta :</div>
          <ol class="sources-list">
            <li>Peta Rupa Bumi Indonesia Skala 1 : 25.000</li>
            <li>Kawasan Hutan Keputusan Menteri LHK Nomor SK. 6606 Tahun 2021</li>
            <li>SK Menteri LHK Nomor SK. 287 Tahun 2022 tentang Penetapan Kawasan Hutan dengan Pengelolaan Khusus</li>
            <li>SK KTH: <?= htmlspecialchars($nomorSk) ?> (Tahun <?= htmlspecialchars($tahunSk) ?>)</li>
          </ol>
        </div>

        <!-- 5. PETA SITUASI (INSET MAP) -->
        <div class="carto-box box-inset">
          <div class="inset-header">PETA SITUASI</div>
          <div class="inset-scale">SKALA 1 : 1.000.000</div>

          <div class="inset-map-box" id="insetMapBox">
            <!-- Peta Vektor Presisi Kabupaten Bojonegoro -->
            <svg class="inset-svg" viewBox="111.4 -7.45 0.82 0.42" preserveAspectRatio="xMidYMid meet">
              <!-- Background grid koordinat inset -->
              <line x1="111.5" y1="-7.45" x2="111.5" y2="-7.05" stroke="#cbd5e1" stroke-dasharray="2,2" stroke-width="0.002"/>
              <line x1="112.0" y1="-7.45" x2="112.0" y2="-7.05" stroke="#cbd5e1" stroke-dasharray="2,2" stroke-width="0.002"/>
              <line x1="111.4" y1="-7.15" x2="112.2" y2="-7.15" stroke="#cbd5e1" stroke-dasharray="2,2" stroke-width="0.002"/>
              <line x1="111.4" y1="-7.35" x2="112.2" y2="-7.35" stroke="#cbd5e1" stroke-dasharray="2,2" stroke-width="0.002"/>

              <!-- Wilayah Bojonegoro & Sekitar -->
              <!-- Poligon batas kabupaten luar -->
              <path d="M 111.42,-7.32 L 111.46,-7.39 L 111.54,-7.42 L 111.62,-7.40 L 111.75,-7.42 L 111.88,-7.41 L 112.02,-7.36 L 112.12,-7.25 L 112.18,-7.15 L 112.09,-7.08 L 111.95,-7.08 L 111.82,-7.11 L 111.68,-7.14 L 111.55,-7.18 L 111.46,-7.23 Z"
                    fill="#ffffff" stroke="#334155" stroke-width="0.004" />

              <!-- Garis batas kecamatan di Bojonegoro -->
              <path d="M 111.55,-7.18 L 111.65,-7.26 L 111.72,-7.25 L 111.80,-7.20 L 111.88,-7.24 L 111.95,-7.20 L 112.09,-7.18" fill="none" stroke="#64748b" stroke-width="0.002"/>
              <path d="M 111.65,-7.26 L 111.68,-7.33 L 111.75,-7.34 L 111.85,-7.32 L 111.98,-7.30" fill="none" stroke="#64748b" stroke-width="0.002"/>
              <path d="M 111.46,-7.23 L 111.52,-7.28 L 111.60,-7.30 L 111.68,-7.33 L 111.71,-7.38 L 111.75,-7.42" fill="none" stroke="#64748b" stroke-width="0.002"/>
              <path d="M 111.60,-7.30 L 111.63,-7.38 L 111.62,-7.40" fill="none" stroke="#64748b" stroke-width="0.002"/>
              <path d="M 111.75,-7.34 L 111.78,-7.39 L 111.88,-7.41" fill="none" stroke="#64748b" stroke-width="0.002"/>
              <path d="M 111.85,-7.32 L 111.92,-7.38 L 112.02,-7.36" fill="none" stroke="#64748b" stroke-width="0.002"/>

              <!-- Label beberapa kecamatan utama -->
              <text x="111.50" y="-7.25" font-size="0.016" fill="#64748b" font-family="'Inter',sans-serif">Tambakrejo</text>
              <text x="111.64" y="-7.35" font-size="0.015" fill="#475569" font-weight="bold" font-family="'Inter',sans-serif">Ngambon</text>
              <text x="111.78" y="-7.28" font-size="0.016" fill="#64748b" font-family="'Inter',sans-serif">Dander</text>
              <text x="111.86" y="-7.17" font-size="0.016" fill="#64748b" font-family="'Inter',sans-serif">Bojonegoro</text>
              <text x="111.98" y="-7.22" font-size="0.016" fill="#64748b" font-family="'Inter',sans-serif">Sumberejo</text>
              <text x="111.52" y="-7.35" font-size="0.015" fill="#64748b" font-family="'Inter',sans-serif">Ngraho</text>
              <text x="111.78" y="-7.37" font-size="0.015" fill="#64748b" font-family="'Inter',sans-serif">Bubulan</text>

              <!-- Kotak Merah Lokasi yang Dipetakan (Ngambon / Bondol) -->
              <rect x="111.68" y="-7.32" width="0.04" height="0.035"
                    fill="rgba(220,38,38,0.25)" stroke="#dc2626" stroke-width="0.004" id="insetTargetBox"/>
            </svg>
          </div>

          <div class="inset-legend">
            <span class="sym-loc-box"></span>
            <span>Lokasi yang dipetakan</span>
          </div>
        </div>

      </div>

    </div>
  </div>
</div>

<!-- Modal Edit Kop Judul -->
<div class="modal-overlay" id="modalEditJudul">
  <div class="modal-card">
    <h3>✏️ Sesuaikan Teks Kop Judul &amp; Skala</h3>
    <div class="modal-field">
      <label>Baris 1 (Judul Utama):</label>
      <input type="text" id="inpTitle1" value="PETA KAWASAN HUTAN">
    </div>
    <div class="modal-field">
      <label>Baris 2 (Sub Judul):</label>
      <input type="text" id="inpTitle2" value="DENGAN PENGELOLAAN KHUSUS (KHDPK)">
    </div>
    <div class="modal-field">
      <label>Baris 3 (Desa &amp; Kecamatan):</label>
      <input type="text" id="inpTitle3" value="DESA <?= htmlspecialchars($namaDesa) ?> KEC. <?= htmlspecialchars($namaKec) ?>">
    </div>
    <div class="modal-field">
      <label>Baris 4 (Kabupaten):</label>
      <input type="text" id="inpTitle4" value="KABUPATEN <?= htmlspecialchars($namaKab) ?>">
    </div>
    <div class="modal-field">
      <label>Teks Skala (Format: SKALA 1:15.000):</label>
      <input type="text" id="inpScale" value="SKALA 1:15.000">
    </div>
    <div class="modal-actions">
      <button style="background:#e2e8f0;color:#334155" onclick="tutupModalEdit()">Batal</button>
      <button style="background:#059669;color:#fff" onclick="simpanModalEdit()">Terapkan ke Peta</button>
    </div>
  </div>
</div>

<!-- SVG Pola Garis Arsir Merah (KHDPK PS) -->
<svg width="0" height="0" style="position:absolute;width:0;height:0;overflow:hidden">
  <defs>
    <pattern id="redHatchPattern" patternUnits="userSpaceOnUse" width="10" height="10" patternTransform="rotate(45)">
      <line x1="0" y1="0" x2="0" y2="10" stroke="#dc2626" stroke-width="2"/>
    </pattern>
  </defs>
</svg>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function() {
  const KTH_ID = <?= (int)$kthId ?>;
  const KTH_NAMA = <?= json_encode($namaKth) ?>;
  const PETAK_NO = <?= json_encode($petakNo) ?>;

  // Inisialisasi Map dengan Canvas Renderer (Ringan & Cepat untuk Cetak)
  const mapCanvas = L.canvas({ padding: 0.5 });
  const map = L.map('map', {
    zoomControl: false,
    attributionControl: false,
    preferCanvas: true,
    fadeAnimation: false,
    markerZoomAnimation: false,
  });

  // Layer groups
  const basemapGroup = L.layerGroup().addTo(map);
  const polygonGroup = L.layerGroup().addTo(map);
  const villageLinesGroup = L.layerGroup().addTo(map);
  const villageLabelsGroup = L.layerGroup().addTo(map);
  const pointsGroup = L.layerGroup().addTo(map);

  let currentBasemap = 'kartografis';
  let showPoints = true;
  let showVillages = true;

  // Daftar Basemap Tiles
  const TILE_LAYERS = {
    osm: L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, crossOrigin: true }),
    satelit: L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', { maxZoom: 19, crossOrigin: true }),
    positron: L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', { maxZoom: 19, crossOrigin: true }),
  };

  window.gantiBasemap = function(tipe) {
    currentBasemap = tipe;
    basemapGroup.clearLayers();
    if (tipe === 'kartografis') {
      // Mode Kartografis Bersih BPKH (Background putih, super cepat, 0 lag)
      document.getElementById('map').style.backgroundColor = '#ffffff';
    } else if (TILE_LAYERS[tipe]) {
      document.getElementById('map').style.backgroundColor = '#f1f5f9';
      basemapGroup.addLayer(TILE_LAYERS[tipe]);
    }
  };

  // Format Derajat Menit Detik (DMS) untuk Grid Koordinat
  function formatDMS(val, isLng) {
    const absVal = Math.abs(val);
    const d = Math.floor(absVal);
    const m = Math.floor((absVal - d) * 60);
    const s = Math.round(((absVal - d) * 60 - m) * 60);
    const dir = isLng ? (val >= 0 ? 'E' : 'W') : (val >= 0 ? 'N' : 'S');
    return `${d}°${m}'${s}"${dir}`;
  }

  // Update Ticks Koordinat di 4 Tepi
  function updateGraticuleCoords() {
    const b = map.getBounds();
    const minLng = b.getWest();
    const maxLng = b.getEast();
    const minLat = b.getSouth();
    const maxLat = b.getNorth();

    const midLng = (minLng + maxLng) / 2;
    const midLat = (minLat + maxLat) / 2;

    // Atas & Bawah (Bujur)
    const topHtml = `
      <span class="coord-h">${formatDMS(minLng + (maxLng-minLng)*0.15, true)}</span>
      <span class="coord-h">${formatDMS(midLng, true)}</span>
      <span class="coord-h">${formatDMS(minLng + (maxLng-minLng)*0.85, true)}</span>
    `;
    document.getElementById('coordTop').innerHTML = topHtml;
    document.getElementById('coordBottom').innerHTML = topHtml;

    // Kiri & Kanan (Lintang)
    const leftHtml = `
      <span class="coord-v">${formatDMS(maxLat - (maxLat-minLat)*0.15, false)}</span>
      <span class="coord-v">${formatDMS(midLat, false)}</span>
      <span class="coord-v">${formatDMS(maxLat - (maxLat-minLat)*0.85, false)}</span>
    `;
    document.getElementById('coordLeft').innerHTML = leftHtml;
    document.getElementById('coordRight').innerHTML = leftHtml;
  }

  map.on('moveend', updateGraticuleCoords);

  // Buat Garis Batas & Nama Desa Sekitar (Sesuai Gambar Referensi)
  function renderBatasDesaSekitar(centerLng, centerLat) {
    villageLinesGroup.clearLayers();
    villageLabelsGroup.clearLayers();

    // Garis batas desa putus-putus
    const villageLines = [
      // Garis Utara (Sendangrejo - Bondol)
      [[centerLat + 0.015, centerLng - 0.02], [centerLat + 0.012, centerLng], [centerLat + 0.016, centerLng + 0.025]],
      // Garis Barat (Mulyorejo - Bondol - Turi)
      [[centerLat + 0.025, centerLng - 0.012], [centerLat + 0.012, centerLng - 0.012], [centerLat - 0.005, centerLng - 0.018], [centerLat - 0.02, centerLng - 0.015]],
      // Garis Timur (Kacangan - Ngambon - Sengon)
      [[centerLat + 0.028, centerLng + 0.022], [centerLat + 0.014, centerLng + 0.015], [centerLat - 0.002, centerLng + 0.016], [centerLat - 0.022, centerLng + 0.013]],
      // Garis Selatan (Bondol - Sengon)
      [[centerLat - 0.008, centerLng - 0.018], [centerLat - 0.010, centerLng + 0.005], [centerLat - 0.009, centerLng + 0.03]],
    ];

    villageLines.forEach(pts => {
      L.polyline(pts, {
        color: '#000000',
        weight: 1.8,
        dashArray: '7, 5',
        opacity: 0.9,
      }).addTo(villageLinesGroup);
    });

    // Label Nama Desa
    const villages = [
      { name: 'Sendangrejo', lat: centerLat + 0.022, lng: centerLng + 0.008 },
      { name: 'Mulyorejo',   lat: centerLat + 0.018, lng: centerLng - 0.018 },
      { name: 'Kacangan',    lat: centerLat + 0.026, lng: centerLng + 0.027 },
      { name: 'Bondol',      lat: centerLat + 0.004, lng: centerLng + 0.003 },
      { name: 'Ngambon',     lat: centerLat - 0.002, lng: centerLng + 0.024 },
      { name: 'Sengon',      lat: centerLat - 0.018, lng: centerLng - 0.002 },
      { name: 'Turi',        lat: centerLat - 0.008, lng: centerLng - 0.022 },
    ];

    villages.forEach(v => {
      const icon = L.divIcon({
        className: 'village-label',
        html: `<div>${v.name}</div>`,
        iconSize: [100, 20],
        iconAnchor: [50, 10],
      });
      L.marker([v.lat, v.lng], { icon: icon, interactive: false }).addTo(villageLabelsGroup);
    });
  }

  // Load Data Poligon & Titik Petani
  fetch('peta_data.php?kth_id=' + KTH_ID)
    .then(r => r.json())
    .then(data => {
      const allBounds = [];
      let centerCoord = { lat: -7.297, lng: 111.702 };

      // 1. Poligon KPS Kulin KK / IPHPS (Kuning Solid dengan Garis Tepi Hitam)
      if (data.polygon) {
        // Layer Kuning Utama
        const polyYellow = L.geoJSON(data.polygon, {
          style: {
            color: '#000000',
            weight: 1.8,
            fillColor: '#ffeb3b',
            fillOpacity: 0.88,
          },
        }).addTo(polygonGroup);

        // Layer Arsir Merah KHDPK PS (Bila ingin overlay arsir diagonal merah)
        const polyHatch = L.geoJSON(data.polygon, {
          style: {
            color: '#dc2626',
            weight: 1.2,
            dashArray: '3, 3',
            fillColor: '#ef4444',
            fillOpacity: 0.22,
          },
        }).addTo(polygonGroup);

        const b = polyYellow.getBounds();
        if (b.isValid()) {
          allBounds.push(b);
          centerCoord = b.getCenter();

          // Label Teks di Tengah Poligon (LMDH / KTH SUMBER JATI 180)
          const labelHtml = `<div style="font-weight:800;font-size:10px;text-align:center;line-height:1.2">${KTH_NAMA}<br>${PETAK_NO ? 'Petak ' + PETAK_NO : ''}</div>`;
          const polyIcon = L.divIcon({
            className: 'poly-name-label',
            html: labelHtml,
            iconSize: [160, 30],
            iconAnchor: [80, 15],
          });
          L.marker(centerCoord, { icon: polyIcon, interactive: false }).addTo(polygonGroup);
        }
      }

      // Render Batas Desa sekitar lokasi poligon
      renderBatasDesaSekitar(centerCoord.lng, centerCoord.lat);

      // 2. Titik Petani (Canvas Renderer — Instan saat Cetak, Tanpa Lag)
      if (data.titik && data.titik.features) {
        data.titik.features.forEach(f => {
          if (!f.geometry || !f.geometry.coordinates) return;
          const [lng, lat] = f.geometry.coordinates;
          const p = f.properties;

          let color = '#16a34a'; // Hijau: Sesuai
          if (p.warna === 'merah') color = '#dc2626';
          else if (p.warna === 'oranye') color = '#ea580c';

          const marker = L.circleMarker([lat, lng], {
            renderer: mapCanvas,
            radius: 5,
            fillColor: color,
            color: '#000000',
            weight: 1.2,
            opacity: 1,
            fillOpacity: 0.95,
          });

          // Tooltip nomor petani
          if (p.no) {
            marker.bindTooltip(String(p.no), {
              permanent: false,
              direction: 'top',
              className: 'point-tooltip',
            });
          }

          marker.addTo(pointsGroup);
          allBounds.push(L.latLngBounds([[lat, lng], [lat, lng]]));
        });
      }

      // Fit bounds presisi sesuai proporsi peta
      if (allBounds.length > 0) {
        const groupBounds = allBounds[0];
        for (let i = 1; i < allBounds.length; i++) {
          groupBounds.extend(allBounds[i]);
        }
        map.fitBounds(groupBounds, {
          padding: [95, 95],
          maxZoom: 15,
          animate: false,
        });
      } else {
        map.setView([centerCoord.lat, centerCoord.lng], 14.5);
      }

      // Trigger pembaruan graticule
      setTimeout(updateGraticuleCoords, 100);
    })
    .catch(err => {
      console.error('Gagal memuat peta data:', err);
    });

  // Toggle Layer Titik Petani
  window.toggleLayerTitik = function() {
    showPoints = !showPoints;
    if (showPoints) {
      map.addLayer(pointsGroup);
      document.getElementById('lblPointsStatus').textContent = 'ON (<?= (int)$live['total'] ?>)';
      document.getElementById('legRowSesuai').style.display = 'flex';
      document.getElementById('legRowBelum').style.display = 'flex';
      document.getElementById('legRowLuar').style.display = 'flex';
    } else {
      map.removeLayer(pointsGroup);
      document.getElementById('lblPointsStatus').textContent = 'OFF';
      document.getElementById('legRowSesuai').style.display = 'none';
      document.getElementById('legRowBelum').style.display = 'none';
      document.getElementById('legRowLuar').style.display = 'none';
    }
  };

  // Toggle Layer Desa
  window.toggleLayerDesa = function() {
    showVillages = !showVillages;
    if (showVillages) {
      map.addLayer(villageLinesGroup);
      map.addLayer(villageLabelsGroup);
      document.getElementById('lblVillagesStatus').textContent = 'ON';
    } else {
      map.removeLayer(villageLinesGroup);
      map.removeLayer(villageLabelsGroup);
      document.getElementById('lblVillagesStatus').textContent = 'OFF';
    }
  };

  // Modal Edit Kop
  window.bukaModalEdit = function() {
    document.getElementById('modalEditJudul').style.display = 'flex';
  };
  window.tutupModalEdit = function() {
    document.getElementById('modalEditJudul').style.display = 'none';
  };
  window.simpanModalEdit = function() {
    document.getElementById('dispTitle1').textContent = document.getElementById('inpTitle1').value;
    document.getElementById('dispTitle2').textContent = document.getElementById('inpTitle2').value;
    document.getElementById('dispTitle3').textContent = document.getElementById('inpTitle3').value;
    document.getElementById('dispTitle4').textContent = document.getElementById('inpTitle4').value;
    document.getElementById('scaleRatioText').textContent = document.getElementById('inpScale').value;
    tutupModalEdit();
  };

  window.zoomInPeta = function() {
    map.zoomIn();
  };
  window.zoomOutPeta = function() {
    map.zoomOut();
  };

  // Cetak Peta Instan (Bebas Lag / Hang)
  window.cetakPetaLangsung = function() {
    map.invalidateSize();
    updateGraticuleCoords();
    // Beri jeda 150ms agar canvas renderer selesai melukis lalu panggil print dialog
    setTimeout(() => {
      window.print();
    }, 150);
  };

})();
</script>
</body>
</html>
