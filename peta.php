<?php
// peta.php — Peta Spasial Interaktif Kasus KTH
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/layout.php';

$kthId = (int)($_GET['kth_id'] ?? 0);
$vParam = isset($_GET['v']) ? (int)$_GET['v'] : null;

$pdo = db();
$stKth = $pdo->prepare('SELECT * FROM kth WHERE id = ?');
$stKth->execute([$kthId]);
$kth = $stKth->fetch();
if (!$kth) {
    flash_set('error', 'Data KTH tidak ditemukan.');
    header('Location: index.php');
    exit;
}

$daftarVersi = ambil_daftar_versi($pdo, $kthId);
$versiAktif = ambil_versi_terpilih($kth, $vParam);

// Data panel kanan: query baca untuk tampilan saja. Tidak mengubah GeoJSON
// maupun perhitungan spasial di peta_data.php.
$qPetani = $pdo->prepare(
    'SELECT u.id, u.no_urut, u.nama, u.nik, u.petak, u.desa, u.kecamatan,
            u.luas_lahan,
            COALESCE(h.koordinat_koreksi_x, u.koordinat_x) AS koordinat_x,
            COALESCE(h.koordinat_koreksi_y, u.koordinat_y) AS koordinat_y,
            h.status_sk, h.status_koordinat, h.catatan, h.nama_mirip_sk, h.kemiripan_nama,
            (SELECT COUNT(*) FROM usulan_titik t
              WHERE t.usulan_id = u.id AND t.x IS NOT NULL AND t.y IS NOT NULL) AS jumlah_titik,
            (SELECT t.x FROM usulan_titik t
              WHERE t.usulan_id = u.id AND t.x IS NOT NULL AND t.y IS NOT NULL
              ORDER BY t.urutan LIMIT 1) AS titik_x,
            (SELECT t.y FROM usulan_titik t
              WHERE t.usulan_id = u.id AND t.x IS NOT NULL AND t.y IS NOT NULL
              ORDER BY t.urutan LIMIT 1) AS titik_y
     FROM usulan_pupuk u
     LEFT JOIN hasil_verifikasi h ON (h.usulan_id = u.id AND h.versi_ke = u.versi_ke)
     WHERE u.kth_id = ? AND u.versi_ke = ?
     ORDER BY COALESCE(u.no_urut, u.id)'
);
$qPetani->execute([$kthId, $versiAktif]);
$rowsPetani = $qPetani->fetchAll();

$petaniPeta = [];
$totalPetani = count($rowsPetani);
$totalTitik = 0;
$jumlahDalam = 0;
$jumlahLuar = 0;
$jumlahLuas = 0;
$jumlahTanpaKoordinat = 0;

foreach ($rowsPetani as $r) {
    $id = (int)$r['id'];
    $lat = ($r['koordinat_y'] !== null && $r['koordinat_y'] !== '') ? (float)$r['koordinat_y'] : null;
    $lng = ($r['koordinat_x'] !== null && $r['koordinat_x'] !== '') ? (float)$r['koordinat_x'] : null;
    if ($lat === null || $lng === null) {
        $lat = ($r['titik_y'] !== null && $r['titik_y'] !== '') ? (float)$r['titik_y'] : null;
        $lng = ($r['titik_x'] !== null && $r['titik_x'] !== '') ? (float)$r['titik_x'] : null;
    }
    $punyaKoordinat = $lat !== null && $lng !== null;
    $jumlahTitik = (int)($r['jumlah_titik'] ?? 0);
    if ($jumlahTitik <= 0 && $punyaKoordinat) $jumlahTitik = 1;
    $luas = ($r['luas_lahan'] !== null && $r['luas_lahan'] !== '') ? (float)$r['luas_lahan'] : 0.0;
    $statusKoord = (string)($r['status_koordinat'] ?? 'Luar Peta PS');
    $statusSK = (string)($r['status_sk'] ?? 'Belum Sesuai SK PS');
    $dalamPeta = $statusKoord === 'Dalam Peta PS';
    $luasLebih = $luas > 2.0;
    $no = $r['no_urut'] !== null ? (int)$r['no_urut'] : 0;
    $noLabel = $no > 0 ? '#' . $no : '—';
    $petak = trim((string)($r['petak'] ?? ''));
    $petakLabel = $petak !== '' ? 'Petak ' . $petak : 'Petak —';
    $desaKec = implode(', ', array_filter([
        trim((string)($r['desa'] ?? '')),
        trim((string)($r['kecamatan'] ?? '')),
    ]));
    $latFmt = $punyaKoordinat ? number_format($lat, 6, '.', '') : '';
    $lngFmt = $punyaKoordinat ? number_format($lng, 6, '.', '') : '';
    $luasFmt = number_format($luas, 2, ',', '.');

    if ($dalamPeta) $jumlahDalam++; else $jumlahLuar++;
    if ($luasLebih) $jumlahLuas++;
    if (!$punyaKoordinat) $jumlahTanpaKoordinat++;
    $totalTitik += $jumlahTitik;

    $cari = mb_strtolower(implode(' ', array_filter([
        $noLabel,
        (string)($r['nama'] ?? ''),
        (string)($r['nik'] ?? ''),
        $petak,
        $petakLabel,
        (string)($r['desa'] ?? ''),
        (string)($r['kecamatan'] ?? ''),
        $latFmt,
        $lngFmt,
        $statusKoord,
        $statusSK,
        $luasFmt,
    ])), 'UTF-8');

    $petaniPeta[$id] = [
        'id' => $id,
        'no' => $noLabel,
        'nama' => (string)($r['nama'] ?? ''),
        'nik' => (string)($r['nik'] ?? ''),
        'petak' => $petak,
        'petak_label' => $petakLabel,
        'desa' => (string)($r['desa'] ?? ''),
        'kecamatan' => (string)($r['kecamatan'] ?? ''),
        'desa_kec' => $desaKec,
        'lat' => $lat,
        'lng' => $lng,
        'lat_fmt' => $latFmt,
        'lng_fmt' => $lngFmt,
        'jumlah_titik' => $jumlahTitik,
        'luas' => $luas,
        'luas_fmt' => $luasFmt,
        'luas_lebih' => $luasLebih,
        'dalam' => $dalamPeta,
        'punya_koordinat' => $punyaKoordinat,
        'status_sk' => $statusSK,
        'status_koord' => $statusKoord,
        'catatan' => (string)($r['catatan'] ?? ''),
        'nama_mirip' => (string)($r['nama_mirip_sk'] ?? ''),
        'kemiripan' => $r['kemiripan_nama'],
        'cari' => $cari,
    ];
}

layout_head('Peta Spasial & Titik — ' . ($kth['nama_kth'] ?? ''));

// Sub-Navbar Navigasi Terpadu
layout_kth_subnav($kth, 'peta', $versiAktif, $daftarVersi);
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css">
<style>
#peta-interaktif {
  width: 100%;
  height: 100%;
  background: #E5E7EB;
}
.leaflet-popup-content {
  font-size: 12px;
  line-height: 1.5;
  min-width: 240px;
  font-family: "Plus Jakarta Sans", sans-serif;
}
.map-workspace {
  display: grid;
  grid-template-columns: minmax(0, 1.62fr) minmax(330px, 1fr);
  gap: 16px;
  align-items: start;
}
.map-pane {
  position: sticky;
  top: 16px;
  height: calc(100vh - 280px);
  min-height: 520px;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}
.map-viewport {
  position: relative;
  flex: 1;
  min-height: 0;
}
.map-toolbar {
  position: absolute;
  top: 12px;
  left: 12px;
  z-index: 650;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 8px;
  pointer-events: none;
}
.map-toolbar > * {
  pointer-events: auto;
}
.map-tool-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  min-height: 30px;
  padding: 6px 10px;
  background: rgba(255, 255, 255, .96);
  border: 1px solid #DDD5C7;
  color: #142219;
  font-size: 11px;
  font-weight: 600;
  line-height: 1;
  box-shadow: 0 2px 8px rgba(20, 34, 25, .12);
  transition: background-color .15s ease, border-color .15s ease;
}
.map-tool-btn:hover {
  background: #F9F7F1;
  border-color: #7D664E;
}
.basemap-group {
  display: inline-flex;
  align-items: stretch;
  background: rgba(255, 255, 255, .96);
  border: 1px solid #DDD5C7;
  box-shadow: 0 2px 8px rgba(20, 34, 25, .12);
}
.basemap-btn {
  padding: 6px 9px;
  color: #57655B;
  font-size: 10px;
  font-weight: 700;
  line-height: 1;
  transition: background-color .15s ease, color .15s ease;
}
.basemap-btn + .basemap-btn { border-left: 1px solid #DDD5C7; }
.basemap-btn:hover { background: #F9F7F1; color: #142219; }
.basemap-btn.is-active { background: #1B382B; color: #FFFFFF; }
.map-legend {
  position: absolute;
  left: 12px;
  bottom: 16px;
  z-index: 700;
  width: max-content;
  max-width: calc(100% - 24px);
  max-height: calc(100% - 96px);
  overflow-y: auto;
  padding: 8px 10px;
  background: rgba(255, 255, 255, .96);
  border: 1px solid #DDD5C7;
  box-shadow: 0 2px 8px rgba(20, 34, 25, .12);
  color: #57655B;
  font-size: 10px;
  line-height: 1.7;
  pointer-events: auto;
}
.legend-line {
  display: inline-block;
  width: 14px;
  height: 3px;
  background: #1B382B;
  vertical-align: middle;
}
.legend-dot {
  display: inline-block;
  width: 8px;
  height: 8px;
  border-radius: 999px;
  border: 1.5px solid #FFFFFF;
  box-shadow: 0 0 0 1px rgba(20, 34, 25, .2);
  vertical-align: middle;
}
.list-pane {
  height: calc(100vh - 280px);
  min-height: 520px;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}
.list-scroll {
  min-height: 0;
  flex: 1;
  overflow-y: auto;
  scrollbar-gutter: stable;
}
.petani-row {
  cursor: pointer;
  border-left: 4px solid transparent;
  transition: background-color .12s ease, border-color .12s ease;
}
.petani-row:hover { background: #F6F3EC; }
.petani-row.active-item {
  background: #EEF5F0;
  border-left-color: #1E4620;
}
.petani-row:focus-visible {
  outline: 2px solid #1D5C3A;
  outline-offset: -2px;
}
.filter-pill {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 5px 8px;
  border: 1px solid #DDD5C7;
  background: #FFFFFF;
  color: #57655B;
  font-size: 10px;
  font-weight: 600;
  line-height: 1;
  transition: background-color .12s ease, border-color .12s ease, color .12s ease;
}
.filter-pill:hover { background: #F9F7F1; border-color: #7D664E; }
.filter-pill.is-active {
  background: #1B382B;
  border-color: #1B382B;
  color: #FFFFFF;
}
.filter-pill .pill-count {
  font-family: "JetBrains Mono", ui-monospace, monospace;
  font-size: 9px;
  opacity: .8;
}
.status-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 2px 5px;
  border: 1px solid;
  font-size: 9px;
  font-weight: 700;
  line-height: 1.25;
  white-space: nowrap;
}
.status-dalam { color: #1D5C3A; background: #F0F7F2; border-color: #B2D8C0; }
.status-luar { color: #9E2A2B; background: #FDF2F2; border-color: #F2B8B8; }
.status-luas { color: #B45309; background: #FEF9EE; border-color: #F6DBA5; }
.status-abu { color: #57655B; background: #F3EFE7; border-color: #DDD5C7; }
.pop-badge {
  display: inline-block;
  padding: 2px 6px;
  border: 1px solid;
  font-size: 10px;
  font-weight: 700;
  line-height: 1.3;
}
.pop-ok { color: #1D5C3A; background: #F0F7F2; border-color: #B2D8C0; }
.pop-bad { color: #9E2A2B; background: #FDF2F2; border-color: #F2B8B8; }
.pop-warn { color: #B45309; background: #FEF9EE; border-color: #F6DBA5; }
@media (max-width: 991px) {
  .map-workspace { grid-template-columns: minmax(0, 1fr); }
  .map-pane {
    position: static;
    height: auto;
    min-height: 0;
  }
  .map-viewport { flex: none; height: 520px; }
  .list-pane {
    height: auto;
    min-height: 0;
  }
  .list-scroll { max-height: 560px; }
  .map-legend { bottom: 12px; max-height: calc(100% - 84px); font-size: 9.5px; }
}
</style>

<div class="map-workspace mb-6">
  <!-- KIRI: Peta -->
  <section class="doc-card map-pane">
    <div class="shrink-0 px-4 py-3 bg-[#FAF8F3] border-b border-kadaster-border flex flex-wrap items-center justify-between gap-3">
      <div>
        <div class="flex items-center gap-2">
          <span class="w-2 h-2 bg-forest-900 inline-block"></span>
          <h3 class="font-serif font-bold text-base text-ink">Peta Spasial Areal PS &amp; Titik Pemohon</h3>
        </div>
        <p class="text-[11px] text-ink-muted mt-0.5">
          Poligon batas Perhutanan Sosial dan sebaran titik koordinat petani pengusul.
        </p>
      </div>
      <div class="flex flex-wrap items-center gap-2">
        <a href="cetak_peta.php?kth_id=<?= $kthId ?>&v=<?= $versiAktif ?>" target="_blank" rel="noopener"
           class="btn-kadaster px-3 py-1.5 text-[11px] font-semibold inline-flex items-center gap-1.5 text-forest-900 hover:bg-forest-100">
          <svg class="w-3.5 h-3.5 text-forest-900" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
          <span>Cetak Peta</span>
        </a>
        <a href="hasil.php?kth_id=<?= $kthId ?>&v=<?= $versiAktif ?>"
           class="btn-forest px-3 py-1.5 text-[11px] font-semibold inline-flex items-center gap-1.5">
          <span>Tabel Hasil</span>
          <span>→</span>
        </a>
      </div>
    </div>

    <div class="map-viewport">
      <div id="peta-interaktif"></div>

      <div class="map-toolbar">
        <button type="button" id="btn-fit-sk" class="map-tool-btn" title="Kembali ke seluruh poligon SK Perhutanan Sosial">
          <svg class="w-3.5 h-3.5 text-forest-900" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-5h-4m4 0v4m0-4l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5h-4m4 0v-4m0 4l-5-5"/></svg>
          <span>Fokus Area SK</span>
        </button>
        <div class="basemap-group" role="group" aria-label="Pilih basemap">
          <button type="button" class="basemap-btn is-active" data-basemap="sat" aria-pressed="true">Citra Satelit</button>
          <button type="button" class="basemap-btn" data-basemap="osm" aria-pressed="false">OSM</button>
        </div>
      </div>

      <div class="map-legend">
        <div class="font-semibold text-ink mb-0.5">Legenda</div>
        <div><span class="legend-line mr-1.5"></span>Batas Poligon SK PS</div>
        <div><span class="legend-dot mr-1.5" style="background:#1D5C3A"></span>Dalam poligon</div>
        <div><span class="legend-dot mr-1.5" style="background:#9E2A2B"></span>Luar poligon / outlier</div>
        <div><span class="legend-dot mr-1.5" style="background:#B45309"></span>Luas &gt; 2 Ha / perlu cek</div>
      </div>

      <div id="peta-error" class="hidden absolute inset-0 z-[700] items-center justify-center bg-white/90 px-6 text-center text-xs text-audit-revisi"></div>
    </div>
  </section>

  <!-- KANAN: Daftar titik -->
  <aside class="doc-card list-pane">
    <div class="shrink-0 px-4 py-3 bg-[#FAF8F3] border-b border-kadaster-border">
      <div class="flex items-start justify-between gap-2">
        <div>
          <h3 class="font-serif font-bold text-base text-ink leading-tight">Daftar Titik Koordinat &amp; Luasan</h3>
          <p class="text-[11px] text-ink-muted mt-0.5">Klik baris untuk melihat titik pada peta.</p>
        </div>
        <span id="counter-petani" class="font-mono text-[10px] text-ink-faint whitespace-nowrap mt-1"><?= $totalPetani ?> petani &middot; <?= $totalTitik ?> titik</span>
      </div>

      <div class="mt-3 flex items-center gap-1.5">
        <div class="relative flex-1 min-w-0">
          <svg class="w-3.5 h-3.5 text-ink-faint absolute left-2.5 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
          <input id="cari-petani" type="search" placeholder="Cari nama, NIK, petak, atau koordinat..."
                 class="w-full border border-kadaster-border bg-white pl-8 pr-2.5 py-2 text-[11px] text-ink placeholder:text-ink-faint focus:border-forest-900 focus:outline-none">
        </div>
        <button type="button" id="reset-filter-petani" data-reset-filter
                class="hidden px-2 py-2 text-[10px] font-semibold text-forest-900 border border-forest-300 bg-forest-50 hover:bg-forest-100 transition-colors whitespace-nowrap">
          Reset
        </button>
      </div>

      <div class="mt-2 flex flex-wrap gap-1.5">
        <button type="button" class="filter-pill is-active" data-filter="semua" aria-pressed="true">
          Semua <span class="pill-count">(<?= $totalPetani ?>)</span>
        </button>
        <button type="button" class="filter-pill" data-filter="dalam" aria-pressed="false">
          <span class="w-1.5 h-1.5 rounded-full bg-emerald-600"></span>
          Dalam <span class="pill-count">(<?= $jumlahDalam ?>)</span>
        </button>
        <button type="button" class="filter-pill" data-filter="luar" aria-pressed="false">
          <span class="w-1.5 h-1.5 rounded-full bg-red-600"></span>
          Luar <span class="pill-count">(<?= $jumlahLuar ?>)</span>
        </button>
        <button type="button" class="filter-pill" data-filter="luas" aria-pressed="false">
          <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
          &gt;2 Ha <span class="pill-count">(<?= $jumlahLuas ?>)</span>
        </button>
      </div>

      <?php if ($jumlahTanpaKoordinat > 0): ?>
      <div class="mt-2 text-[10px] text-audit-warn">
        <?= $jumlahTanpaKoordinat ?> petani belum memiliki koordinat.
      </div>
      <?php endif; ?>
    </div>

    <div id="daftar-petani" class="list-scroll divide-y divide-kadaster-border/70">
      <?php foreach ($petaniPeta as $d): ?>
      <div class="petani-row px-3.5 py-3 bg-white focus:outline-none"
           data-petani-row
           data-id="<?= (int)$d['id'] ?>"
           data-status="<?= $d['dalam'] ? 'dalam' : 'luar' ?>"
           data-luas="<?= $d['luas_lebih'] ? '1' : '0' ?>"
           data-koordinat="<?= $d['punya_koordinat'] ? '1' : '0' ?>"
           data-cari="<?= e($d['cari']) ?>"
           tabindex="0" role="button" aria-pressed="false"
           aria-label="Lihat titik <?= e($d['nama'] ?: 'tanpa nama') ?>">
        <div class="flex items-start justify-between gap-2">
          <div class="min-w-0">
            <div class="flex items-center gap-1.5 min-w-0">
              <span class="font-mono text-[10px] font-bold text-ink-faint shrink-0"><?= e($d['no']) ?></span>
              <span class="font-bold text-xs text-ink uppercase truncate"><?= e($d['nama'] ?: 'Tanpa Nama') ?></span>
            </div>
            <div class="font-mono text-[10px] text-ink-muted mt-0.5 truncate">NIK <?= e($d['nik'] ?: '—') ?></div>
            <div class="text-[10px] text-ink-muted truncate"><?= e($d['desa_kec'] ?: 'Desa/Kec. —') ?> · <?= e($d['petak_label']) ?></div>
          </div>
          <div class="text-right shrink-0">
            <div class="font-mono text-sm font-bold text-forest-900 tabular-nums"><?= e($d['luas_fmt']) ?> <span class="font-sans text-[9px] font-medium text-ink-muted">Ha</span></div>
            <?php if ($d['luas_lebih']): ?>
            <span class="status-badge status-luas mt-0.5">Perlu Cek</span>
            <?php endif; ?>
            <?php if (($d['jumlah_titik'] ?? 1) > 1): ?>
            <span class="status-badge status-abu mt-0.5"><?= (int)$d['jumlah_titik'] ?> titik</span>
            <?php endif; ?>
          </div>
        </div>

        <div class="mt-2 flex items-end justify-between gap-2">
          <div class="font-mono text-[10px] leading-snug text-ink-muted tabular-nums">
            <?php if ($d['punya_koordinat']): ?>
            <span class="text-ink-faint">LAT</span> <?= e($d['lat_fmt']) ?><br>
            <span class="text-ink-faint">LNG</span> <?= e($d['lng_fmt']) ?>
            <?php else: ?>
            <span class="text-audit-warn">Koordinat belum tersedia</span>
            <?php endif; ?>
          </div>
          <div class="flex flex-wrap justify-end gap-1">
            <span class="status-badge <?= $d['dalam'] ? 'status-dalam' : 'status-luar' ?>">
              <span class="w-1.5 h-1.5 rounded-full <?= $d['dalam'] ? 'bg-emerald-600' : 'bg-red-600' ?>"></span>
              <?= $d['dalam'] ? 'Dalam poligon' : 'Luar poligon' ?>
            </span>
            <?php if (!$d['punya_koordinat']): ?>
            <span class="status-badge status-abu">Tanpa titik</span>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>

      <div id="daftar-kosong" class="<?= $petaniPeta ? 'hidden' : '' ?> px-4 py-10 text-center">
        <div class="w-9 h-9 border border-dashed border-kadaster-brown/40 text-kadaster-brown mx-auto flex items-center justify-center mb-2">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        </div>
        <div id="pesan-kosong-petani" class="text-xs text-ink-muted">
          <?= $totalPetani ? 'Tidak ada titik yang cocok dengan pencarian atau filter.' : 'Belum ada data usulan pada versi ini.' ?>
        </div>
        <button type="button" data-reset-filter class="<?= $totalPetani ? '' : 'hidden' ?> mt-2 text-[10px] font-semibold text-forest-900 underline underline-offset-2">
          Reset filter
        </button>
      </div>
    </div>
  </aside>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const KTHID = <?= (int)$kthId ?>;
  const VERSI = <?= (int)$versiAktif ?>;
  const PETANI_LIST = <?= json_encode(array_values($petaniPeta), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE) ?>;
  const PETANI = {};
  PETANI_LIST.forEach(d => { PETANI[d.id] = d; });

  const rowsById = new Map();
  document.querySelectorAll('[data-petani-row]').forEach(row => {
    rowsById.set(Number(row.dataset.id), row);
  });

  const cariInput = document.getElementById('cari-petani');
  const counterPetani = document.getElementById('counter-petani');
  const daftarKosong = document.getElementById('daftar-kosong');
  const pesanKosong = document.getElementById('pesan-kosong-petani');
  const resetHeader = document.getElementById('reset-filter-petani');
  const totalPetani = rowsById.size;
  const totalTitik = <?= (int)$totalTitik ?>;

  let filterAktif = 'semua';
  let selectedId = null;
  let selectedMarkers = [];
  let popupSeq = 0;
  let cluster = null;
  let map = null;
  let areaSkBounds = null;
  let semuaBounds = null;
  const markersById = new Map();

  const osm = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '© OpenStreetMap'
  });
  const sat = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
    maxZoom: 19,
    attribution: '© Esri Satellite'
  });
  const basemaps = { sat, osm };
  let basemapAktif = 'sat';

  map = L.map('peta-interaktif', { zoomControl: false, layers: [sat] }).setView([-7.15, 111.88], 12);
  L.control.zoom({ position: 'topright' }).addTo(map);

  document.querySelectorAll('[data-basemap]').forEach(btn => {
    btn.addEventListener('click', () => {
      const key = btn.dataset.basemap;
      if (!basemaps[key] || key === basemapAktif) return;
      map.removeLayer(basemaps[basemapAktif]);
      basemapAktif = key;
      map.addLayer(basemaps[key]);
      document.querySelectorAll('[data-basemap]').forEach(b => {
        const aktif = b.dataset.basemap === key;
        b.classList.toggle('is-active', aktif);
        b.setAttribute('aria-pressed', aktif ? 'true' : 'false');
      });
    });
  });

  function escHtml(v) {
    return String(v ?? '').replace(/[&<>"']/g, m => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[m]));
  }

  function petaniCocok(d, q) {
    if (!d) return false;
    const cocokCari = !q || (d.cari || '').includes(q);
    const cocokFilter = filterAktif === 'semua'
      || (filterAktif === 'dalam' && d.dalam)
      || (filterAktif === 'luar' && !d.dalam)
      || (filterAktif === 'luas' && d.luas > 2);
    return cocokCari && cocokFilter;
  }

  function barisCocok(row, q) {
    const d = PETANI[Number(row.dataset.id)];
    if (d) return petaniCocok(d, q);
    const cocokCari = !q || (row.dataset.cari || '').includes(q);
    const cocokFilter = filterAktif === 'semua'
      || (filterAktif === 'dalam' && row.dataset.status === 'dalam')
      || (filterAktif === 'luar' && row.dataset.status === 'luar')
      || (filterAktif === 'luas' && row.dataset.luas === '1');
    return cocokCari && cocokFilter;
  }

  function refreshMarkers(q) {
    if (!cluster) return;
    cluster.clearLayers();
    markersById.forEach((markers, id) => {
      if (petaniCocok(PETANI[id], q)) {
        markers.forEach(marker => cluster.addLayer(marker));
      }
    });
  }

  function filterPetani() {
    const q = (cariInput?.value || '').toLowerCase().trim();
    let visible = 0;

    rowsById.forEach(row => {
      const tampil = barisCocok(row, q);
      row.classList.toggle('hidden', !tampil);
      if (tampil) visible++;
    });

    if (counterPetani) {
      counterPetani.textContent = visible === totalPetani
        ? totalPetani + ' petani · ' + totalTitik + ' titik'
        : visible + ' / ' + totalPetani + ' petani';
    }

    if (daftarKosong) daftarKosong.classList.toggle('hidden', visible !== 0);
    if (pesanKosong) {
      pesanKosong.textContent = totalPetani === 0
        ? 'Belum ada data usulan pada versi ini.'
        : 'Tidak ada titik yang cocok dengan pencarian atau filter.';
    }
    if (resetHeader) {
      resetHeader.classList.toggle('hidden', !q && filterAktif === 'semua');
    }

    refreshMarkers(q);
  }

  function setFilter(nilai) {
    filterAktif = nilai;
    document.querySelectorAll('[data-filter]').forEach(btn => {
      const aktif = btn.dataset.filter === nilai;
      btn.classList.toggle('is-active', aktif);
      btn.setAttribute('aria-pressed', aktif ? 'true' : 'false');
    });
    filterPetani();
  }

  function resetFilterPetani() {
    if (cariInput) cariInput.value = '';
    setFilter('semua');
  }

  document.querySelectorAll('[data-filter]').forEach(btn => {
    btn.addEventListener('click', () => setFilter(btn.dataset.filter));
  });
  document.querySelectorAll('[data-reset-filter]').forEach(btn => {
    btn.addEventListener('click', resetFilterPetani);
  });
  if (cariInput) cariInput.addEventListener('input', filterPetani);

  function highlightMarker(id) {
    selectedMarkers.forEach(marker => {
      marker.setStyle({ weight: 2, color: '#FFFFFF' });
    });
    selectedMarkers = markersById.get(id) || [];
    selectedMarkers.forEach(marker => {
      marker.setStyle({ weight: 3, color: '#F3C64F' });
      marker.bringToFront();
    });
  }

  function setSelectedRow(id, scrollKeBaris) {
    selectedId = id;
    rowsById.forEach((row, rowId) => {
      const aktif = rowId === id;
      row.classList.toggle('active-item', aktif);
      row.setAttribute('aria-pressed', aktif ? 'true' : 'false');
    });
    if (scrollKeBaris) {
      const row = rowsById.get(id);
      if (row) row.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
    highlightMarker(id);
  }

  function pilihPetani(id, dariDaftar) {
    const seq = ++popupSeq;
    setSelectedRow(id, !dariDaftar);
    if (!dariDaftar) return;

    const d = PETANI[id];
    const markers = markersById.get(id) || [];
    if (!d || !d.punya_koordinat || !markers.length) {
      map.closePopup();
      return;
    }
    const marker = markers[0];

    let sudahBuka = false;
    const bukaPopup = () => {
      if (sudahBuka || seq !== popupSeq) return;
      sudahBuka = true;
      const ll = marker.getLatLng();
      L.popup({ maxWidth: 300 })
        .setLatLng(ll)
        .setContent(popupHtml(d, marker.titikInfo, ll.lat, ll.lng))
        .openOn(map);
    };

    if (markers.length > 1) {
      map.flyToBounds(L.featureGroup(markers).getBounds(), { padding: [45, 45], maxZoom: 18, duration: 0.75 });
    } else {
      map.flyTo(marker.getLatLng(), 18, { duration: 0.75 });
    }
    map.once('moveend', () => window.setTimeout(bukaPopup, 120));
    window.setTimeout(bukaPopup, 1400);

    if (window.matchMedia('(max-width: 991px)').matches) {
      window.setTimeout(() => {
        document.querySelector('.map-pane')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }, 250);
    }
  }

  const daftar = document.getElementById('daftar-petani');
  if (daftar) {
    daftar.addEventListener('click', e => {
      const row = e.target.closest('[data-petani-row]');
      if (row) pilihPetani(Number(row.dataset.id), true);
    });
    daftar.addEventListener('keydown', e => {
      if (e.key !== 'Enter' && e.key !== ' ') return;
      const row = e.target.closest('[data-petani-row]');
      if (!row) return;
      e.preventDefault();
      pilihPetani(Number(row.dataset.id), true);
    });
  }

  function popupHtml(d, titik, lat, lng) {
    const statusTitik = titik?.status_koord || (d.dalam ? 'Dalam Peta PS' : 'Luar Peta PS');
    const badgeSK = d.status_sk === 'Sesuai SK PS'
      ? '<span class="pop-badge pop-ok">Sesuai SK PS</span>'
      : '<span class="pop-badge pop-bad">Belum Sesuai SK PS</span>';
    const badgeSpasial = statusTitik === 'Dalam Peta PS'
      ? '<span class="pop-badge pop-ok">Dalam Peta PS</span>'
      : '<span class="pop-badge pop-bad">Luar Peta PS</span>';
    const infoTitik = titik && titik.jumlah_titik > 1
      ? `<div class="text-[10px] font-semibold text-forest-900 bg-forest-50 border border-forest-100 px-1.5 py-0.5 inline-block mb-1">Titik ${titik.urutan} dari ${titik.jumlah_titik}${titik.utama ? ' (utama)' : ''}</div>`
      : '';
    const mirip = d.nama_mirip && d.kemiripan
      ? `<div class="mt-1 text-[11px] text-ink-muted">Kemiripan SK (${escHtml(parseFloat(d.kemiripan).toFixed(1))}%): <b>${escHtml(d.nama_mirip)}</b></div>`
      : '';
    const catatan = d.catatan
      ? `<div class="mt-1 border-l-2 border-kadaster-border bg-kadaster-light px-2 py-1 text-[11px] italic text-ink">${escHtml(d.catatan)}</div>`
      : '';
    const latVal = lat ?? d.lat;
    const lngVal = lng ?? d.lng;
    return `<div class="p-1">
      <div class="font-bold text-ink text-sm mb-1">${escHtml(d.no)} - ${escHtml(d.nama || 'Tanpa Nama')}</div>
      ${infoTitik}
      <div class="font-mono text-[11px] text-ink-muted mb-1">NIK: ${escHtml(d.nik || '-')}</div>
      <div class="text-[11px] text-ink-muted mb-1">${escHtml(d.desa_kec || 'Desa/Kec. -')} &middot; ${escHtml(d.petak_label)}</div>
      <div class="flex flex-wrap gap-1 my-1.5">${badgeSK}${badgeSpasial}</div>
      <div class="text-xs text-ink"><b>Luas:</b> <span class="font-mono font-bold">${escHtml(d.luas_fmt)} Ha</span></div>
      <div class="font-mono text-[10px] text-ink-muted mt-1 pt-1 border-t border-kadaster-border">Lat ${escHtml(latVal != null ? Number(latVal).toFixed(6) : '-')} &middot; Lng ${escHtml(lngVal != null ? Number(lngVal).toFixed(6) : '-')}</div>
      ${mirip}${catatan}
    </div>`;
  }

  function fokusAreaSk() {
    const bounds = (areaSkBounds && areaSkBounds.isValid())
      ? areaSkBounds
      : (semuaBounds && semuaBounds.isValid() ? semuaBounds : null);
    if (bounds) map.flyToBounds(bounds, { padding: [30, 30], maxZoom: 17, duration: 0.7 });
  }

  document.getElementById('btn-fit-sk')?.addEventListener('click', fokusAreaSk);

  cluster = L.markerClusterGroup({ disableClusteringAtZoom: 16, maxClusterRadius: 45 });
  semuaBounds = L.latLngBounds([]);

  fetch(`peta_data.php?kth_id=${KTHID}&v=${VERSI}`)
    .then(r => r.json())
    .then(data => {
      if (data.polygon) {
        const polyLayer = L.geoJSON(data.polygon, {
          style: { color: '#1B382B', weight: 2.5, fillColor: '#52B788', fillOpacity: 0.22, dashArray: '6 4' }
        }).addTo(map);
        polyLayer.bindTooltip('Batas Areal Persetujuan PS', { sticky: true, className: 'text-xs' });
        const b = polyLayer.getBounds();
        if (b.isValid()) {
          areaSkBounds = b;
          semuaBounds.extend(b.getSouthWest());
          semuaBounds.extend(b.getNorthEast());
        }
      }

      if (data.titik && data.titik.features) {
        data.titik.features.forEach(f => {
          if (!f.geometry || !f.geometry.coordinates) return;
          const [lng, lat] = f.geometry.coordinates;
          const d = PETANI[f.properties.id];
          if (!d) return;

          const dalamTitik = f.properties.status_koord === 'Dalam Peta PS';
          const warna = !dalamTitik ? '#9E2A2B' : (d.luas_lebih ? '#B45309' : '#1D5C3A');
          const marker = L.circleMarker([lat, lng], {
            radius: 7,
            color: '#FFFFFF',
            weight: 2,
            fillColor: warna,
            fillOpacity: 0.95
          });
          marker.titikInfo = f.properties;
          marker.bindPopup(popupHtml(d, f.properties, lat, lng), { maxWidth: 300 });
          marker.on('click', () => pilihPetani(d.id, false));
          if (!markersById.has(d.id)) markersById.set(d.id, []);
          markersById.get(d.id).push(marker);
          semuaBounds.extend([lat, lng]);
        });
      }

      map.addLayer(cluster);
      if (semuaBounds.isValid()) {
        map.fitBounds(semuaBounds, { padding: [30, 30], maxZoom: 17 });
      } else {
        map.setView([-7.15, 111.88], 12);
      }
      filterPetani();
    })
    .catch(err => {
      console.error('Gagal memuat GeoJSON peta:', err);
      const box = document.getElementById('peta-error');
      if (box) {
        box.textContent = 'Gagal memuat layer peta: ' + err.message;
        box.classList.remove('hidden');
        box.classList.add('flex');
      }
    });

  filterPetani();
});
</script>

<?php layout_foot(); ?>
