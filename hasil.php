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

$qLoc = $pdo->prepare('SELECT desa, kecamatan FROM usulan_pupuk WHERE kth_id = ? AND (desa IS NOT NULL AND desa != "") LIMIT 1');
$qLoc->execute([$kthId]);
$loc = $qLoc->fetch() ?: [];
$namaDesa = !empty($loc['desa']) ? $loc['desa'] : (!empty($k['desa']) ? $k['desa'] : '');
$namaKec  = !empty($loc['kecamatan']) ? $loc['kecamatan'] : (!empty($k['kecamatan']) ? $k['kecamatan'] : '');

layout_head('Hasil Verifikasi Spasial & Yuridis — ' . ($k['nama_kth'] ?? ''));

// Leaflet CSS
echo '<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">';
echo '<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css">';
echo '<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css">';
echo '<style>
#peta-verifikasi { height: 490px; border-radius: 4px; overflow: hidden; background: #E5E7EB; border: 1px solid #DDD5C7; }
.leaflet-popup-content { font-size: 12px; line-height: 1.5; min-width: 230px; font-family: "Plus Jakarta Sans", sans-serif; }
.tr-petani { cursor: pointer; transition: background-color .15s ease; }
.tr-petani:hover td { background-color: #F6F2E9 !important; }
.tr-aktif td { background-color: #E8EDE8 !important; }
.badge-merah { background-color: #FDF2F2; color: #9E2A2B; border: 1px solid #F2B8B8; font-size: 11px; font-weight: 600; padding: 2px 6px; border-radius: 2px; display: inline-block; }
.badge-hijau { background-color: #F0F7F2; color: #1D5C3A; border: 1px solid #B2D8C0; font-size: 11px; font-weight: 600; padding: 2px 6px; border-radius: 2px; display: inline-block; }
.badge-oranye { background-color: #FEF9EE; color: #B45309; border: 1px solid #F6DBA5; font-size: 11px; font-weight: 600; padding: 2px 6px; border-radius: 2px; display: inline-block; }
.badge-abu { background-color: #F3EFE7; color: #57655B; border: 1px solid #DDD5C7; font-size: 11px; font-weight: 600; padding: 2px 6px; border-radius: 2px; display: inline-block; }
/* Koreksi koordinat */
#panel-koreksi { display:none; margin-top:10px; background:#FFFBEB; border:1px solid #F6DBA5; border-radius:6px; padding:14px 16px; font-size:12px; }
#panel-koreksi.aktif { display:flex; flex-wrap:wrap; align-items:center; gap:12px; }
.marker-draggable { filter: drop-shadow(0 0 6px rgba(180,83,9,0.8)); animation: pulse-oranye 1.2s infinite; }
@keyframes pulse-oranye { 0%,100%{filter:drop-shadow(0 0 4px rgba(180,83,9,.6))} 50%{filter:drop-shadow(0 0 10px rgba(180,83,9,1))} }
</style>';

wizard(3);
?>

<!-- ═══ HEADER KASUS & BILAH AUDIT SPASIAL ═══ -->
<div class="doc-card p-6 mb-5 fade-in">
  <div class="flex flex-wrap items-start justify-between gap-4 pb-5 border-b border-kadaster-border">
    <div>
      <div class="flex items-center gap-2 mb-1">
        <span class="text-[11px] font-semibold tracking-wider text-forest-700 uppercase">Tahap 3 dari 4 — Hasil Uji Spasial &amp; Yuridis</span>
        <span class="text-kadaster-border">·</span>
        <span class="text-[11px] text-ink-muted">Tahun Usulan <?= e($k['tahun_usulan'] ?? date('Y')) ?></span>
      </div>
      <h2 class="font-serif text-2xl font-bold text-ink">
        <?= e($k['nama_kth'] ?? '-') ?>
      </h2>
      <p class="text-xs text-ink-muted mt-1 flex flex-wrap items-center gap-3">
        <span><b>No. SK:</b> <?= e(!empty($k['nomor_sk']) ? $k['nomor_sk'] : 'Belum tercatat') ?></span>
        <?php if ($namaDesa || $namaKec): ?>
        <span class="text-kadaster-border">·</span>
        <span><b>Desa/Kec.:</b> <?= e(implode(', ', array_filter([$namaDesa, $namaKec]))) ?></span>
        <?php endif; ?>
        <?php if (!empty($k['nama_kph'])): ?>
        <span class="text-kadaster-border">·</span>
        <span><b>KPH:</b> <?= e($k['nama_kph']) ?></span>
        <?php endif; ?>
        <?php if (!empty($k['luas_areal'])): ?>
        <span class="text-kadaster-border">·</span>
        <span><b>Luas SK:</b> <?= e(number_format((float)$k['luas_areal'], 2, ',', '.')) ?> Ha</span>
        <?php endif; ?>
      </p>
    </div>

    <div class="flex flex-wrap items-center gap-2">
      <a href="konfirmasi_sk.php?kth_id=<?= $kthId ?>" class="btn-kadaster px-3.5 py-2 text-xs inline-flex items-center gap-1.5 font-medium">
        <svg class="w-3.5 h-3.5 text-ink-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        Kembali ke Konfirmasi SK
      </a>
      <form action="verifikasi_ulang.php" method="post" class="inline m-0">
        <input type="hidden" name="kth_id" value="<?= $kthId ?>">
        <button type="submit" class="btn-kadaster px-3.5 py-2 text-xs inline-flex items-center gap-1.5 font-medium hover:text-forest-900" title="Hitung ulang kecocokan spasial dan nama">
          <svg class="w-3.5 h-3.5 text-ink-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
          Uji Ulang
        </button>
      </form>
      <a href="cetak_peta.php?kth_id=<?= $kthId ?>" target="_blank" class="btn-kadaster px-3.5 py-2 text-xs inline-flex items-center gap-1.5 font-medium text-forest-700 hover:text-forest-900">
        <svg class="w-3.5 h-3.5 fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
        Cetak Format Peta
      </a>
      <a href="laporan.php?kth_id=<?= $kthId ?>" class="btn-forest px-4 py-2 text-xs inline-flex items-center gap-1.5 font-semibold">
        Lanjut ke Berita Acara
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
      </a>
    </div>
  </div>

  <!-- Bilah Status Audit Spasial & Rekonsiliasi (Administrative Ledger Bar) -->
  <div class="grid grid-cols-2 md:grid-cols-4 gap-px bg-kadaster-border border border-kadaster-border rounded-sm overflow-hidden mt-5">
    <div class="bg-white p-4">
      <div class="text-[11px] font-semibold text-ink-muted uppercase tracking-wider">Total Terdaftar</div>
      <div class="mt-1 flex items-baseline gap-2">
        <span class="font-serif text-3xl font-bold text-ink tabular-nums"><?= $hitung['total'] ?></span>
        <span class="text-xs text-ink-muted">pemohon</span>
      </div>
      <div class="text-[11px] text-ink-muted mt-1">Data usulan elektronik e-RDKK</div>
    </div>

    <div class="bg-white p-4">
      <div class="text-[11px] font-semibold text-audit-valid uppercase tracking-wider flex items-center justify-between">
        <span>Sesuai SK Perhutanan</span>
        <span class="font-mono font-bold"><?= $pctSK ?>%</span>
      </div>
      <div class="mt-1 flex items-baseline gap-2">
        <span class="font-serif text-3xl font-bold text-audit-valid tabular-nums"><?= $hitung['sesuai'] ?></span>
        <span class="text-xs text-ink-muted">dari <?= $hitung['total'] ?></span>
      </div>
      <div class="w-full bg-kadaster-light h-1.5 rounded-sm overflow-hidden mt-2">
        <div class="bg-audit-valid h-full" style="width: <?= $pctSK ?>%"></div>
      </div>
    </div>

    <div class="bg-white p-4">
      <div class="text-[11px] font-semibold text-forest-700 uppercase tracking-wider flex items-center justify-between">
        <span>Titik Dalam Poligon PS</span>
        <span class="font-mono font-bold"><?= $pctPeta ?>%</span>
      </div>
      <div class="mt-1 flex items-baseline gap-2">
        <span class="font-serif text-3xl font-bold text-forest-700 tabular-nums"><?= $hitung['dalam'] ?></span>
        <span class="text-xs text-ink-muted">dari <?= $hitung['total'] ?></span>
      </div>
      <div class="w-full bg-kadaster-light h-1.5 rounded-sm overflow-hidden mt-2">
        <div class="bg-forest-700 h-full" style="width: <?= $pctPeta ?>%"></div>
      </div>
    </div>

    <div class="bg-white p-4">
      <div class="text-[11px] font-semibold <?= $jmlMasalah > 0 ? 'text-audit-revisi' : 'text-audit-valid' ?> uppercase tracking-wider">
        <span>Status Diskrepansi</span>
      </div>
      <div class="mt-1 flex items-baseline gap-2">
        <span class="font-serif text-3xl font-bold <?= $jmlMasalah > 0 ? 'text-audit-revisi' : 'text-audit-valid' ?> tabular-nums"><?= $jmlMasalah ?></span>
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
  <div class="flex flex-wrap items-center justify-between gap-3 pb-3 border-b border-kadaster-border mb-3">
    <div>
      <h3 class="font-serif font-bold text-lg text-ink flex items-center gap-2">
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
      <a href="cetak_peta.php?kth_id=<?= $kthId ?>" target="_blank" class="btn-kadaster px-3 py-1.5 text-xs inline-flex items-center gap-1 font-medium">
        <svg class="w-3.5 h-3.5 text-ink-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
        Buka Lembar Cetak
      </a>
    </div>
  </div>

  <div id="peta-verifikasi"></div>

  <!-- Panel Koreksi Koordinat -->
  <div id="panel-koreksi">
    <div class="flex items-center gap-2 text-amber-800 font-semibold text-xs">
      <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
      <span id="panel-koreksi-nama">Mode Koreksi Aktif</span>
    </div>
    <div class="text-amber-700 text-xs">Seret marker oranye ke posisi yang benar <b>di dalam poligon PS</b>, lalu klik tombol Simpan.</div>
    <div class="flex items-center gap-2">
      <span id="panel-koreksi-koord" class="font-mono text-[11px] text-amber-800 bg-amber-100 border border-amber-300 rounded px-2 py-1">—</span>
      <button id="btn-simpan-koreksi" disabled onclick="simpanKoreksi()"
        class="px-3 py-1.5 rounded text-xs font-semibold bg-forest-900 text-white hover:bg-forest-800 disabled:opacity-40 disabled:cursor-not-allowed transition-opacity">
        ✓ Simpan Posisi Baru
      </button>
      <button onclick="batalKoreksi()" class="px-3 py-1.5 rounded text-xs font-medium text-amber-800 border border-amber-300 hover:bg-amber-100">
        Batal
      </button>
    </div>
    <div id="panel-koreksi-pesan" class="text-xs text-audit-revisi font-medium hidden"></div>
  </div>

  <div id="peta-tanpa-koord" class="mt-3 p-3 bg-kadaster-light border border-kadaster-border rounded-sm hidden">
    <p class="text-xs font-semibold text-audit-revisi mb-1.5 flex items-center gap-1.5">
      <svg class="w-4 h-4 text-audit-revisi" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
      Petani tanpa data koordinat lintang/bujur (tidak dapat diproyeksikan ke peta):
    </p>
    <div id="list-tanpa-koord" class="flex flex-wrap gap-1.5 text-xs"></div>
  </div>
</div>

<!-- ═══ TABEL REGISTER HASIL UJI ═══ -->
<div x-data="{ filter: 'semua', cari: '' }" class="doc-card overflow-hidden fade-in">
  <div class="flex flex-wrap gap-3 p-4 border-b border-kadaster-border bg-kadaster-light items-center justify-between">
    <div class="flex flex-wrap items-center gap-3">
      <div class="flex items-center gap-2">
        <label class="text-xs font-semibold text-ink-muted">Filter Audit:</label>
        <select x-model="filter" class="border border-kadaster-border rounded px-3 py-1.5 text-xs font-medium bg-white text-ink focus:border-forest-900 outline-none">
          <option value="semua">Semua Usulan (<?= count($rows) ?>)</option>
          <option value="tidak-sk">Belum Sesuai SK (<?= $hitung['tidak'] ?>)</option>
          <option value="luar">Luar Peta PS (<?= $hitung['luar'] ?>)</option>
          <option value="bermasalah">Semua Masalah (<?= $jmlMasalah ?>)</option>
        </select>
      </div>
      <div class="relative w-64">
        <svg class="w-3.5 h-3.5 text-ink-muted absolute left-2.5 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        <input x-model="cari" placeholder="Cari nama / NIK pemohon…" class="w-full border border-kadaster-border rounded pl-8 pr-3 py-1.5 text-xs bg-white text-ink focus:border-forest-900 outline-none">
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
        <tr class="bg-[#F6F2E9] border-b border-kadaster-border text-ink">
          <th class="px-3 py-2.5 text-center font-semibold uppercase tracking-wider w-12 text-[11px]">No</th>
          <th class="px-3 py-2.5 text-left font-semibold uppercase tracking-wider text-[11px]">Nama Pemohon (Usulan)</th>
          <th class="px-3 py-2.5 text-left font-semibold uppercase tracking-wider text-[11px]">NIK</th>
          <th class="px-3 py-2.5 text-center font-semibold uppercase tracking-wider text-[11px]">Status SK PS</th>
          <th class="px-3 py-2.5 text-center font-semibold uppercase tracking-wider text-[11px]">Posisi Spasial</th>
          <th class="px-3 py-2.5 text-left font-semibold uppercase tracking-wider text-[11px] min-w-[280px]">Catatan Verifikator</th>
          <th class="px-2 py-2.5 text-center font-semibold uppercase tracking-wider w-14 text-[11px]">Sorot</th>
          <th class="px-2 py-2.5 text-center font-semibold uppercase tracking-wider w-20 text-[11px]">Koreksi</th>
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
          <td class="px-3 py-2 font-medium text-ink">
            <?= e($r['nama'] ?? '-') ?>
          </td>
          <td class="px-3 py-2 font-mono text-[11px] text-ink-muted">
            <?= e($r['nik'] ?? '-') ?>
          </td>
          <td class="px-3 py-2 text-center">
            <?= badge_sk((string)($r['status_sk'] ?? '')) ?>
          </td>
          <td class="px-3 py-2 text-center">
            <?= badge_koord((string)($r['status_koordinat'] ?? '')) ?>
          </td>
          <td class="px-3 py-2" x-data="{ edit: false, val: <?= e(json_encode((string)($r['catatan'] ?? ''), JSON_UNESCAPED_UNICODE)) ?>, saved: true }">
            <template x-if="!edit">
              <div @click="edit = true" class="cursor-text hover:bg-kadaster-light rounded px-2 py-1 min-h-[1.75rem] flex items-center justify-between group border border-transparent hover:border-kadaster-border" title="Klik untuk mengedit catatan verifikasi">
                <span class="text-xs text-ink" x-text="val || '—'"></span>
                <span class="text-[10px] text-ink-muted opacity-0 group-hover:opacity-100 italic">Edit</span>
                <span x-show="!saved" class="text-[10px] text-audit-revisi ml-1">…menyimpan</span>
              </div>
            </template>
            <template x-if="edit">
              <div class="flex gap-1.5 items-center">
                <input type="text" x-model="val" @keydown.enter="edit = false; saved = false; fetch('proses_catatan.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: 'hasil_id=<?= (int)$r['hasil_id'] ?>&catatan=' + encodeURIComponent(val)}).then(r => r.json()).then(j => { saved = true; if (!j.ok) alert('Gagal simpan: ' + j.msg); }).catch(e => { saved = true; alert('Gagal simpan'); })"
                  class="w-full border border-forest-900 rounded px-2 py-1 text-xs outline-none bg-white text-ink">
                <button @click="edit = false; saved = false; fetch('proses_catatan.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: 'hasil_id=<?= (int)$r['hasil_id'] ?>&catatan=' + encodeURIComponent(val)}).then(r => r.json()).then(j => { saved = true; if (!j.ok) alert('Gagal simpan: ' + j.msg); }).catch(e => { saved = true; alert('Gagal simpan'); })"
                  class="px-2 py-1 rounded bg-forest-900 text-white text-[11px] font-semibold hover:bg-forest-800">Simpan</button>
              </div>
            </template>
          </td>
          <td class="px-2 py-2 text-center">
            <button onclick="zoomKePetani(<?= (int)$r['id'] ?>)" title="Sorot koordinat di peta"
              class="w-6 h-6 rounded bg-kadaster-light text-forest-700 hover:bg-forest-900 hover:text-white inline-flex items-center justify-center transition-colors">
              <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/></svg>
            </button>
          </td>
          <td class="px-2 py-2 text-center">
            <?php if (($r['status_koordinat'] ?? '') !== 'Dalam Peta PS'): ?>
            <button onclick="aktifkanKoreksi(<?= (int)$r['id'] ?>, <?= (int)$r['id'] ?>)"
              id="btn-koreksi-<?= (int)$r['id'] ?>"
              title="Koreksi posisi koordinat petani ini"
              class="px-2 py-1 rounded text-[10px] font-semibold bg-amber-50 text-amber-700 border border-amber-300 hover:bg-amber-100 transition-colors whitespace-nowrap">
              📍 Koreksi
            </button>
            <?php elseif (!empty($r['status_koordinat']) && ($r['status_koordinat'] === 'Dalam Peta PS')): ?>
            <span class="text-[10px] text-audit-valid font-semibold">✓ OK</span>
            <?php endif; ?>
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
        const catatan = p.catatan ? `<div class="mt-1 text-ink italic text-xs border-l-2 border-kadaster-border pl-2 py-0.5 bg-kadaster-light">${p.catatan}</div>` : '';
        const popup = `<div class="p-1">
            <div class="font-bold text-ink text-sm mb-1">${p.no ? '#'+p.no+' — ' : ''}${p.nama}</div>
            <div class="text-ink-muted font-mono text-[11px] mb-1">NIK: ${p.nik}</div>
            <div class="text-ink-muted text-xs mb-1">${[p.desa, p.kecamatan].filter(Boolean).join(', ')}</div>
            <div class="flex gap-1 flex-wrap my-1.5">${bSK} ${bKoord}</div>
            ${mirip}${catatan}
            <div class="text-ink-muted font-mono text-[10px] mt-1 pt-1 border-t border-kadaster-border">📍 ${lat.toFixed(6)}, ${lng.toFixed(6)}</div>
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
          tag.className = 'bg-white border border-kadaster-border text-ink-muted rounded px-2 py-0.5 font-medium text-[11px]';
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

  // ─── FITUR KOREKSI KOORDINAT ───────────────────────────────────────────────
  let koreksiAktif = null; // { uid, marker, namaAsli }
  let polygonRings = [];   // rings dari poligon PS untuk validasi client-side

  // Simpan rings setelah data peta dimuat (dari fetch peta_data.php di atas)
  // Patch: tambahkan listener setelah polygon dimuat
  fetch(`peta_data.php?kth_id=${KTHID}`)
    .then(r => r.json())
    .then(data => {
      if (data.polygon && data.polygon.geometry) {
        const geo = data.polygon.geometry;
        if (geo.type === 'MultiPolygon') {
          geo.coordinates.forEach(poly => { if (poly[0]) polygonRings.push(poly[0]); });
        } else if (geo.type === 'Polygon') {
          if (geo.coordinates[0]) polygonRings.push(geo.coordinates[0]);
        }
      }
    }).catch(() => {});

  /** Ray-casting point-in-polygon (mirror dari PHP helpers.php) */
  function titikDalamRing(lng, lat, ring) {
    let inside = false;
    const n = ring.length;
    for (let i = 0, j = n - 1; i < n; j = i++) {
      const xi = ring[i][0], yi = ring[i][1];
      const xj = ring[j][0], yj = ring[j][1];
      const inter = ((yi > lat) !== (yj > lat)) && (lng < (xj - xi) * (lat - yi) / (yj - yi || 1e-12) + xi);
      if (inter) inside = !inside;
    }
    return inside;
  }
  function titikDalamPoligon(lng, lat) {
    if (!polygonRings.length) return true; // Belum ada poligon → izinkan
    return polygonRings.some(ring => titikDalamRing(lng, lat, ring));
  }

  window.aktifkanKoreksi = function(uid) {
    if (koreksiAktif) batalKoreksi();
    const marker = markerMap[uid];
    if (!marker) {
      alert('Petani ini belum memiliki koordinat. Tambahkan koordinat pada data usulan terlebih dahulu.');
      return;
    }
    // Scroll ke peta
    document.getElementById('peta-verifikasi').scrollIntoView({ behavior: 'smooth', block: 'start' });
    peta.flyTo(marker.getLatLng(), 17, { duration: 0.8 });

    // Aktifkan drag pada marker
    marker.dragging.enable();
    marker.getElement()?.classList.add('marker-draggable');

    const baris = document.getElementById('baris-' + uid);
    const nama = baris ? baris.querySelector('td:nth-child(2)')?.textContent?.trim() : 'Petani #' + uid;

    koreksiAktif = { uid, marker, namaAsli: nama };

    // Tampilkan panel
    const panel = document.getElementById('panel-koreksi');
    panel.className = 'mt-3 aktif';
    document.getElementById('panel-koreksi-nama').textContent = '✏️ Koreksi Posisi: ' + nama;

    const updatePanel = () => {
      const ll = marker.getLatLng();
      const masuk = titikDalamPoligon(ll.lng, ll.lat);
      document.getElementById('panel-koreksi-koord').textContent =
        ll.lat.toFixed(6) + ', ' + ll.lng.toFixed(6);
      const btnSimpan = document.getElementById('btn-simpan-koreksi');
      btnSimpan.disabled = !masuk;
      const pesan = document.getElementById('panel-koreksi-pesan');
      if (!masuk) {
        pesan.textContent = '⚠️ Posisi masih di luar poligon PS. Geser marker lebih ke dalam area.';
        pesan.classList.remove('hidden');
      } else {
        pesan.textContent = '✓ Posisi valid — di dalam poligon PS.';
        pesan.className = 'text-xs text-audit-valid font-medium';
        pesan.classList.remove('hidden');
      }
    };
    marker.on('drag dragend', updatePanel);
    updatePanel();
  };

  window.batalKoreksi = function() {
    if (!koreksiAktif) return;
    const { uid, marker } = koreksiAktif;
    marker.dragging.disable();
    marker.getElement()?.classList.remove('marker-draggable');
    marker.off('drag dragend');
    koreksiAktif = null;
    const panel = document.getElementById('panel-koreksi');
    panel.className = 'mt-3'; panel.style.display = 'none';
    document.getElementById('panel-koreksi-pesan').classList.add('hidden');
    document.getElementById('btn-simpan-koreksi').disabled = true;
  };

  window.simpanKoreksi = function() {
    if (!koreksiAktif) return;
    const { uid, marker, namaAsli } = koreksiAktif;
    const ll = marker.getLatLng();

    const btn = document.getElementById('btn-simpan-koreksi');
    btn.disabled = true;
    btn.textContent = '⏳ Menyimpan…';

    fetch('proses_koreksi_koordinat.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `usulan_id=${uid}&lat=${ll.lat}&lng=${ll.lng}`,
    })
    .then(r => r.json())
    .then(j => {
      if (j.ok) {
        // Update badge di baris tabel
        const baris = document.getElementById('baris-' + uid);
        if (baris) {
          const tdKoord = baris.querySelector('td:nth-child(5)');
          if (tdKoord) tdKoord.innerHTML = '<span class="badge-hijau">Dalam Peta PS</span>';
          baris.dataset.ko = 'dalam';
          const btnKoreksi = document.getElementById('btn-koreksi-' + uid);
          if (btnKoreksi) btnKoreksi.outerHTML = '<span class="text-[10px] text-audit-valid font-semibold">✓ OK</span>';
        }
        // Ganti warna marker ke hijau
        marker.setIcon(buatIkon('hijau', String(uid)));
        batalKoreksi();
        alert('✅ Koreksi posisi "' + namaAsli + '" berhasil disimpan.\nKoordinat asli tetap tersimpan di data usulan.');
      } else {
        btn.disabled = false;
        btn.textContent = '✓ Simpan Posisi Baru';
        const pesan = document.getElementById('panel-koreksi-pesan');
        pesan.textContent = '⚠️ ' + j.msg;
        pesan.className = 'text-xs text-audit-revisi font-medium';
        pesan.classList.remove('hidden');
      }
    })
    .catch(() => {
      btn.disabled = false;
      btn.textContent = '✓ Simpan Posisi Baru';
      alert('Gagal menghubungi server. Periksa koneksi.');
    });
  };

})();
</script>
<?php layout_foot(); ?>
