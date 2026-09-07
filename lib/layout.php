<?php
// Layout + komponen UI bersama — Modern Design System.
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
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config={theme:{extend:{fontFamily:{sans:['Inter','system-ui','sans-serif']}}}}</script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<style>
  *{font-family:'Inter',system-ui,sans-serif}
  /* Animated page fade-in */
  @keyframes fadeInUp{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
  .fade-in{animation:fadeInUp .4s ease-out both}
  .fade-in-delay-1{animation-delay:.08s}
  .fade-in-delay-2{animation-delay:.16s}
  .fade-in-delay-3{animation-delay:.24s}
  /* Glass card */
  .glass-card{background:rgba(255,255,255,.85);backdrop-filter:blur(12px);border:1px solid rgba(255,255,255,.6);box-shadow:0 4px 24px rgba(0,0,0,.06),0 1px 2px rgba(0,0,0,.04)}
  /* Button shine */
  .btn-primary{background:linear-gradient(135deg,#059669,#047857);color:#fff;font-weight:600;transition:all .2s}
  .btn-primary:hover{background:linear-gradient(135deg,#047857,#065f46);box-shadow:0 4px 16px rgba(5,150,105,.35);transform:translateY(-1px)}
  .btn-primary:active{transform:translateY(0)}
  .btn-secondary{background:#fff;border:1px solid #e2e8f0;color:#475569;font-weight:500;transition:all .2s}
  .btn-secondary:hover{background:#f8fafc;border-color:#cbd5e1;box-shadow:0 2px 8px rgba(0,0,0,.06)}
  .btn-danger{background:linear-gradient(135deg,#ef4444,#dc2626);color:#fff;font-weight:600;transition:all .2s}
  .btn-danger:hover{box-shadow:0 4px 16px rgba(239,68,68,.35);transform:translateY(-1px)}
  /* Header pattern */
  .header-pattern{background-image:url("data:image/svg+xml,%3Csvg width='40' height='40' viewBox='0 0 40 40' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M20 20.5V18H0v-2h20v-2H0v-2h20v-2H0V8h20V6H0V4h20V2H0V0h22v20h2V0h2v20h2V0h2v20h2V0h2v20h2V0h4v2h-2v2h2v2h-2v2h2v2h-2v2h2v2h-2v2h2v18H0v-2h20v-2H0v-2h20v-2H0v-2h20v-2H0v-2h20v-2z' fill='%23ffffff' fill-opacity='.04'/%3E%3C/svg%3E")}
  /* Smooth transitions */
  .smooth-all{transition:all .2s ease}
  /* Table hover */
  .tbl-row{transition:background .15s ease}
  .tbl-row:hover{background:linear-gradient(90deg,#f0fdf4,#f8fafc)!important}
  /* Scrollbar */
  ::-webkit-scrollbar{width:6px;height:6px}
  ::-webkit-scrollbar-track{background:#f1f5f9;border-radius:3px}
  ::-webkit-scrollbar-thumb{background:#cbd5e1;border-radius:3px}
  ::-webkit-scrollbar-thumb:hover{background:#94a3b8}
  /* Flash dismiss animation */
  @keyframes flashIn{from{opacity:0;transform:translateX(-20px)}to{opacity:1;transform:translateX(0)}}
  .flash-msg{animation:flashIn .3s ease-out both}
</style>
</head>
<body class="bg-gradient-to-br from-slate-50 via-slate-100 to-emerald-50/30 text-slate-800 min-h-screen">
<header class="bg-gradient-to-r from-emerald-800 via-emerald-900 to-teal-900 text-white shadow-xl header-pattern relative overflow-hidden">
  <div class="absolute inset-0 bg-gradient-to-b from-black/10 to-transparent"></div>
  <div class="max-w-7xl mx-auto px-5 py-5 flex flex-wrap items-center gap-4 justify-between relative z-10">
    <div class="flex items-center gap-3">
      <div class="w-10 h-10 rounded-xl bg-white/15 backdrop-blur flex items-center justify-center text-xl shadow-lg border border-white/20">
        🌲
      </div>
      <div>
        <h1 class="text-lg font-bold leading-tight tracking-tight"><?= e(APP_NAME) ?></h1>
        <p class="text-emerald-200/80 text-xs font-medium">Dinas Kehutanan — Verifikasi KTH/LMDH</p>
      </div>
    </div>
    <nav class="flex gap-2 text-sm">
      <?php
        $navItems = [
          'daftar' => ['index.php', '📋', 'Daftar Kasus'],
          'baru'   => ['baru.php',  '➕', 'Verifikasi Baru'],
        ];
        foreach ($navItems as $key => [$href, $icon, $label]):
          $isActive = $aktif === $key;
      ?>
      <a href="<?= $href ?>" class="flex items-center gap-1.5 px-4 py-2 rounded-lg font-medium transition-all duration-200 <?= $isActive
        ? 'bg-white/20 backdrop-blur shadow-inner text-white'
        : 'bg-white/5 hover:bg-white/15 text-emerald-100 hover:text-white' ?>">
        <span class="text-sm"><?= $icon ?></span>
        <?= $label ?>
      </a>
      <?php endforeach; ?>
    </nav>
  </div>
</header>
<main class="max-w-7xl mx-auto px-5 py-7">
    <?php
    foreach (flash_take() as $idx => $f) {
        $cls = $f['tipe'] === 'error'
            ? 'bg-red-50 border-red-300 text-red-800'
            : ($f['tipe'] === 'warn'
                ? 'bg-amber-50 border-amber-300 text-amber-900'
                : 'bg-emerald-50 border-emerald-300 text-emerald-900');
        $icon = $f['tipe'] === 'error' ? '❌' : ($f['tipe'] === 'warn' ? '⚠️' : '✅');
        echo '<div x-data="{show:true}" x-show="show" x-transition.opacity class="flash-msg border rounded-xl px-4 py-3 mb-4 flex items-start gap-3 ' . $cls . '">'
            . '<span class="text-lg leading-none mt-0.5">' . $icon . '</span>'
            . '<div class="flex-1 text-sm">' . nl2br(e($f['pesan'])) . '</div>'
            . '<button @click="show=false" class="text-current/40 hover:text-current/70 text-lg leading-none ml-2">&times;</button>'
            . '</div>';
    }
}

function layout_foot(): void {
    ?>
</main>
<footer class="max-w-7xl mx-auto px-5 pb-8 pt-4">
  <div class="border-t border-slate-200 pt-4 flex flex-wrap items-center justify-between text-xs text-slate-400 gap-2">
    <span>© <?= date('Y') ?> Dinas Kehutanan — Aplikasi Verifikasi Pupuk Subsidi</span>
    <span class="flex items-center gap-1.5">
      <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 inline-block"></span>
      PHP + MySQL + Tailwind + Alpine.js
    </span>
  </div>
</footer>
</body>
</html>
    <?php
}

function badge_sk(string $s): string {
    return $s === 'Sesuai SK PS'
        ? '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 ring-1 ring-emerald-200/60"><svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>Sesuai SK</span>'
        : '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-800 ring-1 ring-red-200/60"><svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>Belum Sesuai</span>';
}
function badge_koord(string $s): string {
    return $s === 'Dalam Peta PS'
        ? '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-sky-100 text-sky-800 ring-1 ring-sky-200/60"><svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/></svg>Dalam Peta</span>'
        : '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-orange-100 text-orange-800 ring-1 ring-orange-200/60"><svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>Luar Peta</span>';
}

/** Langkah wizard 1-4 — modern stepper with icons & connecting lines. */
function wizard(int $step): void {
    $steps = [
        1 => ['icon' => '📤', 'label' => 'Upload File'],
        2 => ['icon' => '📋', 'label' => 'Konfirmasi SK'],
        3 => ['icon' => '🔍', 'label' => 'Hasil Verifikasi'],
        4 => ['icon' => '📊', 'label' => 'Laporan Akhir'],
    ];
    echo '<div class="flex items-center gap-0 mb-7 fade-in">';
    $total = count($steps);
    foreach ($steps as $i => $s) {
        $done    = $i < $step;
        $current = $i === $step;
        $future  = $i > $step;

        if ($done) {
            $circleClass = 'bg-emerald-600 text-white shadow-lg shadow-emerald-200';
            $labelClass  = 'text-emerald-700 font-semibold';
        } elseif ($current) {
            $circleClass = 'bg-white text-emerald-700 ring-2 ring-emerald-500 shadow-lg shadow-emerald-100';
            $labelClass  = 'text-emerald-800 font-bold';
        } else {
            $circleClass = 'bg-slate-100 text-slate-400 border border-slate-200';
            $labelClass  = 'text-slate-400 font-medium';
        }

        echo '<div class="flex flex-col items-center flex-shrink-0">';
        echo   '<div class="w-10 h-10 rounded-full flex items-center justify-center text-sm transition-all duration-300 ' . $circleClass . '">';
        echo     $done ? '<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>' : $s['icon'];
        echo   '</div>';
        echo   '<span class="text-xs mt-1.5 whitespace-nowrap ' . $labelClass . '">' . e($s['label']) . '</span>';
        echo '</div>';

        if ($i < $total) {
            $lineClass = $done ? 'bg-emerald-400' : 'bg-slate-200';
            echo '<div class="flex-1 h-0.5 mx-2 rounded-full mt-[-12px] ' . $lineClass . '"></div>';
        }
    }
    echo '</div>';
}
