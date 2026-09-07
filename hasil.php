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
$jmlMasalah = $hitung['tidak'] + $hitung['luar'];

layout_head('Hasil Verifikasi Spasial & Yuridis — ' . $k['nama_kth']);

// Leaflet CSS
echo '<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">';
echo '<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css">';
echo '<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css">';
echo '<style>
#peta-verifikasi { height: 490px; border-radius: 6px; overflow: hidden; background: #E5E7EB; border: 1px solid #DDD5C7; }
.leaflet-popup-content { font-size: 12px; line-height: 1.5; min-width: 230px; font-family: var(--font-sans); }
.tr-petani { cursor: pointer; transition: background-color .15s ease; }
.tr-petani:hover td { background-color: #F6F2E9 !important; }
.tr-aktif td { background-color: #E8EDE8 !important; }
.badge-merah { background-color: #FDF2F2; color: #9E2A2B; border: 1px solid #F8B4B4; font-size: 11px; font-weight: 600; padding: 2px 6px; border-radius: 4px; display: inline-block; }
.badge-hijau { background-color: #EEF7F2; color: #1D5C3A; border: 1px solid #A3D4B6; font-size: 11px; font-weight: 600; padding: 2px 6px; border-radius: 4px; display: inline-block; }
.badge-oranye { background-color: #FEF7EE; color: #B45309; border: 1px solid #FCD39D; font-size: 11px; font-weight: 600; padding: 2px 6px; border-radius: 4px; display: inline-block; }
.badge-abu { background-color: #F1EFEA; color: #635E55; border: 1px solid #DDD5C7; font-size: 11px; font-weight: 600; padding: 2px 6px; border-radius: 4px; display: inline-block; }
</style>';

wizard(3);
?>

<!-- ═══ HEADER KASUS & BILAH AUDIT SPASIAL ═══ -->
<div class="doc-card p-6 mb-5 fade-in">
  <div class="flex flex-wrap items-start justify-between gap-4 pb-5 border-b border-cadastral">
    <div>
      <div class="flex items-center gap-2 mb-1">
        <span class="text-[11px] font-semibold tracking-wider text-forest-subtle uppercase">Tahap 3 dari 4 — Hasil Uji Spasial & Yuridis</span>
        <span class="text-slate-300">·</span>
        <span class="text-[11px] text-ink-muted">Tahun Usulan <?= e($k['tahun_usulan'] ?? date('Y')) ?></span>
      </div>
      <h2 class="font-serif text-2xl font-bold text-forest-ink">
        <?= e($k['nama_kth']) ?>
      </h2>
      <p class="text-xs text-ink-muted mt-1 flex flex-wrap items-center gap-3">
        <span><b>No. SK:</b> <?= e($k['nomor_sk'] ?: 'Belum tercatat') ?></span>
        <span class="text-slate-300">·</span>
        <span><b>Desa/Kec.:</b> <?= e($k['desa'] ?: '-') ?>, <?= e($k['kecamatan'] ?: '-') ?></span>
        <span class="text-slate-300">·</span>
        <span><b>Pemegang Izin:</b> <?= e($k['nama_pengurus'] ?: '-') ?></span>
      </p>
    </div>

    <div class="flex flex-wrap items-center gap-2">
      <a href="konfirmasi_sk.php?kth_id=<?= $kthId ?>" class="btn-secondary px-3.5 py-2 text-xs inline-flex items-center gap-1.5 font-medium">
        <svg class="w-3.5 h-3.5 text-ink-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        Kembali ke Konfirmasi SK
      </a>
      <form action="verifikasi_ulang.php" method="post" class="inline m-0">
        <input type="hidden" name="kth_id" value="<?= $kthId ?>">
        <button type="submit" class="btn-secondary px-3.5 py-2 text-xs inline-flex items-center gap-1.5 font-medium hover:text-forest-dark" title="Hitung ulang kecocokan spasial dan nama">
          <svg class="w-3.5 h-3.5 text-ink-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
          Uji Ulang
        </button>
      </form>
      <a href="cetak_peta.php?kth_id=<?= $kthId ?>" target="_blank" class="btn-secondary px-3.5 py-2 text-xs inline-flex items-center gap-1.5 font-medium text-forest-subtle hover:text-forest-dark">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
        Cetak Format Peta
      </a>
      <a href="laporan.php?kth_id=<?= $kthId ?>" class="btn-primary px-4 py-2 text-xs inline-flex items-center gap-1.5 font-semibold">
        Lanjut ke Berita Acara
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
      </a>
    </div>
  </div>

  <!-- Bilah Status Audit Spasial & Rekonsiliasi (Administrative Ledger Bar) -->
  <div class="grid grid-cols-2 md:grid-cols-4 gap-px bg-cadastral border border-cadastral rounded-md overflow-hidden mt-5">
    <div class="bg-white p-4">
      <div class="text-[11px] font-semibold text-ink-muted uppercase tracking-wider">Total Terdaftar</div>
      <div class="mt-1 flex items-baseline gap-2">
        <span class="font-serif text-3xl font-bold text-forest-ink tabular-nums"><?= $hitung['total'] ?></span>
        <span class="text-xs text-ink-muted">pemohon</span>
      </div>
      <div class="text-[11px] text-ink-muted mt-1">Data usulan elektronik e-RDKK</div>
    </div>

    <div class="bg-white p-4">
      <div class="text-[11px] font-semibold text-status-sesuai uppercase tracking-wider flex items-center justify-between">
        <span>Sesuai SK Perhutanan</span>
        <span class="font-mono font-bold"><?= $pctSK ?>%</span>
      </div>
      <div class="mt-1 flex items-baseline gap-2">
        <span class="font-serif text-3xl font-bold text-status-sesuai tabular-nums"><?= $hitung['sesuai'] ?></span>
        <span class="text-xs text-ink-muted">dari <?= $hitung['total'] ?></span>
      </div>
      <div class="w-full bg-paper-tint h-1.5 rounded-sm overflow-hidden mt-2">
        <div class="bg-status-sesuai h-full" style="width: <?= $pctSK ?>%"></div>
      </div>
    </div>

    <div class="bg-white p-4">
      <div class="text-[11px] font-semibold text-forest-subtle uppercase tracking-wider flex items-center justify-between">
        <span>Titik Dalam Poligon PS</span>
        <span class="font-mono font-bold"><?= $pctPeta ?>%</span>
      </div>
      <div class="mt-1 flex items-baseline gap-2">
        <span class="font-serif text-3xl font-bold text-forest-subtle tabular-nums"><?= $hitung['dalam'] ?></span>
        <span class="text-xs text-ink-muted">dari <?= $hitung['total'] ?></span>
      </div>
      <div class="w-full bg-paper-tint h-1.5 rounded-sm overflow-hidden mt-2">
        <div class="bg-forest-subtle h-full" style="width: <?= $pctPeta ?>%"></div>
      </div>
    </div>

    <div class="bg-white p-4">
      <div class="text-[11px] font-semibold <?= $jmlMasalah > 0 ? 'text-status-revisi' : 'text-status-sesuai' ?> uppercase tracking-wider">
        <span>Status Diskrepansi</span>
      </div>
      <div class="mt-1 flex items-baseline gap-2">
        <span class="font-serif text-3xl font-bold <?= $jmlMasalah > 0 ? 'text-status-revisi' : 'text-status-sesuai' ?> tabular-nums"><?= $jmlMasalah ?></span>
        <span class="text-xs text-ink-muted">perlu atensi</span>
      </div>
      <div class="text-[11px] text-ink-muted mt-1 leading-tight">
        <?= $hitung['tidak'] ?> belum SK · <?= $hitung['luar'] ?> luar peta areal
      </div>
    </div>
  </div>
</div>

<!-- ═══ PETA SEBARAN SPASIAL PETANI ═══ -->
<div class="doc-card p-5 mb-5 fade-in">
  <div class="flex flex-wrap items-center justify-between gap-3 pb-3 border-b border-cadastral mb-3">
    <div>
      <h3 class="font-serif font-bold text-lg text-forest-ink flex items-center gap-2">
        <span>Peta Kadastral &amp; Sebaran Titik Usulan</span>
      </h3>
      <p class="text-xs text-ink-muted">
        Layer poligon batas persetujuan PS (garis hijau tua putus-putus) dioverlay dengan sebaran koordinat garapan petani.
      </p>
    </div>
    
    <div class="flex items-center gap-4 text-xs">
      <div class="hidden sm:flex items-center gap-3 text-ink-muted">
        <span class="flex items-center gap-1.5">
          <span style="display:inline-block;width:9px;height:9px;border-radius:2px;background:#1D5C3A;"></span>
          Sesuai &amp; Dalam
        </span>
        <span class="flex items-center gap-1.5">
          <span style="display:inline-block;width:9px;height:9px;border-radius:2px;background:#9E2A2B;"></span>
          Belum SK
        </span>
        <span class="flex items-center gap-1.5">
          <span style="display:inline-block;width:9px;height:9px;border-radius:2px;background:#B45309;"></span>
          Luar Peta PS
        </span>
      </div>
      <a href="cetak_peta.php?kth_id=<?= $kthId ?>" target="_blank" class="btn-secondary px-3 py-1.5 text-xs inline-flex items-center gap-1 font-medium">
        <svg class="w-3.5 h-3.5 text-ink-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
        Buka Lembar Cetak
      </a>
    </div>
  </div>

  <div id="peta-verifikasi"></div>

  <div id="peta-tanpa-koord" class="mt-3 p-3 bg-paper-tint border border-cadastral rounded-md hidden">
    <p class="text-xs font-semibold text-status-revisi mb-1.5 flex items-center gap-1.5">
      <svg class="w-4 h-4 text-status-revisi" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
      Petani tanpa data koordinat lintang/bujur (tidak dapat diproyeksikan ke peta):
    </p>
    <div id="list-tanpa-koord" class="flex flex-wrap gap-1.5 text-xs"></div>
  </div>
</div>

<!-- ═══ TABEL REGISTER HASIL UJI ═══ -->
<div x-data="{ filter: 'semua', cari: '' }" class="doc-card overflow-hidden fade-in">
  <div class="flex flex-wrap gap-3 p-4 border-b border-cadastral bg-paper-tint items-center justify-between">
    <div class="flex flex-wrap items-center gap-3">
      <div class="flex items-center gap-2">
        <label class="text-xs font-semibold text-ink-muted">Filter Audit:</label>
        <select x-model="filter" class="border border-cadastral rounded px-3 py-1.5 text-xs font-medium bg-white text-forest-ink focus:border-forest-dark outline-none">
          <option value="semua">Semua Usulan (<?= count($rows) ?>)</option>
          <option value="tidak-sk">Belum Sesuai SK (<?= $hitung['tidak'] ?>)</option>
          <option value="luar">Luar Peta PS (<?= $hitung['luar'] ?>)</option>
          <option value="bermasalah">Semua Masalah (<?= $jmlMasalah ?>)</option>
        </select>
      </div>
      <div class="relative w-64">
        <svg class="w-3.5 h-3.5 text-ink-muted absolute left-2.5 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        <input x-model="cari" placeholder="Cari nama / NIK pemohon…" class="w-full border border-cadastral rounded pl-8 pr-3 py-1.5 text-xs bg-white text-forest-ink focus:border-forest-dark outline-none">
      </div>
    </div>
    <div class="text-[11px] text-ink-muted flex items-center gap-1.5">
      <svg class="w-3.5 h-3.5 text-ink-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
      <span>Klik baris untuk menyorot titik di peta. Catatan disimpan otomatis.</span>
    </div>
  </div>

  <div class="overflow-x-auto">
    <table class="min-w-full text-xs">
      <thead>
        <tr class="bg-[#F6F2E9] border-b border-cadastral text-forest-ink">
          <th class="px-3 py-2.5 text-center font-semibold uppercase tracking-wider w-12 text-[11px]">No</th>
          <th class="px-3 py-2.5 text-left font-semibold uppercase tracking-wider text-[11px]">Nama Pemohon (Usulan)</th>
          <th class="px-3 py-2.5 text-left font-semibold uppercase tracking-wider text-[11px]">NIK</th>
          <th class="px-3 py-2.5 text-center font-semibold uppercase tracking-wider text-[11px]">Status SK PS</th>
          <th class="px-3 py-2.5 text-center font-semibold uppercase tracking-wider text-[11px]">Posisi Spasial</th>
          <th class="px-3 py-2.5 text-left font-semibold uppercase tracking-wider text-[11px] min-w-[280px]">Catatan Verifikator</th>
          <th class="px-2 py-2.5 text-center font-semibold uppercase tracking-wider w-14 text-[11px]">Sorot</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $idx => $r):
        $dsSk = ($r['status_sk'] ?? '') === 'Sesuai SK PS' ? 'ok' : 'tidak';
        $dsKo = ($r['status_koordinat'] ?? '') === 'Dalam Peta PS' ? 'dalam' : 'luar';
        $rowBg = $idx % 2 === 0 ? 'bg-white' : 'bg-[#FAF8F5]';
      ?>
        <tr class="tr-petani <?= $rowBg ?> hairline-row"
          id="baris-<?= (int)$r['id'] ?>"
          data-uid="<?= (int)$r['id'] ?>"
          data-sk="<?= $dsSk ?>" data-ko="<?= $dsKo ?>" data-cari="<?= e(mb_strtolower(($r['nama'] ?? '') . ' ' . ($r['nik'] ?? ''), 'UTF-8')) ?>"
          x-show="(filter === 'semua' || (filter === 'tidak-sk' && $el.dataset.sk !== 'ok') || (filter === 'luar' && $el.dataset.ko !== 'dalam') || (filter === 'bermasalah' && ($el.dataset.sk !== 'ok' || $el.dataset.ko !== 'dalam'))) && $el.dataset.cari.includes(cari.toLowerCase())">
          <td class="px-3 py-2 text-center text-ink-muted tabular-nums">
            <?= e($r['no_urut'] ?? ($idx + 1)) ?>
          </td>
          <td class="px-3 py-2 font-medium text-forest-ink">
            <?= e($r['nama']) ?>
          </td>
          <td class="px-3 py-2 font-mono text-[11px] text-ink-muted">
            <?= e($r['nik']) ?>
          </td>
          <td class="px-3 py-2 text-center">
            <?= badge_sk((string)($r['status_sk'] ?? '')) ?>
          </td>
          <td class="px-3 py-2 text-center">
            <?= badge_koord((string)($r['status_koordinat'] ?? '')) ?>
          </td>
          <td class="px-3 py-2" x-data="{ edit: false, val: <?= e(json_encode((string)($r['catatan'] ?? ''), JSON_UNESCAPED_UNICODE)) ?>, saved: true }">
            <template x-if="!edit">
              <div @click="edit = true" class="cursor-text hover:bg-paper-tint rounded px-2 py-1 min-h-[1.75rem] flex items-center justify-between group border border-transparent hover:border-cadastral" title="Klik untuk mengedit catatan verifikasi">
                <span class="text-xs text-forest-ink" x-text="val || '—'"></span>
                <span class="text-[10px] text-ink-muted opacity-0 group-hover:opacity-100 italic">Edit</span>
                <span x-show="!saved" class="text-[10px] text-status-revisi ml-1">…menyimpan</span>
              </div>
            </template>
            <template x-if="edit">
              <div class="flex gap-1.5 items-center">
                <input type="text" x-model="val" @keydown.enter="edit = false; saved = false; fetch('proses_catatan.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: 'hasil_id=<?= (int)$r['hasil_id'] ?>&catatan=' + encodeURIComponent(val)}).then(r => r.json()).then(j => { saved = true; if (!j.ok) alert('Gagal simpan: ' + j.msg); }).catch(e => { saved = true; alert('Gagal simpan'); })"
                  class="w-full border border-forest-dark rounded px-2 py-1 text-xs outline-none bg-white text-forest-ink">
                <button @click="edit = false; saved = false; fetch('proses_catatan.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: 'hasil_id=<?= (int)$r['hasil_id'] ?>&catatan=' + encodeURIComponent(val)}).then(r => r.json()).then(j => { saved = true; if (!j.ok) alert('Gagal simpan: ' + j.msg); }).catch(e => { saved = true; alert('Gagal simpan'); })"
                  class="px-2 py-1 rounded bg-forest-dark text-white text-[11px] font-semibold hover:bg-forest-subtle">Simpan</button>
              </div>
            </template>
          </td>
          <td class="px-2 py-2 text-center">
            <button onclick="zoomKePetani(<?= (int)$r['id'] ?>)" title="Sorot koordinat di peta"
              class="w-6 h-6 rounded bg-paper-tint text-forest-subtle hover:bg-forest-dark hover:text-white inline-flex items-center justify-center transition-colors">
              <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/></svg>
            </button>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?>
        <tr>
          <td colspan="7" class="px-4 py-8 text-center text-ink-muted">
            Belum ada data usulan untuk kasus ini.
          </td>
        </tr>
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
    hijau:  { fill: '#1D5C3A', border: '#142219' },
    merah:  { fill: '#9E2A2B', border: '#5C1415' },
    oranye: { fill: '#B45309', border: '#78350F' },
    abu:    { fill: '#7D664E', border: '#4A3B2C' },
  };

  function buatIkon(warna, label) {
    const c = WARNA[warna] || WARNA.abu;
    const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="32" viewBox="0 0 26 34">
      <path d="M13 0C5.82 0 0 5.82 0 13c0 9.75 13 21 13 21S26 22.75 26 13C26 5.82 20.18 0 13 0z"
        fill="${c.fill}" stroke="${c.border}" stroke-width="1.5"/>
      <text x="13" y="17" text-anchor="middle" font-size="10" font-weight="bold"
        fill="#fff" font-family="Plus Jakarta Sans, sans-serif">${label}</text>
    </svg>`;
    return L.divIcon({ html: svg, className: '', iconSize: [24, 32], iconAnchor: [12, 32], popupAnchor: [0, -30] });
  }

  function badgeHtml(teks, kls) { return `<span class="badge-${kls}">${teks}</span>`; }

  const peta = L.map('peta-verifikasi', { zoomControl: true });
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap kontributor', maxZoom: 19,
  }).addTo(peta);

  const cluster = L.markerClusterGroup({ disableClusteringAtZoom: 16, maxClusterRadius: 50 });
  const markerMap = {};

  fetch(`peta_data.php?kth_id=${KTHID}`)
    .then(r => r.json())
    .then(data => {
      const bounds = [];

      if (data.polygon) {
        const polyLayer = L.geoJSON(data.polygon, {
          style: { color: '#1B382B', weight: 2.5, fillColor: '#264E3D', fillOpacity: 0.22, dashArray: '6 4' },
        }).addTo(peta);
        polyLayer.eachLayer(l => { if (l.getBounds) { const b = l.getBounds(); if (b.isValid()) bounds.push(b); } });
        polyLayer.bindTooltip('Batas Areal Persetujuan PS', { sticky: true, className: 'text-xs' });
      }

      const tanpaKoord = [];
      data.titik.features.forEach(f => {
        const p = f.properties;
        if (!f.geometry) { tanpaKoord.push(p); return; }
        const [lng, lat] = f.geometry.coordinates;
        const bSK = p.status_sk === 'Sesuai SK PS' ? badgeHtml('Sesuai SK','hijau') : badgeHtml('Belum Sesuai SK','merah');
        const bKoord = p.status_koord === 'Dalam Peta PS' ? badgeHtml('Dalam Peta PS','hijau') : badgeHtml('Luar Peta PS','oranye');
        const mirip = p.nama_mirip && p.kemiripan
          ? `<div class="mt-1 text-ink-muted text-xs">Kemiripan nama SK (${parseFloat(p.kemiripan).toFixed(1)}%): <b>${p.nama_mirip}</b></div>` : '';
        const catatan = p.catatan ? `<div class="mt-1 text-forest-ink italic text-xs border-l-2 border-cadastral pl-2 py-0.5 bg-paper-tint">${p.catatan}</div>` : '';
        const popup = `<div class="p-1">
            <div class="font-bold text-forest-ink text-sm mb-1">${p.no ? '#'+p.no+' — ' : ''}${p.nama}</div>
            <div class="text-ink-muted font-mono text-[11px] mb-1">NIK: ${p.nik}</div>
            <div class="text-ink-muted text-xs mb-1">${[p.desa, p.kecamatan].filter(Boolean).join(', ')}</div>
            <div class="flex gap-1 flex-wrap my-1.5">${bSK} ${bKoord}</div>
            ${mirip}${catatan}
            <div class="text-ink-muted font-mono text-[10px] mt-1 pt-1 border-t border-cadastral">📍 ${lat.toFixed(6)}, ${lng.toFixed(6)}</div>
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
          tag.className = 'bg-white border border-cadastral text-ink-muted rounded px-2 py-0.5 font-medium text-[11px]';
          tag.textContent = (p.no ? '#'+p.no+' ' : '') + p.nama;
          list.appendChild(tag);
        });
      }
    })
    .catch(err => {
      document.getElementById('peta-verifikasi').innerHTML =
        '<div class="flex items-center justify-center h-full text-ink-muted text-xs">Gagal memuat layer peta: ' + err.message + '</div>';
    });

  window.zoomKePetani = function(uid) {
    const marker = markerMap[uid];
    if (!marker) { alert('Petani ini tidak memiliki data koordinat lintang/bujur.'); return; }
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
