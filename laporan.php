<?php
// Langkah 4 — Laporan akhir: narasi editable + rekomendasi override + export Excel.
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/layout.php';
require_once __DIR__ . '/lib/verify.php';

$kthId = (int)($_GET['kth_id'] ?? 0);
$pdo = db();
$kth = $pdo->prepare('SELECT * FROM kth WHERE id = ?');
$kth->execute([$kthId]);
$k = $kth->fetch();
if (!$k) { flash_set('error', 'Kasus tidak ditemukan.'); header('Location: index.php'); exit; }

$st = $pdo->prepare('SELECT * FROM laporan WHERE kth_id = ? ORDER BY id DESC LIMIT 1');
$st->execute([$kthId]);
$lap = $st->fetch();

$h = $pdo->prepare('SELECT COUNT(*) total,
  SUM(status_sk = "Sesuai SK PS") sesuai,
  SUM(status_sk != "Sesuai SK PS") tidak,
  SUM(status_koordinat = "Dalam Peta PS") dalam,
  SUM(status_koordinat != "Dalam Peta PS") luar FROM hasil_verifikasi WHERE kth_id = ?');
$h->execute([$kthId]);
$live = $h->fetch() ?: ['total' => 0, 'sesuai' => 0, 'tidak' => 0, 'dalam' => 0, 'luar' => 0];
$hitung = ['total' => (int)$live['total'], 'sesuai' => (int)$live['sesuai'], 'tidak' => (int)$live['tidak'],
           'dalam' => (int)$live['dalam'], 'luar' => (int)$live['luar']];
$rekomAuto = ($hitung['tidak'] === 0 && $hitung['luar'] === 0 && $hitung['total'] > 0) ? 'Dapat Ditindaklanjuti' : 'Perlu Revisi';

if (!$lap) {
    $lap = ['tahun' => $k['tahun_usulan'] ?? date('Y'), 'narasi' => buat_narasi_default($k, (int)($k['tahun_usulan'] ?? date('Y')), $hitung),
            'rekomendasi' => $rekomAuto, 'total_petani' => $hitung['total'], 'jumlah_sesuai_sk' => $hitung['sesuai'],
            'jumlah_tidak_sesuai_sk' => $hitung['tidak'], 'jumlah_dalam_peta' => $hitung['dalam'], 'jumlah_luar_peta' => $hitung['luar']];
}

$pctSK = $hitung['total'] > 0 ? round($hitung['sesuai'] / $hitung['total'] * 100) : 0;
$pctPeta = $hitung['total'] > 0 ? round($hitung['dalam'] / $hitung['total'] * 100) : 0;
$rekomFinal = $lap['rekomendasi'] ?? $rekomAuto;
$isSesuaiSemua = ($rekomFinal === 'Dapat Ditindaklanjuti');
$qLoc = $pdo->prepare('SELECT desa, kecamatan FROM usulan_pupuk WHERE kth_id = ? AND (desa IS NOT NULL AND desa != "") LIMIT 1');
$qLoc->execute([$kthId]);
$loc = $qLoc->fetch() ?: [];
$namaDesa = $loc['desa'] ?? '';
$namaKec = $loc['kecamatan'] ?? '';

layout_head('Berita Acara Rekomendasi — ' . $k['nama_kth']);
wizard(4);
?>

<!-- ═══ HEADER KASUS & IDENTITAS DOKUMEN ═══ -->
<div class="doc-card p-6 mb-5 fade-in">
  <div class="flex flex-wrap items-start justify-between gap-4">
    <div>
      <div class="flex items-center gap-2 mb-1">
        <span class="text-[11px] font-semibold tracking-wider text-forest-subtle uppercase">Tahap 4 dari 4 — Berita Acara &amp; Rekomendasi Akhir</span>
        <span class="text-slate-300">·</span>
        <span class="text-[11px] text-ink-muted">Tahun Usulan <?= e($lap['tahun'] ?? date('Y')) ?></span>
      </div>
      <h2 class="font-serif text-2xl font-bold text-forest-ink">
        Berita Acara Rekomendasi Verifikasi
      </h2>
      <p class="text-xs text-ink-muted mt-1 flex flex-wrap items-center gap-3">
        <span><b>Kelompok Tani:</b> <?= e($k['nama_kth']) ?></span>
        <span class="text-slate-300">·</span>
        <span><b>Nomor SK:</b> <?= e($k['nomor_sk'] ?: 'Belum tercatat') ?></span>
        <?php if ($namaDesa || $namaKec): ?>
        <span class="text-slate-300">·</span>
        <span><b>Wilayah:</b> <?= e(implode(', ', array_filter([$namaDesa, $namaKec]))) ?></span>
        <?php endif; ?>
        <?php if (!empty($k['nama_kph'])): ?>
        <span class="text-slate-300">·</span>
        <span><b>KPH:</b> <?= e($k['nama_kph']) ?></span>
        <?php endif; ?>
      </p>
    </div>

    <div class="flex flex-wrap items-center gap-2">
      <a href="hasil.php?kth_id=<?= $kthId ?>" class="btn-secondary px-3.5 py-2 text-xs inline-flex items-center gap-1.5 font-medium">
        <svg class="w-3.5 h-3.5 text-ink-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        Kembali ke Uji Spasial
      </a>
      <a href="export.php?kth_id=<?= $kthId ?>" class="btn-secondary px-3.5 py-2 text-xs inline-flex items-center gap-1.5 font-medium text-forest-subtle hover:text-forest-dark border-cadastral">
        <svg class="w-3.5 h-3.5 text-forest-subtle" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        Ekspor Excel Rekap
      </a>
      <a href="cetak_peta.php?kth_id=<?= $kthId ?>" target="_blank" class="btn-secondary px-3.5 py-2 text-xs inline-flex items-center gap-1.5 font-medium text-forest-subtle hover:text-forest-dark">
        <svg class="w-3.5 h-3.5 text-ink-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
        Cetak Peta Lampiran
      </a>
    </div>
  </div>
</div>

<!-- ═══ REKOMENDASI BERITA ACARA & NERACA VERIFIKASI ═══ -->
<div class="grid md:grid-cols-12 gap-5 mb-5 fade-in">
  <!-- Blok Keputusan / Rekomendasi Administratif -->
  <div class="md:col-span-5 doc-card p-6 flex flex-col justify-between" style="border-left: 4px solid <?= $isSesuaiSemua ? '#1D5C3A' : '#9E2A2B' ?>;">
    <div>
      <div class="flex items-center justify-between gap-2 mb-3">
        <span class="text-[11px] font-semibold uppercase tracking-wider text-ink-muted">Kesimpulan Verifikasi Lapangan</span>
        <span class="doc-badge <?= $isSesuaiSemua ? 'badge-sk-sesuai' : 'badge-sk-belum' ?>">
          <?= $isSesuaiSemua ? 'Disetujui' : 'Catatan Khusus' ?>
        </span>
      </div>
      
      <div class="font-serif text-2xl font-bold <?= $isSesuaiSemua ? 'text-status-sesuai' : 'text-status-revisi' ?> leading-tight mb-2">
        <?= e($rekomFinal) ?>
      </div>

      <p class="text-xs text-forest-ink leading-relaxed mt-2">
        <?php if ($isSesuaiSemua): ?>
          Berdasarkan penelaahan komparatif, seluruh <b><?= $hitung['total'] ?> pemohon</b> tercantum dalam Keputusan Persetujuan Pengelolaan Perhutanan Sosial dan seluruh titik koordinat garapan berada di dalam deliniasi peta areal izin.
        <?php else: ?>
          Ditemukan ketidaksesuaian yuridis atau spasial: 
          <?php if ($hitung['tidak'] > 0): ?><b><?= $hitung['tidak'] ?> nama belum tercantum dalam SK</b><?php endif; ?>
          <?php if ($hitung['tidak'] > 0 && $hitung['luar'] > 0): ?> dan <?php endif; ?>
          <?php if ($hitung['luar'] > 0): ?><b><?= $hitung['luar'] ?> koordinat berada di luar peta areal izin</b><?php endif; ?>. Perlu dilakukan verifikasi lapangan atau revisi dokumen sebelum penerbitan alokasi.
        <?php endif; ?>
      </p>
    </div>

    <div class="mt-6 pt-4 border-t border-cadastral flex items-center justify-between text-[11px] text-ink-muted">
      <span>Status Sistem: <b><?= e($rekomAuto) ?></b></span>
      <span><?= date('d M Y') ?></span>
    </div>
  </div>

  <!-- Neraca Ringkasan Verifikasi (Cadastral Audit Ledger) -->
  <div class="md:col-span-7 doc-card p-6">
    <div class="flex items-center justify-between mb-4 pb-2 border-b border-cadastral">
      <h3 class="font-serif font-bold text-base text-forest-ink">Neraca Kesesuaian Yuridis &amp; Spasial</h3>
      <span class="text-xs font-mono font-semibold text-ink-muted tabular-nums">Total: <?= $hitung['total'] ?> Pemohon</span>
    </div>

    <div class="space-y-4">
      <!-- Uji Kesesuaian SK -->
      <div>
        <div class="flex justify-between text-xs mb-1.5">
          <span class="font-semibold text-forest-ink">1. Kesesuaian Nama &amp; NIK dengan SK PS</span>
          <span class="font-mono font-bold text-forest-ink tabular-nums">
            <?= $hitung['sesuai'] ?> / <?= $hitung['total'] ?> 
            <span class="text-status-sesuai font-normal">(<?= $pctSK ?>%)</span>
          </span>
        </div>
        <div class="h-2.5 bg-paper-tint border border-cadastral rounded-sm overflow-hidden flex">
          <div class="bg-status-sesuai h-full" style="width: <?= $pctSK ?>%"></div>
          <?php if ($hitung['tidak'] > 0): ?>
            <div class="bg-status-revisi h-full" style="width: <?= 100 - $pctSK ?>%"></div>
          <?php endif; ?>
        </div>
        <div class="flex justify-between text-[11px] text-ink-muted mt-1">
          <span><span class="font-bold text-status-sesuai"><?= $hitung['sesuai'] ?></span> nama sesuai daftar lampiran SK</span>
          <span><span class="font-bold text-status-revisi"><?= $hitung['tidak'] ?></span> belum ditemukan / beda identitas</span>
        </div>
      </div>

      <!-- Uji Koordinat Spasial -->
      <div>
        <div class="flex justify-between text-xs mb-1.5">
          <span class="font-semibold text-forest-ink">2. Posisi Koordinat Garapan Petani</span>
          <span class="font-mono font-bold text-forest-ink tabular-nums">
            <?= $hitung['dalam'] ?> / <?= $hitung['total'] ?> 
            <span class="text-forest-subtle font-normal">(<?= $pctPeta ?>%)</span>
          </span>
        </div>
        <div class="h-2.5 bg-paper-tint border border-cadastral rounded-sm overflow-hidden flex">
          <div class="bg-forest-subtle h-full" style="width: <?= $pctPeta ?>%"></div>
          <?php if ($hitung['luar'] > 0): ?>
            <div class="bg-status-luar h-full" style="width: <?= 100 - $pctPeta ?>%"></div>
          <?php endif; ?>
        </div>
        <div class="flex justify-between text-[11px] text-ink-muted mt-1">
          <span><span class="font-bold text-forest-subtle"><?= $hitung['dalam'] ?></span> titik di dalam poligon areal izin PS</span>
          <span><span class="font-bold text-status-luar"><?= $hitung['luar'] ?></span> titik di luar deliniasi peta</span>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ═══ FORMULIR RESMI BERITA ACARA ═══ -->
<div class="doc-card p-6 mb-5 fade-in">
  <div class="pb-3 border-b border-cadastral mb-5">
    <h3 class="font-serif font-bold text-lg text-forest-ink">Formulir Pengesahan &amp; Redaksi Laporan</h3>
    <p class="text-xs text-ink-muted">
      Tinjau redaksi narasi resmi sebelum dicetak atau diekspor sebagai lampiran Berita Acara Verifikasi Pupuk Bersubsidi.
    </p>
  </div>

  <form action="proses_laporan.php" method="post" class="space-y-5">
    <input type="hidden" name="kth_id" value="<?= $kthId ?>">
    
    <div class="grid md:grid-cols-3 gap-4">
      <div>
        <label class="block text-xs font-semibold text-forest-ink mb-1.5">Tahun Anggaran Usulan</label>
        <input type="text" name="tahun" value="<?= e($lap['tahun'] ?? date('Y')) ?>" class="w-full border border-cadastral rounded px-3 py-2 text-xs bg-white text-forest-ink focus:border-forest-dark outline-none font-mono">
        <p class="text-[11px] text-ink-muted mt-1">Tahun alokasi pupuk bersubsidi.</p>
      </div>

      <div>
        <label class="block text-xs font-semibold text-forest-ink mb-1.5">Penetapan Rekomendasi Petugas</label>
        <select name="rekomendasi" class="w-full border border-cadastral rounded px-3 py-2 text-xs bg-white text-forest-ink focus:border-forest-dark outline-none font-semibold">
          <?php foreach (['Dapat Ditindaklanjuti', 'Perlu Revisi'] as $op): ?>
            <option value="<?= $op ?>" <?= (($lap['rekomendasi'] ?? $rekomAuto) === $op) ? 'selected' : '' ?>><?= $op ?></option>
          <?php endforeach; ?>
        </select>
        <p class="text-[11px] text-ink-muted mt-1">Sistem menyarankan: <b><?= e($rekomAuto) ?></b>.</p>
      </div>

      <div>
        <label class="block text-xs font-semibold text-forest-ink mb-1.5">Ringkasan Angka Audit</label>
        <div class="border border-cadastral bg-paper-tint rounded px-3 py-2 text-[11px] text-ink-muted font-mono leading-relaxed">
          Total: <?= $hitung['total'] ?> | SK: <?= $hitung['sesuai'] ?> ok, <?= $hitung['tidak'] ?> beda | Peta: <?= $hitung['dalam'] ?> dlm, <?= $hitung['luar'] ?> luar
        </div>
        <p class="text-[11px] text-ink-muted mt-1">Dihitung otomatis dari basis data.</p>
      </div>
    </div>

    <div x-data="{ len: <?= mb_strlen($lap['narasi'] ?? '', 'UTF-8') ?> }">
      <div class="flex items-center justify-between mb-1.5">
        <label class="block text-xs font-semibold text-forest-ink">Teks Narasi Berita Acara / Rekomendasi</label>
        <span class="text-[11px] text-ink-muted font-mono" x-text="len + ' karakter'"></span>
      </div>
      <textarea name="narasi" rows="7" x-on:input="len = $event.target.value.length"
        class="w-full border border-cadastral rounded px-3.5 py-2.5 text-xs bg-white text-forest-ink focus:border-forest-dark outline-none leading-relaxed font-sans"><?= e($lap['narasi'] ?? '') ?></textarea>
      <p class="text-[11px] text-ink-muted mt-1">
        Narasi ini akan tampil pada lembar cetak Berita Acara dan rekapan hasil audit Dinas Kehutanan.
      </p>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3 pt-3 border-t border-cadastral">
      <div class="flex flex-wrap items-center gap-2">
        <button type="submit" class="btn-primary px-5 py-2 text-xs inline-flex items-center gap-1.5 font-semibold">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
          Simpan Laporan &amp; Berita Acara
        </button>
        <a href="export.php?kth_id=<?= $kthId ?>" class="btn-secondary px-4 py-2 text-xs inline-flex items-center gap-1.5 font-medium text-forest-subtle hover:text-forest-dark">
          <svg class="w-3.5 h-3.5 text-forest-subtle" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
          Unduh File Excel
        </a>
        <a href="cetak_peta.php?kth_id=<?= $kthId ?>" target="_blank" class="btn-secondary px-4 py-2 text-xs inline-flex items-center gap-1.5 font-medium text-forest-subtle hover:text-forest-dark">
          <svg class="w-3.5 h-3.5 text-ink-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
          Cetak Peta Spasial
        </a>
      </div>

      <div>
        <a href="index.php" class="btn-secondary px-4 py-2 text-xs inline-flex items-center gap-1.5 font-medium text-ink-muted">
          Kembali ke Buku Register
        </a>
      </div>
    </div>
  </form>
</div>

<!-- ═══ REFERENSI TEMPLATE RESMI ═══ -->
<div class="doc-card p-5 text-xs fade-in" x-data="{ open: false }">
  <button type="button" @click="open = !open" class="w-full flex items-center justify-between text-left">
    <div class="flex items-center gap-2">
      <svg class="w-4 h-4 text-ink-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
      <span class="font-serif font-bold text-forest-ink">Format Standar Narasi Berita Acara Dinas</span>
    </div>
    <span class="text-[11px] text-forest-subtle font-medium" x-text="open ? 'Tutup Panduan' : 'Lihat Standar Redaksi'"></span>
  </button>
  <div x-show="open" x-transition class="mt-3 pt-3 border-t border-cadastral">
    <p class="text-ink-muted mb-2">Redaksi default yang disusun oleh sistem menggunakan format klausul berikut:</p>
    <div class="bg-paper-tint border border-cadastral rounded p-3 font-mono text-[11px] text-forest-ink whitespace-pre-wrap leading-relaxed">Berdasarkan hasil verifikasi data usulan pupuk subsidi tahun [TAHUN] terdapat sebanyak [TOTAL] petani. Dari hasil telaah diperoleh data bahwa sejumlah [JUMLAH_SESUAI] petani sudah sesuai dengan SK [NOMOR_SK], terdapat [JUMLAH_TIDAK_SESUAI] petani yang belum masuk ke dalam SK tersebut. Titik koordinat petani yang mengusulkan pupuk, sejumlah [JUMLAH_DALAM_PETA] berada dalam peta areal [NAMA_KTH] dan [JUMLAH_LUAR_PETA] berada di luar peta.</div>
    <form action="proses_laporan.php" method="post" class="mt-3">
      <input type="hidden" name="kth_id" value="<?= $kthId ?>">
      <input type="hidden" name="reset_template" value="1">
      <button type="submit" class="text-forest-subtle hover:text-forest-dark text-[11px] font-semibold inline-flex items-center gap-1.5">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
        Reset teks ke redaksi standar dinas
      </button>
    </form>
  </div>
</div>

<?php layout_foot(); ?>
