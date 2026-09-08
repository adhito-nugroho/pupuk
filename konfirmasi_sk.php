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

<!-- ═══ Header Dokumen Verifikasi ═══ -->
<div class="doc-card p-5 mb-5">
  <div class="flex flex-wrap items-start justify-between gap-4 pb-4 border-b border-kadaster-border">
    <div>
      <div class="text-[11px] font-mono text-ink-faint uppercase tracking-wider">
        Tahap 02 Konfirmasi SK
      </div>
      <h2 class="font-serif text-2xl font-bold text-ink mt-0.5">
        Konfirmasi Daftar Anggota Resmi SK
      </h2>
      <div class="text-xs text-ink-muted mt-1.5 flex flex-wrap items-center gap-2">
        <span>Kelompok: <b class="text-ink"><?= e($k['nama_kth']) ?></b></span>
        <span class="text-kadaster-border">|</span>
        <span>Nomor SK: <b class="font-mono text-ink"><?= e($k['nomor_sk'] ?? '-') ?></b></span>
        <?php if ($info): ?>
          <span class="text-kadaster-border">|</span>
          <span class="font-mono text-[11px] px-1.5 py-0.5 bg-[#FAF8F3] border border-kadaster-border text-ink-muted">
            Berkas: <?= e($info['file']) ?>
          </span>
        <?php endif; ?>
      </div>
    </div>

    <!-- Ringkasan Hitungan Tabular -->
    <div class="flex items-center gap-3">
      <div class="border border-kadaster-border px-3.5 py-2 bg-[#FAF8F3] text-right min-w-[100px]">
        <div class="text-[10px] text-ink-faint uppercase font-mono">Total Terbaca</div>
        <div class="font-mono text-xl font-bold text-ink tabular-nums" id="counter-total"><?= $totalBaris ?> <span class="text-xs font-normal text-ink-faint">orang</span></div>
      </div>
      <div class="border border-audit-warnBorder px-3.5 py-2 bg-audit-warnBg text-right min-w-[100px]">
        <div class="text-[10px] text-audit-warn uppercase font-mono font-semibold">Perlu Cek</div>
        <div class="font-mono text-xl font-bold text-audit-warn tabular-nums"><?= $perluDicekCount ?> <span class="text-xs font-normal text-audit-warn/80">baris</span></div>
      </div>
    </div>
  </div>

  <?php if ($perluDicekCount > 0): ?>
  <div class="mt-3.5 p-3 bg-audit-warnBg border-l-2 border-audit-warn text-xs text-audit-warn flex items-start gap-2.5">
    <span class="font-bold text-sm leading-none">!</span>
    <div class="leading-relaxed">
      Baris dengan latar <b>kuning muda</b> memiliki catatan (NIK tidak 16 digit atau duplikat). Anda dapat mengoreksi angka NIK atau nama secara langsung pada tabel sebelum disimpan.
    </div>
  </div>
  <?php endif; ?>
</div>

<form action="proses_konfirmasi.php" method="post" id="form-sk">
  <input type="hidden" name="kth_id" value="<?= $kthId ?>">

  <!-- ═══ Metadata SK (Collapsible) ═══ -->
  <div class="doc-card p-4 mb-5" x-data="{ open: false }">
    <button type="button" @click="open = !open" class="w-full flex items-center justify-between text-left">
      <div class="flex items-center gap-2">
        <span class="w-2 h-2 bg-kadaster-brown inline-block"></span>
        <h3 class="text-xs font-bold uppercase tracking-wider text-ink">
          Data Atribut Surat Keputusan (SK)
        </h3>
        <span class="text-[11px] text-ink-faint font-normal">— Klik untuk melihat/mengubah nomor SK &amp; luas</span>
      </div>
      <span class="font-mono text-xs text-ink-muted" x-text="open ? '[Tutup -]' : '[Buka +]'"></span>
    </button>
    <div x-show="open" x-transition class="grid md:grid-cols-4 gap-3 text-xs mt-3.5 pt-3.5 border-t border-kadaster-border">
      <div>
        <label class="block text-[11px] font-semibold text-ink-muted mb-1">Nomor SK</label>
        <input name="meta_nomor_sk" value="<?= e($k['nomor_sk'] ?? '') ?>" 
          class="w-full border border-kadaster-border px-2.5 py-1.5 font-mono text-xs bg-white text-ink focus:border-forest-900 focus:outline-none">
      </div>
      <div>
        <label class="block text-[11px] font-semibold text-ink-muted mb-1">Nama KPH</label>
        <input name="meta_nama_kph" value="<?= e($k['nama_kph'] ?? '') ?>" 
          class="w-full border border-kadaster-border px-2.5 py-1.5 text-xs bg-white text-ink focus:border-forest-900 focus:outline-none">
      </div>
      <div>
        <label class="block text-[11px] font-semibold text-ink-muted mb-1">Luas Areal (Ha)</label>
        <input name="meta_luas_areal" type="number" step="0.01" value="<?= e($k['luas_areal'] ?? '') ?>" 
          class="w-full border border-kadaster-border px-2.5 py-1.5 font-mono text-xs bg-white text-ink focus:border-forest-900 focus:outline-none">
      </div>
      <div>
        <label class="block text-[11px] font-semibold text-ink-muted mb-1">Tanggal Penetapan SK</label>
        <input name="meta_tanggal_sk" type="date" value="<?= e($k['tanggal_sk'] ?? '') ?>" 
          class="w-full border border-kadaster-border px-2.5 py-1.5 font-mono text-xs bg-white text-ink focus:border-forest-900 focus:outline-none">
      </div>
    </div>
  </div>

  <!-- ═══ Tabel Anggota SK (Dense Audit Table) ═══ -->
  <div class="doc-card overflow-hidden">
    <div class="px-4 py-3 bg-[#FAF8F3] border-b border-kadaster-border flex flex-wrap justify-between items-center gap-2">
      <div class="flex items-center gap-2">
        <span class="w-2 h-2 bg-forest-900 inline-block"></span>
        <span class="text-xs font-bold uppercase tracking-wider text-ink">
          Tabel Data Anggota SK (Dapat Diedit Langsung)
        </span>
      </div>
      <button type="button" id="btn-tambah" 
        class="btn-kadaster px-3 py-1 text-xs inline-flex items-center gap-1.5">
        + Tambah Baris Anggota
      </button>
    </div>

    <div class="overflow-x-auto max-h-[580px] overflow-y-auto">
      <table class="min-w-full text-xs text-left" id="tbl-sk">
        <thead class="bg-[#F6F2E9] border-b border-kadaster-border sticky top-0 z-10 text-[11px] font-semibold text-ink-muted">
          <tr>
            <th class="py-2.5 px-3 text-center w-12">No</th>
            <th class="py-2.5 px-3 min-w-[200px]">Nama Lengkap Petani *</th>
            <th class="py-2.5 px-3 min-w-[170px]">NIK (16 Digit) *</th>
            <th class="py-2.5 px-3 text-center w-16">L / P</th>
            <th class="py-2.5 px-3 min-w-[130px]">Desa</th>
            <th class="py-2.5 px-3 min-w-[130px]">Kecamatan</th>
            <th class="py-2.5 px-3 min-w-[180px]">Catatan Verifikasi</th>
            <th class="py-2.5 px-2 text-center w-10"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-kadaster-border/60">
        <?php $i = 0; foreach ($rows as $r): $i++;
          $perlu = !empty($r['perlu_dicek']) || (isset($r['nik']) && strlen(norm_nik($r['nik'])) !== 16);
          $rowClass = $perlu ? 'bg-audit-warnBg' : 'bg-white';
        ?>
          <tr class="sk-row hairline-row <?= $rowClass ?>">
            <td class="py-1.5 px-3 text-center font-mono text-[11px] text-ink-faint">
              <?= $i ?>
            </td>
            <td class="py-1.5 px-2">
              <input name="nama[]" value="<?= e($r['nama'] ?? '') ?>" 
                class="w-full border border-kadaster-border px-2 py-1 bg-white text-ink text-xs uppercase font-medium focus:border-forest-900 focus:outline-none" required>
            </td>
            <td class="py-1.5 px-2">
              <input name="nik[]" value="<?= e($r['nik'] ?? '') ?>" maxlength="16" 
                class="w-full border border-kadaster-border px-2 py-1 font-mono text-xs bg-white text-ink nik-input focus:border-forest-900 focus:outline-none" required>
            </td>
            <td class="py-1.5 px-2 text-center">
              <select name="jk[]" class="w-full border border-kadaster-border px-1 py-1 text-center bg-white text-xs text-ink focus:border-forest-900 focus:outline-none">
                <option value=""></option>
                <option value="L" <?= (($r['jk'] ?? $r['jenis_kelamin'] ?? '') === 'L') ? 'selected' : '' ?>>L</option>
                <option value="P" <?= (($r['jk'] ?? $r['jenis_kelamin'] ?? '') === 'P') ? 'selected' : '' ?>>P</option>
              </select>
            </td>
            <td class="py-1.5 px-2">
              <input name="desa[]" value="<?= e($r['desa'] ?? '') ?>" 
                class="w-full border border-kadaster-border px-2 py-1 bg-white text-xs uppercase text-ink focus:border-forest-900 focus:outline-none">
            </td>
            <td class="py-1.5 px-2">
              <input name="kecamatan[]" value="<?= e($r['kecamatan'] ?? '') ?>" 
                class="w-full border border-kadaster-border px-2 py-1 bg-white text-xs uppercase text-ink focus:border-forest-900 focus:outline-none">
            </td>
            <td class="py-1.5 px-2">
              <?php if (!empty($r['catatan'])): ?>
                <span class="text-audit-warn font-mono text-[11px]">
                  <?= e($r['catatan']) ?>
                </span>
              <?php else: ?>
                <span class="text-ink-faint text-[11px]">—</span>
              <?php endif; ?>
            </td>
            <td class="py-1.5 px-2 text-center">
              <button type="button" onclick="this.closest('tr').remove(); updateCounter()" 
                class="text-ink-faint hover:text-audit-revisi px-1 py-0.5 text-xs font-bold" title="Hapus baris">
                &times;
              </button>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Tombol Aksi Navigasi -->
  <div class="flex flex-wrap items-center justify-between gap-3 mt-5">
    <a href="index.php" class="btn-kadaster px-4 py-2 text-xs inline-flex items-center gap-1.5">
      ← Kembali ke Buku Register
    </a>
    <button type="submit" class="btn-forest px-5 py-2.5 text-xs inline-flex items-center gap-2">
      <span>Simpan &amp; Lanjut ke Tahap 03 — Uji Spasial &amp; Titik</span>
      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
      </svg>
    </button>
  </div>
</form>

<script>
function updateCounter() {
  const n = document.querySelectorAll('#tbl-sk tbody .sk-row').length;
  const el = document.getElementById('counter-total');
  if (el) el.innerHTML = n + ' <span class="text-xs font-normal text-ink-faint">orang</span>';
}

document.getElementById('btn-tambah').addEventListener('click', () => {
  const tb = document.querySelector('#tbl-sk tbody');
  const n = tb.querySelectorAll('.sk-row').length + 1;
  const tr = document.createElement('tr');
  tr.className = 'sk-row hairline-row bg-white';
  tr.innerHTML = `<td class="py-1.5 px-3 text-center font-mono text-[11px] text-ink-faint">${n}</td>
    <td class="py-1.5 px-2"><input name="nama[]" class="w-full border border-kadaster-border px-2 py-1 bg-white text-ink text-xs uppercase font-medium focus:border-forest-900 focus:outline-none" required></td>
    <td class="py-1.5 px-2"><input name="nik[]" maxlength="16" class="w-full border border-kadaster-border px-2 py-1 font-mono text-xs bg-white text-ink nik-input focus:border-forest-900 focus:outline-none" required></td>
    <td class="py-1.5 px-2 text-center">
      <select name="jk[]" class="w-full border border-kadaster-border px-1 py-1 text-center bg-white text-xs text-ink focus:border-forest-900 focus:outline-none">
        <option value=""></option><option value="L">L</option><option value="P">P</option>
      </select>
    </td>
    <td class="py-1.5 px-2"><input name="desa[]" class="w-full border border-kadaster-border px-2 py-1 bg-white text-xs uppercase text-ink focus:border-forest-900 focus:outline-none"></td>
    <td class="py-1.5 px-2"><input name="kecamatan[]" class="w-full border border-kadaster-border px-2 py-1 bg-white text-xs uppercase text-ink focus:border-forest-900 focus:outline-none"></td>
    <td class="py-1.5 px-2 text-ink-faint text-[11px]">—</td>
    <td class="py-1.5 px-2 text-center">
      <button type="button" onclick="this.closest('tr').remove(); updateCounter()" class="text-ink-faint hover:text-audit-revisi px-1 py-0.5 text-xs font-bold">&times;</button>
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
      inp.classList.add('border-audit-revisi', 'bg-audit-revisiBg');
      invalid++;
    } else {
      inp.classList.remove('border-audit-revisi', 'bg-audit-revisiBg');
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
