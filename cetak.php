<?php
// cetak.php — Lembar Cetak Resmi Hasil Verifikasi Administrasi & Spasial
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/verify.php';

$kthId = (int)($_GET['kth_id'] ?? 0);
$vParam = isset($_GET['v']) ? (int)$_GET['v'] : null;

$pdo = db();
$stKth = $pdo->prepare('SELECT * FROM kth WHERE id = ?');
$stKth->execute([$kthId]);
$kth = $stKth->fetch();
if (!$kth) {
    echo '<div style="font-family:sans-serif;padding:30px;text-align:center">Data KTH tidak ditemukan.</div>';
    exit;
}

$versiAktif = ambil_versi_terpilih($kth, $vParam);

// Ambil info versi
$stVer = $pdo->prepare('SELECT * FROM kth_versi_usulan WHERE kth_id = ? AND versi_ke = ?');
$stVer->execute([$kthId, $versiAktif]);
$verInfo = $stVer->fetch() ?: [
    'label_versi' => 'Usulan (v' . $versiAktif . ')',
    'dibuat_pada' => $kth['dibuat_pada'],
    'nama_file_asli' => 'usulan.xlsx'
];

$stRows = $pdo->prepare('
    SELECT u.*, h.status_sk, h.status_koordinat, h.catatan 
    FROM usulan_pupuk u 
    LEFT JOIN hasil_verifikasi h ON (h.usulan_id = u.id AND h.versi_ke = u.versi_ke)
    WHERE u.kth_id = ? AND u.versi_ke = ? 
    ORDER BY COALESCE(u.no_urut, u.id)
');
$stRows->execute([$kthId, $versiAktif]);
$rows = $stRows->fetchAll();

$hitung = ['total' => count($rows), 'sesuai' => 0, 'tidak' => 0, 'dalam' => 0, 'luar' => 0, 'luas' => 0.0, 'lebih' => 0];
foreach ($rows as $r) {
    if (($r['status_sk'] ?? '') === 'Sesuai SK PS') $hitung['sesuai']++; else $hitung['tidak']++;
    if (($r['status_koordinat'] ?? '') === 'Dalam Peta PS') $hitung['dalam']++; else $hitung['luar']++;
    $hitung['luas'] += (float)($r['luas_lahan'] ?? 0);
    if ((float)($r['luas_lahan'] ?? 0) > 2.0) $hitung['lebih']++;
}

$rekomAuto = ($hitung['tidak'] === 0 && $hitung['luar'] === 0 && $hitung['lebih'] === 0 && $hitung['total'] > 0) ? 'DAPAT DITINDAKLANJUTI' : 'PERLU REVISI';

// Status Akhir mengikuti Kesimpulan Verifikasi (laporan.rekomendasi) bila sudah disimpan;
// fallback ke hitung otomatis bila belum ada laporan untuk versi ini.
$rekom = $rekomAuto;
$rekomSumber = 'otomatis';
try {
    $cekLap = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'laporan' AND COLUMN_NAME = 'versi_ke'");
    $cekLap->execute();
    if ((int)$cekLap->fetchColumn() > 0) {
        $stLap = $pdo->prepare('SELECT rekomendasi FROM laporan WHERE kth_id = ? AND versi_ke = ? AND rekomendasi IS NOT NULL AND rekomendasi != "" ORDER BY id DESC LIMIT 1');
        $stLap->execute([$kthId, $versiAktif]);
    } else {
        $stLap = $pdo->prepare('SELECT rekomendasi FROM laporan WHERE kth_id = ? AND rekomendasi IS NOT NULL AND rekomendasi != "" ORDER BY id DESC LIMIT 1');
        $stLap->execute([$kthId]);
    }
    if (($rowLap = $stLap->fetch()) && !empty($rowLap['rekomendasi'])) {
        $rekom = mb_strtoupper(trim((string)$rowLap['rekomendasi']), 'UTF-8');
        $rekomSumber = 'kesimpulan';
    }
} catch (Throwable $eLap) { /* tetap pakai otomatis */ }

$verifikator = pengaturan_verifikator($pdo);
$ttdDinas = pengaturan_penandatangan_dinas($pdo);
$tglUsulanIndo = tgl_indo($verInfo['dibuat_pada'] ?? 'now');
$tglCetakIndo = tgl_indo('now');

// --- Data tambahan format Dinas (cerminan export Excel verifikasi) ---
$narasiDinas = '';
try {
    $stNar = $pdo->prepare('SELECT narasi FROM laporan WHERE kth_id = ? AND versi_ke = ? AND narasi IS NOT NULL AND narasi != "" ORDER BY id DESC LIMIT 1');
    $stNar->execute([$kthId, $versiAktif]);
    $rowNar = $stNar->fetch();
    if ($rowNar && trim((string)$rowNar['narasi']) !== '') $narasiDinas = trim((string)$rowNar['narasi']);
} catch (Throwable $eNar) {}
if ($narasiDinas === '') {
    $tahunNar = (int)($kth['tahun_usulan'] ?? date('Y'));
    $narasiDinas = buat_narasi_default($kth, $tahunNar > 0 ? $tahunNar : (int)date('Y'), [
        'total' => $hitung['total'], 'sesuai' => $hitung['sesuai'], 'tidak' => $hitung['tidak'],
        'dalam' => $hitung['dalam'], 'luar' => $hitung['luar'],
        'lebih_luas' => $hitung['lebih'], 'total_luas' => $hitung['luas'],
    ]);
}
$isTindakDinas = (stripos($rekom, 'TINDAK') !== false);
$rekomCatatanDinas = $isTindakDinas ? 'Dapat direkomendasikan.' : 'Perlu revisi / verifikasi lanjutan sebelum direkomendasikan.';
$nomorSkDinas = trim((string)($kth['nomor_sk'] ?? ''));
$adaSkDinas = ($nomorSkDinas !== '' && strtolower($nomorSkDinas) !== '-');
$luasSkDinas = !empty($kth['luas_areal']) ? (float)$kth['luas_areal'] : 0.0;
$desaDinas = ''; $kecDinas = '';
try {
    $qLocD = $pdo->prepare('SELECT desa, kecamatan FROM usulan_pupuk WHERE kth_id = ? AND versi_ke = ? AND (desa IS NOT NULL AND desa != "") LIMIT 1');
    $qLocD->execute([$kthId, $versiAktif]);
    $locD = $qLocD->fetch() ?: [];
    $desaDinas = (string)($locD['desa'] ?? $kth['desa'] ?? '');
    $kecDinas = (string)($locD['kecamatan'] ?? $kth['kecamatan'] ?? '');
} catch (Throwable $eLocD) {}
$titleCase = function (string $s): string {
    $s = trim(mb_strtolower($s, 'UTF-8'));
    return $s === '' ? '' : mb_convert_case($s, MB_CASE_TITLE, 'UTF-8');
};
$desaTDinas = $titleCase($desaDinas); $kecTDinas = $titleCase($kecDinas);
if ($adaSkDinas) {
    $luasIntD = (int)round($luasSkDinas);
    $terbD = $luasIntD > 0 ? ' (' . terbilang_id($luasIntD) . ')' : '';
    $luasTxtD = $luasSkDinas > 0 ? 'Seluas ± ' . number_format($luasSkDinas, 0, ',', '.') . $terbD . ' Hektare ' : '';
    $wilTxtD = trim(($desaTDinas !== '' ? 'Di Desa ' . $desaTDinas . ' ' : '') . ($kecTDinas !== '' ? 'Kecamatan ' . $kecTDinas . ' ' : ''));
    $catLegalDinas = trim(preg_replace('/\s+/', ' ', 'Sudah terdapat legalitas SK Menlhk Nomor SK: ' . $nomorSkDinas
        . ' tentang Pemberian Persetujuan Pengelolaan Hutan Kemasyarakatan Kepada ' . ($kth['nama_kth'] ?? '')
        . ' ' . $luasTxtD . 'Berada Dalam Kawasan Hutan Produksi Tetap Pada Kawasan Hutan Dengan Pengelolaan Khusus '
        . ($wilTxtD !== '' ? $wilTxtD . ' ' : '') . 'Kabupaten Bojonegoro Provinsi Jawa Timur.'));
} else {
    $catLegalDinas = 'Belum terdapat legalitas SK Perhutanan Sosial (SK PS belum tercatat) untuk ' . ($kth['nama_kth'] ?? '') . '.';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Lembar Hasil Verifikasi — <?= e($kth['nama_kth']) ?> (v<?= $versiAktif ?>)</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Newsreader:ital,wght@0,400;0,700;1,400&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  @page { size: A4 portrait; margin: 12mm 15mm; }
  body {
    font-family: 'Plus Jakarta Sans', Arial, sans-serif;
    color: #111827;
    background: #fff;
    font-size: 11px;
    line-height: 1.4;
    margin: 0;
    padding: 20px;
  }
  .kop {
    text-align: center;
    border-bottom: 2.5px double #1B382B;
    padding-bottom: 8px;
    margin-bottom: 12px;
  }
  .kop-instansi { font-size: 12px; font-weight: 700; letter-spacing: 0.5px; text-transform: uppercase; color: #1B382B; }
  .kop-cabang   { font-size: 14px; font-weight: 800; letter-spacing: 0.5px; text-transform: uppercase; color: #0F1E16; }
  .kop-alamat   { font-size: 9.5px; color: #4B5563; margin-top: 2px; }
  .judul-dok    { text-align: center; font-size: 13px; font-weight: 800; text-transform: uppercase; margin: 10px 0 4px; color: #1B382B; }
  .subjudul-dok { text-align: center; font-size: 10px; color: #4B5563; margin-bottom: 14px; }
  
  table.meta-grid { width: 100%; border-collapse: collapse; margin-bottom: 12px; font-size: 10.5px; }
  table.meta-grid td { padding: 3px 6px; vertical-align: top; }
  
  .rekap-box {
    display: flex;
    justify-content: space-between;
    gap: 8px;
    margin-bottom: 14px;
  }
  .rekap-item {
    flex: 1;
    border: 1px solid #D1D5DB;
    padding: 6px 8px;
    border-radius: 4px;
    background: #F9FAFB;
    text-align: center;
  }
  .rekap-item .num { font-size: 14px; font-weight: 800; color: #1B382B; }
  .rekap-item .lbl { font-size: 9.5px; color: #4B5563; text-transform: uppercase; font-weight: 600; }

  table.data-table { width: 100%; border-collapse: collapse; margin-bottom: 16px; font-size: 9.5px; }
  table.data-table th, table.data-table td { border: 1px solid #9CA3AF; padding: 4.5px 5px; }
  table.data-table th { background: #E5E7EB; font-weight: 700; text-align: center; color: #111827; }

  .status-ok  { color: #15803D; font-weight: 700; }
  .status-err { color: #B91C1C; font-weight: 700; }

  .ttd-grid {
    margin-top: 20px;
    display: flex;
    justify-content: space-between;
    page-break-inside: avoid;
    font-size: 10.5px;
  }
  .ttd-box { width: 42%; text-align: center; }
  .ttd-space { height: 50px; }

  .no-print {
    background: #1B382B;
    color: white;
    padding: 10px 16px;
    border-radius: 6px;
    margin-bottom: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
  }
  .btn-print {
    background: #fff;
    color: #1B382B;
    border: none;
    font-weight: 700;
    font-size: 12px;
    padding: 6px 14px;
    border-radius: 4px;
    cursor: pointer;
  }
  @media print {
    .no-print { display: none; }
    body { padding: 0; }
  }
</style>
</head>
<body>

<div class="no-print">
  <div>
    <b>Lembar Verifikasi Cetak:</b> <?= e($kth['nama_kth']) ?> (<?= e($verInfo['label_versi'] ?: ('Versi ' . $versiAktif)) ?>)
  </div>
  <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
    <a href="export.php?kth_id=<?= $kthId ?>&v=<?= $versiAktif ?>&mode=lembar" class="btn-print" style="text-decoration:none;">⬇ Unduh Excel</a>
    <a href="export.php?kth_id=<?= $kthId ?>&v=<?= $versiAktif ?>&mode=verifikasi" class="btn-print" style="text-decoration:none;background:#0E5E38;color:#fff;">⬇ Excel Verifikasi Dinas</a>
    <a href="pengaturan.php?kembali=<?= urlencode('cetak.php?kth_id=' . $kthId . '&v=' . $versiAktif) ?>" class="btn-print" style="text-decoration:none;background:#E5E7EB;color:#1B382B;">✏️ Verifikator: <?= $verifikator['nama'] !== '' ? e($verifikator['nama']) : 'Belum diatur' ?></a>
    <button class="btn-print" onclick="window.print()">🖨️ Cetak Dokumen</button>
  </div>
</div>

<div class="kop">
  <div class="kop-instansi">Pemerintah Provinsi Jawa Timur · Dinas Kehutanan</div>
  <div class="kop-cabang">Cabang Dinas Kehutanan Wilayah Bojonegoro</div>
  <div class="kop-alamat">Jl. Panglima Polim No. 19 Bojonegoro · Email: cdk.bojonegoro@jatimprov.go.id</div>
</div>

<div class="judul-dok">LEMBAR HASIL VERIFIKASI ALOKASI PUPUK BERSUBSIDI</div>
<div class="subjudul-dok">Dokumen Rekonsiliasi Legalitas SK Perhutanan Sosial &amp; Uji Spasial Titik Lahan Petani</div>

<table class="meta-grid">
  <tr>
    <td width="18%"><b>Nama Kelompok</b></td>
    <td width="2%">:</td>
    <td width="35%"><b><?= e($kth['nama_kth']) ?></b></td>
    <td width="18%"><b>Putaran Usulan</b></td>
    <td width="2%">:</td>
    <td width="25%"><b><?= e($verInfo['label_versi'] ?: ('Versi ' . $versiAktif)) ?></b></td>
  </tr>
  <tr>
    <td><b>Nomor SK PS</b></td>
    <td>:</td>
    <td><?= e($kth['nomor_sk'] ?: '-') ?></td>
    <td><b>Tanggal Usulan</b></td>
    <td>:</td>
    <td><?= e($tglUsulanIndo) ?></td>
  </tr>
  <tr>
    <td><b>Luas Areal SK</b></td>
    <td>:</td>
    <td><?= !empty($kth['luas_areal']) ? number_format((float)$kth['luas_areal'], 2, ',', '.') . ' Ha' : '-' ?></td>
    <td><b>Total Luas Usulan</b></td>
    <td>:</td>
    <td><b><?= number_format($hitung['luas'], 2, ',', '.') ?> Ha</b></td>
  </tr>
  <tr>
    <td><b>Status Akhir</b></td>
    <td>:</td>
    <td colspan="4">
      <b style="color: <?= $rekom === 'DAPAT DITINDAKLANJUTI' ? '#15803D' : '#B91C1C' ?>;">
        <?= e($rekom) ?>
      </b>
      <?php if ($rekomSumber === 'kesimpulan'): ?>
      <span style="font-size:9px;color:#6B7280;font-weight:400;">(mengikuti Kesimpulan Verifikasi Tahap 04)</span>
      <?php else: ?>
      <span style="font-size:9px;color:#6B7280;font-weight:400;">(otomatis — belum ada Kesimpulan tersimpan di Tahap 04)</span>
      <?php endif; ?>
    </td>
  </tr>
</table>

<div class="rekap-box">
  <div class="rekap-item">
    <div class="num"><?= $hitung['total'] ?></div>
    <div class="lbl">Total Petani</div>
  </div>
  <div class="rekap-item">
    <div class="num" style="color:#15803D"><?= $hitung['sesuai'] ?></div>
    <div class="lbl">Sesuai SK</div>
  </div>
  <div class="rekap-item">
    <div class="num" style="color:<?= $hitung['tidak'] > 0 ? '#B91C1C' : '#15803D' ?>"><?= $hitung['tidak'] ?></div>
    <div class="lbl">Belum Sesuai SK</div>
  </div>
  <div class="rekap-item">
    <div class="num" style="color:#15803D"><?= $hitung['dalam'] ?></div>
    <div class="lbl">Dalam Peta</div>
  </div>
  <div class="rekap-item">
    <div class="num" style="color:<?= $hitung['luar'] > 0 ? '#D97706' : '#15803D' ?>"><?= $hitung['luar'] ?></div>
    <div class="lbl">Luar Peta</div>
  </div>
</div>

<table class="data-table">
  <thead>
    <tr>
      <th width="4%">No</th>
      <th width="16%">NIK</th>
      <th width="24%">Nama Petani</th>
      <th width="8%">Luas (Ha)</th>
      <th width="15%">Kesesuaian SK</th>
      <th width="15%">Posisi Peta</th>
      <th>Catatan Hasil Telaah</th>
    </tr>
  </thead>
  <tbody>
    <?php if (empty($rows)): ?>
    <tr><td colspan="7" style="text-align:center;padding:10px;">Belum ada data usulan untuk versi ini.</td></tr>
    <?php else: foreach ($rows as $i => $r): 
        $okSK = ($r['status_sk'] ?? '') === 'Sesuai SK PS';
        $okPeta = ($r['status_koordinat'] ?? '') === 'Dalam Peta PS';
    ?>
    <tr>
      <td style="text-align:center;"><?= $i + 1 ?></td>
      <td style="font-family:monospace;"><?= e($r['nik']) ?></td>
      <td><b><?= e($r['nama']) ?></b></td>
      <td style="text-align:right;"><?= number_format((float)($r['luas_lahan'] ?? 0), 2, ',', '.') ?></td>
      <td style="text-align:center;" class="<?= $okSK ? 'status-ok' : 'status-err' ?>">
        <?= $okSK ? '✓ Sesuai SK' : '✗ Belum Sesuai' ?>
      </td>
      <td style="text-align:center;" class="<?= $okPeta ? 'status-ok' : 'status-err' ?>">
        <?= $okPeta ? '✓ Dalam Peta' : '⚠ Luar Peta' ?>
      </td>
      <td style="font-size:8.5px;color:#374151;"><?= e($r['catatan'] ?: '-') ?></td>
    </tr>
    <?php endforeach; endif; ?>
  </tbody>
</table>

<div class="ttd-grid">
  <div class="ttd-box">
    <div>Mengetahui,</div>
    <div><b>Ketua Kelompok Tani Hutan</b></div>
    <div class="ttd-space"></div>
    <div>( <b><?= e($kth['nama_kth']) ?></b> )</div>
  </div>

  <div class="ttd-box">
    <div>Bojonegoro, <?= e($tglCetakIndo) ?></div>
    <div><b>Tim Verifikator CDK Bojonegoro</b></div>
    <?php if ($verifikator['jabatan'] !== ''): ?>
    <div><?= e($verifikator['jabatan']) ?></div>
    <?php endif; ?>
    <div class="ttd-space"></div>
    <?php if ($verifikator['nama'] !== ''): ?>
    <div>( <b><u><?= e($verifikator['nama']) ?></u></b> )</div>
    <?php if ($verifikator['nip'] !== ''): ?>
    <div>NIP. <?= e($verifikator['nip']) ?></div>
    <?php endif; ?>
    <?php else: ?>
    <div>( ..................................................... )</div>
    <?php endif; ?>
  </div>
</div>

<!-- ═══ TAMBAHAN: FORMAT DINAS HASIL VERIFIKASI DATA (cerminan Excel) ═══ -->
<div style="page-break-before:always;"></div>
<div class="judul-dok" style="margin-top:6px;">HASIL VERIFIKASI DATA</div>
<div class="judul-dok" style="margin-top:0;">USULAN PUPUK BERSUBSIDI BAGI PETANI HUTAN</div>
<div class="subjudul-dok">KTH <?= e($kth['nama_kth']) ?> · <?= e($verInfo['label_versi'] ?: ('Versi ' . $versiAktif)) ?> · Sama persis dengan unduhan "Excel Verifikasi Dinas"</div>

<table class="data-table" style="font-size:10px;">
  <thead>
    <tr>
      <th width="4%" rowspan="2" style="vertical-align:middle;">No</th>
      <th width="16%" rowspan="2" style="vertical-align:middle;">Indikator</th>
      <th colspan="2">Kategori</th>
      <th width="46%" rowspan="2" style="vertical-align:middle;">Catatan</th>
    </tr>
    <tr>
      <th colspan="2" style="font-size:8px;font-weight:400;">(tanda ✓ pada kolom yang sesuai)</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td rowspan="2" style="text-align:center;vertical-align:middle;"><b>1</b></td>
      <td rowspan="2" style="text-align:center;vertical-align:middle;">Legalitas Kelompok</td>
      <td style="text-align:center;"><b>Sudah ada SK PS</b></td>
      <td style="text-align:center;"><b>Belum ada SK PS</b></td>
      <td rowspan="2" style="text-align:justify;"><?= e($catLegalDinas) ?></td>
    </tr>
    <tr>
      <td style="text-align:center;font-size:16px;font-weight:800;"><?= $adaSkDinas ? '✓' : '' ?></td>
      <td style="text-align:center;font-size:16px;font-weight:800;"><?= $adaSkDinas ? '' : '✓' ?></td>
    </tr>
    <tr>
      <td rowspan="2" style="text-align:center;vertical-align:middle;"><b>2</b></td>
      <td rowspan="2" style="text-align:center;vertical-align:middle;">Daftar Pesanggem</td>
      <td style="text-align:center;"><b>Sesuai SK PS</b></td>
      <td style="text-align:center;"><b>Belum Sesuai SK PS</b></td>
      <td rowspan="2" style="text-align:justify;"><?= e($narasiDinas) ?></td>
    </tr>
    <tr>
      <td style="text-align:center;"><b><?= $hitung['sesuai'] ?> petani</b></td>
      <td style="text-align:center;"><b><?= $hitung['tidak'] ?> petani</b></td>
    </tr>
    <tr>
      <td colspan="2" style="vertical-align:middle;">Rekomendasi :</td>
      <td style="text-align:center;"><b>Revisi</b></td>
      <td style="text-align:center;"><b>Ditindaklanjuti</b></td>
      <td rowspan="2" style="text-align:justify;vertical-align:middle;"><?= e($rekomCatatanDinas) ?></td>
    </tr>
    <tr>
      <td colspan="2"></td>
      <td style="text-align:center;font-size:16px;font-weight:800;"><?= !$isTindakDinas ? '✓' : '' ?></td>
      <td style="text-align:center;font-size:16px;font-weight:800;"><?= $isTindakDinas ? '✓' : '' ?></td>
    </tr>
  </tbody>
</table>

<div class="ttd-grid" style="font-size:10.5px;">
  <div style="width:32%;text-align:left;">
    <div>Mengetahui,</div>
    <div>Kepala Seksi RLPM</div>
    <div class="ttd-space" style="height:60px;"></div>
    <div><?= e($ttdDinas['kasi_nama']) ?></div>
    <div>NIP. <?= e($ttdDinas['kasi_nip']) ?></div>
  </div>
  <div style="width:34%;text-align:left;">
    <div>Penyuluh Kehutanan</div>
    <div>Wilayah Binaan</div>
    <div class="ttd-space" style="height:60px;"></div>
    <div><?= e($ttdDinas['penyuluh_nama']) ?></div>
    <div>NIP. <?= e($ttdDinas['penyuluh_nip']) ?></div>
  </div>
  <div style="width:30%;text-align:left;">
    <div>Verifikator</div>
    <div>&nbsp;</div>
    <div class="ttd-space" style="height:60px;"></div>
    <div><?= e($ttdDinas['verifikator_nama']) ?></div>
    <div>NIP. <?= e($ttdDinas['verifikator_nip']) ?></div>
  </div>
</div>
<div class="no-print" style="margin-top:16px;background:#0E5E38;">
  <div style="font-size:11px;">Format Dinas di atas otomatis mengikuti <b>Excel Verifikasi Dinas</b> — nama penandatangan dapat diubah di Pengaturan.</div>
  <a href="pengaturan.php?kembali=<?= urlencode('cetak.php?kth_id=' . $kthId . '&v=' . $versiAktif) ?>" class="btn-print" style="text-decoration:none;">✏️ Ubah Penandatangan</a>
</div>

</body>
</html>
