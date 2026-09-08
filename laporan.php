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
$namaDesa = !empty($loc['desa']) ? $loc['desa'] : (!empty($k['desa']) ? $k['desa'] : '');
$namaKec  = !empty($loc['kecamatan']) ? $loc['kecamatan'] : (!empty($k['kecamatan']) ? $k['kecamatan'] : '');

layout_head('Berita Acara Rekomendasi — ' . ($k['nama_kth'] ?? ''));
wizard(4);

// Ambil info BA dari laporan terbaru
$baFile  = $lap['berkas_ba']   ?? null;
$baNama  = $lap['nama_file_ba'] ?? null;
$baTgl   = $lap['tgl_ba']       ?? null;
?>

<!-- ═══ HEADER KASUS & IDENTITAS DOKUMEN ═══ -->
<div class="doc-card p-6 mb-5 fade-in">
  <div class="flex flex-wrap items-start justify-between gap-4">
    <div>
      <div class="flex items-center gap-2 mb-1">
        <span class="text-[11px] font-semibold tracking-wider text-forest-700 uppercase">Tahap 4 dari 4 — Berita Acara &amp; Rekomendasi Akhir</span>
        <span class="text-kadaster-border">·</span>
        <span class="text-[11px] text-ink-muted">Tahun Usulan <?= e($lap['tahun'] ?? date('Y')) ?></span>
      </div>
      <h2 class="font-serif text-2xl font-bold text-ink">
        Berita Acara Rekomendasi Verifikasi
      </h2>
      <p class="text-xs text-ink-muted mt-1 flex flex-wrap items-center gap-3">
        <span><b>Kelompok Tani:</b> <?= e($k['nama_kth'] ?? '-') ?></span>
        <span class="text-kadaster-border">·</span>
        <span><b>Nomor SK:</b> <?= e(!empty($k['nomor_sk']) ? $k['nomor_sk'] : 'Belum tercatat') ?></span>
        <?php if ($namaDesa || $namaKec): ?>
        <span class="text-kadaster-border">·</span>
        <span><b>Wilayah:</b> <?= e(implode(', ', array_filter([$namaDesa, $namaKec]))) ?></span>
        <?php endif; ?>
        <?php if (!empty($k['nama_kph'])): ?>
        <span class="text-kadaster-border">·</span>
        <span><b>KPH:</b> <?= e($k['nama_kph']) ?></span>
        <?php endif; ?>
      </p>
    </div>

    <div class="flex flex-wrap items-center gap-2">
      <a href="hasil.php?kth_id=<?= $kthId ?>" class="btn-kadaster px-3.5 py-2 text-xs inline-flex items-center gap-1.5 font-medium">
        <svg class="w-3.5 h-3.5 text-ink-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        Kembali ke Uji Spasial
      </a>
      <a href="export.php?kth_id=<?= $kthId ?>" class="btn-kadaster px-3.5 py-2 text-xs inline-flex items-center gap-1.5 font-medium text-forest-700 hover:text-forest-900 border-kadaster-border">
        <svg class="w-3.5 h-3.5 text-forest-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        Ekspor Excel Rekap
      </a>
      <a href="cetak_peta.php?kth_id=<?= $kthId ?>" target="_blank" class="btn-kadaster px-3.5 py-2 text-xs inline-flex items-center gap-1.5 font-medium text-forest-700 hover:text-forest-900">
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
      
      <div class="font-serif text-2xl font-bold <?= $isSesuaiSemua ? 'text-audit-valid' : 'text-audit-revisi' ?> leading-tight mb-2">
        <?= e($rekomFinal) ?>
      </div>

      <p class="text-xs text-ink leading-relaxed mt-2">
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

    <div class="mt-6 pt-4 border-t border-kadaster-border flex items-center justify-between text-[11px] text-ink-muted">
      <span>Status Sistem: <b><?= e($rekomAuto) ?></b></span>
      <span><?= date('d M Y') ?></span>
    </div>
  </div>

  <!-- Neraca Ringkasan Verifikasi (Cadastral Audit Ledger) -->
  <div class="md:col-span-7 doc-card p-6">
    <div class="flex items-center justify-between mb-4 pb-2 border-b border-kadaster-border">
      <h3 class="font-serif font-bold text-base text-ink">Neraca Kesesuaian Yuridis &amp; Spasial</h3>
      <span class="text-xs font-mono font-semibold text-ink-muted tabular-nums">Total: <?= $hitung['total'] ?> Pemohon</span>
    </div>

    <div class="space-y-4">
      <!-- Uji Kesesuaian SK -->
      <div>
        <div class="flex justify-between text-xs mb-1.5">
          <span class="font-semibold text-ink">1. Kesesuaian Nama &amp; NIK dengan SK PS</span>
          <span class="font-mono font-bold text-ink tabular-nums">
            <?= $hitung['sesuai'] ?> / <?= $hitung['total'] ?> 
            <span class="text-audit-valid font-normal">(<?= $pctSK ?>%)</span>
          </span>
        </div>
        <div class="h-2.5 bg-kadaster-light border border-kadaster-border rounded-sm overflow-hidden flex">
          <div class="bg-audit-valid h-full" style="width: <?= $pctSK ?>%"></div>
          <?php if ($hitung['tidak'] > 0): ?>
            <div class="bg-audit-revisi h-full" style="width: <?= 100 - $pctSK ?>%"></div>
          <?php endif; ?>
        </div>
        <div class="flex justify-between text-[11px] text-ink-muted mt-1">
          <span><span class="font-bold text-audit-valid"><?= $hitung['sesuai'] ?></span> nama sesuai daftar lampiran SK</span>
          <span><span class="font-bold text-audit-revisi"><?= $hitung['tidak'] ?></span> belum ditemukan / beda identitas</span>
        </div>
      </div>

      <!-- Uji Koordinat Spasial -->
      <div>
        <div class="flex justify-between text-xs mb-1.5">
          <span class="font-semibold text-ink">2. Posisi Koordinat Garapan Petani</span>
          <span class="font-mono font-bold text-ink tabular-nums">
            <?= $hitung['dalam'] ?> / <?= $hitung['total'] ?> 
            <span class="text-forest-700 font-normal">(<?= $pctPeta ?>%)</span>
          </span>
        </div>
        <div class="h-2.5 bg-kadaster-light border border-kadaster-border rounded-sm overflow-hidden flex">
          <div class="bg-forest-700 h-full" style="width: <?= $pctPeta ?>%"></div>
          <?php if ($hitung['luar'] > 0): ?>
            <div class="bg-audit-warn h-full" style="width: <?= 100 - $pctPeta ?>%"></div>
          <?php endif; ?>
        </div>
        <div class="flex justify-between text-[11px] text-ink-muted mt-1">
          <span><span class="font-bold text-forest-700"><?= $hitung['dalam'] ?></span> titik di dalam poligon areal izin PS</span>
          <span><span class="font-bold text-audit-warn"><?= $hitung['luar'] ?></span> titik di luar deliniasi peta</span>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ═══ FORMULIR RESMI BERITA ACARA ═══ -->
<div class="doc-card p-6 mb-5 fade-in">
  <div class="pb-3 border-b border-kadaster-border mb-5">
    <h3 class="font-serif font-bold text-lg text-ink">Formulir Pengesahan &amp; Redaksi Laporan</h3>
    <p class="text-xs text-ink-muted">
      Tinjau redaksi narasi resmi sebelum dicetak atau diekspor sebagai lampiran Berita Acara Verifikasi Pupuk Bersubsidi.
    </p>
  </div>

  <form action="proses_laporan.php" method="post" class="space-y-5">
    <input type="hidden" name="kth_id" value="<?= $kthId ?>">
    
    <div class="grid md:grid-cols-3 gap-4">
      <div>
        <label class="block text-xs font-semibold text-ink mb-1.5">Tahun Anggaran Usulan</label>
        <input type="text" name="tahun" value="<?= e($lap['tahun'] ?? date('Y')) ?>" class="w-full border border-kadaster-border rounded px-3 py-2 text-xs bg-white text-ink focus:border-forest-900 outline-none font-mono">
        <p class="text-[11px] text-ink-muted mt-1">Tahun alokasi pupuk bersubsidi.</p>
      </div>

      <div>
        <label class="block text-xs font-semibold text-ink mb-1.5">Penetapan Rekomendasi Petugas</label>
        <select name="rekomendasi" class="w-full border border-kadaster-border rounded px-3 py-2 text-xs bg-white text-ink focus:border-forest-900 outline-none font-semibold">
          <?php foreach (['Dapat Ditindaklanjuti', 'Perlu Revisi'] as $op): ?>
            <option value="<?= $op ?>" <?= (($lap['rekomendasi'] ?? $rekomAuto) === $op) ? 'selected' : '' ?>><?= $op ?></option>
          <?php endforeach; ?>
        </select>
        <p class="text-[11px] text-ink-muted mt-1">Sistem menyarankan: <b><?= e($rekomAuto) ?></b>.</p>
      </div>

      <div>
        <label class="block text-xs font-semibold text-ink mb-1.5">Ringkasan Angka Audit</label>
        <div class="border border-kadaster-border bg-kadaster-light rounded px-3 py-2 text-[11px] text-ink-muted font-mono leading-relaxed">
          Total: <?= $hitung['total'] ?> | SK: <?= $hitung['sesuai'] ?> ok, <?= $hitung['tidak'] ?> beda | Peta: <?= $hitung['dalam'] ?> dlm, <?= $hitung['luar'] ?> luar
        </div>
        <p class="text-[11px] text-ink-muted mt-1">Dihitung otomatis dari basis data.</p>
      </div>
    </div>

    <div x-data="{ len: <?= mb_strlen($lap['narasi'] ?? '', 'UTF-8') ?> }">
      <div class="flex items-center justify-between mb-1.5">
        <label class="block text-xs font-semibold text-ink">Teks Narasi Berita Acara / Rekomendasi</label>
        <span class="text-[11px] text-ink-muted font-mono" x-text="len + ' karakter'"></span>
      </div>
      <textarea name="narasi" rows="7" x-on:input="len = $event.target.value.length"
        class="w-full border border-kadaster-border rounded px-3.5 py-2.5 text-xs bg-white text-ink focus:border-forest-900 outline-none leading-relaxed font-sans"><?= e($lap['narasi'] ?? '') ?></textarea>
      <p class="text-[11px] text-ink-muted mt-1">
        Narasi ini akan tampil pada lembar cetak Berita Acara dan rekapan hasil audit Dinas Kehutanan.
      </p>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3 pt-3 border-t border-kadaster-border">
      <div class="flex flex-wrap items-center gap-2">
        <button type="submit" class="btn-forest px-5 py-2 text-xs inline-flex items-center gap-1.5 font-semibold">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
          Simpan Laporan &amp; Berita Acara
        </button>
        <a href="export.php?kth_id=<?= $kthId ?>" class="btn-kadaster px-4 py-2 text-xs inline-flex items-center gap-1.5 font-medium text-forest-700 hover:text-forest-900">
          <svg class="w-3.5 h-3.5 text-forest-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
          Unduh File Excel
        </a>
        <a href="cetak_peta.php?kth_id=<?= $kthId ?>" target="_blank" class="btn-kadaster px-4 py-2 text-xs inline-flex items-center gap-1.5 font-medium text-forest-700 hover:text-forest-900">
          <svg class="w-3.5 h-3.5 text-ink-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
          Cetak Peta Spasial
        </a>
      </div>

      <div>
        <a href="index.php" class="btn-kadaster px-4 py-2 text-xs inline-flex items-center gap-1.5 font-medium text-ink-muted">
          Kembali ke Buku Register
        </a>
      </div>
    </div>
  </form>
</div>

<!-- ═══ LAMPIRAN BERITA ACARA PERBAIKAN ═══ -->
<?php if ($hitung['tidak'] > 0): ?>
<div class="doc-card p-6 mb-5 fade-in" x-data="{
  uploading: false,
  namaFile: null,
  pesanError: null,
  pesanOk: null,
  upload(e) {
    const f = e.target.files[0];
    if (!f) return;
    const ext = f.name.split('.').pop().toLowerCase();
    if (!['doc','docx'].includes(ext)) {
      this.pesanError = 'Hanya file Word (.doc / .docx) yang diizinkan.';
      e.target.value = '';
      return;
    }
    if (f.size > 5 * 1024 * 1024) {
      this.pesanError = 'Ukuran file melebihi 5 MB.';
      e.target.value = '';
      return;
    }
    this.pesanError = null;
    this.namaFile = f.name;
  },
  submit(e) {
    const form = e.target;
    if (!this.namaFile) { this.pesanError = 'Pilih file terlebih dahulu.'; return; }
    this.uploading = true;
    this.pesanError = null;
    this.pesanOk = null;
    const fd = new FormData(form);
    fetch('proses_upload_ba.php', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(j => {
        this.uploading = false;
        if (j.ok) {
          this.pesanOk = '✅ ' + j.msg;
          setTimeout(() => location.reload(), 1200);
        } else {
          this.pesanError = '⚠️ ' + j.msg;
        }
      })
      .catch(() => {
        this.uploading = false;
        this.pesanError = 'Gagal menghubungi server.';
      });
  }
}">
  <div class="pb-3 border-b border-kadaster-border mb-5">
    <h3 class="font-serif font-bold text-lg text-ink flex items-center gap-2">
      <svg class="w-5 h-5 text-audit-revisi" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
      Lampiran Berita Acara Perbaikan
    </h3>
    <p class="text-xs text-ink-muted mt-1">
      Upload satu file Berita Acara (<code>.docx</code>) untuk seluruh kasus ini (<b><?= $hitung['tidak'] ?> petani</b> belum tercantum dalam SK).
      File ini menjadi dokumen pendukung perbaikan nama/NIK secara administratif.
      <span class="font-semibold text-amber-700">Data usulan asli tidak akan diubah.</span>
    </p>
  </div>

  <?php if ($baFile && is_file(__DIR__ . '/' . $baFile)): ?>
  <!-- File BA sudah ada -->
  <div class="flex flex-wrap items-center justify-between gap-4 p-4 bg-forest-50 border border-forest-200 rounded-md mb-4">
    <div class="flex items-center gap-3">
      <div class="w-10 h-10 rounded bg-blue-600 flex items-center justify-center flex-shrink-0">
        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
      </div>
      <div>
        <div class="font-semibold text-sm text-ink"><?= e($baNama ?? basename($baFile)) ?></div>
        <div class="text-xs text-ink-muted flex items-center gap-2 mt-0.5">
          <?php if ($baTgl): ?>
          <span>Tanggal BA: <b><?= e(date('d M Y', strtotime($baTgl))) ?></b></span>
          <span class="text-kadaster-border">·</span>
          <?php endif; ?>
          <span class="text-audit-valid font-semibold">✓ Terlampir</span>
        </div>
      </div>
    </div>
    <div class="flex items-center gap-2">
      <a href="<?= e($baFile) ?>" download="<?= e($baNama ?? basename($baFile)) ?>"
        class="btn-kadaster px-3.5 py-2 text-xs inline-flex items-center gap-1.5 font-medium text-forest-700 hover:text-forest-900">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        Unduh BA
      </a>
      <form action="hapus_ba.php" method="post" class="inline m-0"
        onsubmit="return confirm('Hapus file Berita Acara ini? File tidak dapat dipulihkan.')">
        <input type="hidden" name="kth_id" value="<?= $kthId ?>">
        <button type="submit"
          class="px-3.5 py-2 text-xs inline-flex items-center gap-1.5 font-medium text-audit-revisi hover:text-red-800 border border-red-200 rounded hover:bg-red-50 transition-colors">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
          Hapus
        </button>
      </form>
    </div>
  </div>
  <p class="text-[11px] text-ink-muted mb-3">Untuk mengganti file BA, upload file baru di bawah (file lama akan otomatis terhapus).</p>
  <?php else: ?>
  <div class="flex items-center gap-2 p-3 bg-amber-50 border border-amber-200 rounded mb-4 text-xs text-amber-800">
    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
    <span>Belum ada file Berita Acara yang diunggah untuk kasus ini.</span>
  </div>
  <?php endif; ?>

  <!-- Form Upload -->
  <form @submit.prevent="submit($event)" enctype="multipart/form-data" class="space-y-4">
    <input type="hidden" name="kth_id" value="<?= $kthId ?>">

    <div class="grid md:grid-cols-2 gap-4">
      <div>
        <label class="block text-xs font-semibold text-ink mb-1.5">File Berita Acara <span class="text-audit-revisi">*</span></label>
        <div class="relative">
          <input type="file" name="file_ba" accept=".doc,.docx" @change="upload($event)"
            class="w-full border border-kadaster-border rounded px-3 py-2 text-xs bg-white text-ink focus:border-forest-900 outline-none
                   file:mr-3 file:py-1 file:px-3 file:rounded file:border-0 file:text-xs file:font-semibold
                   file:bg-forest-900 file:text-white hover:file:bg-forest-800 cursor-pointer">
        </div>
        <p class="text-[11px] text-ink-muted mt-1">Format: .doc / .docx · Maks. 5 MB</p>
        <p x-show="namaFile" class="text-[11px] text-audit-valid font-semibold mt-1" x-text="'📄 ' + namaFile"></p>
      </div>

      <div>
        <label class="block text-xs font-semibold text-ink mb-1.5">Tanggal Berita Acara</label>
        <input type="date" name="tgl_ba" value="<?= e($baTgl ?? date('Y-m-d')) ?>"
          class="w-full border border-kadaster-border rounded px-3 py-2 text-xs bg-white text-ink focus:border-forest-900 outline-none">
        <p class="text-[11px] text-ink-muted mt-1">Opsional — tanggal penandatanganan BA perbaikan.</p>
      </div>
    </div>

    <div x-show="pesanError" class="text-xs text-audit-revisi font-semibold p-2 bg-red-50 border border-red-200 rounded" x-text="pesanError"></div>
    <div x-show="pesanOk"    class="text-xs text-audit-valid  font-semibold p-2 bg-green-50 border border-green-200 rounded" x-text="pesanOk"></div>

    <div class="flex items-center gap-3 pt-2 border-t border-kadaster-border">
      <button type="submit" :disabled="uploading"
        class="btn-forest px-5 py-2 text-xs inline-flex items-center gap-1.5 font-semibold disabled:opacity-50 disabled:cursor-not-allowed">
        <svg class="w-3.5 h-3.5" :class="uploading ? 'animate-spin' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
        <span x-text="uploading ? 'Mengunggah…' : 'Upload Berita Acara'"></span>
      </button>
      <p class="text-[11px] text-ink-muted">File disimpan ke server dan terhubung ke kasus ini.</p>
    </div>
  </form>
</div>
<?php endif; ?>

<!-- ═══ REFERENSI TEMPLATE RESMI ═══ -->
<div class="doc-card p-5 text-xs fade-in" x-data="{ open: false }">
  <button type="button" @click="open = !open" class="w-full flex items-center justify-between text-left">
    <div class="flex items-center gap-2">
      <svg class="w-4 h-4 text-ink-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
      <span class="font-serif font-bold text-ink">Format Standar Narasi Berita Acara Dinas</span>
    </div>
    <span class="text-[11px] text-forest-700 font-medium" x-text="open ? 'Tutup Panduan' : 'Lihat Standar Redaksi'"></span>
  </button>
  <div x-show="open" x-transition class="mt-3 pt-3 border-t border-kadaster-border">
    <p class="text-ink-muted mb-2">Redaksi default yang disusun oleh sistem menggunakan format klausul berikut:</p>
    <div class="bg-kadaster-light border border-kadaster-border rounded p-3 font-mono text-[11px] text-ink whitespace-pre-wrap leading-relaxed">Berdasarkan hasil verifikasi data usulan pupuk subsidi tahun [TAHUN] terdapat sebanyak [TOTAL] petani. Dari hasil telaah diperoleh data bahwa sejumlah [JUMLAH_SESUAI] petani sudah sesuai dengan SK [NOMOR_SK], terdapat [JUMLAH_TIDAK_SESUAI] petani yang belum masuk ke dalam SK tersebut. Titik koordinat petani yang mengusulkan pupuk, sejumlah [JUMLAH_DALAM_PETA] berada dalam peta areal [NAMA_KTH] dan [JUMLAH_LUAR_PETA] berada di luar peta.</div>
    <form action="proses_laporan.php" method="post" class="mt-3">
      <input type="hidden" name="kth_id" value="<?= $kthId ?>">
      <input type="hidden" name="reset_template" value="1">
      <button type="submit" class="text-forest-700 hover:text-forest-900 text-[11px] font-semibold inline-flex items-center gap-1.5">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
        Reset teks ke redaksi standar dinas
      </button>
    </form>
  </div>
</div>

<?php layout_foot(); ?>
