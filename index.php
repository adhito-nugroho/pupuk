<?php
// Daftar kasus verifikasi — Dashboard utama.
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/layout.php';

$rows = db()->query('SELECT k.*,
    (SELECT COUNT(*) FROM usulan_pupuk u WHERE u.kth_id = k.id) AS jml_usulan,
    (SELECT COUNT(*) FROM sk_anggota s WHERE s.kth_id = k.id) AS jml_sk,
    (SELECT rekomendasi FROM laporan l WHERE l.kth_id = k.id ORDER BY l.id DESC LIMIT 1) AS rekomendasi
  FROM kth k ORDER BY k.id DESC')->fetchAll();

$totalKasus  = count($rows);
$perluRevisi = count(array_filter($rows, fn($r) => ($r['rekomendasi'] ?? '') === 'Perlu Revisi'));
$dapatTindak = count(array_filter($rows, fn($r) => ($r['rekomendasi'] ?? '') === 'Dapat Ditindaklanjuti'));

layout_head('Daftar Kasus', 'daftar');
?>

<!-- ═══ Statistik Cards ═══ -->
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6 fade-in">
  <div class="glass-card rounded-2xl p-5 flex items-center gap-4">
    <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-slate-500 to-slate-700 flex items-center justify-center text-white text-xl shadow-lg">📋</div>
    <div>
      <div class="text-2xl font-extrabold text-slate-800"><?= $totalKasus ?></div>
      <div class="text-xs text-slate-500 font-medium">Total Kasus</div>
    </div>
  </div>
  <div class="glass-card rounded-2xl p-5 flex items-center gap-4">
    <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-emerald-500 to-emerald-700 flex items-center justify-center text-white text-xl shadow-lg">✅</div>
    <div>
      <div class="text-2xl font-extrabold text-emerald-700"><?= $dapatTindak ?></div>
      <div class="text-xs text-slate-500 font-medium">Dapat Ditindaklanjuti</div>
    </div>
  </div>
  <div class="glass-card rounded-2xl p-5 flex items-center gap-4">
    <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-amber-500 to-orange-600 flex items-center justify-center text-white text-xl shadow-lg">⚠️</div>
    <div>
      <div class="text-2xl font-extrabold text-amber-700"><?= $perluRevisi ?></div>
      <div class="text-xs text-slate-500 font-medium">Perlu Revisi</div>
    </div>
  </div>
</div>

<!-- ═══ Header + CTA ═══ -->
<div class="glass-card rounded-2xl p-5 mb-5 fade-in fade-in-delay-1">
  <div class="flex flex-wrap items-center justify-between gap-3">
    <div>
      <h2 class="text-xl font-bold text-slate-800">Daftar Kasus Verifikasi</h2>
      <p class="text-sm text-slate-500 mt-0.5">Satu kasus = satu KTH/LMDH dengan 3 input (Excel usulan, PDF SK, ZIP shapefile).</p>
    </div>
    <a href="baru.php" class="btn-primary px-5 py-2.5 rounded-xl text-sm inline-flex items-center gap-2 shadow">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
      Buat Verifikasi Baru
    </a>
  </div>
</div>

<!-- ═══ Tabel ═══ -->
<div class="glass-card rounded-2xl overflow-hidden fade-in fade-in-delay-2">
<?php if (!$rows): ?>
  <div class="py-16 px-6 text-center">
    <div class="text-6xl mb-4 opacity-60">📂</div>
    <h3 class="text-lg font-bold text-slate-600 mb-1">Belum ada kasus verifikasi</h3>
    <p class="text-sm text-slate-400 mb-5">Mulai dengan menambahkan kasus baru untuk memverifikasi data petani.</p>
    <a href="baru.php" class="btn-primary px-5 py-2.5 rounded-xl text-sm inline-flex items-center gap-2">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
      Buat Verifikasi Baru
    </a>
  </div>
<?php else: ?>
<div class="overflow-x-auto">
<table class="min-w-full text-sm">
  <thead>
    <tr class="bg-gradient-to-r from-slate-50 to-slate-100 border-b border-slate-200">
      <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">ID</th>
      <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">KTH / LMDH</th>
      <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">No. SK</th>
      <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider">Tahun</th>
      <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider">Usulan</th>
      <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider">Anggota SK</th>
      <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Rekomendasi</th>
      <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider">Aksi</th>
    </tr>
  </thead>
  <tbody class="divide-y divide-slate-100">
  <?php foreach ($rows as $idx => $r): ?>
    <tr class="tbl-row <?= $idx % 2 === 0 ? 'bg-white' : 'bg-slate-50/50' ?>">
      <td class="px-4 py-3">
        <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-slate-100 text-slate-600 text-xs font-bold"><?= (int)$r['id'] ?></span>
      </td>
      <td class="px-4 py-3">
        <div class="font-semibold text-slate-800"><?= e($r['nama_kth']) ?></div>
        <?php if ($r['nama_kph'] ?? ''): ?>
          <div class="text-xs text-slate-400 mt-0.5"><?= e($r['nama_kph']) ?></div>
        <?php endif; ?>
      </td>
      <td class="px-4 py-3 text-slate-600 text-xs font-mono"><?= e($r['nomor_sk'] ?? '-') ?></td>
      <td class="px-4 py-3 text-center">
        <span class="inline-flex items-center px-2.5 py-0.5 rounded-md bg-slate-100 text-slate-700 text-xs font-semibold"><?= e($r['tahun_usulan'] ?? '-') ?></span>
      </td>
      <td class="px-4 py-3 text-center font-bold text-slate-700"><?= (int)$r['jml_usulan'] ?></td>
      <td class="px-4 py-3 text-center font-bold text-slate-700"><?= (int)$r['jml_sk'] ?></td>
      <td class="px-4 py-3">
        <?php
          $rek = $r['rekomendasi'] ?? '';
          if ($rek === 'Dapat Ditindaklanjuti'):
        ?>
          <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 ring-1 ring-emerald-200/50">
            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
            Dapat Ditindaklanjuti
          </span>
        <?php elseif ($rek === 'Perlu Revisi'): ?>
          <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-800 ring-1 ring-amber-200/50">
            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
            Perlu Revisi
          </span>
        <?php else: ?>
          <span class="text-xs text-slate-400">—</span>
        <?php endif; ?>
      </td>
      <td class="px-4 py-3">
        <div class="flex items-center justify-center gap-1">
          <a href="konfirmasi_sk.php?kth_id=<?= (int)$r['id'] ?>" title="Konfirmasi SK"
            class="w-8 h-8 rounded-lg bg-sky-50 text-sky-600 hover:bg-sky-100 flex items-center justify-center smooth-all">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
          </a>
          <a href="hasil.php?kth_id=<?= (int)$r['id'] ?>" title="Hasil Verifikasi"
            class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 hover:bg-emerald-100 flex items-center justify-center smooth-all">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
          </a>
          <a href="cetak_peta.php?kth_id=<?= (int)$r['id'] ?>" target="_blank" title="Cetak Peta"
            class="w-8 h-8 rounded-lg bg-teal-50 text-teal-600 hover:bg-teal-100 flex items-center justify-center smooth-all">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
          </a>
          <a href="laporan.php?kth_id=<?= (int)$r['id'] ?>" title="Laporan"
            class="w-8 h-8 rounded-lg bg-violet-50 text-violet-600 hover:bg-violet-100 flex items-center justify-center smooth-all">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
          </a>
          <a href="hapus.php?kth_id=<?= (int)$r['id'] ?>" onclick="return confirm('Hapus kasus ini beserta seluruh datanya?')" title="Hapus"
            class="w-8 h-8 rounded-lg bg-red-50 text-red-500 hover:bg-red-100 flex items-center justify-center smooth-all">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
          </a>
        </div>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>
</div>
<?php layout_foot(); ?>
