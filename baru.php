<?php
// Langkah 1 — Buat verifikasi baru: isi metadata SK + upload 3 berkas kerja.
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/layout.php';

$kths = db()->query('SELECT id, nama_kth, nomor_sk FROM kth ORDER BY nama_kth')->fetchAll();
layout_head('Buka Kasus Verifikasi Baru — Tahap 01', 'baru');
wizard(1);
?>

<div class="doc-card p-6 max-w-4xl">
  <!-- Header Formulir Berkas -->
  <div class="border-b border-kadaster-border pb-4 mb-6">
    <div class="text-[11px] font-mono text-ink-faint uppercase tracking-wider">
      Tahap 01 Berkas Usulan &amp; Peta
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
          <input id="input_nama_kth_baru" name="nama_kth_baru" list="list_kth" 
            class="w-full border border-kadaster-border px-3 py-2 text-xs bg-white text-ink focus:border-forest-900 focus:outline-none" 
            placeholder="Contoh: KTH SUMBER JATI" required autocomplete="off">
          <datalist id="list_kth">
            <?php foreach ($kths as $k): ?><option value="<?= e($k['nama_kth']) ?>"><?php endforeach; ?>
          </datalist>
          <div id="notice_kth_terdaftar" class="hidden text-xs text-amber-900 bg-amber-100/80 border border-amber-300 p-2.5 rounded mt-2">
            ⚠️ <b>Kelompok Tani ini sudah terdaftar.</b> Pastikan Anda mencentang persetujuan di bawah bila ingin menimpa data lama dengan berkas baru ini.
          </div>
          <p class="text-[11px] text-ink-faint mt-1">Pilih kelompok tani terdaftar atau ketik nama baru.</p>
          <label id="label_timpa" class="flex items-start gap-2 mt-2 text-[11px] text-ink-muted bg-amber-50 border border-amber-200 rounded px-2.5 py-2 leading-relaxed">
            <input type="checkbox" id="check_timpa" name="timpa_jika_ada" value="1" class="mt-0.5">
            <span>Saya sadar: bila nama KTH ini <b>sudah terdaftar</b>, seluruh data lama kasus tersebut akan <b>diganti</b> dengan berkas yang saya unggah sekarang. Centang untuk menyetujui.</span>
          </label>
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
      <div class="flex items-center justify-between pb-3 mb-2 border-b border-kadaster-border">
        <div class="flex items-center gap-2">
          <span class="w-2 h-2 bg-forest-900 inline-block"></span>
          <h3 class="text-xs font-bold uppercase tracking-wider text-ink">
            Bagian B: Tiga Dokumen Kerja Verifikasi
          </h3>
        </div>
        <span id="badge_status_berkas" class="text-[11px] font-mono px-2.5 py-0.5 rounded bg-amber-50 text-amber-800 border border-amber-200">
          0 dari 3 Berkas Dipilih
        </span>
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
          'desc' => 'File hasil konversi/ekstraksi tabel lampiran SK Menteri LHK (Nomor, Nama, NIK, L/P, Desa, Kecamatan). Jika file memiliki lebih dari 1 sheet, sistem akan meminta Anda memilih sheet yang benar setelah upload.',
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
      <div id="wrapper_<?= $f['name'] ?>" class="file-card-wrapper bg-white border border-kadaster-border p-4 transition-all duration-200">
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
          <input type="file" id="input_<?= $f['name'] ?>" name="<?= $f['name'] ?>" accept="<?= $f['accept'] ?>" required
            class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
          
          <div id="box_<?= $f['name'] ?>" class="file-drop-box flex items-center justify-between border-2 border-dashed border-kadaster-border px-4 py-3 text-xs transition-all duration-150 bg-[#FAF8F3] hover:bg-white rounded">
            <!-- Tampilan Belum Pilih File -->
            <div id="empty_<?= $f['name'] ?>" class="flex items-center gap-2.5 text-ink-faint">
              <svg class="w-5 h-5 text-kadaster-brown flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
              </svg>
              <div>
                <span class="font-medium text-ink">Klik di sini untuk memilih file</span> atau seret file ke area ini
                <span class="block text-[11px] text-ink-faint mt-0.5">Format yang didukung: <?= $f['ext'] ?></span>
              </div>
            </div>

            <!-- Tampilan File Terpilih (Awalnya Sembunyi) -->
            <div id="chosen_<?= $f['name'] ?>" class="hidden items-center gap-3 w-full">
              <div class="w-8 h-8 rounded bg-emerald-600 text-white flex items-center justify-center flex-shrink-0 font-bold text-sm">
                ✓
              </div>
              <div class="min-w-0 flex-1">
                <div id="name_<?= $f['name'] ?>" class="font-mono font-bold text-emerald-950 truncate text-xs"></div>
                <div id="size_<?= $f['name'] ?>" class="text-[11px] text-emerald-700 mt-0.5"></div>
              </div>
              <span class="text-[11px] text-emerald-800 font-semibold uppercase px-2 py-1 bg-white border border-emerald-300 rounded shadow-sm">
                Ganti File
              </span>
            </div>

            <span id="btn_pick_<?= $f['name'] ?>" class="text-[11px] text-ink-faint font-mono font-bold ml-2">PILIH FILE</span>
          </div>
          
          <p id="err_<?= $f['name'] ?>" class="hidden text-xs text-audit-revisi font-semibold mt-1.5"></p>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- ═══ Indikator Loading Saat Submit ═══ -->
    <div id="upload_progress_banner" class="hidden p-4 bg-emerald-50 border-2 border-emerald-500 rounded-md">
      <div class="flex items-center gap-3">
        <svg class="animate-spin h-5 w-5 text-emerald-700 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
        </svg>
        <div>
          <div class="font-bold text-emerald-900 text-xs">Sedang Mengunggah &amp; Menganalisis 3 Berkas...</div>
          <div class="text-[11px] text-emerald-700 mt-0.5">Proses membaca Excel dan parsing poligon spasial sedang berlangsung. Mohon tunggu sejenak...</div>
        </div>
      </div>
    </div>

    <!-- ═══ Tombol Aksi ═══ -->
    <div class="flex items-center justify-between pt-2 border-t border-kadaster-border">
      <a href="index.php" class="btn-kadaster px-4 py-2 text-xs inline-flex items-center gap-1.5">
        ← Batal
      </a>
      <button type="submit" id="btn_submit_baru" class="btn-forest px-6 py-2.5 text-xs inline-flex items-center gap-2 font-semibold">
        <span id="btn_submit_text">Simpan &amp; Lanjut ke Tahap 02 — Konfirmasi SK</span>
        <svg id="btn_submit_icon" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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

<script>
(function() {
  const fileNames = ['f_excel', 'f_sk', 'f_zip'];
  const maxBytes = 50 * 1024 * 1024; // 50 MB
  
  function formatBytes(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / 1048576).toFixed(2) + ' MB';
  }

  function updateStatusCount() {
    let filled = 0;
    fileNames.forEach(fn => {
      const inp = document.getElementById('input_' + fn);
      if (inp && inp.files && inp.files.length > 0) filled++;
    });
    const badge = document.getElementById('badge_status_berkas');
    if (badge) {
      badge.textContent = filled + ' dari 3 Berkas Dipilih';
      if (filled === 3) {
        badge.className = 'text-[11px] font-mono px-2.5 py-0.5 rounded bg-emerald-100 text-emerald-900 border border-emerald-300 font-bold';
      } else {
        badge.className = 'text-[11px] font-mono px-2.5 py-0.5 rounded bg-amber-50 text-amber-800 border border-amber-200';
      }
    }
  }

  fileNames.forEach(fn => {
    const input = document.getElementById('input_' + fn);
    const box = document.getElementById('box_' + fn);
    const emptyView = document.getElementById('empty_' + fn);
    const chosenView = document.getElementById('chosen_' + fn);
    const nameEl = document.getElementById('name_' + fn);
    const sizeEl = document.getElementById('size_' + fn);
    const errEl = document.getElementById('err_' + fn);
    const btnPick = document.getElementById('btn_pick_' + fn);

    if (!input || !box) return;

    input.addEventListener('change', function() {
      if (errEl) { errEl.textContent = ''; errEl.classList.add('hidden'); }
      
      if (this.files && this.files.length > 0) {
        const file = this.files[0];
        
        // Cek ukuran
        if (file.size > maxBytes) {
          if (errEl) {
            errEl.textContent = 'Ukuran file ' + file.name + ' (' + formatBytes(file.size) + ') melebihi batas maksimal 50 MB!';
            errEl.classList.remove('hidden');
          }
          this.value = '';
          return;
        }

        // Tampilkan feedback instan
        if (nameEl) nameEl.textContent = file.name;
        if (sizeEl) sizeEl.textContent = 'Ukuran: ' + formatBytes(file.size) + ' · Siap diunggah';
        if (emptyView) emptyView.classList.add('hidden');
        if (chosenView) { chosenView.classList.remove('hidden'); chosenView.classList.add('flex'); }
        if (btnPick) btnPick.classList.add('hidden');

        // Beri style box hijau aktif
        box.classList.remove('border-dashed', 'border-kadaster-border', 'bg-[#FAF8F3]');
        box.classList.add('border-solid', 'border-emerald-600', 'bg-emerald-50/70', 'shadow-sm');
      } else {
        // Reset bila batal
        if (emptyView) emptyView.classList.remove('hidden');
        if (chosenView) { chosenView.classList.add('hidden'); chosenView.classList.remove('flex'); }
        if (btnPick) btnPick.classList.remove('hidden');
        box.classList.add('border-dashed', 'border-kadaster-border', 'bg-[#FAF8F3]');
        box.classList.remove('border-solid', 'border-emerald-600', 'bg-emerald-50/70', 'shadow-sm');
      }
      updateStatusCount();
    });

    // Efek drag & drop visual
    ['dragenter', 'dragover'].forEach(eventName => {
      input.addEventListener(eventName, function() {
        box.classList.add('border-forest-900', 'bg-forest-50');
      });
    });
    ['dragleave', 'drop'].forEach(eventName => {
      input.addEventListener(eventName, function() {
        box.classList.remove('border-forest-900', 'bg-forest-50');
      });
    });
  });

  // Handler Submit Form: Validasi & Tampilan Loading
  const form = document.querySelector('form[action="proses_baru.php"]');
  if (form) {
    form.addEventListener('submit', function(e) {
      let missing = [];
      fileNames.forEach(fn => {
        const inp = document.getElementById('input_' + fn);
        if (!inp || !inp.files || inp.files.length === 0) {
          missing.push(fn);
        }
      });

      if (missing.length > 0) {
        e.preventDefault();
        const first = missing[0];
        const wrap = document.getElementById('wrapper_' + first);
        const err = document.getElementById('err_' + first);
        if (err) {
          err.textContent = '⚠️ Berkas ini wajib dipilih sebelum melanjutkan!';
          err.classList.remove('hidden');
        }
        if (wrap) {
          wrap.scrollIntoView({ behavior: 'smooth', block: 'center' });
          wrap.classList.add('ring-2', 'ring-red-500');
          setTimeout(() => wrap.classList.remove('ring-2', 'ring-red-500'), 2500);
        }
        return false;
      }

      // Valid: Tampilkan Indikator Loading Instan
      const submitBtn = document.getElementById('btn_submit_baru');
      const submitText = document.getElementById('btn_submit_text');
      const submitIcon = document.getElementById('btn_submit_icon');
      const banner = document.getElementById('upload_progress_banner');

      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.classList.add('opacity-80', 'cursor-wait');
      }
      if (submitText) {
        submitText.textContent = '⏳ Mengunggah 3 Berkas & Memproses... Mohon Tunggu...';
      }
      if (submitIcon) {
        submitIcon.classList.add('animate-spin');
      }
      if (banner) {
        banner.classList.remove('hidden');
        banner.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      }
    });
  }

  // Deteksi Nama KTH yang Sudah Terdaftar
  const kthNames = <?= json_encode(array_map(fn($k) => mb_strtolower(trim($k['nama_kth']), 'UTF-8'), $kths)) ?>;
  const inputKth = document.getElementById('input_nama_kth_baru');
  const noticeKth = document.getElementById('notice_kth_terdaftar');
  const checkTimpa = document.getElementById('check_timpa');
  const labelTimpa = document.getElementById('label_timpa');

  if (inputKth && noticeKth) {
    function checkKth() {
      const val = (inputKth.value || '').trim().toLowerCase();
      if (val && kthNames.includes(val)) {
        noticeKth.classList.remove('hidden');
        if (labelTimpa) {
          labelTimpa.classList.remove('bg-amber-50', 'border-amber-200');
          labelTimpa.classList.add('bg-amber-100', 'border-amber-400', 'ring-2', 'ring-amber-400/50');
        }
      } else {
        noticeKth.classList.add('hidden');
        if (labelTimpa) {
          labelTimpa.classList.add('bg-amber-50', 'border-amber-200');
          labelTimpa.classList.remove('bg-amber-100', 'border-amber-400', 'ring-2', 'ring-amber-400/50');
        }
      }
    }
    inputKth.addEventListener('input', checkKth);
    inputKth.addEventListener('change', checkKth);
    checkKth();
  }
})();
</script>

<?php layout_foot(); ?>
