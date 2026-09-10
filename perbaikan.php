<?php
// perbaikan.php — Halaman Riwayat & Upload Berkas Usulan Perbaikan
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/layout.php';

$kthId = (int)($_GET['kth_id'] ?? 0);
if (!$kthId) {
    flash_set('error', 'Parameter kth_id tidak valid.');
    header('Location: index.php');
    exit;
}

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
$versiAktif = (int)($kth['versi_aktif'] ?? 1);
$nextVersiKe = count($daftarVersi) + 1;

layout_head('Riwayat & Upload Perbaikan — ' . ($kth['nama_kth'] ?? ''));
layout_kth_subnav($kth, 'perbaikan', $versiAktif, $daftarVersi);
?>

<div class="grid lg:grid-cols-3 gap-6 items-start">
  <!-- Kolom Kiri: Form Upload Usulan Perbaikan Baru -->
  <div class="lg:col-span-1">
    <div class="doc-card p-5">
      <div class="pb-3 border-b border-kadaster-border mb-4">
        <div class="flex items-center gap-2">
          <span class="w-7 h-7 rounded bg-forest-900 text-white flex items-center justify-center text-xs font-bold font-mono">
            +v<?= $nextVersiKe ?>
          </span>
          <div>
            <h3 class="font-serif font-bold text-base text-ink">Upload Usulan Perbaikan</h3>
            <p class="text-[11px] text-ink-muted">Unggah berkas Excel revisi/perbaikan baru.</p>
          </div>
        </div>
      </div>

      <form action="proses_upload_perbaikan.php" method="post" enctype="multipart/form-data" class="space-y-4">
        <input type="hidden" name="kth_id" value="<?= $kthId ?>">

        <div>
          <label class="block text-xs font-semibold text-ink mb-1">
            Label / Judul Putaran Revisi <span class="text-audit-revisi">*</span>
          </label>
          <input type="text" name="label_versi" required
                 value="Perbaikan Ke-<?= $nextVersiKe - 1 ?> (v<?= $nextVersiKe ?>)"
                 placeholder="Contoh: Perbaikan NIK & Koordinat Desa..."
                 class="w-full border border-kadaster-border rounded px-3 py-2 text-xs bg-white text-ink focus:border-forest-900 outline-none">
          <p class="text-[10.5px] text-ink-muted mt-1">Penanda untuk membedakan putaran revisi ini.</p>
        </div>

        <div>
          <label class="block text-xs font-semibold text-ink mb-1">
            File Excel Usulan Perbaikan <span class="text-audit-revisi">*</span>
          </label>
          <div class="relative">
            <input type="file" name="f_usulan_perbaikan" accept=".xlsx,.xls" required
                   class="w-full border border-kadaster-border rounded px-3 py-2 text-xs bg-white text-ink focus:border-forest-900 outline-none
                          file:mr-3 file:py-1 file:px-3 file:rounded file:border-0 file:text-xs file:font-semibold
                          file:bg-forest-900 file:text-white hover:file:bg-forest-800 cursor-pointer">
          </div>
          <p class="text-[10.5px] text-ink-muted mt-1">Format: Excel (<code>.xlsx</code> / <code>.xls</code>) · Maks 50 MB.</p>
        </div>

        <div>
          <label class="block text-xs font-semibold text-ink mb-1">
            Catatan Perubahan / Keterangan
          </label>
          <textarea name="catatan_perbaikan" rows="3"
                    placeholder="Contoh: Perbaikan NIK 3 anggota yang typo dan pembaruan 5 titik koordinat pemohon..."
                    class="w-full border border-kadaster-border rounded px-3 py-2 text-xs bg-white text-ink focus:border-forest-900 outline-none"></textarea>
          <p class="text-[10.5px] text-ink-muted mt-1">Opsional — catatan dokumentasi untuk audit.</p>
        </div>

        <div class="p-3 bg-amber-50 border border-amber-200 rounded text-xs text-amber-900 space-y-1">
          <div class="font-bold flex items-center gap-1.5 text-[11px] text-amber-800 uppercase tracking-wider">
            <span>🛡️</span> Data Lama Terlindungi
          </div>
          <p class="text-[11px] leading-relaxed">
            Data usulan versi terdahulu tetap tersimpan utuh di database. Sistem akan otomatis memverifikasi berkas perbaikan ini terhadap SK dan Peta SHP yang sudah terdaftar.
          </p>
        </div>

        <button type="submit"
                class="w-full btn-forest py-2.5 px-4 text-xs font-semibold rounded flex items-center justify-center gap-2">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
          <span>Unggah & Verifikasi Versi Baru</span>
        </button>
      </form>
    </div>
  </div>

  <!-- Kolom Kanan: Timeline Riwayat Versi Usulan -->
  <div class="lg:col-span-2">
    <div class="doc-card p-5">
      <div class="pb-3 border-b border-kadaster-border mb-4 flex flex-wrap items-center justify-between gap-2">
        <div>
          <h3 class="font-serif font-bold text-base text-ink">Rekam Jejak Versi Usulan (Audit Trail)</h3>
          <p class="text-xs text-ink-muted mt-0.5">Seluruh putaran usulan pupuk yang pernah diajukan untuk kasus ini.</p>
        </div>
        <span class="text-xs font-mono font-bold px-2.5 py-1 rounded bg-forest-50 text-forest-900 border border-forest-100">
          Total <?= count($daftarVersi) ?> Versi
        </span>
      </div>

      <div class="space-y-4">
        <?php foreach ($daftarVersi as $v): 
            $vNum = (int)$v['versi_ke'];
            $isAktif = $vNum === $versiAktif;
            $isDapat = ($v['rekomendasi'] ?? '') === 'Dapat Ditindaklanjuti';
            $tglStr = !empty($v['dibuat_pada']) ? date('d M Y · H:i', strtotime($v['dibuat_pada'])) : '-';
        ?>
        <div class="p-4 border rounded-md transition-all <?= $isAktif 
          ? 'bg-forest-50/70 border-forest-300 ring-1 ring-forest-900/10' 
          : 'bg-white border-kadaster-border hover:border-forest-200' ?>">
          
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="flex items-start gap-3">
              <div class="w-9 h-9 rounded flex items-center justify-center font-mono font-bold text-xs flex-shrink-0 <?= $isAktif ? 'bg-forest-900 text-white' : 'bg-[#EAE4D8] text-ink' ?>">
                v<?= $vNum ?>
              </div>
              <div>
                <div class="flex items-center gap-2 flex-wrap">
                  <h4 class="font-bold text-sm text-ink"><?= e($v['label_versi'] ?: ('Versi ' . $vNum)) ?></h4>
                  <?php if ($isAktif): ?>
                  <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-emerald-700 text-white uppercase tracking-wider">
                    Versi Aktif
                  </span>
                  <?php endif; ?>
                </div>

                <div class="text-xs text-ink-muted flex flex-wrap items-center gap-x-3 gap-y-1 mt-1">
                  <span>📅 <?= $tglStr ?> WIB</span>
                  <span class="text-kadaster-border">·</span>
                  <span>📄 <?= e($v['nama_file_asli'] ?: 'Berkas Usulan') ?></span>
                </div>
              </div>
            </div>

            <div class="flex items-center gap-2">
              <a href="hasil.php?kth_id=<?= $kthId ?>&v=<?= $vNum ?>"
                 class="btn-kadaster px-3.5 py-1.5 text-xs font-semibold inline-flex items-center gap-1.5 text-forest-900 hover:bg-forest-100">
                <span>Lihat Hasil</span>
                <span>→</span>
              </a>
            </div>
          </div>

          <!-- Matriks Ringkasan Angka Hasil Verifikasi -->
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mt-3 pt-3 border-t border-kadaster-border/60 text-xs">
            <div class="bg-white/80 p-2 rounded border border-kadaster-border/50">
              <div class="text-[10.5px] text-ink-muted">Total Usulan</div>
              <div class="font-bold text-ink mt-0.5"><?= number_format((int)$v['total_petani']) ?> Petani</div>
              <div class="text-[10px] text-ink-faint"><?= number_format((float)$v['total_luas'], 2, ',', '.') ?> Ha</div>
            </div>

            <div class="bg-white/80 p-2 rounded border border-kadaster-border/50">
              <div class="text-[10.5px] text-ink-muted">Kesesuaian SK</div>
              <div class="font-bold text-audit-valid mt-0.5"><?= (int)$v['jumlah_sesuai_sk'] ?> Sesuai</div>
              <?php if ((int)$v['jumlah_tidak_sesuai_sk'] > 0): ?>
              <div class="text-[10px] text-audit-revisi font-semibold"><?= (int)$v['jumlah_tidak_sesuai_sk'] ?> Belum Sesuai</div>
              <?php endif; ?>
            </div>

            <div class="bg-white/80 p-2 rounded border border-kadaster-border/50">
              <div class="text-[10.5px] text-ink-muted">Posisi Spasial</div>
              <div class="font-bold text-forest-900 mt-0.5"><?= (int)$v['jumlah_dalam_peta'] ?> Dalam Peta</div>
              <?php if ((int)$v['jumlah_luar_peta'] > 0): ?>
              <div class="text-[10px] text-audit-warn font-semibold"><?= (int)$v['jumlah_luar_peta'] ?> Luar Peta</div>
              <?php endif; ?>
            </div>

            <div class="bg-white/80 p-2 rounded border border-kadaster-border/50 flex flex-col justify-center">
              <div class="text-[10.5px] text-ink-muted mb-0.5">Rekomendasi</div>
              <div>
                <?php if ($isDapat): ?>
                <span class="inline-flex items-center gap-1 text-[11px] font-bold text-audit-valid">
                  <span class="w-1.5 h-1.5 rounded-full bg-audit-valid"></span> Dapat Disetujui
                </span>
                <?php else: ?>
                <span class="inline-flex items-center gap-1 text-[11px] font-bold text-audit-revisi">
                  <span class="w-1.5 h-1.5 rounded-full bg-audit-revisi"></span> Perlu Revisi
                </span>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <?php if (!empty($v['catatan_perbaikan'])): ?>
          <div class="mt-2.5 text-[11px] text-ink-muted bg-[#F6F3EC] px-3 py-1.5 rounded border border-kadaster-border/40">
            <span class="font-semibold text-ink">Catatan:</span> <?= e($v['catatan_perbaikan']) ?>
          </div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<?php layout_foot(); ?>
