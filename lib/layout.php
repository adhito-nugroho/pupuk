<?php
// Layout + komponen UI bersama — Sistem Desain Resmi Kehutanan (CDK).
function layout_head(string $judul, string $aktif = ''): void {
    ?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($judul) ?> — <?= e(APP_NAME) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Newsreader:ital,opsz,wght@0,6..72,400;0,6..72,500;0,6..72,600;0,6..72,700;1,6..72,400&family=Plus+Jakarta+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config = {
  theme: {
    extend: {
      colors: {
        forest: {
          950: '#0F1E16',
          900: '#1B382B', // Identitas Utama Kehutanan
          800: '#264E3D',
          700: '#32634E',
          100: '#E4ECE7',
          50:  '#F2F6F4',
          // Aliases untuk konsistensi markup
          dark: '#1B382B',
          subtle: '#264E3D',
          ink: '#142219',
        },
        paper: {
          DEFAULT: '#F9F7F1', // Base netral kertas register
          card: '#FFFFFF',
          muted: '#F3EFE7',
          tint: '#F6F2E9', // Alias paper-tint
        },
        kadaster: {
          border: '#DDD5C7', // Hairline rule peta kadaster
          dark: '#524333',
          brown: '#7D664E',
          light: '#F6F2E9',
        },
        cadastral: {
          DEFAULT: '#DDD5C7', // Alias border-cadastral & bg-cadastral
        },
        ink: {
          DEFAULT: '#142219', // Teks utama nyaris hitam kehijauan
          muted: '#57655B',
          faint: '#86948B',
        },
        audit: {
          valid: '#1D5C3A',
          validBg: '#F0F7F2',
          validBorder: '#B2D8C0',
          revisi: '#9E2A2B',
          revisiBg: '#FDF2F2',
          revisiBorder: '#F2B8B8',
          warn: '#B45309',
          warnBg: '#FEF9EE',
          warnBorder: '#F6DBA5',
        },
        status: {
          sesuai: '#1D5C3A', // Alias audit.valid
          revisi: '#9E2A2B', // Alias audit.revisi
          luar: '#B45309',   // Alias audit.warn
        }
      },
      fontFamily: {
        serif: ['Newsreader', 'Georgia', 'serif'],
        sans: ['"Plus Jakarta Sans"', 'Inter', 'system-ui', 'sans-serif'],
        mono: ['"JetBrains Mono"', 'Roboto Mono', 'ui-monospace', 'monospace'],
      }
    }
  }
}
</script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<style>
  [x-cloak] { display: none !important; }
  body {
    background-color: #F9F7F1;
    color: #142219;
    font-family: "Plus Jakarta Sans", system-ui, sans-serif;
  }
  .font-serif {
    font-family: 'Newsreader', Georgia, serif;
  }
  .tabular-nums {
    font-variant-numeric: tabular-nums;
  }
  /* Dokumen Card — Garis Kadaster Bersih, Tanpa Shadow Lebam */
  .doc-card {
    background: #FFFFFF;
    border: 1px solid #DDD5C7;
    box-shadow: 0 1px 3px rgba(27, 56, 43, 0.04);
  }
  /* Tombol Khusus Alat Kerja */
  .btn-forest, .btn-primary {
    background-color: #1B382B;
    color: #FFFFFF;
    border: 1px solid #0F1E16;
    font-weight: 600;
    transition: background-color 0.15s ease;
  }
  .btn-forest:hover, .btn-primary:hover {
    background-color: #264E3D;
  }
  .btn-kadaster, .btn-secondary {
    background-color: #FFFFFF;
    color: #524333;
    border: 1px solid #DDD5C7;
    font-weight: 600;
    transition: all 0.15s ease;
  }
  .btn-kadaster:hover, .btn-secondary:hover {
    background-color: #F9F7F1;
    border-color: #7D664E;
    color: #142219;
  }
  .btn-revisi {
    background-color: #9E2A2B;
    color: #FFFFFF;
    border: 1px solid #751F20;
    font-weight: 600;
    transition: background-color 0.15s ease;
  }
  .btn-revisi:hover {
    background-color: #B53234;
  }
  /* Badge Dokumen Resmi */
  .doc-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.2rem 0.55rem;
    font-size: 0.72rem;
    font-weight: 600;
    border-radius: 2px;
  }
  .badge-sk-sesuai {
    background-color: #F0F7F2;
    color: #1D5C3A;
    border: 1px solid #B2D8C0;
    border-left: 3px solid #1D5C3A;
  }
  .badge-sk-belum {
    background-color: #FDF2F2;
    color: #9E2A2B;
    border: 1px solid #F2B8B8;
    border-left: 3px solid #9E2A2B;
  }
  /* Animasi Transisi Halus */
  @keyframes fadeIn {
    from { opacity: 0; transform: translateY(2px); }
    to { opacity: 1; transform: translateY(0); }
  }
  .fade-in {
    animation: fadeIn 0.2s ease-out forwards;
  }
  /* Garis Baris Tabel */
  .hairline-row {
    border-bottom: 1px solid #DDD5C7;
    transition: background-color 0.1s ease;
  }
  .hairline-row:hover {
    background-color: #F6F3EC;
  }
  /* Scrollbar Bersih */
  ::-webkit-scrollbar { width: 6px; height: 6px; }
  ::-webkit-scrollbar-track { background: #F3EFE7; }
  ::-webkit-scrollbar-thumb { background: #C8BEAF; border-radius: 2px; }
  ::-webkit-scrollbar-thumb:hover { background: #7D664E; }
</style>
</head>
<body class="bg-paper text-ink min-h-screen flex flex-col antialiased selection:bg-forest-100 selection:text-forest-900">

<!-- Header Resmi Instansi Kehutanan -->
<header class="bg-forest-900 text-white border-b border-[#2A4839] relative">
  <div class="max-w-7xl mx-auto px-5 py-3.5 flex flex-wrap items-center justify-between gap-4">
    <div class="flex items-center gap-3.5">
      <!-- Lambang Dinas Kehutanan Resmi / Institutional Shield -->
      <div class="w-10 h-10 rounded bg-[#0F261B] border border-emerald-500/30 flex items-center justify-center flex-shrink-0 shadow-sm">
        <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <!-- Perisai Kedinasan -->
          <path d="M12 2.2L4.5 5.2V11.2C4.5 16.2 7.7 20.8 12 21.8C16.3 20.8 19.5 16.2 19.5 11.2V5.2L12 2.2Z" 
                fill="#143423" stroke="#52B788" stroke-width="1.3" stroke-linejoin="round"/>
          <!-- Bintang Pengayom / Bintang Kehormatan -->
          <path d="M12 4.5L12.5 5.8H13.8L12.7 6.6L13.1 7.9L12 7.1L10.9 7.9L11.3 6.6L10.2 5.8H11.5L12 4.5Z" 
                fill="#F3C64F"/>
          <!-- Tajuk Daun Pohon Jati/Hutan Rindang -->
          <path d="M12 8C10.1 8 8.8 9.3 8.8 10.9C8.8 11.4 9 11.8 9.2 12.1C8.3 12.4 7.8 13.2 7.8 14.1C7.8 15.3 8.8 16.2 10 16.2C10.3 16.2 10.6 16.1 10.8 16C11.1 16.8 11.5 17.2 12 17.2C12.5 17.2 12.9 16.8 13.2 16C13.4 16.1 13.7 16.2 14 16.2C15.2 16.2 16.2 15.3 16.2 14.1C16.2 13.2 15.7 12.4 14.8 12.1C15 11.8 15.2 11.4 15.2 10.9C15.2 9.3 13.9 8 12 8Z" 
                fill="#52B788"/>
          <!-- Batang Pohon Jati & Percabangan -->
          <path d="M12 12.8V18.2M10.2 18.2H13.8M12 15.2L10.5 13.8M12 14.5L13.5 13.5" 
                stroke="#F3C64F" stroke-width="1.1" stroke-linecap="round" stroke-linejoin="round"/>
          <!-- Untaian Padi & Kapas Kemakmuran Petani Hutan -->
          <path d="M6.2 10.5C5.8 12.2 6.1 14.5 7.2 16.5M17.8 10.5C18.2 12.2 17.9 14.5 16.8 16.5" 
                stroke="#F3C64F" stroke-width="0.9" stroke-linecap="round" opacity="0.85"/>
        </svg>
      </div>
      <div>
        <div class="text-[10.5px] font-semibold text-emerald-200/75 uppercase tracking-wider leading-none mb-1">
          Dinas Kehutanan Provinsi Jawa Timur · CDK Wilayah Bojonegoro
        </div>
        <h1 class="font-serif text-lg font-semibold tracking-wide text-white leading-none">
          <?= e(APP_NAME) ?>
        </h1>
      </div>
    </div>

    <!-- Navigasi Register Kasus -->
    <nav class="flex items-center gap-1.5 text-xs font-semibold">
      <?php
        $navItems = [
          'daftar' => ['index.php', 'Buku Register Kasus'],
          'baru'   => ['baru.php',  '+ Buka Kasus Baru'],
        ];
        foreach ($navItems as $key => [$href, $label]):
          $isActive = $aktif === $key;
      ?>
      <a href="<?= $href ?>" class="px-3.5 py-1.5 transition-colors border <?= $isActive
        ? 'bg-[#2A4839] text-white border-emerald-400/30'
        : 'border-transparent text-emerald-100/70 hover:text-white hover:bg-forest-800' ?>">
        <?= $label ?>
      </a>
      <?php endforeach; ?>
    </nav>
  </div>
</header>

<main class="max-w-7xl mx-auto px-5 py-6 flex-1 w-full">
    <?php
    foreach (flash_take() as $idx => $f) {
        $isErr = $f['tipe'] === 'error';
        $isWarn = $f['tipe'] === 'warn';
        $borderCls = $isErr ? 'border-audit-revisi bg-audit-revisiBg text-audit-revisi'
            : ($isWarn ? 'border-audit-warn bg-audit-warnBg text-audit-warn'
                       : 'border-audit-valid bg-audit-validBg text-audit-valid');
        echo '<div x-data="{show:true}" x-show="show" class="border-l-4 border px-4 py-3 mb-4 text-xs font-medium flex items-start justify-between gap-3 ' . $borderCls . '">'
            . '<div>' . nl2br(e($f['pesan'])) . '</div>'
            . '<button @click="show=false" class="text-current/60 hover:text-current font-bold text-sm">&times;</button>'
            . '</div>';
    }
}

function layout_foot(): void {
    ?>
</main>
<footer class="bg-forest-900 text-white border-t border-[#2A4839] mt-auto">
  <div class="max-w-7xl mx-auto px-5 py-3.5 flex flex-wrap items-center justify-between text-xs text-emerald-100/75 gap-2">
    <div>
      <span class="font-semibold text-white">Cabang Dinas Kehutanan Wilayah Bojonegoro</span>
      <span class="mx-2 text-emerald-500/40">|</span>
      <span>Aplikasi Verifikasi Alokasi Pupuk Bersubsidi Sektor Kehutanan</span>
    </div>
  </div>
</footer>
</body>
</html>
    <?php
}

// Stempel Status SK (Legalitas)
function badge_sk(string $s): string {
    return $s === 'Sesuai SK PS'
        ? '<span class="inline-flex items-center gap-1.5 px-2 py-0.5 text-xs font-semibold text-audit-valid bg-audit-validBg border border-audit-validBorder border-l-2 border-l-audit-valid"><span class="w-1.5 h-1.5 bg-audit-valid inline-block"></span>Sesuai SK PS</span>'
        : '<span class="inline-flex items-center gap-1.5 px-2 py-0.5 text-xs font-semibold text-audit-revisi bg-audit-revisiBg border border-audit-revisiBorder border-l-2 border-l-audit-revisi"><span class="w-1.5 h-1.5 bg-audit-revisi inline-block"></span>Belum Sesuai SK PS</span>';
}

// Stempel Status Posisi Koordinat (Spasial)
function badge_koord(string $s): string {
    return $s === 'Dalam Peta PS'
        ? '<span class="inline-flex items-center gap-1.5 px-2 py-0.5 text-xs font-semibold text-forest-900 bg-forest-50 border border-forest-100 border-l-2 border-l-forest-900"><span class="w-1.5 h-1.5 bg-forest-900 inline-block"></span>Dalam Peta PS</span>'
        : '<span class="inline-flex items-center gap-1.5 px-2 py-0.5 text-xs font-semibold text-audit-warn bg-audit-warnBg border border-audit-warnBorder border-l-2 border-l-audit-warn"><span class="w-1.5 h-1.5 bg-audit-warn inline-block"></span>Luar Peta PS</span>';
}

/** Bilah Tahapan Verifikasi Administrasi (1 s.d. 4) — Format Checklist Kedinasan */
function wizard(int $step): void {
    $steps = [
        1 => ['no' => '01', 'label' => 'Berkas Usulan & Peta', 'sub' => 'Upload Excel & SHP'],
        2 => ['no' => '02', 'label' => 'Konfirmasi SK',       'sub' => 'Daftar Anggota SK'],
        3 => ['no' => '03', 'label' => 'Uji Spasial & Titik',  'sub' => 'Validasi Koordinat'],
        4 => ['no' => '04', 'label' => 'Berita Acara Rekomendasi', 'sub' => 'Laporan Akhir'],
    ];
    echo '<div class="doc-card mb-6 p-3.5">';
    echo '  <div class="grid grid-cols-2 md:grid-cols-4 gap-2">';
    foreach ($steps as $i => $s) {
        $done    = $i < $step;
        $current = $i === $step;

        if ($current) {
            $cardBg = 'bg-forest-900 text-white border-forest-950';
            $noColor = 'text-emerald-300';
            $subColor = 'text-emerald-100/70';
        } elseif ($done) {
            $cardBg = 'bg-audit-validBg text-audit-valid border-audit-validBorder';
            $noColor = 'text-audit-valid font-bold';
            $subColor = 'text-audit-valid/70';
        } else {
            $cardBg = 'bg-[#FAF8F3] text-ink-muted border-kadaster-border';
            $noColor = 'text-ink-faint';
            $subColor = 'text-ink-faint';
        }

        echo '<div class="p-2.5 border ' . $cardBg . ' flex items-start gap-2.5">';
        echo '  <div class="font-mono text-xs font-bold mt-0.5 ' . $noColor . '">' . ($done ? '✓' : $s['no']) . '</div>';
        echo '  <div class="min-w-0">';
        echo '    <div class="text-xs font-bold truncate leading-tight">' . e($s['label']) . '</div>';
        echo '    <div class="text-[10.5px] mt-0.5 ' . $subColor . '">' . e($s['sub']) . '</div>';
        echo '  </div>';
        echo '</div>';
    }
    echo '  </div>';
    echo '</div>';
}

/**
 * Sub-Navbar Navigasi Terpadu Kasus KTH & Switcher Versi Usulan.
 */
function layout_kth_subnav(array $kth, string $aktif, int $versiAktif = 1, array $daftarVersi = []): void {
    $kthId = (int)($kth['id'] ?? 0);
    $namaKth = $kth['nama_kth'] ?? 'KTH';
    $nomorSk = $kth['nomor_sk'] ?? '-';
    $luasSk  = !empty($kth['luas_areal']) ? number_format((float)$kth['luas_areal'], 2, ',', '.') . ' Ha' : '-';
    $vUtama  = (int)($kth['versi_aktif'] ?? 1);

    // Cari info versi terpilih
    $infoVersiTerpilih = null;
    foreach ($daftarVersi as $v) {
        if ((int)$v['versi_ke'] === $versiAktif) {
            $infoVersiTerpilih = $v;
            break;
        }
    }
    ?>
    <div class="doc-card mb-6 rounded-md overflow-hidden fade-in">
      <!-- Header Identitas Kasus KTH -->
      <div class="p-4 bg-gradient-to-r from-forest-950 via-forest-900 to-forest-800 text-white flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3">
          <a href="index.php" title="Kembali ke Buku Register" class="w-8 h-8 rounded bg-white/10 hover:bg-white/20 border border-white/15 flex items-center justify-center text-white text-xs font-bold transition-colors">
            ←
          </a>
          <div>
            <div class="flex items-center gap-2">
              <span class="text-[10px] font-mono font-bold tracking-wider px-2 py-0.5 rounded bg-emerald-400/20 text-emerald-300 border border-emerald-400/30 uppercase">KASUS #<?= $kthId ?></span>
              <h2 class="font-serif font-bold text-lg text-white leading-tight inline-block"><?= e($namaKth) ?></h2>
            </div>
            <div class="text-[11px] text-emerald-100/70 flex flex-wrap items-center gap-x-3 gap-y-1 mt-0.5">
              <span>SK: <b class="text-white"><?= e($nomorSk) ?></b></span>
              <span class="text-emerald-500/50">·</span>
              <span>Luas SK: <b class="text-white"><?= $luasSk ?></b></span>
              <?php if ($vUtama > 1): ?>
              <span class="text-emerald-500/50">·</span>
              <span class="text-amber-300 font-semibold">Tersedia <?= count($daftarVersi) ?> Putaran Usulan</span>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Pemilih Versi Usulan (Version Switcher) -->
        <?php if (!empty($daftarVersi)): ?>
        <div class="flex items-center gap-2 bg-black/25 px-3 py-1.5 rounded border border-white/10 text-xs">
          <label class="text-[11px] font-medium text-emerald-200/80 flex items-center gap-1.5">
            <svg class="w-3.5 h-3.5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Data Usulan:
          </label>
          <select class="bg-forest-950 text-white font-semibold text-xs rounded px-2.5 py-1 border border-emerald-500/30 focus:outline-none focus:border-emerald-400 cursor-pointer"
                  onchange="const u = new URL(window.location.href); u.searchParams.set('v', this.value); window.location.href = u.toString();">
            <?php foreach ($daftarVersi as $v): 
                $vNum = (int)$v['versi_ke'];
                $isSel = $vNum === $versiAktif;
                $isMain = $vNum === $vUtama;
                $label = 'v' . $vNum . ' — ' . ($v['label_versi'] ?: ('Versi ' . $vNum)) . ($isMain ? ' (Aktif)' : '');
            ?>
              <option value="<?= $vNum ?>" <?= $isSel ? 'selected' : '' ?>>
                <?= e($label) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <?php if ($versiAktif !== $vUtama): ?>
            <span class="text-[10px] px-2 py-0.5 rounded bg-amber-500/20 text-amber-300 border border-amber-500/30 font-semibold" title="Anda sedang melihat data historis versi terdahulu">
              Riwayat (v<?= $versiAktif ?>)
            </span>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- Tab Navigasi Modul -->
      <div class="bg-[#FAF8F3] border-t border-kadaster-border px-3 py-1 flex flex-wrap items-center justify-between gap-2 text-xs font-semibold">
        <div class="flex flex-wrap items-center gap-1">
          <?php
            $vParam = $versiAktif > 1 ? '&v=' . $versiAktif : '';
            $tabs = [
              'hasil'      => ['href' => "hasil.php?kth_id={$kthId}{$vParam}",      'icon' => '📊', 'label' => 'Hasil Verifikasi'],
              'sk'         => ['href' => "konfirmasi_sk.php?kth_id={$kthId}",        'icon' => '👥', 'label' => 'Anggota SK'],
              'peta'       => ['href' => "peta.php?kth_id={$kthId}{$vParam}",       'icon' => '🗺️', 'label' => 'Peta Spasial'],
              'laporan'    => ['href' => "laporan.php?kth_id={$kthId}{$vParam}",    'icon' => '📝', 'label' => 'Berita Acara & Laporan'],
              'perbaikan'  => ['href' => "perbaikan.php?kth_id={$kthId}",           'icon' => '🔄', 'label' => 'Riwayat & Upload Perbaikan'],
            ];

            foreach ($tabs as $key => $tab):
              $isTabActive = $aktif === $key;
          ?>
            <a href="<?= $tab['href'] ?>" 
               class="px-3 py-2 rounded-t transition-all inline-flex items-center gap-1.5 border-b-2 <?= $isTabActive 
                ? 'border-forest-900 text-forest-900 bg-white font-bold shadow-sm' 
                : 'border-transparent text-ink-muted hover:text-forest-900 hover:bg-white/60' ?>">
              <span><?= $tab['icon'] ?></span>
              <span><?= $tab['label'] ?></span>
            </a>
          <?php endforeach; ?>
        </div>

        <div class="flex items-center gap-2 py-1">
          <a href="cetak.php?kth_id=<?= $kthId ?><?= $vParam ?>" target="_blank" rel="noopener"
             class="btn-kadaster px-3 py-1.5 text-xs inline-flex items-center gap-1.5 font-medium text-forest-900 hover:bg-white">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
            <span>Cetak Lembar Hasil</span>
          </a>
        </div>
      </div>
    </div>
    <?php
}

