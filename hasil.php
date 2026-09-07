<?php
// Langkah 3 — Hasil verifikasi per baris, catatan editable inline + peta interaktif.
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/layout.php';

$kthId = (int)($_GET['kth_id'] ?? 0);
$pdo = db();
$kth = $pdo->prepare('SELECT * FROM kth WHERE id = ?');
$kth->execute([$kthId]);
$k = $kth->fetch();
if (!$k) { flash_set('error', 'Kasus tidak ditemukan.'); header('Location: index.php'); exit; }

$q = $pdo->prepare('SELECT u.*, h.status_sk, h.status_koordinat, h.catatan, h.id AS hasil_id
  FROM usulan_pupuk u LEFT JOIN hasil_verifikasi h ON h.usulan_id = u.id
  WHERE u.kth_id = ? ORDER BY COALESCE(u.no_urut, u.id)');
$q->execute([$kthId]);
$rows = $q->fetchAll();

$hitung = ['total' => count($rows), 'sesuai' => 0, 'tidak' => 0, 'dalam' => 0, 'luar' => 0];
foreach ($rows as $r) {
    if (($r['status_sk'] ?? '') === 'Sesuai SK PS') $hitung['sesuai']++; else $hitung['tidak']++;
    if (($r['status_koordinat'] ?? '') === 'Dalam Peta PS') $hitung['dalam']++; else $hitung['luar']++;
}
$pctSK = $hitung['total'] > 0 ? round($hitung['sesuai'] / $hitung['total'] * 100) : 0;
$pctPeta = $hitung['total'] > 0 ? round($hitung['dalam'] / $hitung['total'] * 100) : 0;

layout_head('Hasil Verifikasi — ' . $k['nama_kth']);
// Leaflet CSS
echo '<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">';
echo '<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css">';
echo '<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css">';
echo '<style>
#peta-verifikasi{height:480px;border-radius:1rem;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.1)}
.leaflet-popup-content{font-size:13px;line-height:1.6;min-width:220px}
.badge-merah{background:#fee2e2;color:#991b1b;padding:2px 8px;border-radius:9999px;font-size:11px;font-weight:600}
.badge-hijau{background:#d1fae5;color:#065f46;padding:2px 8px;border-radius:9999px;font-size:11px;font-weight:600}
.badge-oranye{background:#ffedd5;color:#9a3412;padding:2px 8px;border-radius:9999px;font-size:11px;font-weight:600}
.tr-petani{cursor:pointer;transition:background .15s ease}
.tr-petani:hover td{background:linear-gradient(90deg,#f0fdf4,#f8fafc)!important}
.tr-aktif td{background:#d1fae5!important}
</style>';
wizard(3);
?>

<!-- ═══ Header + Statistik Cards ═══ -->
<div class="glass-card rounded-2xl p-6 mb-5 fade-in">
  <div class="flex flex-wrap items-center justify-between gap-4 mb-5">
    <div>
      <h2 class="text-xl font-bold text-slate-800 flex items-center gap-2">
        <span class="w-8 h-8 rounded-lg bg-gradient-to-br from-emerald-500 to-teal-700 text-white flex items-center justify-center text-sm">3</span>
        Hasil Verifikasi
      </h2>
      <p class="text-sm text-slate-500 mt-1 ml-10">
        <b class="text-slate-700"><?= e($k['nama_kth']) ?></b> · SK: <?= e($k['nomor_sk'] ?? '-') ?>
      </p>
    </div>
    <div class="flex flex-wrap gap-2">
      <a href="konfirmasi_sk.php?kth_id=<?= $kthId ?>" class="btn-secondary px-4 py-2 rounded-xl text-xs inline-flex items-center gap-1.5">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        Kembali
      </a>
      <form action="verifikasi_ulang.php" method="post" class="inline">
        <input type="hidden" name="kth_id" value="<?= $kthId ?>">
        <button class="btn-secondary px-4 py-2 rounded-xl text-xs inline-flex items-center gap-1.5">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
          Hitung Ulang
        </button>
      </form>
      <a href="cetak_peta.php?kth_id=<?= $kthId ?>" target="_blank" class="btn-secondary px-4 py-2 rounded-xl text-xs inline-flex items-center gap-1.5 hover:text-emerald-700">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
        Cetak Peta
      </a>
      <a href="laporan.php?kth_id=<?= $kthId ?>" class="btn-primary px-4 py-2 rounded-xl text-xs inline-flex items-center gap-1.5">
        Lanjut ke Laporan
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
      </a>
    </div>
  </div>

  <!-- Stat cards -->
  <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
    <div class="bg-gradient-to-br from-slate-50 to-slate-100 rounded-xl p-4 border border-slate-200/60">
      <div class="text-xs text-slate-500 font-semibold uppercase tracking-wider">Total Petani</div>
      <div class="text-3xl font-extrabold text-slate-800 mt-1"><?= $hitung['total'] ?></div>
    </div>
    <div class="bg-gradient-to-br from-emerald-50 to-emerald-100 rounded-xl p-4 border border-emerald-200/60">
      <div class="text-xs text-emerald-600 font-semibold uppercase tracking-wider">Sesuai SK</div>
      <div class="text-3xl font-extrabold text-emerald-800 mt-1"><?= $hitung['sesuai'] ?></div>
      <div class="mt-2 h-1.5 bg-emerald-200 rounded-full overflow-hidden">
        <div class="h-full bg-emerald-500 rounded-full" style="width:<?= $pctSK ?>%"></div>
      </div>
      <div class="text-[10px] text-emerald-600 mt-1 font-medium"><?= $pctSK ?>% dari total</div>
    </div>
    <div class="bg-gradient-to-br from-sky-50 to-sky-100 rounded-xl p-4 border border-sky-200/60">
      <div class="text-xs text-sky-600 font-semibold uppercase tracking-wider">Dalam Peta</div>
      <div class="text-3xl font-extrabold text-sky-800 mt-1"><?= $hitung['dalam'] ?></div>
      <div class="mt-2 h-1.5 bg-sky-200 rounded-full overflow-hidden">
        <div class="h-full bg-sky-500 rounded-full" style="width:<?= $pctPeta ?>%"></div>
      </div>
      <div class="text-[10px] text-sky-600 mt-1 font-medium"><?= $pctPeta ?>% dari total</div>
    </div>
    <div class="bg-gradient-to-br from-red-50 to-orange-50 rounded-xl p-4 border border-red-200/60">
      <div class="text-xs text-red-600 font-semibold uppercase tracking-wider">Bermasalah</div>
      <div class="text-3xl font-extrabold text-red-800 mt-1"><?= $hitung['tidak'] + $hitung['luar'] ?></div>
      <div class="text-[10px] text-red-500 mt-1.5 font-medium">
        <?= $hitung['tidak'] ?> belum sesuai SK · <?= $hitung['luar'] ?> luar peta
      </div>
    </div>
  </div>
</div>

<!-- ═══ PETA INTERAKTIF ═══ -->
<div class="glass-card rounded-2xl p-5 mb-5 fade-in fade-in-delay-1">
  <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
    <h3 class="font-bold text-slate-700 flex items-center gap-2.5">
      <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-teal-500 to-emerald-600 text-white flex items-center justify-center text-sm">🗺️</div>
      Peta Sebaran Titik Petani
    </h3>
    <div class="flex items-center gap-3">
      <a href="cetak_peta.php?kth_id=<?= $kthId ?>" target="_blank" class="btn-secondary px-3 py-1.5 rounded-xl text-xs inline-flex items-center gap-1.5 font-medium hover:text-emerald-700 shadow-sm">
        <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
        Buka Mode Cetak
      </a>
      <div class="hidden sm:flex items-center gap-3 text-xs text-slate-500">
        <span class="flex items-center gap-1.5"><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#16a34a;box-shadow:0 0 0 2px #d1fae5"></span> Sesuai &amp; Dalam</span>
        <span class="flex items-center gap-1.5"><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#dc2626;box-shadow:0 0 0 2px #fee2e2"></span> Belum SK</span>
        <span class="flex items-center gap-1.5"><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#ea580c;box-shadow:0 0 0 2px #ffedd5"></span> Luar Peta</span>
      </div>
    </div>
  </div>
  <div id="peta-verifikasi"></div>
  <div id="peta-tanpa-koord" class="mt-3 hidden">
    <p class="text-xs text-slate-500 font-semibold mb-1.5 flex items-center gap-1">
      <svg class="w-3.5 h-3.5 text-amber-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
      Petani tanpa koordinat (tidak ditampilkan di peta):
    </p>
    <div id="list-tanpa-koord" class="flex flex-wrap gap-1.5 text-xs"></div>
  </div>
</div>

<!-- ═══ Tabel Verifikasi ═══ -->
<div x-data="{ filter: 'semua', cari: '' }" class="glass-card rounded-2xl overflow-hidden fade-in fade-in-delay-2">
  <div class="flex flex-wrap gap-3 p-4 border-b border-slate-200/60 text-sm items-center bg-gradient-to-r from-slate-50/80 to-white">
    <div class="flex items-center gap-2">
      <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
      <select x-model="filter" class="border border-slate-200 rounded-lg px-3 py-1.5 text-xs font-medium bg-white focus:border-emerald-500 outline-none smooth-all">
        <option value="semua">Semua</option>
        <option value="tidak-sk">Belum Sesuai SK</option>
        <option value="luar">Luar Peta</option>
        <option value="bermasalah">Bermasalah (SK/luar)</option>
      </select>
    </div>
    <div class="relative flex-1 max-w-xs">
      <svg class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
      <input x-model="cari" placeholder="Cari nama / NIK…" class="w-full border border-slate-200 rounded-lg pl-9 pr-3 py-1.5 text-xs bg-white focus:border-emerald-500 outline-none smooth-all">
    </div>
    <span class="text-slate-400 text-xs ml-auto">Catatan tersimpan otomatis saat keluar kolom.</span>
  </div>
  <div class="overflow-x-auto">
  <table class="min-w-full text-sm">
    <thead>
      <tr class="bg-gradient-to-r from-slate-100 to-slate-50 border-b border-slate-200">
        <th class="px-3 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider w-14">No</th>
        <th class="px-3 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Nama</th>
        <th class="px-3 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">NIK</th>
        <th class="px-3 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider">Status SK</th>
        <th class="px-3 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider">Koordinat</th>
        <th class="px-3 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider min-w-[260px]">Catatan</th>
        <th class="px-2 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider w-12">Peta</th>
      </tr>
    </thead>
    <tbody class="divide-y divide-slate-100">
    <?php foreach ($rows as $idx => $r):
      $dsSk = ($r['status_sk'] ?? '') === 'Sesuai SK PS' ? 'ok' : 'tidak';
      $dsKo = ($r['status_koordinat'] ?? '') === 'Dalam Peta PS' ? 'dalam' : 'luar';
      $rowBg = $idx % 2 === 0 ? 'bg-white' : 'bg-slate-50/40';
    ?>
      <tr class="tr-petani <?= $rowBg ?>"
        id="baris-<?= (int)$r['id'] ?>"
        data-uid="<?= (int)$r['id'] ?>"
        data-sk="<?= $dsSk ?>" data-ko="<?= $dsKo ?>" data-cari="<?= e(mb_strtolower(($r['nama'] ?? '') . ' ' . ($r['nik'] ?? ''), 'UTF-8')) ?>"
        x-show="(filter === 'semua' || (filter === 'tidak-sk' && $el.dataset.sk !== 'ok') || (filter === 'luar' && $el.dataset.ko !== 'dalam') || (filter === 'bermasalah' && ($el.dataset.sk !== 'ok' || $el.dataset.ko !== 'dalam'))) && $el.dataset.cari.includes(cari.toLowerCase())">
        <td class="px-3 py-2.5 text-center">
          <span class="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-slate-100 text-slate-600 text-xs font-bold"><?= e($r['no_urut'] ?? '') ?></span>
        </td>
        <td class="px-3 py-2.5 font-semibold text-slate-800"><?= e($r['nama']) ?></td>
        <td class="px-3 py-2.5 font-mono text-xs text-slate-600"><?= e($r['nik']) ?></td>
        <td class="px-3 py-2.5 text-center"><?= badge_sk((string)($r['status_sk'] ?? '')) ?></td>
        <td class="px-3 py-2.5 text-center"><?= badge_koord((string)($r['status_koordinat'] ?? '')) ?></td>
        <td class="px-3 py-2.5" x-data="{ edit: false, val: <?= e(json_encode((string)($r['catatan'] ?? ''), JSON_UNESCAPED_UNICODE)) ?>, saved: true }">
          <template x-if="!edit">
            <div @click="edit = true" class="cursor-text hover:bg-amber-50 rounded-lg px-2.5 py-1.5 min-h-[2rem] smooth-all" :title="'Klik untuk edit'">
              <span class="text-xs text-slate-600" x-text="val || '—'"></span>
              <span x-show="!saved" class="text-xs text-amber-600 ml-1">…menyimpan</span>
            </div>
          </template>
          <template x-if="edit">
            <div class="flex gap-1.5">
              <textarea x-model="val" rows="2" class="w-full border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs focus:border-emerald-500 outline-none smooth-all"></textarea>
              <button @click="edit = false; saved = false; fetch('proses_catatan.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: 'hasil_id=<?= (int)$r['hasil_id'] ?>&catatan=' + encodeURIComponent(val)}).then(r => r.json()).then(j => { saved = true; if (!j.ok) alert('Gagal simpan: ' + j.msg); }).catch(e => { saved = true; alert('Gagal simpan'); })"
                class="px-2.5 py-1 rounded-lg bg-emerald-600 text-white text-xs h-fit hover:bg-emerald-700 smooth-all font-medium">✓</button>
            </div>
          </template>
        </td>
        <td class="px-2 py-2.5 text-center">
          <button onclick="zoomKePetani(<?= (int)$r['id'] ?>)" title="Lihat di peta"
            class="w-7 h-7 rounded-lg bg-teal-50 text-teal-600 hover:bg-teal-100 flex items-center justify-center smooth-all mx-auto">
            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/></svg>
          </button>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?>
      <tr><td colspan="7" class="px-4 py-10 text-center text-slate-400">
        <div class="text-4xl mb-2 opacity-50">📊</div>
        Belum ada data usulan.
      </td></tr>
    <?php endif; ?>
    </tbody>
  </table>
  </div>
</div>

<!-- ═══ JS LEAFLET ═══ -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
<script>
(function () {
  const KTHID = <?= (int)$kthId ?>;
  const WARNA = {
    hijau:  { fill: '#16a34a', border: '#14532d' },
    merah:  { fill: '#dc2626', border: '#7f1d1d' },
    oranye: { fill: '#ea580c', border: '#7c2d12' },
    abu:    { fill: '#94a3b8', border: '#475569' },
  };

  function buatIkon(warna, label) {
    const c = WARNA[warna] || WARNA.abu;
    const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="26" height="34" viewBox="0 0 26 34">
      <path d="M13 0C5.82 0 0 5.82 0 13c0 9.75 13 21 13 21S26 22.75 26 13C26 5.82 20.18 0 13 0z"
        fill="${c.fill}" stroke="${c.border}" stroke-width="1.5"/>
      <text x="13" y="17" text-anchor="middle" font-size="11" font-weight="bold"
        fill="#fff" font-family="sans-serif">${label}</text>
    </svg>`;
    return L.divIcon({ html: svg, className: '', iconSize: [26, 34], iconAnchor: [13, 34], popupAnchor: [0, -32] });
  }

  function badgeHtml(teks, kls) { return `<span class="badge-${kls}">${teks}</span>`; }

  const peta = L.map('peta-verifikasi', { zoomControl: true });
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap', maxZoom: 19,
  }).addTo(peta);

  const cluster = L.markerClusterGroup({ disableClusteringAtZoom: 16, maxClusterRadius: 50 });
  const markerMap = {};

  fetch(`peta_data.php?kth_id=${KTHID}`)
    .then(r => r.json())
    .then(data => {
      const bounds = [];

      if (data.polygon) {
        const polyLayer = L.geoJSON(data.polygon, {
          style: { color: '#065f46', weight: 2.5, fillColor: '#d1fae5', fillOpacity: 0.35, dashArray: '6 3' },
        }).addTo(peta);
        polyLayer.eachLayer(l => { if (l.getBounds) { const b = l.getBounds(); if (b.isValid()) bounds.push(b); } });
        polyLayer.bindTooltip('Areal PS', { sticky: true, className: 'text-xs' });
      }

      const tanpaKoord = [];
      data.titik.features.forEach(f => {
        const p = f.properties;
        if (!f.geometry) { tanpaKoord.push(p); return; }
        const [lng, lat] = f.geometry.coordinates;
        const bSK = p.status_sk === 'Sesuai SK PS' ? badgeHtml('Sesuai SK','hijau') : badgeHtml('Belum Sesuai','merah');
        const bKoord = p.status_koord === 'Dalam Peta PS' ? badgeHtml('Dalam Peta','hijau') : badgeHtml('Luar Peta','oranye');
        const mirip = p.nama_mirip && p.kemiripan
          ? `<div class="mt-1 text-slate-500">Nama mirip (${parseFloat(p.kemiripan).toFixed(1)}%): <b>${p.nama_mirip}</b></div>` : '';
        const catatan = p.catatan ? `<div class="mt-1 text-slate-600 italic text-xs">${p.catatan}</div>` : '';
        const popup = `<div>
            <div class="font-bold text-slate-800 text-sm mb-1">${p.no ? '#'+p.no+' — ' : ''}${p.nama}</div>
            <div class="text-slate-500 font-mono text-xs mb-1">${p.nik}</div>
            <div class="text-slate-500 text-xs mb-1">${[p.desa, p.kecamatan].filter(Boolean).join(', ')}</div>
            <div class="flex gap-1 flex-wrap mb-1">${bSK} ${bKoord}</div>
            ${mirip}${catatan}
            <div class="text-slate-400 text-xs mt-1">📍 ${lat.toFixed(6)}, ${lng.toFixed(6)}</div>
          </div>`;

        const marker = L.marker([lat, lng], { icon: buatIkon(p.warna, p.no ? String(p.no) : '?') })
          .bindPopup(popup, { maxWidth: 300 });
        marker.on('click', () => {
          document.querySelectorAll('.tr-aktif').forEach(el => el.classList.remove('tr-aktif'));
          const baris = document.getElementById('baris-' + p.id);
          if (baris) { baris.classList.add('tr-aktif'); baris.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
        });
        cluster.addLayer(marker);
        markerMap[p.id] = marker;
        bounds.push(L.latLng(lat, lng));
      });

      peta.addLayer(cluster);
      if (bounds.length > 0) {
        const lb = L.latLngBounds(bounds.map(b => b instanceof L.LatLng ? b : b.getCenter()));
        if (lb.isValid()) peta.fitBounds(lb, { padding: [30, 30] });
      } else { peta.setView([-7.3, 111.5], 10); }

      if (tanpaKoord.length > 0) {
        document.getElementById('peta-tanpa-koord').classList.remove('hidden');
        const list = document.getElementById('list-tanpa-koord');
        tanpaKoord.forEach(p => {
          const tag = document.createElement('span');
          tag.className = 'bg-slate-100 text-slate-600 rounded-md px-2.5 py-1 font-medium';
          tag.textContent = (p.no ? '#'+p.no+' ' : '') + p.nama;
          list.appendChild(tag);
        });
      }
    })
    .catch(err => {
      document.getElementById('peta-verifikasi').innerHTML =
        '<div class="flex items-center justify-center h-full text-slate-400">Gagal memuat peta: ' + err.message + '</div>';
    });

  window.zoomKePetani = function(uid) {
    const marker = markerMap[uid];
    if (!marker) { alert('Petani ini tidak memiliki koordinat.'); return; }
    peta.flyTo(marker.getLatLng(), 17, { duration: 1 });
    setTimeout(() => marker.openPopup(), 1100);
    document.querySelectorAll('.tr-aktif').forEach(el => el.classList.remove('tr-aktif'));
    const baris = document.getElementById('baris-' + uid);
    if (baris) baris.classList.add('tr-aktif');
    document.getElementById('peta-verifikasi').scrollIntoView({ behavior: 'smooth', block: 'start' });
  };
})();
</script>
<?php layout_foot(); ?>
