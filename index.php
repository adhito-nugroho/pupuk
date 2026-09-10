<?php
// Daftar kasus verifikasi — Buku Register Kasus CDK Bojonegoro.
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/layout.php';

// Query kasus
$rows = db()->query('SELECT k.*,
    (SELECT COUNT(*) FROM usulan_pupuk u WHERE u.kth_id = k.id AND u.versi_ke = COALESCE(k.versi_aktif, 1)) AS jml_usulan,
    (SELECT COUNT(*) FROM sk_anggota s WHERE s.kth_id = k.id) AS jml_sk,
    (SELECT rekomendasi FROM laporan l WHERE l.kth_id = k.id ORDER BY l.id DESC LIMIT 1) AS rekomendasi
  FROM kth k ORDER BY k.id DESC')->fetchAll();

// Ambil seluruh riwayat versi usulan
$semuaVersi = db()->query('SELECT * FROM kth_versi_usulan ORDER BY kth_id ASC, versi_ke DESC')->fetchAll();
$versiPerKth = [];
foreach ($semuaVersi as $v) {
    $versiPerKth[$v['kth_id']][] = $v;
}

$totalKasus  = count($rows);
$perluRevisi = count(array_filter($rows, fn($r) => ($r['rekomendasi'] ?? '') === 'Perlu Revisi'));
$dapatTindak = count(array_filter($rows, fn($r) => ($r['rekomendasi'] ?? '') === 'Dapat Ditindaklanjuti'));
$totalPetani = array_sum(array_map(fn($r) => (int)$r['jml_usulan'], $rows));

layout_head('Buku Register Kasus', 'daftar');
?>

<!-- ═══ Bilah Ringkasan Register (Bukan SaaS Stat Card) ═══ -->
<div class="doc-card mb-6 p-5">
  <div class="flex flex-col md:flex-row md:items-center justify-between gap-5 pb-4 border-b border-kadaster-border">
    <div>
      <div class="text-[11px] font-mono uppercase tracking-wider text-ink-faint">
        Dokumen Register CDK Bojonegoro · Tahun Usulan <?= date('Y') ?>
      </div>
      <h2 class="font-serif text-2xl font-bold text-ink mt-0.5">
        Buku Register Verifikasi Usulan Pupuk
      </h2>
      <p class="text-xs text-ink-muted mt-1 max-w-2xl leading-relaxed">
        Pencatatan resmi verifikasi kesesuaian data usulan petani hutan terhadap Surat Keputusan (SK) Menteri LHK dan batas areal peta Perhutanan Sosial (KHDPK).
      </p>
    </div>

    <!-- Tombol Buka Berkas Baru -->
    <a href="baru.php" class="btn-forest px-4 py-2.5 text-xs inline-flex items-center gap-2 flex-shrink-0">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
      </svg>
      <span>Buka Kasus Verifikasi Baru</span>
    </a>
  </div>

  <!-- Baris Data Faktual (Ledger Summary) -->
  <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-4 text-xs">
    <div class="border-l-2 border-kadaster-brown pl-3">
      <div class="text-[11px] text-ink-muted font-medium">Total Kasus KTH</div>
      <div class="font-mono text-xl font-bold text-ink tabular-nums mt-0.5"><?= $totalKasus ?> <span class="text-xs font-normal text-ink-muted">berkas</span></div>
    </div>
    <div class="border-l-2 border-audit-valid pl-3">
      <div class="text-[11px] text-ink-muted font-medium">Dapat Ditindaklanjuti</div>
      <div class="font-mono text-xl font-bold text-audit-valid tabular-nums mt-0.5"><?= $dapatTindak ?> <span class="text-xs font-normal text-ink-muted">kasus</span></div>
    </div>
    <div class="border-l-2 border-audit-revisi pl-3">
      <div class="text-[11px] text-ink-muted font-medium">Memerlukan Revisi</div>
      <div class="font-mono text-xl font-bold text-audit-revisi tabular-nums mt-0.5"><?= $perluRevisi ?> <span class="text-xs font-normal text-ink-muted">kasus</span></div>
    </div>
    <div class="border-l-2 border-kadaster-dark pl-3">
      <div class="text-[11px] text-ink-muted font-medium">Total Petani Terdata</div>
      <div class="font-mono text-xl font-bold text-ink tabular-nums mt-0.5"><?= number_format($totalPetani, 0, ',', '.') ?> <span class="text-xs font-normal text-ink-muted">orang</span></div>
    </div>
  </div>
</div>

<!-- ═══ Tabel Register Berkas ═══ -->
<div class="doc-card overflow-hidden">
  <div class="px-5 py-3.5 bg-[#FAF8F3] border-b border-kadaster-border flex items-center justify-between">
    <div class="flex items-center gap-2">
      <span class="w-2 h-2 bg-kadaster-brown inline-block"></span>
      <h3 class="text-xs font-bold uppercase tracking-wider text-ink">
        Daftar Berkas Terdaftar
      </h3>
    </div>
    <span class="font-mono text-[11px] text-ink-faint">
      <?= count($rows) ?> entri tercatat
    </span>
  </div>

<?php if (!$rows): ?>
  <div class="py-16 px-6 text-center">
    <div class="w-12 h-12 border border-dashed border-kadaster-brown/40 text-kadaster-brown mx-auto flex items-center justify-center mb-3">
      <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
      </svg>
    </div>
    <h3 class="font-serif text-base font-bold text-ink mb-1">Belum Ada Berkas Kasus</h3>
    <p class="text-xs text-ink-muted mb-4 max-w-sm mx-auto">
      Belum ada data kasus KTH yang dimasukkan. Silakan mulai dengan membuat berkas verifikasi usulan baru.
    </p>
    <a href="baru.php" class="btn-forest px-4 py-2 text-xs inline-flex items-center gap-1.5">
      + Buka Kasus Verifikasi Baru
    </a>
  </div>
<?php else: ?>
  <div class="overflow-x-auto">
    <table class="min-w-full text-xs text-left">
      <thead>
        <tr class="bg-[#F6F2E9] border-b border-kadaster-border text-[11px] font-semibold text-ink-muted">
          <th class="py-2.5 px-3.5 w-12 text-center">No</th>
          <th class="py-2.5 px-3.5">Lembaga Pemohon (KTH / LMDH)</th>
          <th class="py-2.5 px-3.5">Nomor SK Perhutanan Sosial</th>
          <th class="py-2.5 px-3 text-center">Tahun</th>
          <th class="py-2.5 px-3 text-right">Diusulkan</th>
          <th class="py-2.5 px-3 text-right">SK Anggota</th>
          <th class="py-2.5 px-3.5">Status Rekomendasi</th>
          <th class="py-2.5 px-3.5 text-center">Tindakan Berkas</th>
        </tr>
      </thead>
      <?php foreach ($rows as $idx => $r): 
        $kthId = (int)$r['id'];
        $listVersi = $versiPerKth[$kthId] ?? [];
        $totalVersi = count($listVersi);
        $versiAktif = (int)($r['versi_aktif'] ?? 1);
        if ($versiAktif <= 0) $versiAktif = 1;
      ?>
      <tbody x-data="{ open: false }" class="border-b border-kadaster-border/70 last:border-b-0">
        <tr class="hairline-row bg-white hover:bg-forest-50/30 transition-colors">
          <td class="py-3 px-3.5 text-center font-mono text-[11px] text-ink-faint">
            <div class="flex items-center justify-center gap-1.5">
              <span><?= sprintf('%02d', $idx + 1) ?></span>
              <?php if ($totalVersi > 0): ?>
              <button @click="open = !open" 
                      type="button"
                      class="text-forest-800 hover:text-forest-950 p-0.5 rounded transition-transform"
                      :class="{ 'rotate-180': open }"
                      title="Lihat Histori Perbaikan">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
              </button>
              <?php endif; ?>
            </div>
          </td>
          <td class="py-3 px-3.5">
            <div class="font-bold text-ink leading-snug flex items-center gap-2">
              <span><?= e($r['nama_kth']) ?></span>
              <?php if ($totalVersi > 1): ?>
                <button @click="open = !open" 
                        type="button" 
                        class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-mono font-semibold bg-forest-100 text-forest-900 border border-forest-300/80 hover:bg-forest-200 transition-colors">
                  <span>v<?= $versiAktif ?> aktif</span>
                  <span class="text-ink-muted font-normal">(<?= $totalVersi ?> versi)</span>
                  <svg class="w-2.5 h-2.5 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                  </svg>
                </button>
              <?php elseif ($totalVersi === 1): ?>
                <span class="text-[10px] font-mono text-ink-faint px-1.5 py-0.2 border border-kadaster-border/60 rounded">v1</span>
              <?php endif; ?>
            </div>
            <?php if ($r['nama_kph'] ?? ''): ?>
              <div class="text-[11px] text-kadaster-brown font-medium mt-0.5">
                <?= e($r['nama_kph']) ?>
              </div>
            <?php endif; ?>
          </td>
          <td class="py-3 px-3.5 font-mono text-[11px] text-ink-muted">
            <?= e($r['nomor_sk'] ?? '-') ?>
          </td>
          <td class="py-3 px-3 text-center font-mono text-[11px] text-ink-muted">
            <?= e($r['tahun_usulan'] ?? '-') ?>
          </td>
          <td class="py-3 px-3 text-right font-mono font-bold text-ink tabular-nums">
            <?= (int)$r['jml_usulan'] ?>
          </td>
          <td class="py-3 px-3 text-right font-mono font-bold text-ink tabular-nums">
            <?= (int)$r['jml_sk'] ?>
          </td>
          <td class="py-3 px-3.5">
            <?php
              $rek = $r['rekomendasi'] ?? '';
              if ($rek === 'Dapat Ditindaklanjuti'):
            ?>
              <span class="inline-flex items-center gap-1.5 px-2 py-0.5 text-xs font-semibold text-audit-valid bg-audit-validBg border border-audit-validBorder border-l-2 border-l-audit-valid">
                <span class="w-1.5 h-1.5 bg-audit-valid inline-block"></span>
                Dapat Ditindaklanjuti
              </span>
            <?php elseif ($rek === 'Perlu Revisi'): ?>
              <span class="inline-flex items-center gap-1.5 px-2 py-0.5 text-xs font-semibold text-audit-revisi bg-audit-revisiBg border border-audit-revisiBorder border-l-2 border-l-audit-revisi">
                <span class="w-1.5 h-1.5 bg-audit-revisi inline-block"></span>
                Perlu Revisi
              </span>
            <?php else: ?>
              <span class="text-xs text-ink-faint italic">Belum diverifikasi</span>
            <?php endif; ?>
          </td>
          <td class="py-3 px-3.5">
            <div class="flex items-center justify-center gap-1">
              <a href="konfirmasi_sk.php?kth_id=<?= (int)$r['id'] ?>"
                 title="Tahap 02 — Konfirmasi SK"
                 class="px-2 py-1 border border-kadaster-border text-ink-muted hover:text-forest-900 hover:border-forest-900 bg-[#FAF8F3] text-[11px] font-medium transition-colors">
                SK
              </a>
              <a href="hasil.php?kth_id=<?= (int)$r['id'] ?>"
                 title="Tahap 03 — Uji Spasial & Titik"
                 class="px-2 py-1 border border-kadaster-border text-ink-muted hover:text-forest-900 hover:border-forest-900 bg-[#FAF8F3] text-[11px] font-medium transition-colors">
                Peta
              </a>
              <a href="cetak_peta.php?kth_id=<?= (int)$r['id'] ?>"
                 target="_blank"
                 title="Cetak Peta Format BPKH"
                 class="px-2 py-1 border border-kadaster-border text-ink-muted hover:text-forest-900 hover:border-forest-900 bg-[#FAF8F3] text-[11px] font-medium transition-colors">
                Cetak
              </a>
              <a href="laporan.php?kth_id=<?= (int)$r['id'] ?>"
                 title="Tahap 04 — Berita Acara Rekomendasi"
                 class="px-2 py-1 border border-kadaster-border text-ink-muted hover:text-forest-900 hover:border-forest-900 bg-[#FAF8F3] text-[11px] font-medium transition-colors">
                Laporan
              </a>
              <a href="perbaikan.php?kth_id=<?= (int)$r['id'] ?>"
                 title="Histori & Unggah Perbaikan Usulan"
                 class="px-2 py-1 border border-kadaster-border text-forest-900 hover:bg-forest-100 hover:border-forest-900 bg-forest-50/80 text-[11px] font-semibold transition-colors flex items-center gap-1">
                <span>Perbaikan</span>
              </a>
              <a href="hapus.php?kth_id=<?= (int)$r['id'] ?>"
                 onclick="return confirm('Hapus seluruh berkas kasus ini beserta semua versi perbaikannya?')"
                 title="Hapus Berkas Kasus Lengkap"
                 class="px-1.5 py-1 border border-audit-revisiBorder text-audit-revisi hover:bg-audit-revisiBg text-[11px] transition-colors ml-1">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
              </a>
            </div>
          </td>
        </tr>

        <!-- ═══ Collapsible Histori Perbaikan Usulan ═══ -->
        <tr x-show="open" x-cloak class="bg-[#FAF7F0] border-t border-dashed border-kadaster-border">
          <td colspan="8" class="p-4 sm:p-5">
            <div class="bg-white border border-kadaster-border rounded p-4 shadow-sm">
              <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-kadaster-border">
                <div class="flex items-center gap-2">
                  <div class="w-2.5 h-2.5 bg-forest-900 rounded-sm"></div>
                  <h4 class="font-serif font-bold text-sm text-ink">
                    Histori Putaran Usulan & Hasil Verifikasi — <?= e($r['nama_kth']) ?>
                  </h4>
                </div>
                <div class="flex items-center gap-2">
                  <a href="perbaikan.php?kth_id=<?= $kthId ?>" class="btn-forest px-3 py-1.5 text-xs inline-flex items-center gap-1.5 rounded">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    <span>+ Upload Revisi Baru</span>
                  </a>
                </div>
              </div>

              <?php if (empty($listVersi)): ?>
                <div class="py-4 text-center text-xs text-ink-muted">
                  Belum ada catatan histori versi perbaikan yang terekam.
                </div>
              <?php else: ?>
                <div class="mt-3 space-y-2.5">
                  <?php foreach ($listVersi as $v): 
                    $vNum = (int)$v['versi_ke'];
                    $isAktif = ($vNum === $versiAktif);
                    $isDapat = ($v['rekomendasi'] ?? '') === 'Dapat Ditindaklanjuti';
                    $tglStr = !empty($v['dibuat_pada']) ? date('d M Y · H:i', strtotime($v['dibuat_pada'])) : '-';
                  ?>
                  <div class="p-3 border rounded transition-all <?= $isAktif 
                    ? 'bg-forest-50/70 border-forest-300 ring-1 ring-forest-900/10' 
                    : 'bg-[#FCFAF6] border-kadaster-border hover:border-forest-200' ?>">
                    
                    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3">
                      <!-- Info Versi -->
                      <div class="flex items-start gap-3">
                        <div class="w-8 h-8 rounded flex items-center justify-center font-mono font-bold text-xs flex-shrink-0 <?= $isAktif ? 'bg-forest-900 text-white' : 'bg-[#EAE4D8] text-ink' ?>">
                          v<?= $vNum ?>
                        </div>
                        <div>
                          <div class="flex items-center gap-2 flex-wrap">
                            <span class="font-bold text-xs text-ink"><?= e($v['label_versi'] ?: ('Versi ' . $vNum)) ?></span>
                            <?php if ($isAktif): ?>
                            <span class="text-[9px] font-bold px-1.5 py-0.2 rounded bg-emerald-700 text-white uppercase tracking-wider">
                              Aktif
                            </span>
                            <?php endif; ?>
                            <span class="text-[11px] text-ink-faint">📅 <?= $tglStr ?></span>
                            <?php if (!empty($v['nama_file_asli'])): ?>
                            <span class="text-kadaster-border">·</span>
                            <span class="text-[11px] text-ink-muted font-mono truncate max-w-xs" title="<?= e($v['nama_file_asli']) ?>">
                              📄 <?= e($v['nama_file_asli']) ?>
                            </span>
                            <?php endif; ?>
                          </div>

                          <!-- Ringkasan Angka Audit -->
                          <div class="flex flex-wrap items-center gap-x-4 gap-y-1 mt-1 text-[11px]">
                            <span><strong class="text-ink font-mono"><?= number_format((int)$v['total_petani']) ?></strong> petani (<?= number_format((float)$v['total_luas'], 2, ',', '.') ?> ha)</span>
                            <span class="text-kadaster-border">|</span>
                            <span class="text-audit-valid font-medium">SK: <strong><?= (int)$v['jumlah_sesuai_sk'] ?></strong> sesuai</span>
                            <?php if ((int)$v['jumlah_tidak_sesuai_sk'] > 0): ?>
                              <span class="text-audit-revisi font-medium">(<?= (int)$v['jumlah_tidak_sesuai_sk'] ?> beda)</span>
                            <?php endif; ?>
                            <span class="text-kadaster-border">|</span>
                            <span class="text-forest-900 font-medium">Peta: <strong><?= (int)$v['jumlah_dalam_peta'] ?></strong> dalam</span>
                            <?php if ((int)$v['jumlah_luar_peta'] > 0): ?>
                              <span class="text-audit-warn font-medium">(<?= (int)$v['jumlah_luar_peta'] ?> luar)</span>
                            <?php endif; ?>
                            <span class="text-kadaster-border">|</span>
                            <span>
                              <?php if ($isDapat): ?>
                                <span class="text-audit-valid font-bold inline-flex items-center gap-1">● Dapat Disetujui</span>
                              <?php else: ?>
                                <span class="text-audit-revisi font-bold inline-flex items-center gap-1">■ Perlu Revisi</span>
                              <?php endif; ?>
                            </span>
                          </div>

                          <?php if (!empty($v['catatan_perbaikan'])): ?>
                          <div class="mt-1 text-[11px] text-ink-muted italic">
                            Catatan: <?= e($v['catatan_perbaikan']) ?>
                          </div>
                          <?php endif; ?>
                        </div>
                      </div>

                      <!-- Tombol Aksi Tiap Versi -->
                      <div class="flex items-center gap-1.5 flex-shrink-0 self-end lg:self-center">
                        <a href="hasil.php?kth_id=<?= $kthId ?>&v=<?= $vNum ?>"
                           title="Lihat Peta & Matriks Hasil Versi <?= $vNum ?>"
                           class="px-2.5 py-1 text-[11px] font-semibold border border-forest-700 bg-white text-forest-900 hover:bg-forest-100 rounded">
                          Hasil Uji
                        </a>
                        <a href="laporan.php?kth_id=<?= $kthId ?>&v=<?= $vNum ?>"
                           title="Lihat Berita Acara Rekomendasi Versi <?= $vNum ?>"
                           class="px-2.5 py-1 text-[11px] font-semibold border border-kadaster-border bg-white text-ink-muted hover:text-forest-900 hover:border-forest-900 rounded">
                          Laporan
                        </a>
                        <a href="cetak_peta.php?kth_id=<?= $kthId ?>&v=<?= $vNum ?>"
                           target="_blank"
                           title="Cetak Peta Versi <?= $vNum ?>"
                           class="px-2.5 py-1 text-[11px] font-semibold border border-kadaster-border bg-white text-ink-muted hover:text-forest-900 hover:border-forest-900 rounded">
                          Cetak
                        </a>

                        <?php if ($vNum > 1): ?>
                        <a href="hapus_versi.php?kth_id=<?= $kthId ?>&versi_ke=<?= $vNum ?>&from=index"
                           onclick="return confirm('Apakah Anda yakin ingin MENGHAPUS versi perbaikan v<?= $vNum ?> ini? Data verifikasi versi ini akan dihapus permanen dan versi aktif dialihkan ke versi terbaru lainnya.')"
                           title="Hapus Versi Perbaikan <?= $vNum ?>"
                           class="px-2 py-1 text-[11px] font-semibold border border-audit-revisiBorder text-audit-revisi hover:bg-audit-revisiBg rounded inline-flex items-center gap-1 ml-1">
                          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                          </svg>
                          <span>Hapus</span>
                        </a>
                        <?php endif; ?>
                      </div>
                    </div>
                  </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      </tbody>
      <?php endforeach; ?>
    </table>
  </div>
<?php endif; ?>
</div>
</div>

<?php layout_foot(); ?>
