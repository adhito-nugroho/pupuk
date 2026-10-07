<?php
// Pengaturan global — nama verifikator penandatangan Lembar Hasil.
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/layout.php';

$pdo = db();
$nama    = pengaturan_get($pdo, 'nama_verifikator', '');
$nip     = pengaturan_get($pdo, 'nip_verifikator', '');
$jabatan = pengaturan_get($pdo, 'jabatan_verifikator', '');
$kembali = trim((string)($_GET['kembali'] ?? ''));

layout_head('Pengaturan Verifikator', 'pengaturan');
?>

<div class="doc-card p-6 mb-5 fade-in max-w-3xl">
  <div class="pb-4 border-b border-kadaster-border mb-5">
    <div class="text-[11px] font-semibold tracking-wider text-forest-700 uppercase mb-1">Pengaturan Dokumen</div>
    <h2 class="font-serif text-2xl font-bold text-ink">Nama Verifikator Penandatangan</h2>
    <p class="text-xs text-ink-muted mt-1">
      Nama di bawah ini akan tampil pada kolom tanda tangan
      <b>Tim Verifikator CDK Bojonegoro</b> di <b>Lembar Hasil Verifikasi</b>
      (cetak &amp; Excel). Kosongkan untuk kembali memakai garis titik-titik.
    </p>
  </div>

  <form action="proses_pengaturan.php" method="post" class="space-y-4">
    <?php if ($kembali !== ''): ?>
      <input type="hidden" name="kembali" value="<?= e($kembali) ?>">
    <?php endif; ?>

    <div>
      <label class="block text-xs font-semibold text-ink mb-1.5">Nama Verifikator <span class="text-audit-revisi">*</span></label>
      <input type="text" name="nama_verifikator" value="<?= e($nama) ?>" maxlength="120"
        placeholder="cth. Budi Santoso, S.Hut"
        class="w-full border border-kadaster-border rounded px-3 py-2 text-sm bg-white text-ink focus:border-forest-900 outline-none">
      <p class="text-[11px] text-ink-muted mt-1">Ditampilkan di dalam kurung tanda tangan, cth. <i>( Budi Santoso, S.Hut )</i>.</p>
    </div>

    <div class="grid md:grid-cols-2 gap-4">
      <div>
        <label class="block text-xs font-semibold text-ink mb-1.5">NIP (opsional)</label>
        <input type="text" name="nip_verifikator" value="<?= e($nip) ?>" maxlength="64"
          placeholder="cth. 19850101 201001 1 001"
          class="w-full border border-kadaster-border rounded px-3 py-2 text-sm bg-white text-ink focus:border-forest-900 outline-none font-mono">
        <p class="text-[11px] text-ink-muted mt-1">Bila diisi, tampil sebagai baris <i>NIP. …</i> di bawah nama.</p>
      </div>
      <div>
        <label class="block text-xs font-semibold text-ink mb-1.5">Jabatan (opsional)</label>
        <input type="text" name="jabatan_verifikator" value="<?= e($jabatan) ?>" maxlength="120"
          placeholder="cth. Verifikator Lapangan / Pengawas Hutan"
          class="w-full border border-kadaster-border rounded px-3 py-2 text-sm bg-white text-ink focus:border-forest-900 outline-none">
        <p class="text-[11px] text-ink-muted mt-1">Bila diisi, tampil di bawah label tim verifikator.</p>
      </div>
    </div>

    <div class="pt-4 border-t border-kadaster-border flex flex-wrap items-center gap-2">
      <button type="submit" class="btn-forest px-5 py-2 text-xs inline-flex items-center gap-1.5 font-semibold">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        Simpan Pengaturan
      </button>
      <?php if ($kembali !== ''): ?>
        <a href="<?= e($kembali) ?>" class="btn-kadaster px-4 py-2 text-xs font-medium">Kembali tanpa menyimpan</a>
      <?php else: ?>
        <a href="index.php" class="btn-kadaster px-4 py-2 text-xs font-medium">Kembali ke Buku Register</a>
      <?php endif; ?>
    </div>
  </form>
</div>

<div class="doc-card p-5 max-w-3xl fade-in">
  <h3 class="font-serif font-bold text-base text-ink mb-2">Pratinjau Kolom Tanda Tangan</h3>
  <div class="grid md:grid-cols-2 gap-4 text-center text-xs border border-kadaster-border rounded p-4 bg-kadaster-light">
    <div>
      <div>Mengetahui,</div>
      <div><b>Ketua Kelompok Tani Hutan</b></div>
      <div style="height:40px"></div>
      <div>( <b>Nama KTH</b> )</div>
    </div>
    <div>
      <div>Bojonegoro, <?= e(tgl_indo('now')) ?></div>
      <div><b>Tim Verifikator CDK Bojonegoro</b></div>
      <?php if ($jabatan !== ''): ?><div><?= e($jabatan) ?></div><?php endif; ?>
      <div style="height:40px"></div>
      <?php if ($nama !== ''): ?>
        <div>( <b><u><?= e($nama) ?></u></b> )</div>
        <?php if ($nip !== ''): ?><div class="font-mono">NIP. <?= e($nip) ?></div><?php endif; ?>
      <?php else: ?>
        <div>( ..................................................... )</div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php layout_foot(); ?>
