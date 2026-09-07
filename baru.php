<?php
// Langkah 1 — Buat verifikasi baru: isi metadata SK + upload 3 file.
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/layout.php';

$kths = db()->query('SELECT id, nama_kth, nomor_sk FROM kth ORDER BY nama_kth')->fetchAll();
layout_head('Buat Verifikasi Baru', 'baru');
wizard(1);
?>
<div class="glass-card rounded-2xl p-7 max-w-4xl fade-in">
  <div class="border-b border-slate-200/80 pb-5 mb-6">
    <h2 class="text-xl font-bold text-slate-800 flex items-center gap-2">
      <span class="w-8 h-8 rounded-lg bg-gradient-to-br from-emerald-500 to-emerald-700 text-white flex items-center justify-center text-sm">1</span>
      Buat Verifikasi Usulan Pupuk Baru
    </h2>
    <p class="text-sm text-slate-500 mt-2 ml-10">
      Lengkapi metadata SK dan upload 3 file input: <b>Excel Usulan Petani</b>, <b>Excel/CSV Daftar Anggota SK</b>, dan <b>ZIP Shapefile Batas PS</b>.
    </p>
  </div>

  <form action="proses_baru.php" method="post" enctype="multipart/form-data" class="space-y-6">
    
    <!-- ═══ Bagian 1: Metadata SK ═══ -->
    <div class="bg-gradient-to-br from-slate-50 to-slate-100/80 border border-slate-200/80 rounded-2xl p-5">
      <h3 class="text-sm font-bold uppercase tracking-wider text-slate-600 mb-4 flex items-center gap-2.5">
        <span class="w-6 h-6 rounded-full bg-emerald-600 text-white flex items-center justify-center text-[10px] font-bold">A</span>
        Informasi &amp; Metadata SK Perhutanan Sosial
      </h3>
      <div class="grid md:grid-cols-2 gap-4">
        <div>
          <label class="block text-xs font-bold text-slate-600 mb-1.5">Nama KTH / LMDH <span class="text-red-500">*</span></label>
          <input name="nama_kth_baru" list="list_kth" class="w-full border border-slate-300 rounded-xl px-3.5 py-2.5 text-sm bg-white focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500 outline-none smooth-all" placeholder="Contoh: KTH SUMBER JATI" required>
          <datalist id="list_kth">
            <?php foreach ($kths as $k): ?><option value="<?= e($k['nama_kth']) ?>"><?php endforeach; ?>
          </datalist>
          <p class="text-xs text-slate-400 mt-1.5">Pilih kelompok yang ada atau ketik nama baru.</p>
        </div>

        <div>
          <label class="block text-xs font-bold text-slate-600 mb-1.5">Nomor SK Kemitraan Kehutanan</label>
          <input name="nomor_sk" class="w-full border border-slate-300 rounded-xl px-3.5 py-2.5 text-sm bg-white focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500 outline-none smooth-all" placeholder="Contoh: SK.9761/MENLHK-PSKL/...">
          <p class="text-xs text-slate-400 mt-1.5">Otomatis dilengkapi dari atribut DBF shapefile jika dikosongkan.</p>
        </div>

        <div>
          <label class="block text-xs font-bold text-slate-600 mb-1.5">Nama KPH</label>
          <input name="nama_kph" class="w-full border border-slate-300 rounded-xl px-3.5 py-2.5 text-sm bg-white focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500 outline-none smooth-all" placeholder="Contoh: KPH Padangan">
        </div>

        <div class="grid grid-cols-3 gap-3">
          <div>
            <label class="block text-xs font-bold text-slate-600 mb-1.5">Luas (ha)</label>
            <input name="luas_areal" type="number" step="0.01" class="w-full border border-slate-300 rounded-xl px-3 py-2.5 text-sm bg-white focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500 outline-none smooth-all" placeholder="47.30">
          </div>
          <div>
            <label class="block text-xs font-bold text-slate-600 mb-1.5">Tanggal SK</label>
            <input name="tanggal_sk" type="date" class="w-full border border-slate-300 rounded-xl px-3 py-2.5 text-sm bg-white focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500 outline-none smooth-all">
          </div>
          <div>
            <label class="block text-xs font-bold text-slate-600 mb-1.5">Tahun Usulan</label>
            <input name="tahun" type="number" min="2000" max="2100" value="<?= date('Y') ?>" class="w-full border border-slate-300 rounded-xl px-3 py-2.5 text-sm bg-white focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500 outline-none smooth-all">
          </div>
        </div>
      </div>
    </div>

    <!-- ═══ Bagian 2: Upload 3 File ═══ -->
    <div class="bg-gradient-to-br from-sky-50/50 to-slate-50 border border-slate-200/80 rounded-2xl p-5 space-y-4">
      <h3 class="text-sm font-bold uppercase tracking-wider text-slate-600 mb-3 flex items-center gap-2.5">
        <span class="w-6 h-6 rounded-full bg-sky-600 text-white flex items-center justify-center text-[10px] font-bold">B</span>
        Berkas Input Verifikasi
      </h3>

      <?php
      $files = [
        ['f_excel', '.xlsx,.xls', 'File Excel Usulan Pupuk Petani', '(.xlsx / .xls)', 'Daftar petani yang mengusulkan pupuk bersubsidi beserta kolom titik koordinat (X dan Y).', '📊', 'from-emerald-400 to-emerald-600'],
        ['f_sk', '.xlsx,.xls,.csv', 'File Daftar Anggota Resmi SK', '(.xlsx / .xls / .csv)', 'File hasil ekstraksi tabel lampiran SK (No, Nama, NIK, L/P, Desa, Kecamatan).', '📋', 'from-sky-400 to-sky-600'],
        ['f_zip', '.zip', 'File Shapefile Batas Areal PS', '(.zip)', 'File .zip berisi .shp, .dbf, .shx poligon batas areal Perhutanan Sosial.', '🗺️', 'from-violet-400 to-violet-600'],
      ];
      foreach ($files as $i => [$name, $accept, $label, $ext, $desc, $icon, $gradient]):
        $n = $i + 1;
      ?>
      <div class="bg-white border border-slate-200/80 rounded-xl p-4 smooth-all hover:shadow-md hover:border-slate-300" x-data="{ filename: '' }">
        <div class="flex items-start gap-3">
          <div class="w-10 h-10 rounded-xl bg-gradient-to-br <?= $gradient ?> text-white flex items-center justify-center text-lg flex-shrink-0 shadow"><?= $icon ?></div>
          <div class="flex-1 min-w-0">
            <label class="block text-sm font-semibold text-slate-800 mb-0.5">
              <?= $n ?>. <?= $label ?> <span class="text-slate-400 font-normal"><?= $ext ?></span> <span class="text-red-500">*</span>
            </label>
            <p class="text-xs text-slate-500 mb-3"><?= $desc ?></p>
            <div class="relative">
              <input type="file" name="<?= $name ?>" accept="<?= $accept ?>" required 
                @change="filename = $event.target.files.length ? $event.target.files[0].name : ''"
                class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
              <div class="flex items-center gap-3 border-2 border-dashed rounded-xl px-4 py-3 text-sm smooth-all"
                :class="filename ? 'border-emerald-300 bg-emerald-50/50' : 'border-slate-300 bg-slate-50 hover:border-slate-400'">
                <svg class="w-5 h-5 flex-shrink-0" :class="filename ? 'text-emerald-500' : 'text-slate-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                </svg>
                <span x-show="!filename" class="text-slate-400">Klik atau seret file ke sini…</span>
                <span x-show="filename" class="text-emerald-700 font-medium truncate" x-text="'✓ ' + filename"></span>
              </div>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- ═══ Tombol Submit ═══ -->
    <div class="flex items-center justify-between pt-2">
      <a href="index.php" class="btn-secondary px-5 py-2.5 rounded-xl text-sm inline-flex items-center gap-2">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        Batal
      </a>
      <button type="submit" class="btn-primary px-6 py-2.5 rounded-xl text-sm inline-flex items-center gap-2 shadow-lg">
        Upload &amp; Lanjut ke Konfirmasi SK
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
      </button>
    </div>
  </form>
</div>

<div class="glass-card rounded-2xl p-5 mt-5 max-w-4xl text-sm fade-in fade-in-delay-1">
  <div class="flex items-start gap-3">
    <div class="w-8 h-8 rounded-lg bg-amber-100 text-amber-700 flex items-center justify-center text-lg flex-shrink-0">💡</div>
    <div>
      <div class="font-bold text-slate-700 mb-1">Informasi Preprocessing SK &amp; Format Koordinat</div>
      <div class="text-xs text-slate-500 leading-relaxed space-y-1">
        <p>• <b>Daftar Anggota SK:</b> Ekstraksi PDF scan dilakukan via skrip/aplikasi converter (<code class="bg-slate-100 px-1.5 py-0.5 rounded text-slate-600">Konversi_SK_Anggota.exe</code> atau <code class="bg-slate-100 px-1.5 py-0.5 rounded text-slate-600">extract_sk_anggota_offline.py</code>).</p>
        <p>• <b>Koordinat Lahan:</b> Format seperti <code class="bg-slate-100 px-1.5 py-0.5 rounded text-slate-600">X: 111. 701717</code> otomatis dibersihkan dan diuji via Ray-Casting spasial PHP.</p>
      </div>
    </div>
  </div>
</div>

<?php layout_foot(); ?>
