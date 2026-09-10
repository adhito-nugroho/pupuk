<?php
// cetak.php — Lembar Cetak Resmi Hasil Verifikasi Administrasi & Spasial
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/helpers.php';

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

$hitung = ['total' => count($rows), 'sesuai' => 0, 'tidak' => 0, 'dalam' => 0, 'luar' => 0, 'luas' => 0.0];
foreach ($rows as $r) {
    if (($r['status_sk'] ?? '') === 'Sesuai SK PS') $hitung['sesuai']++; else $hitung['tidak']++;
    if (($r['status_koordinat'] ?? '') === 'Dalam Peta PS') $hitung['dalam']++; else $hitung['luar']++;
    $hitung['luas'] += (float)($r['luas_lahan'] ?? 0);
}

$rekom = ($hitung['tidak'] === 0 && $hitung['luar'] === 0 && $hitung['total'] > 0) ? 'DAPAT DITINDAKLANJUTI' : 'PERLU REVISI';
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
  <div>
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
    <td><?= date('d M Y', strtotime($verInfo['dibuat_pada'] ?? 'now')) ?></td>
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
        <?= $rekom ?>
      </b>
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
    <div>Bojonegoro, <?= date('d F Y') ?></div>
    <div><b>Tim Verifikator CDK Bojonegoro</b></div>
    <div class="ttd-space"></div>
    <div>( ..................................................... )</div>
  </div>
</div>

</body>
</html>
