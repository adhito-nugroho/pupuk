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

layout_head('Laporan Akhir — ' . $k['nama_kth']);
wizard(4);
?>

<!-- ═══ Header ═══ -->
<div class="glass-card rounded-2xl p-6 mb-5 max-w-5xl fade-in">
  <h2 class="text-xl font-bold text-slate-800 flex items-center gap-2.5">
    <span class="w-8 h-8 rounded-lg bg-gradient-to-br from-violet-500 to-purple-700 text-white flex items-center justify-center text-sm">4</span>
    Laporan Akhir — <?= e($k['nama_kth']) ?>
  </h2>
  <p class="text-sm text-slate-500 mt-1.5 ml-10">SK: <?= e($k['nomor_sk'] ?? '-') ?> · Tahun: <?= e($lap['tahun'] ?? '-') ?></p>
</div>

<!-- ═══ Rekomendasi Besar + Statistik ═══ -->
<div class="grid md:grid-cols-3 gap-5 mb-5 max-w-5xl fade-in fade-in-delay-1">
  <!-- Rekomendasi utama -->
  <div class="md:col-span-1 <?= $rekomFinal === 'Dapat Ditindaklanjuti' ? 'bg-gradient-to-br from-emerald-500 to-teal-700' : 'bg-gradient-to-br from-amber-500 to-orange-700' ?> rounded-2xl p-6 text-white shadow-xl relative overflow-hidden">
    <div class="absolute top-0 right-0 w-32 h-32 rounded-full bg-white/10 -translate-x-6 -translate-y-6"></div>
    <div class="relative z-10">
      <div class="text-xs font-bold uppercase tracking-wider text-white/70 mb-2">Rekomendasi</div>
      <div class="text-2xl font-extrabold leading-tight mb-3"><?= e($rekomFinal) ?></div>
      <div class="text-xs text-white/80 leading-relaxed">
        <?php if ($rekomFinal === 'Dapat Ditindaklanjuti'): ?>
          Seluruh petani sesuai SK dan titik koordinat berada dalam peta areal PS.
        <?php else: ?>
          Terdapat ketidaksesuaian pada data SK atau koordinat yang perlu ditinjau kembali.
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Ringkasan statistik -->
  <div class="md:col-span-2 glass-card rounded-2xl p-5">
    <div class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-4">Ringkasan Verifikasi</div>
    <div class="space-y-4">
      <!-- SK -->
      <div>
        <div class="flex justify-between text-xs mb-1.5">
          <span class="font-semibold text-slate-700">Kesesuaian SK</span>
          <span class="font-bold"><?= $hitung['sesuai'] ?> / <?= $hitung['total'] ?> <span class="text-emerald-600">(<?= $pctSK ?>%)</span></span>
        </div>
        <div class="h-3 bg-slate-100 rounded-full overflow-hidden flex">
          <div class="h-full bg-gradient-to-r from-emerald-400 to-emerald-600 rounded-full smooth-all" style="width:<?= $pctSK ?>%"></div>
          <?php if ($hitung['tidak'] > 0): ?>
          <div class="h-full bg-gradient-to-r from-red-400 to-red-500 rounded-r-full" style="width:<?= 100 - $pctSK ?>%"></div>
          <?php endif; ?>
        </div>
        <div class="flex justify-between text-[10px] text-slate-400 mt-1">
          <span><?= $hitung['sesuai'] ?> sesuai SK PS</span>
          <span><?= $hitung['tidak'] ?> belum sesuai</span>
        </div>
      </div>
      <!-- Koordinat -->
      <div>
        <div class="flex justify-between text-xs mb-1.5">
          <span class="font-semibold text-slate-700">Posisi Koordinat</span>
          <span class="font-bold"><?= $hitung['dalam'] ?> / <?= $hitung['total'] ?> <span class="text-sky-600">(<?= $pctPeta ?>%)</span></span>
        </div>
        <div class="h-3 bg-slate-100 rounded-full overflow-hidden flex">
          <div class="h-full bg-gradient-to-r from-sky-400 to-sky-600 rounded-full smooth-all" style="width:<?= $pctPeta ?>%"></div>
          <?php if ($hitung['luar'] > 0): ?>
          <div class="h-full bg-gradient-to-r from-orange-400 to-orange-500 rounded-r-full" style="width:<?= 100 - $pctPeta ?>%"></div>
          <?php endif; ?>
        </div>
        <div class="flex justify-between text-[10px] text-slate-400 mt-1">
          <span><?= $hitung['dalam'] ?> dalam peta PS</span>
          <span><?= $hitung['luar'] ?> luar peta</span>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ═══ Form Laporan ═══ -->
<div class="glass-card rounded-2xl p-6 mb-5 max-w-5xl fade-in fade-in-delay-2">
  <form action="proses_laporan.php" method="post" class="space-y-5">
    <input type="hidden" name="kth_id" value="<?= $kthId ?>">
    
    <div class="grid md:grid-cols-3 gap-4">
      <div>
        <label class="block text-xs font-bold text-slate-600 mb-1.5">Tahun</label>
        <input name="tahun" value="<?= e($lap['tahun'] ?? '') ?>" class="w-full border border-slate-200 rounded-xl px-3.5 py-2.5 text-sm bg-white focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500 outline-none smooth-all">
      </div>
      <div>
        <label class="block text-xs font-bold text-slate-600 mb-1.5">Rekomendasi Akhir</label>
        <select name="rekomendasi" class="w-full border border-slate-200 rounded-xl px-3.5 py-2.5 text-sm bg-white focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500 outline-none smooth-all font-semibold">
          <?php foreach (['Dapat Ditindaklanjuti', 'Perlu Revisi'] as $op): ?>
            <option <?= (($lap['rekomendasi'] ?? $rekomAuto) === $op) ? 'selected' : '' ?>><?= $op ?></option>
          <?php endforeach; ?>
        </select>
        <p class="text-[10px] text-slate-400 mt-1">Rekomendasi otomatis: <b><?= e($rekomAuto) ?></b> — bisa di-override.</p>
      </div>
      <div class="flex items-end">
        <div class="text-xs text-slate-500 bg-slate-50 rounded-xl px-4 py-3 w-full border border-slate-200/60">
          <div class="font-semibold text-slate-600 mb-1">Angka Verifikasi</div>
          Total <?= $hitung['total'] ?> · Sesuai <?= $hitung['sesuai'] ?> · Belum <?= $hitung['tidak'] ?> · Dalam <?= $hitung['dalam'] ?> · Luar <?= $hitung['luar'] ?>
        </div>
      </div>
    </div>

    <div x-data="{ len: <?= strlen($lap['narasi'] ?? '') ?> }">
      <label class="block text-xs font-bold text-slate-600 mb-1.5">Narasi Ringkasan</label>
      <textarea name="narasi" rows="6" x-on:input="len = $event.target.value.length"
        class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm bg-white focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500 outline-none smooth-all leading-relaxed"><?= e($lap['narasi'] ?? '') ?></textarea>
      <div class="text-right text-[10px] text-slate-400 mt-1" x-text="len + ' karakter'"></div>
    </div>

    <div class="flex flex-wrap gap-3 pt-1">
      <button class="btn-primary px-5 py-2.5 rounded-xl text-sm inline-flex items-center gap-2 shadow-lg">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        Simpan Laporan
      </button>
      <a href="export.php?kth_id=<?= $kthId ?>" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-semibold text-white bg-gradient-to-r from-sky-500 to-sky-700 hover:from-sky-600 hover:to-sky-800 shadow-lg smooth-all">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        Export Excel
      </a>
      <a href="cetak_peta.php?kth_id=<?= $kthId ?>" target="_blank" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-semibold text-white bg-gradient-to-r from-teal-600 to-emerald-700 hover:from-teal-700 hover:to-emerald-800 shadow-lg smooth-all">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
        Cetak Peta
      </a>
      <a href="hasil.php?kth_id=<?= $kthId ?>" class="btn-secondary px-4 py-2.5 rounded-xl text-sm inline-flex items-center gap-2">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        Kembali ke Hasil
      </a>
    </div>
  </form>
</div>

<!-- ═══ Template Narasi ═══ -->
<div class="glass-card rounded-2xl p-5 max-w-5xl text-sm fade-in fade-in-delay-3" x-data="{ open: false }">
  <button type="button" @click="open = !open" class="w-full flex items-center justify-between text-left">
    <h3 class="font-bold text-slate-600 flex items-center gap-2">
      <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
      Template Narasi (placeholder otomatis)
    </h3>
    <svg class="w-5 h-5 text-slate-400 smooth-all" :class="open && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
  </button>
  <div x-show="open" x-transition class="mt-3">
    <code class="block bg-slate-50 border border-slate-200 rounded-xl p-4 whitespace-pre-wrap text-xs text-slate-600 leading-relaxed">Berdasarkan hasil verifikasi data usulan pupuk subsidi tahun [TAHUN] terdapat sebanyak [TOTAL] petani. Dari hasil telaah diperoleh data bahwa sejumlah [JUMLAH_SESUAI] petani sudah sesuai dengan SK [NOMOR_SK], terdapat [JUMLAH_TIDAK_SESUAI] petani yang belum masuk ke dalam SK tersebut. Titik koordinat petani yang mengusulkan pupuk, sejumlah [JUMLAH_DALAM_PETA] berada dalam peta areal [NAMA_KTH] dan [JUMLAH_LUAR_PETA] berada di luar peta.</code>
    <form action="proses_laporan.php" method="post" class="mt-2">
      <input type="hidden" name="kth_id" value="<?= $kthId ?>">
      <input type="hidden" name="reset_template" value="1">
      <button class="text-emerald-700 hover:text-emerald-900 text-xs font-semibold smooth-all inline-flex items-center gap-1">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
        Kembalikan narasi ke template otomatis
      </button>
    </form>
  </div>
</div>
<?php layout_foot(); ?>
