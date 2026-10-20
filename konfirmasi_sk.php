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
    if (!empty($r['perlu_dicek']) || !empty($r['catatan']) || strlen(norm_nik((string)($r['nik'] ?? ''))) !== 16) {
        $perluDicekCount++;
    }
}

$daftarVersi = ambil_daftar_versi($pdo, $kthId);
$versiAktif = ambil_versi_terpilih($k, isset($_GET['v']) ? (int)$_GET['v'] : null);

layout_head('Konfirmasi Anggota SK — ' . $k['nama_kth']);
layout_kth_subnav($k, 'sk', $versiAktif, $daftarVersi);
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
      <button type="button" id="toggle-perlu-cek" onclick="togglePerluCek()" aria-pressed="false"
        title="Klik untuk hanya menampilkan baris yang perlu dicek"
        class="border border-audit-warnBorder px-3.5 py-2 bg-audit-warnBg text-right min-w-[112px] cursor-pointer transition-all duration-150 hover:bg-amber-100 focus:outline-none focus:ring-2 focus:ring-audit-warn/40">
        <div class="text-[10px] text-audit-warn uppercase font-mono font-semibold flex items-center justify-end gap-1">
          <span>Perlu Cek</span>
          <span class="text-[9px] font-sans normal-case tracking-normal opacity-70">filter</span>
        </div>
        <div class="font-mono text-xl font-bold text-audit-warn tabular-nums"><?= $perluDicekCount ?> <span class="text-xs font-normal text-audit-warn/80">baris</span></div>
      </button>
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
  <input type="hidden" name="data_anggota_json" id="data_anggota_json">

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
    <div class="px-4 py-3 bg-[#FAF8F3] border-b border-kadaster-border">
      <div class="flex flex-wrap justify-between items-center gap-2">
        <div class="flex items-center gap-2">
          <span class="w-2 h-2 bg-forest-900 inline-block"></span>
          <span class="text-xs font-bold uppercase tracking-wider text-ink">
            Tabel Data Anggota SK (Dapat Diedit Langsung)
          </span>
        </div>
        <div class="flex items-center gap-2">
          <button type="button" onclick="document.getElementById('modal-upload-sk').classList.remove('hidden')"
            class="btn-kadaster px-3 py-1 text-xs inline-flex items-center gap-1.5 font-medium text-forest-800 hover:text-forest-900 bg-forest-50 hover:bg-forest-100 border border-forest-300 shadow-sm"
            title="Unggah ulang berkas Excel SK untuk membaca kembali seluruh anggota jika data sebelumnya terpotong">
            <svg class="w-3.5 h-3.5 text-forest-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
            Ganti / Unggah Ulang File SK
          </button>
          <button type="button" id="btn-tambah"
            class="btn-kadaster px-3 py-1 text-xs inline-flex items-center gap-1.5">
            + Tambah Baris Manual
          </button>
        </div>
      </div>

      <div class="mt-3 flex flex-wrap items-center gap-2">
        <div class="relative flex-1 min-w-[220px] max-w-md">
          <svg class="w-3.5 h-3.5 text-ink-faint absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
          </svg>
          <input id="cari-anggota" type="search" data-no-dirty oninput="filterAnggota()"
            onkeydown="if (event.key === 'Enter') { event.preventDefault(); filterAnggota(); }"
            placeholder="Cari Nama Petani, NIK, atau Desa..."
            class="w-full border border-kadaster-border bg-white pl-9 pr-3 py-2 text-xs text-ink placeholder:text-ink-faint focus:border-forest-900 focus:outline-none">
        </div>
        <span id="counter-visible" class="font-mono text-[11px] text-ink-faint whitespace-nowrap"><?= $totalBaris ?> tampil</span>
        <button type="button" id="reset-filter-anggota" onclick="resetFilterAnggota()"
          class="hidden px-2.5 py-2 text-[11px] font-semibold text-forest-900 border border-forest-300 bg-forest-50 hover:bg-forest-100 transition-colors">
          Reset Filter
        </button>
      </div>
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
          $perlu = !empty($r['perlu_dicek']) || !empty($r['catatan']) || strlen(norm_nik((string)($r['nik'] ?? ''))) !== 16;
          $rowClass = $perlu ? 'bg-audit-warnBg' : 'bg-white';
          $cariAnggota = mb_strtolower(implode(' ', array_filter([
            (string)($r['nama'] ?? ''),
            (string)($r['nik'] ?? ''),
            (string)($r['desa'] ?? ''),
            (string)($r['kecamatan'] ?? ''),
          ])), 'UTF-8');
        ?>
          <tr class="sk-row hairline-row <?= $rowClass ?>"
            data-perlu="<?= $perlu ? '1' : '0' ?>" data-cari="<?= e($cariAnggota) ?>">
            <td class="py-2 px-3 text-center font-mono text-[11px] text-ink-faint">
              <?= $i ?>
            </td>
            <td class="py-2 px-2">
              <input name="nama[]" value="<?= e($r['nama'] ?? '') ?>" title="<?= e($r['nama'] ?? '') ?>"
                class="w-full min-h-[34px] border border-kadaster-border px-2.5 py-1.5 bg-white text-ink text-xs uppercase font-medium leading-snug focus:border-forest-900 focus:outline-none" required>
            </td>
            <td class="py-2 px-2">
              <input name="nik[]" value="<?= e($r['nik'] ?? '') ?>" maxlength="16" inputmode="numeric" title="<?= e($r['nik'] ?? '') ?>"
                class="w-full min-h-[34px] border border-kadaster-border px-2.5 py-1.5 font-mono text-xs bg-white text-ink nik-input focus:border-forest-900 focus:outline-none" required>
            </td>
            <td class="py-2 px-2 text-center">
              <select name="jk[]" class="w-full min-h-[34px] border border-kadaster-border px-1 py-1.5 text-center bg-white text-xs text-ink focus:border-forest-900 focus:outline-none">
                <option value=""></option>
                <option value="L" <?= (($r['jk'] ?? $r['jenis_kelamin'] ?? '') === 'L') ? 'selected' : '' ?>>L</option>
                <option value="P" <?= (($r['jk'] ?? $r['jenis_kelamin'] ?? '') === 'P') ? 'selected' : '' ?>>P</option>
              </select>
            </td>
            <td class="py-2 px-2">
              <input name="desa[]" value="<?= e($r['desa'] ?? '') ?>" title="<?= e($r['desa'] ?? '') ?>"
                class="w-full min-h-[34px] border border-kadaster-border px-2.5 py-1.5 bg-white text-xs uppercase text-ink focus:border-forest-900 focus:outline-none">
            </td>
            <td class="py-2 px-2">
              <input name="kecamatan[]" value="<?= e($r['kecamatan'] ?? '') ?>" title="<?= e($r['kecamatan'] ?? '') ?>"
                class="w-full min-h-[34px] border border-kadaster-border px-2.5 py-1.5 bg-white text-xs uppercase text-ink focus:border-forest-900 focus:outline-none">
            </td>
            <td class="py-2 px-2">
              <?php if (!empty($r['catatan'])): ?>
                <span class="text-audit-warn font-mono text-[11px] leading-snug block py-1">
                  <?= e($r['catatan']) ?>
                </span>
              <?php else: ?>
                <span class="text-ink-faint text-[11px]">—</span>
              <?php endif; ?>
            </td>
            <td class="py-2 px-2 text-center">
              <button type="button" onclick="this.closest('tr').remove(); markSkDirty(); updateCounter(); filterAnggota()"
                class="text-ink-faint hover:text-audit-revisi px-1.5 py-1 text-xs font-bold" title="Hapus baris">
                &times;
              </button>
            </td>
          </tr>
        <?php endforeach; ?>
          <tr id="baris-kosong-filter" class="hidden">
            <td colspan="8" class="py-10 px-4 text-center">
              <div id="pesan-kosong-anggota" class="text-xs text-ink-muted">Tidak ada anggota yang cocok dengan pencarian atau filter.</div>
              <button type="button" id="reset-kosong-anggota" onclick="resetFilterAnggota()" class="mt-2 text-[11px] font-semibold text-forest-900 underline underline-offset-2">Reset filter</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Sticky Action Bar: selalu terlihat di bawah viewport saat menggulir tabel panjang -->
  <div class="fixed bottom-0 left-0 right-0 z-40 border-t border-kadaster-border bg-white/95 backdrop-blur-sm shadow-[0_-6px_18px_rgba(27,56,43,0.08)]">
    <div class="max-w-7xl mx-auto px-5 py-3 flex flex-wrap items-center justify-between gap-3">
    <div class="flex flex-wrap items-center gap-2.5">
      <a href="index.php" class="btn-kadaster px-3 py-1.5 text-xs inline-flex items-center gap-1.5">
        ← Buku Register
      </a>
      <span id="dirty-indicator" class="hidden items-center gap-1.5 px-2.5 py-1.5 text-[11px] font-semibold text-audit-warn bg-audit-warnBg border border-audit-warnBorder">
        <span class="w-1.5 h-1.5 rounded-full bg-audit-warn animate-pulse"></span>
        Ada perubahan yang belum disimpan
      </span>
      <span id="saved-indicator" class="inline-flex items-center gap-1.5 text-[11px] font-medium text-ink-faint">
        <span class="w-1.5 h-1.5 rounded-full bg-audit-valid"></span>
        Tersimpan
      </span>
    </div>
    <button type="submit" id="btn-submit-sk" class="btn-forest px-5 py-2.5 text-xs inline-flex items-center gap-2 font-semibold transition-all">
      <span>Simpan Perubahan &amp; Lanjut ke Tahap 03</span>
      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
      </svg>
    </button>
    </div>
  </div>
  <div class="h-28 sm:h-20" aria-hidden="true"></div>
</form>

<!-- Modal Upload Ulang Berkas SK -->
<div id="modal-upload-sk" class="hidden fixed inset-0 z-50 bg-black/50 backdrop-blur-sm flex items-center justify-center p-4">
  <div class="bg-white rounded border border-kadaster-border max-w-md w-full p-5 shadow-xl animate-fadeIn">
    <div class="flex items-center justify-between pb-3 border-b border-kadaster-border mb-4">
      <div class="flex items-center gap-2">
        <span class="w-2 h-2 bg-forest-900 inline-block"></span>
        <h3 class="font-serif font-bold text-base text-ink">Unggah Ulang Berkas SK Anggota</h3>
      </div>
      <button type="button" onclick="document.getElementById('modal-upload-sk').classList.add('hidden')" class="text-ink-muted hover:text-ink text-lg leading-none">&times;</button>
    </div>
    <p class="text-xs text-ink-muted mb-4 leading-relaxed">
      Gunakan fitur ini jika berkas SK Anda memiliki jumlah anggota yang banyak atau ingin mengganti daftar anggota SK tanpa harus mengulang input KTH dan SHP dari awal.
    </p>
    <form action="proses_upload_sk_ulang.php" method="post" enctype="multipart/form-data" class="space-y-4">
      <input type="hidden" name="kth_id" value="<?= $kthId ?>">
      <div>
        <label class="block text-xs font-semibold text-ink mb-1">Pilih Berkas Excel / CSV SK:</label>
        <input type="file" name="f_sk_ulang" accept=".xlsx,.xls,.csv" required
          class="w-full border border-kadaster-border px-3 py-2 text-xs bg-white text-ink focus:border-forest-900 outline-none rounded file:mr-2 file:py-1 file:px-2 file:rounded file:border-0 file:text-xs file:bg-forest-900 file:text-white cursor-pointer">
        <p class="text-[11px] text-ink-muted mt-1.5">Mendukung format <code>.xlsx</code>, <code>.xls</code>, atau <code>.csv</code> yang memuat kolom NIK dan Nama.</p>
      </div>
      <div class="flex items-center justify-end gap-2 pt-3 border-t border-kadaster-border">
        <button type="button" onclick="document.getElementById('modal-upload-sk').classList.add('hidden')" class="btn-kadaster px-3 py-1.5 text-xs">Batal</button>
        <button type="submit" class="btn-forest px-4 py-1.5 text-xs font-semibold">Proses &amp; Baca Ulang</button>
      </div>
    </form>
  </div>
</div>

<script>
let skDirty = false;
let perluFilterAktif = false;

function updateCounter() {
  const n = document.querySelectorAll('#tbl-sk tbody .sk-row').length;
  const el = document.getElementById('counter-total');
  if (el) el.innerHTML = n + ' <span class="text-xs font-normal text-ink-faint">orang</span>';
}

function setSkDirty(aktif) {
  skDirty = !!aktif;
  const dirty = document.getElementById('dirty-indicator');
  const saved = document.getElementById('saved-indicator');
  const btn = document.getElementById('btn-submit-sk');

  if (dirty) dirty.style.display = aktif ? 'inline-flex' : 'none';
  if (saved) saved.style.display = aktif ? 'none' : 'inline-flex';
  if (btn) {
    btn.classList.toggle('ring-2', aktif);
    btn.classList.toggle('ring-amber-400', aktif);
    btn.classList.toggle('ring-offset-2', aktif);
    btn.classList.toggle('shadow-lg', aktif);
  }
}

function markSkDirty() {
  setSkDirty(true);
}

function cariBaris(tr) {
  const nilai = sel => (tr.querySelector(sel)?.value || '').toLowerCase();
  return [
    nilai('input[name="nama[]"]'),
    nilai('input[name="nik[]"]'),
    nilai('input[name="desa[]"]'),
    nilai('input[name="kecamatan[]"]'),
    (tr.dataset.cari || '')
  ].join(' ');
}

function filterAnggota() {
  const q = (document.getElementById('cari-anggota')?.value || '').toLowerCase().trim();
  let visible = 0;
  let total = 0;

  document.querySelectorAll('#tbl-sk tbody tr.sk-row').forEach(tr => {
    total++;
    const cocokCari = !q || cariBaris(tr).includes(q);
    const cocokPerlu = !perluFilterAktif || tr.dataset.perlu === '1';
    const tampil = cocokCari && cocokPerlu;
    tr.style.display = tampil ? '' : 'none';
    if (tampil) visible++;
  });

  const counter = document.getElementById('counter-visible');
  if (counter) counter.textContent = visible + ' tampil';

  const empty = document.getElementById('baris-kosong-filter');
  if (empty) empty.classList.toggle('hidden', visible !== 0);

  const pesanKosong = document.getElementById('pesan-kosong-anggota');
  if (pesanKosong) {
    pesanKosong.textContent = total === 0
      ? 'Belum ada data anggota SK. Tambahkan baris manual atau unggah ulang berkas SK.'
      : 'Tidak ada anggota yang cocok dengan pencarian atau filter.';
  }
  const resetKosong = document.getElementById('reset-kosong-anggota');
  if (resetKosong) resetKosong.classList.toggle('hidden', total === 0);

  const reset = document.getElementById('reset-filter-anggota');
  if (reset) reset.classList.toggle('hidden', !q && !perluFilterAktif);
}

function setPerluFilter(aktif) {
  perluFilterAktif = !!aktif;
  const btn = document.getElementById('toggle-perlu-cek');
  if (btn) {
    btn.setAttribute('aria-pressed', perluFilterAktif ? 'true' : 'false');
    btn.style.backgroundColor = perluFilterAktif ? '#FFFFFF' : '';
    btn.style.boxShadow = perluFilterAktif ? 'inset 0 0 0 2px #B45309' : '';
  }
  filterAnggota();
}

function togglePerluCek() {
  setPerluFilter(!perluFilterAktif);
}

function resetFilterAnggota() {
  const search = document.getElementById('cari-anggota');
  if (search) search.value = '';
  setPerluFilter(false);
}

document.getElementById('btn-tambah').addEventListener('click', () => {
  const tb = document.querySelector('#tbl-sk tbody');
  const n = tb.querySelectorAll('.sk-row').length + 1;
  const tr = document.createElement('tr');
  tr.className = 'sk-row hairline-row bg-white';
  tr.dataset.perlu = '1';
  tr.dataset.cari = '';
  tr.innerHTML = `<td class="py-2 px-3 text-center font-mono text-[11px] text-ink-faint">${n}</td>
    <td class="py-2 px-2"><input name="nama[]" class="w-full min-h-[34px] border border-kadaster-border px-2.5 py-1.5 bg-white text-ink text-xs uppercase font-medium leading-snug focus:border-forest-900 focus:outline-none" required></td>
    <td class="py-2 px-2"><input name="nik[]" maxlength="16" inputmode="numeric" class="w-full min-h-[34px] border border-kadaster-border px-2.5 py-1.5 font-mono text-xs bg-white text-ink nik-input focus:border-forest-900 focus:outline-none" required></td>
    <td class="py-2 px-2 text-center">
      <select name="jk[]" class="w-full min-h-[34px] border border-kadaster-border px-1 py-1.5 text-center bg-white text-xs text-ink focus:border-forest-900 focus:outline-none">
        <option value=""></option><option value="L">L</option><option value="P">P</option>
      </select>
    </td>
    <td class="py-2 px-2"><input name="desa[]" class="w-full min-h-[34px] border border-kadaster-border px-2.5 py-1.5 bg-white text-xs uppercase text-ink focus:border-forest-900 focus:outline-none"></td>
    <td class="py-2 px-2"><input name="kecamatan[]" class="w-full min-h-[34px] border border-kadaster-border px-2.5 py-1.5 bg-white text-xs uppercase text-ink focus:border-forest-900 focus:outline-none"></td>
    <td class="py-2 px-2 text-ink-faint text-[11px]">—</td>
    <td class="py-2 px-2 text-center">
      <button type="button" onclick="this.closest('tr').remove(); markSkDirty(); updateCounter(); filterAnggota()" class="text-ink-faint hover:text-audit-revisi px-1.5 py-1 text-xs font-bold">&times;</button>
    </td>`;

  const search = document.getElementById('cari-anggota');
  if (search) search.value = '';
  tb.appendChild(tr);
  markSkDirty();
  updateCounter();
  filterAnggota();
  tr.querySelector('input[name="nama[]"]').focus();
});

const formSk = document.getElementById('form-sk');
function onSkFieldChange(e) {
  if (e.target.closest('[data-no-dirty]')) return;
  markSkDirty();
}
formSk.addEventListener('input', onSkFieldChange);
formSk.addEventListener('change', onSkFieldChange);

window.addEventListener('beforeunload', function(e) {
  if (!skDirty) return;
  e.preventDefault();
  e.returnValue = '';
});

formSk.addEventListener('submit', function(ev) {
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
      return;
    }
  }

  // Serialisasi SELURUH baris .sk-row — termasuk yang sedang disembunyikan filter.
  // Tetap aman dari limit max_input_vars server PHP (1000 vars).
  const rows = [];
  const trs = document.querySelectorAll('#tbl-sk tbody tr.sk-row');
  trs.forEach(tr => {
    const inpNama = tr.querySelector('input[name="nama[]"]') || tr.querySelector('.input-nama');
    const inpNik  = tr.querySelector('input[name="nik[]"]') || tr.querySelector('.input-nik');
    const selJk   = tr.querySelector('select[name="jk[]"]') || tr.querySelector('.select-jk');
    const inpDesa = tr.querySelector('input[name="desa[]"]') || tr.querySelector('.input-desa');
    const inpKec  = tr.querySelector('input[name="kecamatan[]"]') || tr.querySelector('.input-kec');

    const nama = inpNama ? inpNama.value.trim() : '';
    const nik  = inpNik ? inpNik.value.trim() : '';
    const jk   = selJk ? selJk.value.trim() : '';
    const desa = inpDesa ? inpDesa.value.trim() : '';
    const kecamatan = inpKec ? inpKec.value.trim() : '';

    if (nama !== '' || nik !== '') {
      rows.push({ nama, nik, jk, desa, kecamatan });
    }
  });

  document.getElementById('data_anggota_json').value = JSON.stringify(rows);
  // Hapus atribut name pada input massal agar browser tidak mengirim ribuan variabel POST terpisah
  trs.forEach(tr => {
    tr.querySelectorAll('input, select').forEach(el => {
      if (el.name && el.name.endsWith('[]')) {
        el.removeAttribute('name');
      }
    });
  });

  setSkDirty(false);
});

setSkDirty(false);
filterAnggota();
</script>

<?php layout_foot(); ?>
