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

layout_head('Peta Spasial & Titik — ' . ($kth['nama_kth'] ?? ''));

// Sub-Navbar Navigasi Terpadu
layout_kth_subnav($kth, 'peta', $versiAktif, $daftarVersi);
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css">
<style>
#peta-interaktif { height: 600px; border-radius: 6px; overflow: hidden; background: #E5E7EB; border: 1px solid #DDD5C7; }
.leaflet-popup-content { font-size: 12px; line-height: 1.5; min-width: 240px; font-family: "Plus Jakarta Sans", sans-serif; }
</style>

<div class="doc-card p-5 mb-6">
  <div class="flex flex-wrap items-center justify-between gap-3 pb-3 border-b border-kadaster-border mb-4">
    <div>
      <h3 class="font-serif font-bold text-lg text-ink">Peta Spasial Areal PS & Titik Pemohon</h3>
      <p class="text-xs text-ink-muted mt-0.5">
        Visualisasi poligon batas Perhutanan Sosial dan sebaran titik koordinat petani pengusul pupuk.
      </p>
    </div>
    <div class="flex items-center gap-2">
      <a href="cetak_peta.php?kth_id=<?= $kthId ?>&v=<?= $versiAktif ?>" target="_blank" rel="noopener"
         class="btn-kadaster px-3.5 py-1.5 text-xs font-semibold inline-flex items-center gap-1.5 text-forest-900 hover:bg-forest-100">
        <svg class="w-3.5 h-3.5 text-forest-900" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
        <span>Buka Format Cetak Peta Resmi</span>
      </a>
      <a href="hasil.php?kth_id=<?= $kthId ?>&v=<?= $versiAktif ?>"
         class="btn-forest px-3.5 py-1.5 text-xs font-semibold inline-flex items-center gap-1.5">
        <span>Lihat Tabel Hasil</span>
        <span>→</span>
      </a>
    </div>
  </div>

  <div id="peta-interaktif"></div>

  <!-- Legenda Peta -->
  <div class="flex flex-wrap items-center justify-between gap-3 pt-3 border-t border-kadaster-border mt-4 text-xs">
    <div class="flex flex-wrap items-center gap-4">
      <span class="font-semibold text-ink">Legenda Status:</span>
      <span class="inline-flex items-center gap-1.5 text-audit-valid font-medium">
        <span class="w-3 h-3 rounded-full bg-emerald-600 inline-block"></span> Sesuai SK & Dalam Peta
      </span>
      <span class="inline-flex items-center gap-1.5 text-amber-700 font-medium">
        <span class="w-3 h-3 rounded-full bg-amber-500 inline-block"></span> Luar Peta (Perlu Koreksi)
      </span>
      <span class="inline-flex items-center gap-1.5 text-audit-revisi font-medium">
        <span class="w-3 h-3 rounded-full bg-red-600 inline-block"></span> Belum Masuk SK
      </span>
      <span class="inline-flex items-center gap-1.5 text-forest-900 font-medium">
        <span class="w-3.5 h-2.5 bg-forest-900/30 border border-forest-900 inline-block"></span> Batas Poligon PS
      </span>
    </div>
    <div class="text-[11px] text-ink-muted">
      Klik titik marker untuk melihat rincian NIK, nama, desa, dan rekomendasi.
    </div>
  </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const osm = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '© OpenStreetMap' });
  const sat = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', { maxZoom: 19, attribution: '© Esri Satellite' });

  const map = L.map('peta-interaktif', { layers: [sat] }).setView([-7.15, 111.88], 12);
  L.control.layers({ 'Citra Satelit': sat, 'Peta Jalan (OSM)': osm }, null, { position: 'topright' }).addTo(map);

  const cluster = L.markerClusterGroup({ maxClusterRadius: 35 });

  fetch('peta_data.php?kth_id=<?= $kthId ?>&v=<?= $versiAktif ?>')
    .then(r => r.json())
    .then(data => {
      const bounds = [];

      // Render Polygon
      if (data.polygon) {
        const polyLayer = L.geoJSON(data.polygon, {
          style: { color: '#1B382B', weight: 2.5, fillColor: '#52B788', fillOpacity: 0.25 }
        }).addTo(map);
        bounds.push(polyLayer.getBounds());
      }

      // Render Markers
      if (data.titik && data.titik.features) {
        data.titik.features.forEach(f => {
          if (!f.geometry || !f.geometry.coordinates) return;
          const [lng, lat] = f.geometry.coordinates;
          const p = f.properties;

          let colorHex = '#1D5C3A'; // hijau
          if (p.warna === 'merah') colorHex = '#DC2626';
          else if (p.warna === 'oranye') colorHex = '#D97706';

          const marker = L.circleMarker([lat, lng], {
            radius: 6,
            color: '#FFFFFF',
            weight: 1.5,
            fillColor: colorHex,
            fillOpacity: 0.95
          });

          marker.bindPopup(`
            <div class="p-1">
              <div class="font-bold text-sm text-ink mb-1">${p.nama || 'Tanpa Nama'}</div>
              <div class="text-xs text-ink-muted mb-2">NIK: <span class="font-mono font-bold text-ink">${p.nik || '-'}</span></div>
              <div class="space-y-1 text-xs">
                <div><b>Status SK:</b> <span class="${p.status_sk === 'Sesuai SK PS' ? 'text-green-700 font-bold' : 'text-red-700 font-bold'}">${p.status_sk}</span></div>
                <div><b>Status Spasial:</b> <span class="${p.status_koord === 'Dalam Peta PS' ? 'text-green-700 font-bold' : 'text-amber-700 font-bold'}">${p.status_koord}</span></div>
                ${p.desa ? `<div><b>Desa/Kec.:</b> ${p.desa}, ${p.kecamatan || ''}</div>` : ''}
                ${p.catatan ? `<div class="p-1.5 bg-amber-50 border border-amber-200 rounded text-[11px] text-amber-900 mt-1">${p.catatan}</div>` : ''}
              </div>
            </div>
          `);

          cluster.addLayer(marker);
          bounds.push(L.latLngBounds([[lat, lng], [lat, lng]]));
        });
      }

      map.addLayer(cluster);

      if (bounds.length > 0) {
        let fullBounds = bounds[0];
        for (let i = 1; i < bounds.length; i++) {
          fullBounds.extend(bounds[i]);
        }
        map.fitBounds(fullBounds, { padding: [30, 30] });
      }
    })
    .catch(err => {
      console.error('Gagal memuat GeoJSON peta:', err);
    });
});
</script>

<?php layout_foot(); ?>
