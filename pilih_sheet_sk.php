<?php
// Konfirmasi pemilihan sheet Excel Daftar Anggota SK (jika file multi-sheet)
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/lib/parse_sk.php';
require_once __DIR__ . '/lib/layout.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

// Batal: hapus pending & file
if (isset($_GET['batal'])) {
    $p = $_SESSION['pending_baru'] ?? null;
    if ($p) {
        @unlink($p['dstExcel'] ?? '');
        @unlink($p['dstSk'] ?? '');
        @unlink($p['dstZip'] ?? '');
        unset($_SESSION['pending_baru']);
        flash_set('warn', 'Pemilihan sheet dibatalkan. Silakan upload ulang ketiga file.');
    }
    header('Location: baru.php');
    exit;
}

$pending = $_SESSION['pending_baru'] ?? null;
if (!$pending) {
    flash_set('error', 'Tidak ada file pending yang perlu pemilihan sheet. Silakan upload ulang.');
    header('Location: baru.php');
    exit;
}

$dstSk = $pending['dstSk'] ?? '';
$origName = $pending['origSkName'] ?? basename($dstSk);
$sheetNames = $pending['sheetNames'] ?? get_sheet_names_sk($dstSk);

if (!is_file($dstSk)) {
    unset($_SESSION['pending_baru']);
    flash_set('error', 'File SK tidak ditemukan (mungkin sudah kadaluarsa). Silakan upload ulang.');
    header('Location: baru.php');
    exit;
}
if (count($sheetNames) <= 1) {
    // tidak perlu picker, lanjut otomatis
    header('Location: baru.php');
    exit;
}

// preview tiap sheet
$previews = preview_sheets_sk($dstSk);
// tentukan rekomendasi: sheet dengan header NIK dan data_rows terbanyak
$bestIdx = null; $bestScore = -1;
foreach ($previews as $i => $pr) {
    $score = $pr['data_rows'];
    if ($pr['has_nik_header']) $score += 10000;
    if ($score > $bestScore) { $bestScore = $score; $bestIdx = $i; }
}

$namaKth = $pending['post']['nama_kth_baru'] ?? '-';
layout_head('Pilih Sheet SK — ' . $namaKth, 'baru');
wizard(1);
?>

<div class="doc-card p-5 max-w-4xl mb-5">
  <div class="flex items-start justify-between gap-4 pb-4 border-b border-kadaster-border">
    <div>
      <div class="text-[11px] font-mono text-audit-warn uppercase tracking-wider font-semibold flex items-center gap-1.5">
        <span class="w-2 h-2 bg-audit-warn inline-block"></span>
        Perlu Konfirmasi — File Multi-Sheet Terdeteksi
      </div>
      <h2 class="font-serif text-xl font-bold text-ink mt-0.5">
        Pilih Sheet Daftar Anggota SK yang Akan Dibaca
      </h2>
      <p class="text-xs text-ink-muted mt-1.5 leading-relaxed max-w-2xl">
        Berkas <b class="font-mono text-ink"><?= e($origName) ?></b> memiliki <b><?= count($sheetNames) ?> sheet</b>.
        Sistem tidak dapat menentukan otomatis sheet mana yang berisi tabel <em>Daftar Anggota</em> (NIK, Nama, Desa).
        Silakan pilih satu sheet di bawah ini untuk melanjutkan.
      </p>
    </div>
    <a href="pilih_sheet_sk.php?batal=1" onclick="return confirm('Batalkan dan hapus file terunggah?')"
       class="btn-kadaster px-3 py-2 text-xs flex-shrink-0">
      Batal &amp; Upload Ulang
    </a>
  </div>

  <div class="mt-3 p-3 bg-audit-warnBg border-l-2 border-audit-warn text-xs text-audit-warn flex items-start gap-2.5">
    <span class="font-bold text-sm leading-none">!</span>
    <div class="leading-relaxed">
      Hanya <b>satu sheet</b> yang akan dibaca. Pastikan sheet yang dipilih adalah yang memuat header <b>NIK / NAMA / DESA / KECAMATAN</b>.
      Sheet lain akan diabaikan.
    </div>
  </div>
</div>

<form action="proses_baru.php" method="post" id="form-pilih-sheet" class="max-w-4xl">
  <input type="hidden" name="confirm_sheet" value="1">

  <div class="space-y-3">
    <?php foreach ($previews as $idx => $pr):
      $isBest = ($idx === $bestIdx);
      $hasHeader = $pr['has_nik_header'];
      $radioId = 'sheet_' . $idx;
    ?>
    <label for="<?= $radioId ?>" class="block doc-card p-4 cursor-pointer hover:border-forest-900/30 transition-colors <?= $isBest ? 'border-forest-900/40 bg-forest-50/40' : 'bg-white' ?> has-[input:checked]:border-forest-900 has-[input:checked]:ring-1 has-[input:checked]:ring-forest-900/20">
      <div class="flex items-start gap-3">
        <input type="radio" name="sheet_sk" value="<?= e($pr['name']) ?>" id="<?= $radioId ?>" <?= $isBest ? 'checked' : '' ?> required
               class="mt-1 w-4 h-4 accent-forest-900">
        <div class="flex-1 min-w-0">
          <div class="flex flex-wrap items-center gap-2">
            <span class="font-mono text-sm font-bold text-ink">
              <?= e($pr['name']) ?>
            </span>
            <span class="text-[11px] font-mono px-1.5 py-0.5 border <?= $hasHeader ? 'bg-audit-validBg text-audit-valid border-audit-validBorder' : 'bg-audit-warnBg text-audit-warn border-audit-warnBorder' ?>">
              <?= $hasHeader ? '✓ Ada header NIK' : '✗ Tanpa header NIK' ?>
            </span>
            <?php if ($isBest): ?>
              <span class="text-[10px] font-bold px-1.5 py-0.5 bg-forest-900 text-white uppercase tracking-wider">Rekomendasi</span>
            <?php endif; ?>
            <span class="text-[11px] text-ink-faint font-mono">
              Sheet #<?= $pr['index'] + 1 ?> · <?= $pr['max_row'] ?> baris total · Header baris <?= $pr['header_row'] ?? '-' ?>
            </span>
          </div>

          <div class="mt-2 flex flex-wrap gap-2 text-[11px]">
            <span class="px-2 py-1 bg-[#FAF8F3] border border-kadaster-border font-mono">
              <b><?= $pr['data_rows'] ?></b> baris data terdeteksi
            </span>
            <?php if ($pr['header_row']): ?>
              <span class="px-2 py-1 bg-white border border-kadaster-border text-ink-muted">
                Header di baris <?= $pr['header_row'] ?>
              </span>
            <?php else: ?>
              <span class="px-2 py-1 bg-audit-revisiBg border border-audit-revisiBorder text-audit-revisi">
                Header NIK/NAMA tidak ditemukan di 15 baris pertama
              </span>
            <?php endif; ?>
          </div>

          <?php if (!empty($pr['samples'])): ?>
          <div class="mt-3 overflow-x-auto">
            <div class="text-[11px] font-semibold text-ink-muted mb-1">Preview 2 baris pertama setelah header:</div>
            <table class="min-w-full text-[11px] border border-kadaster-border">
              <tbody class="divide-y divide-kadaster-border/60">
                <?php foreach ($pr['samples'] as $sRow): ?>
                <tr class="bg-white">
                  <?php foreach ($sRow as $cell): ?>
                    <td class="px-2 py-1 border-r border-kadaster-border/40 truncate max-w-[160px]" title="<?= e($cell) ?>">
                      <?= e($cell) !== '' ? e($cell) : '<span class="text-ink-faint">∅</span>' ?>
                    </td>
                  <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php else: ?>
            <div class="mt-2 text-[11px] text-ink-faint italic">Tidak ada data preview di sheet ini.</div>
          <?php endif; ?>
        </div>
      </div>
    </label>
    <?php endforeach; ?>
  </div>

  <div class="flex items-center justify-between gap-3 mt-5">
    <a href="pilih_sheet_sk.php?batal=1" onclick="return confirm('Batalkan dan hapus file terunggah?')"
       class="btn-kadaster px-4 py-2 text-xs inline-flex items-center gap-1.5">
      ← Batal
    </a>
    <button type="submit" class="btn-forest px-6 py-2.5 text-xs inline-flex items-center gap-2">
      <span>Konfirmasi Sheet &amp; Lanjutkan Proses</span>
      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
      </svg>
    </button>
  </div>

  <!-- Info file pending (debug, hidden tapi berguna) -->
  <div class="mt-4 text-[11px] text-ink-faint font-mono text-center">
    File SK: <?= e(basename($dstSk)) ?> · <?= count($sheetNames) ?> sheet terdeteksi
    <?php if ($isBest !== null): ?> · Sistem merekomendasikan: <b class="text-forest-900"><?= e($previews[$bestIdx]['name']) ?></b><?php endif; ?>
  </div>
</form>

<script>
// Validasi sebelum submit
document.getElementById('form-pilih-sheet').addEventListener('submit', function(ev){
  const chosen = document.querySelector('input[name="sheet_sk"]:checked');
  if (!chosen) {
    ev.preventDefault();
    alert('Silakan pilih salah satu sheet terlebih dahulu.');
  }
});
</script>

<?php layout_foot(); ?>
