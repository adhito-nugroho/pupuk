<?php
// Langkah 2 — Konfirmasi daftar anggota SK (hasil parsing Excel/CSV, editable, highlight baris perlu dicek).
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/layout.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$kthId = (int)($_GET['kth_id'] ?? 0);
$pdo = db();
$kth = $pdo->prepare('SELECT * FROM kth WHERE id = ?');
$kth->execute([$kthId]);
$k = $kth->fetch();
if (!$k) { flash_set('error', 'Kasus tidak ditemukan.'); header('Location: index.php'); exit; }

$st = $pdo->prepare('SELECT * FROM sk_anggota WHERE kth_id = ? ORDER BY id');
$st->execute([$kthId]);
$tersimpan = $st->fetchAll();

$staging = $_SESSION['sk_parse'][$kthId] ?? null;
$rows = $tersimpan ?: ($staging['rows'] ?? []);
$info = $staging['info'] ?? null;

$totalBaris = count($rows);
$perluDicekCount = 0;
foreach ($rows as $r) {
    if (!empty($r['perlu_dicek']) || !empty($r['catatan'])) {
        $perluDicekCount++;
    }
}

layout_head('Konfirmasi Anggota SK — ' . $k['nama_kth']);
wizard(2);
?>

<!-- ═══ Header Card ═══ -->
<div class="glass-card rounded-2xl p-6 mb-5 fade-in">
  <div class="flex flex-wrap items-start justify-between gap-4 border-b border-slate-200/60 pb-5">
    <div>
      <h2 class="text-xl font-bold text-slate-800 flex items-center gap-2">
        <span class="w-8 h-8 rounded-lg bg-gradient-to-br from-sky-500 to-sky-700 text-white flex items-center justify-center text-sm">2</span>
        Konfirmasi Daftar Anggota Resmi SK
      </h2>
      <p class="text-sm text-slate-500 mt-1.5 ml-10">
        Kelompok: <b class="text-slate-700"><?= e($k['nama_kth']) ?></b> · Nomor SK: <b class="text-slate-700"><?= e($k['nomor_sk'] ?? '-') ?></b>
        <?php if ($info): ?>
          · File: <span class="font-mono text-xs bg-slate-100 px-2 py-0.5 rounded-md"><?= e($info['file']) ?></span>
        <?php endif; ?>
      </p>
    </div>
    <div class="flex gap-3">
      <div class="bg-gradient-to-br from-emerald-50 to-emerald-100 border border-emerald-200/80 rounded-xl px-4 py-2.5 text-center min-w-[90px]">
        <div class="text-xs text-emerald-600 font-bold uppercase tracking-wider">Total</div>
        <div class="text-2xl font-extrabold text-emerald-800" id="counter-total"><?= $totalBaris ?></div>
      </div>
      <div class="bg-gradient-to-br from-amber-50 to-amber-100 border border-amber-200/80 rounded-xl px-4 py-2.5 text-center min-w-[90px]">
        <div class="text-xs text-amber-600 font-bold uppercase tracking-wider">Dicek</div>
        <div class="text-2xl font-extrabold text-amber-800"><?= $perluDicekCount ?></div>
      </div>
    </div>
  </div>

  <div class="mt-4 p-4 bg-gradient-to-r from-amber-50 to-orange-50 border border-amber-200/80 rounded-xl text-sm flex items-start gap-3">
    <div class="w-8 h-8 rounded-lg bg-amber-200/80 text-amber-700 flex items-center justify-center text-lg flex-shrink-0">⚠️</div>
    <div class="text-xs text-amber-900 leading-relaxed space-y-1">
      <p><b>Petunjuk:</b> Baris yang disorot <span class="bg-amber-200/60 px-1.5 py-0.5 rounded font-semibold">warna kuning</span> memiliki catatan (NIK bukan 16 digit / NIK duplikat). Perbaiki langsung pada kolom input.</p>
      <p>Setelah selesai, klik <b>"Simpan &amp; Lanjut Verifikasi"</b> di bawah untuk menyimpan ke database.</p>
    </div>
  </div>
</div>

<form action="proses_konfirmasi.php" method="post" id="form-sk">
  <input type="hidden" name="kth_id" value="<?= $kthId ?>">

  <!-- ═══ Metadata SK (collapsible) ═══ -->
  <div class="glass-card rounded-2xl p-5 mb-5 fade-in fade-in-delay-1" x-data="{ open: true }">
    <button type="button" @click="open = !open" class="w-full flex items-center justify-between text-left">
      <h3 class="text-sm font-bold uppercase tracking-wider text-slate-600 flex items-center gap-2">
        <span class="w-5 h-5 rounded-full bg-slate-500 text-white flex items-center justify-center text-[9px] font-bold">i</span>
        Metadata Surat Keputusan (SK)
      </h3>
      <svg class="w-5 h-5 text-slate-400 smooth-all" :class="open && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
    </button>
    <div x-show="open" x-transition class="grid md:grid-cols-4 gap-3 text-xs mt-4">
      <div>
        <label class="block font-semibold text-slate-500 mb-1.5">Nomor SK</label>
        <input name="meta_nomor_sk" value="<?= e($k['nomor_sk'] ?? '') ?>" class="w-full border border-slate-200 rounded-xl px-3 py-2 font-medium text-slate-800 bg-slate-50 focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500 outline-none smooth-all">
      </div>
      <div>
        <label class="block font-semibold text-slate-500 mb-1.5">Nama KPH</label>
        <input name="meta_nama_kph" value="<?= e($k['nama_kph'] ?? '') ?>" class="w-full border border-slate-200 rounded-xl px-3 py-2 font-medium text-slate-800 bg-slate-50 focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500 outline-none smooth-all">
      </div>
      <div>
        <label class="block font-semibold text-slate-500 mb-1.5">Luas Areal (ha)</label>
        <input name="meta_luas_areal" type="number" step="0.01" value="<?= e($k['luas_areal'] ?? '') ?>" class="w-full border border-slate-200 rounded-xl px-3 py-2 font-medium text-slate-800 bg-slate-50 focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500 outline-none smooth-all">
      </div>
      <div>
        <label class="block font-semibold text-slate-500 mb-1.5">Tanggal SK</label>
        <input name="meta_tanggal_sk" type="date" value="<?= e($k['tanggal_sk'] ?? '') ?>" class="w-full border border-slate-200 rounded-xl px-3 py-2 font-medium text-slate-800 bg-slate-50 focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500 outline-none smooth-all">
      </div>
    </div>
  </div>

  <!-- ═══ Tabel Anggota SK ═══ -->
  <div class="glass-card rounded-2xl overflow-hidden fade-in fade-in-delay-2">
    <div class="p-4 border-b border-slate-200/60 flex justify-between items-center bg-gradient-to-r from-slate-50 to-white">
      <span class="text-xs font-bold text-slate-500 uppercase tracking-wider flex items-center gap-2">
        <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
        Tabel Data Anggota SK (Dapat Diedit Langsung)
      </span>
      <button type="button" id="btn-tambah" class="btn-secondary px-3.5 py-1.5 rounded-lg text-xs inline-flex items-center gap-1.5">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Tambah Baris
      </button>
    </div>

    <div class="overflow-x-auto">
      <table class="min-w-full text-xs" id="tbl-sk">
        <thead class="bg-gradient-to-r from-slate-100 to-slate-50 sticky top-0 z-10">
          <tr>
            <th class="px-3 py-3 text-center w-12 text-slate-500 font-semibold uppercase text-[10px] tracking-wider">No</th>
            <th class="px-3 py-3 text-left min-w-[180px] text-slate-500 font-semibold uppercase text-[10px] tracking-wider">Nama Petani *</th>
            <th class="px-3 py-3 text-left min-w-[170px] text-slate-500 font-semibold uppercase text-[10px] tracking-wider">NIK (16 Digit) *</th>
            <th class="px-3 py-3 text-center w-16 text-slate-500 font-semibold uppercase text-[10px] tracking-wider">L/P</th>
            <th class="px-3 py-3 text-left min-w-[130px] text-slate-500 font-semibold uppercase text-[10px] tracking-wider">Desa</th>
            <th class="px-3 py-3 text-left min-w-[130px] text-slate-500 font-semibold uppercase text-[10px] tracking-wider">Kecamatan</th>
            <th class="px-3 py-3 text-left min-w-[200px] text-slate-500 font-semibold uppercase text-[10px] tracking-wider">Catatan</th>
            <th class="px-2 py-3 text-center w-10"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
        <?php $i = 0; foreach ($rows as $r): $i++;
          $perlu = !empty($r['perlu_dicek']) || (isset($r['nik']) && strlen(norm_nik($r['nik'])) !== 16);
          $rowClass = $perlu ? 'bg-amber-50/60' : ($i % 2 === 0 ? 'bg-slate-50/30' : 'bg-white');
        ?>
          <tr class="sk-row tbl-row <?= $rowClass ?>">
            <td class="px-3 py-2 text-center">
              <span class="inline-flex items-center justify-center w-6 h-6 rounded-md bg-slate-100 text-slate-500 text-[10px] font-bold"><?= $i ?></span>
            </td>
            <td class="px-2 py-1.5">
              <input name="nama[]" value="<?= e($r['nama'] ?? '') ?>" class="w-full border border-slate-200 rounded-lg px-2.5 py-1.5 bg-white focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500/20 outline-none uppercase font-medium smooth-all" required>
            </td>
            <td class="px-2 py-1.5">
              <input name="nik[]" value="<?= e($r['nik'] ?? '') ?>" maxlength="16" class="w-full border border-slate-200 rounded-lg px-2.5 py-1.5 font-mono text-slate-800 bg-white nik-input focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500/20 outline-none smooth-all" required>
            </td>
            <td class="px-2 py-1.5">
              <select name="jk[]" class="w-full border border-slate-200 rounded-lg px-1.5 py-1.5 text-center bg-white smooth-all focus:border-emerald-500 outline-none">
                <option value=""></option>
                <option value="L" <?= (($r['jk'] ?? $r['jenis_kelamin'] ?? '') === 'L') ? 'selected' : '' ?>>L</option>
                <option value="P" <?= (($r['jk'] ?? $r['jenis_kelamin'] ?? '') === 'P') ? 'selected' : '' ?>>P</option>
              </select>
            </td>
            <td class="px-2 py-1.5">
              <input name="desa[]" value="<?= e($r['desa'] ?? '') ?>" class="w-full border border-slate-200 rounded-lg px-2.5 py-1.5 bg-white uppercase smooth-all focus:border-emerald-500 outline-none">
            </td>
            <td class="px-2 py-1.5">
              <input name="kecamatan[]" value="<?= e($r['kecamatan'] ?? '') ?>" class="w-full border border-slate-200 rounded-lg px-2.5 py-1.5 bg-white uppercase smooth-all focus:border-emerald-500 outline-none">
            </td>
            <td class="px-2 py-1.5">
              <?php if (!empty($r['catatan'])): ?>
                <span class="text-amber-800 bg-amber-100/80 px-2 py-0.5 rounded-md inline-block text-[11px] font-medium">
                  <?= e($r['catatan']) ?>
                </span>
              <?php else: ?>
                <span class="text-slate-300 text-[11px]">—</span>
              <?php endif; ?>
            </td>
            <td class="px-2 py-1.5 text-center">
              <button type="button" onclick="this.closest('tr').remove(); updateCounter()" class="w-6 h-6 rounded-md bg-red-50 text-red-400 hover:bg-red-100 hover:text-red-600 flex items-center justify-center smooth-all" title="Hapus baris">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
              </button>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
          <tr class="sk-row tbl-row">
            <td class="px-3 py-2 text-center"><span class="inline-flex items-center justify-center w-6 h-6 rounded-md bg-slate-100 text-slate-500 text-[10px] font-bold">1</span></td>
            <td class="px-2 py-1.5"><input name="nama[]" class="w-full border border-slate-200 rounded-lg px-2.5 py-1.5 uppercase font-medium smooth-all focus:border-emerald-500 outline-none" required></td>
            <td class="px-2 py-1.5"><input name="nik[]" maxlength="16" class="w-full border border-slate-200 rounded-lg px-2.5 py-1.5 font-mono nik-input smooth-all focus:border-emerald-500 outline-none" required></td>
            <td class="px-2 py-1.5">
              <select name="jk[]" class="w-full border border-slate-200 rounded-lg px-1.5 py-1.5 text-center bg-white smooth-all focus:border-emerald-500 outline-none">
                <option value=""></option><option value="L">L</option><option value="P">P</option>
              </select>
            </td>
            <td class="px-2 py-1.5"><input name="desa[]" class="w-full border border-slate-200 rounded-lg px-2.5 py-1.5 uppercase smooth-all focus:border-emerald-500 outline-none"></td>
            <td class="px-2 py-1.5"><input name="kecamatan[]" class="w-full border border-slate-200 rounded-lg px-2.5 py-1.5 uppercase smooth-all focus:border-emerald-500 outline-none"></td>
            <td class="px-2 py-1.5 text-slate-300 text-[11px]">—</td>
            <td class="px-2 py-1.5 text-center">
              <button type="button" onclick="this.closest('tr').remove(); updateCounter()" class="w-6 h-6 rounded-md bg-red-50 text-red-400 hover:bg-red-100 hover:text-red-600 flex items-center justify-center smooth-all">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
              </button>
            </td>
          </tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="flex flex-wrap items-center justify-between gap-3 mt-5 fade-in fade-in-delay-3">
    <a href="index.php" class="btn-secondary px-5 py-2.5 rounded-xl text-sm inline-flex items-center gap-2">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
      Kembali ke Daftar
    </a>
    <button type="submit" class="btn-primary px-6 py-2.5 rounded-xl text-sm inline-flex items-center gap-2 shadow-lg">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
      Simpan &amp; Lanjut Verifikasi
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
    </button>
  </div>
</form>

<script>
function updateCounter() {
  const n = document.querySelectorAll('#tbl-sk tbody .sk-row').length;
  const el = document.getElementById('counter-total');
  if (el) el.textContent = n;
}

document.getElementById('btn-tambah').addEventListener('click', () => {
  const tb = document.querySelector('#tbl-sk tbody');
  const n = tb.querySelectorAll('.sk-row').length + 1;
  const tr = document.createElement('tr');
  tr.className = 'sk-row tbl-row bg-white';
  tr.innerHTML = `<td class="px-3 py-2 text-center"><span class="inline-flex items-center justify-center w-6 h-6 rounded-md bg-slate-100 text-slate-500 text-[10px] font-bold">${n}</span></td>
    <td class="px-2 py-1.5"><input name="nama[]" class="w-full border border-slate-200 rounded-lg px-2.5 py-1.5 uppercase font-medium smooth-all focus:border-emerald-500 outline-none" required></td>
    <td class="px-2 py-1.5"><input name="nik[]" maxlength="16" class="w-full border border-slate-200 rounded-lg px-2.5 py-1.5 font-mono nik-input smooth-all focus:border-emerald-500 outline-none" required></td>
    <td class="px-2 py-1.5">
      <select name="jk[]" class="w-full border border-slate-200 rounded-lg px-1.5 py-1.5 text-center bg-white smooth-all focus:border-emerald-500 outline-none">
        <option value=""></option><option value="L">L</option><option value="P">P</option>
      </select>
    </td>
    <td class="px-2 py-1.5"><input name="desa[]" class="w-full border border-slate-200 rounded-lg px-2.5 py-1.5 uppercase smooth-all focus:border-emerald-500 outline-none"></td>
    <td class="px-2 py-1.5"><input name="kecamatan[]" class="w-full border border-slate-200 rounded-lg px-2.5 py-1.5 uppercase smooth-all focus:border-emerald-500 outline-none"></td>
    <td class="px-2 py-1.5 text-slate-300 text-[11px]">—</td>
    <td class="px-2 py-1.5 text-center">
      <button type="button" onclick="this.closest('tr').remove(); updateCounter()" class="w-6 h-6 rounded-md bg-red-50 text-red-400 hover:bg-red-100 hover:text-red-600 flex items-center justify-center smooth-all">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </td>`;
  tb.appendChild(tr);
  updateCounter();
  tr.querySelector('input[name="nama[]"]').focus();
});

document.getElementById('form-sk').addEventListener('submit', (ev) => {
  let invalid = 0;
  document.querySelectorAll('.nik-input').forEach(inp => {
    const v = inp.value.replace(/\D/g, '').trim();
    if (v === '') return;
    if (v.length !== 16) {
      inp.classList.add('border-red-400', 'bg-red-50', 'ring-1', 'ring-red-300');
      invalid++;
    } else {
      inp.classList.remove('border-red-400', 'bg-red-50', 'ring-1', 'ring-red-300');
    }
  });
  if (invalid > 0) {
    if (!confirm(`Terdapat ${invalid} baris dengan NIK yang bukan 16 digit. Tetap ingin menyimpan? (Baris NIK tidak valid akan ditolak server).`)) {
      ev.preventDefault();
    }
  }
});
</script>

<?php layout_foot(); ?>
