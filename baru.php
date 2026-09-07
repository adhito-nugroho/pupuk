<?php
// Langkah 1 — Buat verifikasi baru: isi metadata SK + upload 3 berkas kerja.
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/layout.php';

$kths = db()->query('SELECT id, nama_kth, nomor_sk FROM kth ORDER BY nama_kth')->fetchAll();
layout_head('Buka Kasus Baru — Tahap 1', 'baru');
wizard(1);
?>

<div class="doc-card p-6 max-w-4xl">
  <!-- Header Formulir Berkas -->
  <div class="border-b border-kadaster-border pb-4 mb-6">
    <div class="text-[11px] font-mono text-ink-faint uppercase tracking-wider">
      Tahap 01 Administrasi &amp; Registrasi Berkas
    </div>
    <h2 class="font-serif text-2xl font-bold text-ink mt-0.5">
      Formulir Berkas Usulan &amp; Data Spasial
    </h2>
    <p class="text-xs text-ink-muted mt-1 max-w-2xl leading-relaxed">
      Lengkapi identitas kelompok tani (KTH/LMDH) dan unggah 3 dokumen kerja: Berkas Usulan Petani, Daftar Anggota SK, serta Arsip Spasial Shapefile (.zip).
    </p>
  </div>

  <form action="proses_baru.php" method="post" enctype="multipart/form-data" class="space-y-6">
    
    <!-- ═══ Bagian 1: Metadata SK ═══ -->
    <div class="border border-kadaster-border p-5 bg-[#FAF8F3]">
      <div class="flex items-center gap-2 pb-3 mb-4 border-b border-kadaster-border">
        <span class="w-2 h-2 bg-kadaster-brown inline-block"></span>
        <h3 class="text-xs font-bold uppercase tracking-wider text-ink">
          Bagian A: Identitas KTH &amp; Legalitas SK
        </h3>
      </div>

      <div class="grid md:grid-cols-2 gap-4">
        <div>
          <label class="block text-xs font-semibold text-ink mb-1">
            Nama KTH / LMDH <span class="text-audit-revisi">*</span>
          </label>
          <input name="nama_kth_baru" list="list_kth" 
            class="w-full border border-kadaster-border px-3 py-2 text-xs bg-white text-ink focus:border-forest-900 focus:outline-none" 
            placeholder="Contoh: KTH SUMBER JATI" required>
          <datalist id="list_kth">
            <?php foreach ($kths as $k): ?><option value="<?= e($k['nama_kth']) ?>"><?php endforeach; ?>
          </datalist>
          <p class="text-[11px] text-ink-faint mt-1">Pilih kelompok tani terdaftar atau ketik nama baru.</p>
        </div>

        <div>
          <label class="block text-xs font-semibold text-ink mb-1">
            Nomor SK Perhutanan Sosial
          </label>
          <input name="nomor_sk" 
            class="w-full border border-kadaster-border px-3 py-2 text-xs bg-white font-mono text-ink focus:border-forest-900 focus:outline-none" 
            placeholder="Contoh: SK.9761/MENLHK-PSKL/PKPS/...">
          <p class="text-[11px] text-ink-faint mt-1">Otomatis diambil dari atribut DBF shapefile jika dikosongkan.</p>
        </div>

        <div>
          <label class="block text-xs font-semibold text-ink mb-1">
            Kesatuan Pengelolaan Hutan (KPH)
          </label>
          <input name="nama_kph" 
            class="w-full border border-kadaster-border px-3 py-2 text-xs bg-white text-ink focus:border-forest-900 focus:outline-none" 
            placeholder="Contoh: KPH Padangan">
        </div>

        <div class="grid grid-cols-3 gap-2.5">
          <div>
            <label class="block text-xs font-semibold text-ink mb-1">Luas (Ha)</label>
            <input name="luas_areal" type="number" step="0.01" 
              class="w-full border border-kadaster-border px-2.5 py-2 text-xs bg-white font-mono text-ink focus:border-forest-900 focus:outline-none" 
              placeholder="47.30">
          </div>
          <div>
            <label class="block text-xs font-semibold text-ink mb-1">Tanggal SK</label>
            <input name="tanggal_sk" type="date" 
              class="w-full border border-kadaster-border px-2 py-2 text-xs bg-white font-mono text-ink focus:border-forest-900 focus:outline-none">
          </div>
          <div>
            <label class="block text-xs font-semibold text-ink mb-1">Tahun Usulan</label>
            <input name="tahun" type="number" min="2000" max="2100" value="<?= date('Y') ?>" 
              class="w-full border border-kadaster-border px-2 py-2 text-xs bg-white font-mono text-ink focus:border-forest-900 focus:outline-none">
          </div>
        </div>
      </div>
    </div>

    <!-- ═══ Bagian 2: Tiga Berkas Input ═══ -->
    <div class="border border-kadaster-border p-5 bg-[#FAF8F3] space-y-4">
      <div class="flex items-center gap-2 pb-3 mb-2 border-b border-kadaster-border">
        <span class="w-2 h-2 bg-forest-900 inline-block"></span>
        <h3 class="text-xs font-bold uppercase tracking-wider text-ink">
          Bagian B: Tiga Dokumen Kerja Verifikasi
        </h3>
      </div>

      <?php
      $files = [
        [
          'name' => 'f_excel',
          'accept' => '.xlsx,.xls',
          'code' => 'DOK-01',
          'label' => 'Excel Usulan Alokasi Pupuk Petani',
          'ext' => '.xlsx / .xls',
          'desc' => 'Daftar petani pemohon pupuk bersubsidi dengan kolom NIK, Nama, dan Koordinat Lahan (X dan Y).',
        ],
        [
          'name' => 'f_sk',
          'accept' => '.xlsx,.xls,.csv',
          'code' => 'DOK-02',
          'label' => 'Daftar Anggota Resmi SK Kemitraan',
          'ext' => '.xlsx / .xls / .csv',
          'desc' => 'File hasil konversi/ekstraksi tabel lampiran SK Menteri LHK (Nomor, Nama, NIK, L/P, Desa, Kecamatan).',
        ],
        [
          'name' => 'f_zip',
          'accept' => '.zip',
          'code' => 'DOK-03',
          'label' => 'Arsip Shapefile Batas Areal PS',
          'ext' => '.zip',
          'desc' => 'File arsip .zip berisi file .shp, .dbf, .shx, dan .prj poligon batas definitif Perhutanan Sosial.',
        ],
      ];
      foreach ($files as $f):
      ?>
      <div class="bg-white border border-kadaster-border p-4" x-data="{ filename: '' }">
        <div class="flex items-start justify-between gap-3 mb-2">
          <div>
            <div class="flex items-center gap-2">
              <span class="font-mono text-[10px] px-1.5 py-0.5 bg-kadaster-light text-kadaster-dark font-semibold border border-kadaster-border">
                <?= $f['code'] ?>
              </span>
              <label class="text-xs font-bold text-ink">
                <?= $f['label'] ?> <span class="text-audit-revisi">*</span>
              </label>
              <span class="text-[11px] font-mono text-ink-faint"><?= $f['ext'] ?></span>
            </div>
            <p class="text-xs text-ink-muted mt-1 leading-normal"><?= $f['desc'] ?></p>
          </div>
        </div>

        <div class="relative mt-2">
          <input type="file" name="<?= $f['name'] ?>" accept="<?= $f['accept'] ?>" required
            @change="filename = $event.target.files.length ? $event.target.files[0].name : ''"
            class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
          <div class="flex items-center justify-between border border-dashed border-kadaster-border px-3.5 py-2.5 text-xs transition-colors bg-[#FAF8F3] hover:bg-white">
            <span x-show="!filename" class="text-ink-faint flex items-center gap-2">
              <svg class="w-4 h-4 text-kadaster-brown" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
              </svg>
              Klik atau seret file berkas ke sini...
            </span>
            <span x-show="filename" class="text-audit-valid font-mono font-semibold truncate flex items-center gap-1.5" x-text="'[OK] ' + filename"></span>
            <span class="text-[11px] text-ink-faint font-mono">PILIH FILE</span>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- ═══ Tombol Aksi ═══ -->
    <div class="flex items-center justify-between pt-2 border-t border-kadaster-border">
      <a href="index.php" class="btn-kadaster px-4 py-2 text-xs inline-flex items-center gap-1.5">
        ← Batal
      </a>
      <button type="submit" class="btn-forest px-5 py-2.5 text-xs inline-flex items-center gap-2">
        <span>Simpan &amp; Lanjut ke Tahap 02 (Konfirmasi SK)</span>
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
        </svg>
      </button>
    </div>
  </form>
</div>

<!-- Catatan Petunjuk Teknis -->
<div class="doc-card p-4 mt-5 max-w-4xl text-xs bg-[#FAF8F3]">
  <div class="flex items-start gap-3">
    <div class="font-mono text-sm text-kadaster-brown font-bold mt-0.5">ℹ</div>
    <div>
      <div class="font-bold text-ink mb-1 uppercase text-[11px] tracking-wider">Petunjuk Teknis Dokumen Masukan</div>
      <div class="text-[11.5px] text-ink-muted leading-relaxed space-y-1">
        <p>• <b>Konversi SK PDF:</b> Bila daftar anggota masih dalam bentuk PDF SK scan resmi, gunakan konverter offline (<span class="font-mono text-ink">Jalankan_Konverter.bat</span>) untuk menghasilkan file Excel anggota sebelum diunggah.</p>
        <p>• <b>Uji Koordinat Lahan:</b> Nilai titik koordinat otomatis diuraikan ke derajat desimal dan divalidasi langsung ke dalam poligon batas kawasan PS melalui algoritma spasial.</p>
      </div>
    </div>
  </div>
</div>

<?php layout_foot(); ?>
