<?php
// Export laporan akhir ke Excel (PhpSpreadsheet).
require_once __DIR__ . '/helpers.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

function build_laporan_spreadsheet(PDO $pdo, int $kthId, ?array $laporan = null, int $versiKe = 0): array {
    $kth = $pdo->prepare('SELECT * FROM kth WHERE id = ?');
    $kth->execute([$kthId]);
    $k = $kth->fetch();
    if (!$k) throw new RuntimeException('Data KTH tidak ditemukan.');

    if ($versiKe <= 0) {
        $versiKe = (int)($k['versi_aktif'] ?? 1);
        if ($versiKe <= 0) $versiKe = 1;
    }

    if ($laporan === null) {
        $laporan = [];
        // Utamakan laporan versi yang diminta; fallback ke terakhir (data lama)
        try {
            $cekV = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'laporan' AND COLUMN_NAME = 'versi_ke'");
            $cekV->execute();
            if ((int)$cekV->fetchColumn() > 0) {
                $stV = $pdo->prepare('SELECT * FROM laporan WHERE kth_id = ? AND versi_ke = ? ORDER BY id DESC LIMIT 1');
                $stV->execute([$kthId, $versiKe]);
                $laporan = $stV->fetch() ?: [];
            }
        } catch (Throwable $eV) { $laporan = []; }
        if (empty($laporan)) {
            $st = $pdo->prepare('SELECT * FROM laporan WHERE kth_id = ? ORDER BY id DESC LIMIT 1');
            $st->execute([$kthId]);
            $laporan = $st->fetch() ?: [];
        }
    }

    $q = $pdo->prepare('SELECT u.no_urut, u.nama, u.nik, u.luas_lahan, h.status_sk, h.status_koordinat, h.catatan
        FROM usulan_pupuk u LEFT JOIN hasil_verifikasi h ON (h.usulan_id = u.id AND h.versi_ke = u.versi_ke)
        WHERE u.kth_id = ? AND u.versi_ke = ? ORDER BY COALESCE(u.no_urut, u.id)');
    $q->execute([$kthId, $versiKe]);
    $rows = $q->fetchAll();

    $qLuas = $pdo->prepare('SELECT SUM(luas_lahan) AS total_luas, COUNT(CASE WHEN luas_lahan > 2.0 THEN 1 END) AS lebih_2ha FROM usulan_pupuk WHERE kth_id = ? AND versi_ke = ?');
    $qLuas->execute([$kthId, $versiKe]);
    $rowLuas = $qLuas->fetch() ?: [];
    $totalLuasUsulan = (float)($rowLuas['total_luas'] ?? 0.0);
    $lebih2haCount = (int)($rowLuas['lebih_2ha'] ?? 0);
    $luasSk = !empty($k['luas_areal']) ? (float)$k['luas_areal'] : 0.0;
    $pctLuasSk = $luasSk > 0 ? round(($totalLuasUsulan / $luasSk) * 100, 2) : 0;

    $ss = new Spreadsheet();
    $ws = $ss->getActiveSheet();
    $ws->setTitle('Hasil Verifikasi');
    $ws->getDefaultRowDimension()->setRowHeight(18);

    $thin = ['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]];
    $center = ['alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true]];
    $wrapLeft = ['alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true]];
    $right = ['alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true]];

    // Judul
    $ws->mergeCells('A1:G1');
    $ws->setCellValue('A1', 'HASIL VERIFIKASI DATA USULAN PUPUK BERSUBSIDI BAGI PETANI HUTAN');
    $ws->getStyle('A1')->getFont()->setBold(true)->setSize(13);
    $ws->getStyle('A1')->applyFromArray($center);
    $ws->getRowDimension(1)->setRowHeight(26);

    $ws->mergeCells('A2:G2');
    $ws->setCellValue('A2', 'KTH/LMDH: ' . $k['nama_kth'] . '   |   SK: ' . ($k['nomor_sk'] ?: '-') . '   |   Luas SK: ' . ($luasSk > 0 ? number_format($luasSk, 2, ',', '.') . ' Ha' : '-') . '   |   Tahun: ' . ($laporan['tahun'] ?? $k['tahun_usulan'] ?? '-'));
    $ws->getStyle('A2')->getFont()->setSize(11);
    $ws->getStyle('A2')->applyFromArray($center);
    $ws->getRowDimension(2)->setRowHeight(22);

    // Ringkasan indikator (gaya tabel indikator–kategori–jumlah–catatan)
    $r = 4;
    $ws->setCellValue("A$r", 'INDIKATOR'); $ws->setCellValue("B$r", 'KATEGORI'); $ws->setCellValue("C$r", 'JUMLAH / HASIL');
    $ws->mergeCells("C$r:D$r"); $ws->setCellValue("E$r", 'KETERANGAN'); $ws->mergeCells("E$r:G$r");
    $ws->getStyle("A$r:G$r")->getFont()->setBold(true);
    $ws->getStyle("A$r:G$r")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFD9EAD3');
    $ws->getStyle("A$r:G$r")->applyFromArray($center);
    $r++;
    $indikator = [
        ['Kesesuaian SK', 'Sesuai SK PS', (int)($laporan['jumlah_sesuai_sk'] ?? 0), 'Nama & NIK cocok dengan SK'],
        ['Kesesuaian SK', 'Belum Sesuai SK PS', (int)($laporan['jumlah_tidak_sesuai_sk'] ?? 0), 'Perlu verifikasi / BA pendukung'],
        ['Posisi Koordinat', 'Dalam Peta PS', (int)($laporan['jumlah_dalam_peta'] ?? 0), 'Titik berada dalam poligon izin'],
        ['Posisi Koordinat', 'Luar Peta PS', (int)($laporan['jumlah_luar_peta'] ?? 0), 'Titik di luar poligon izin'],
        ['Luas Usulan vs SK', 'Total Luas Usulan', number_format($totalLuasUsulan, 2, ',', '.') . ' Ha', ($luasSk > 0 ? number_format($pctLuasSk, 2, ',', '.') . '% dari luas SK PS (' . number_format($luasSk, 2, ',', '.') . ' Ha)' : 'Luas SK belum dicatat')],
        ['Batas Luas per Orang', 'Maksimal 2.0 Ha / Orang', ($lebih2haCount > 0 ? $lebih2haCount . ' petani > 2 Ha' : 'Seluruhnya <= 2 Ha'), ($lebih2haCount > 0 ? 'Melebihi batas maksimal 2 Ha' : 'Memenuhi ketentuan maksimal 2 Ha')],
        ['Total Pemohon', 'Petani Pengusul', (int)($laporan['total_petani'] ?? count($rows)), 'Data elektronik e-RDKK'],
    ];
    $startInd = $r;
    foreach ($indikator as $ind) {
        $ws->setCellValue("A$r", $ind[0]);
        $ws->setCellValue("B$r", $ind[1]);
        $ws->mergeCells("C$r:D$r"); $ws->setCellValue("C$r", $ind[2]);
        $ws->mergeCells("E$r:G$r"); $ws->setCellValue("E$r", $ind[3]);
        $ws->getStyle("A$r:B$r")->applyFromArray($center);
        $ws->getStyle("C$r")->applyFromArray($center);
        $ws->getStyle("E$r")->applyFromArray($wrapLeft);
        $r++;
    }
    $ws->getStyle("A$startInd:G" . ($r - 1))->applyFromArray($thin);

    // Narasi
    $r++;
    $ws->mergeCells("A$r:G$r");
    $ws->setCellValue("A$r", 'NARASI HASIL VERIFIKASI');
    $ws->getStyle("A$r")->getFont()->setBold(true);
    $r++;
    $ws->mergeCells("A$r:G" . ($r + 2));
    $ws->setCellValue("A$r", (string)($laporan['narasi'] ?? ''));
    $ws->getStyle("A$r")->applyFromArray($wrapLeft);
    $ws->getRowDimension($r)->setRowHeight(30);
    $ws->getRowDimension($r + 1)->setRowHeight(30);
    $r += 4;

    // Tabel hasil per baris
    $ws->setCellValue("A$r", 'No'); $ws->setCellValue("B$r", 'Nama'); $ws->setCellValue("C$r", 'NIK');
    $ws->setCellValue("D$r", 'Luas (Ha)'); $ws->setCellValue("E$r", 'Status SK'); $ws->setCellValue("F$r", 'Status Koordinat'); $ws->setCellValue("G$r", 'Catatan');
    $ws->getStyle("A$r:G$r")->getFont()->setBold(true);
    $ws->getStyle("A$r:G$r")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFF2CC');
    $ws->getStyle("A$r:G$r")->applyFromArray($center);
    $headRow = $r; $r++;
    $no = 1;
    foreach ($rows as $row) {
        $rLuas = $row['luas_lahan'] !== null ? (float)$row['luas_lahan'] : null;
        $ws->setCellValue("A$r", $no++);
        $ws->setCellValue("B$r", $row['nama']);
        $ws->setCellValueExplicit("C$r", (string)$row['nik'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $ws->setCellValue("D$r", $rLuas !== null ? $rLuas : '');
        $ws->setCellValue("E$r", $row['status_sk']);
        $ws->setCellValue("F$r", $row['status_koordinat']);
        $ws->setCellValue("G$r", $row['catatan']);
        $ws->getStyle("A$r:C$r")->applyFromArray($center);
        $ws->getStyle("D$r")->applyFromArray($right);
        if ($rLuas !== null) {
            $ws->getStyle("D$r")->getNumberFormat()->setFormatCode('#,##0.00');
            if ($rLuas > 2.0) {
                $ws->getStyle("D$r")->getFont()->getColor()->setARGB('FF9E2A2B');
                $ws->getStyle("D$r")->getFont()->setBold(true);
            }
        }
        $ws->getStyle("E$r:F$r")->applyFromArray($center);
        $ws->getStyle("G$r")->applyFromArray($wrapLeft);
        $ws->getRowDimension($r)->setRowHeight(20);
        $r++;
    }
    $ws->getStyle("A$headRow:G" . ($r - 1))->applyFromArray($thin);

    // Rekomendasi
    $r++;
    $ws->mergeCells("A$r:G$r");
    $ws->setCellValue("A$r", 'REKOMENDASI: ' . mb_strtoupper((string)($laporan['rekomendasi'] ?? '-'), 'UTF-8'));
    $ws->getStyle("A$r")->getFont()->setBold(true)->setSize(12);
    $ws->getStyle("A$r")->applyFromArray($center);
    $ws->getRowDimension($r)->setRowHeight(24);

    foreach (['A' => 6, 'B' => 26, 'C' => 22, 'D' => 14, 'E' => 20, 'F' => 20, 'G' => 60] as $col => $w) {
        $ws->getColumnDimension($col)->setWidth($w);
    }

    $fname = 'Hasil_Verifikasi_' . preg_replace('/[^\w\-]+/', '_', (string)$k['nama_kth']) . '.xlsx';
    return [$ss, $fname];
}

function export_laporan_excel(PDO $pdo, int $kthId, ?array $laporan = null, int $versiKe = 0): void {
    [$ss, $fname] = build_laporan_spreadsheet($pdo, $kthId, $laporan, $versiKe);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $fname . '"');
    header('Cache-Control: max-age=0');
    (new Xlsx($ss))->save('php://output');
    exit;
}

/**
 * Lembar Hasil Verifikasi versi Excel — cerminan cetak.php
 * (kop, identitas, rekap, tabel detail, status akhir, kolom TTD verifikator).
 */
function build_lembar_hasil_spreadsheet(PDO $pdo, int $kthId, int $versiKe = 0): array {
    $kth = $pdo->prepare('SELECT * FROM kth WHERE id = ?');
    $kth->execute([$kthId]);
    $k = $kth->fetch();
    if (!$k) throw new RuntimeException('Data KTH tidak ditemukan.');

    if ($versiKe <= 0) {
        $versiKe = (int)($k['versi_aktif'] ?? 1);
        if ($versiKe <= 0) $versiKe = 1;
    }

    $stVer = $pdo->prepare('SELECT * FROM kth_versi_usulan WHERE kth_id = ? AND versi_ke = ?');
    $stVer->execute([$kthId, $versiKe]);
    $verInfo = $stVer->fetch() ?: ['label_versi' => 'Usulan (v' . $versiKe . ')', 'dibuat_pada' => ($k['dibuat_pada'] ?? 'now')];

    $stRows = $pdo->prepare('
        SELECT u.*, h.status_sk, h.status_koordinat, h.catatan
        FROM usulan_pupuk u
        LEFT JOIN hasil_verifikasi h ON (h.usulan_id = u.id AND h.versi_ke = u.versi_ke)
        WHERE u.kth_id = ? AND u.versi_ke = ?
        ORDER BY COALESCE(u.no_urut, u.id)');
    $stRows->execute([$kthId, $versiKe]);
    $rows = $stRows->fetchAll();

    $hitung = ['total' => count($rows), 'sesuai' => 0, 'tidak' => 0, 'dalam' => 0, 'luar' => 0, 'luas' => 0.0, 'lebih' => 0];
    foreach ($rows as $r) {
        if (($r['status_sk'] ?? '') === 'Sesuai SK PS') $hitung['sesuai']++; else $hitung['tidak']++;
        if (($r['status_koordinat'] ?? '') === 'Dalam Peta PS') $hitung['dalam']++; else $hitung['luar']++;
        $hitung['luas'] += (float)($r['luas_lahan'] ?? 0);
        if ((float)($r['luas_lahan'] ?? 0) > 2.0) $hitung['lebih']++;
    }
    $rekomAuto = ($hitung['tidak'] === 0 && $hitung['luar'] === 0 && $hitung['lebih'] === 0 && $hitung['total'] > 0) ? 'DAPAT DITINDAKLANJUTI' : 'PERLU REVISI';
    // Status Akhir mengikuti Kesimpulan Verifikasi (laporan.rekomendasi) bila sudah disimpan.
    $rekom = $rekomAuto;
    try {
        $cekLap = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'laporan' AND COLUMN_NAME = 'versi_ke'");
        $cekLap->execute();
        if ((int)$cekLap->fetchColumn() > 0) {
            $stLap = $pdo->prepare('SELECT rekomendasi FROM laporan WHERE kth_id = ? AND versi_ke = ? AND rekomendasi IS NOT NULL AND rekomendasi != "" ORDER BY id DESC LIMIT 1');
            $stLap->execute([$kthId, $versiKe]);
        } else {
            $stLap = $pdo->prepare('SELECT rekomendasi FROM laporan WHERE kth_id = ? AND rekomendasi IS NOT NULL AND rekomendasi != "" ORDER BY id DESC LIMIT 1');
            $stLap->execute([$kthId]);
        }
        if (($rowLap = $stLap->fetch()) && !empty($rowLap['rekomendasi'])) {
            $rekom = mb_strtoupper(trim((string)$rowLap['rekomendasi']), 'UTF-8');
        }
    } catch (Throwable $eLap) { /* tetap pakai otomatis */ }
    $verifikator = pengaturan_verifikator($pdo);
    $luasSk = !empty($k['luas_areal']) ? (float)$k['luas_areal'] : 0.0;

    $ss = new Spreadsheet();
    $ws = $ss->getActiveSheet();
    $ws->setTitle('Lembar Hasil');
    $ws->getDefaultRowDimension()->setRowHeight(18);

    $thin = ['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]];
    $center = ['alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true]];
    $wrapLeft = ['alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true]];
    $right = ['alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true]];

    // Kop instansi
    $ws->mergeCells('A1:G1');
    $ws->setCellValue('A1', 'PEMERINTAH PROVINSI JAWA TIMUR · DINAS KEHUTANAN');
    $ws->getStyle('A1')->getFont()->setBold(true)->setSize(11);
    $ws->getStyle('A1')->applyFromArray($center);
    $ws->mergeCells('A2:G2');
    $ws->setCellValue('A2', 'CABANG DINAS KEHUTANAN WILAYAH BOJONEGORO');
    $ws->getStyle('A2')->getFont()->setBold(true)->setSize(12);
    $ws->getStyle('A2')->applyFromArray($center);
    $ws->mergeCells('A3:G3');
    $ws->setCellValue('A3', 'Jl. Panglima Polim No. 19 Bojonegoro · Email: cdk.bojonegoro@jatimprov.go.id');
    $ws->getStyle('A3')->getFont()->setSize(9);
    $ws->getStyle('A3')->applyFromArray($center);

    // Judul dokumen
    $ws->mergeCells('A4:G4');
    $ws->setCellValue('A4', 'LEMBAR HASIL VERIFIKASI ALOKASI PUPUK BERSUBSIDI');
    $ws->getStyle('A4')->getFont()->setBold(true)->setSize(13);
    $ws->getStyle('A4')->applyFromArray($center);
    $ws->mergeCells('A5:G5');
    $ws->setCellValue('A5', 'Dokumen Rekonsiliasi Legalitas SK Perhutanan Sosial & Uji Spasial Titik Lahan Petani');
    $ws->getStyle('A5')->getFont()->setSize(10);
    $ws->getStyle('A5')->applyFromArray($center);

    // Identitas
    $r = 7;
    $ws->setCellValue("A$r", 'Nama Kelompok'); $ws->setCellValue("B$r", $k['nama_kth']);
    $ws->setCellValue("E$r", 'Putaran Usulan'); $ws->setCellValue("F$r", ($verInfo['label_versi'] ?? ('Versi ' . $versiKe)));
    $ws->mergeCells("B$r:D$r"); $ws->mergeCells("F$r:G$r"); $r++;
    $ws->setCellValue("A$r", 'Nomor SK PS'); $ws->setCellValue("B$r", ($k['nomor_sk'] ?: '-'));
    $ws->setCellValue("E$r", 'Tanggal Usulan'); $ws->setCellValue("F$r", tgl_indo($verInfo['dibuat_pada'] ?? 'now'));
    $ws->mergeCells("B$r:D$r"); $ws->mergeCells("F$r:G$r"); $r++;
    $ws->setCellValue("A$r", 'Luas Areal SK'); $ws->setCellValue("B$r", ($luasSk > 0 ? number_format($luasSk, 2, ',', '.') . ' Ha' : '-'));
    $ws->setCellValue("E$r", 'Total Luas Usulan'); $ws->setCellValue("F$r", number_format($hitung['luas'], 2, ',', '.') . ' Ha');
    $ws->mergeCells("B$r:D$r"); $ws->mergeCells("F$r:G$r"); $r++;
    $ws->setCellValue("A$r", 'Status Akhir'); $ws->setCellValue("B$r", $rekom);
    $ws->mergeCells("B$r:G$r");
    $ws->getStyle("A$r")->getFont()->setBold(true);
    $ws->getStyle("B$r")->getFont()->setBold(true);
    $ws->getStyle("B$r")->getFont()->getColor()->setARGB($rekom === 'DAPAT DITINDAKLANJUTI' ? 'FF15803D' : 'FFB91C1C');
    $ws->getStyle('A7:G' . $r)->applyFromArray($wrapLeft);
    $ws->getStyle('A7:G' . $r)->applyFromArray($thin);
    $r += 2;

    // Rekap
    $ws->setCellValue("A$r", 'Total Petani'); $ws->setCellValue("B$r", 'Sesuai SK');
    $ws->setCellValue("C$r", 'Belum Sesuai SK'); $ws->setCellValue("D$r", 'Dalam Peta');
    $ws->setCellValue("E$r", 'Luar Peta'); $ws->setCellValue("F$r", 'Luas > 2 Ha'); $ws->setCellValue("G$r", 'Total Luas');
    $ws->getStyle("A$r:G$r")->getFont()->setBold(true);
    $ws->getStyle("A$r:G$r")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFD9EAD3');
    $ws->getStyle("A$r:G$r")->applyFromArray($center);
    $r++;
    $ws->setCellValue("A$r", $hitung['total']);
    $ws->setCellValue("B$r", $hitung['sesuai']);
    $ws->setCellValue("C$r", $hitung['tidak']);
    $ws->setCellValue("D$r", $hitung['dalam']);
    $ws->setCellValue("E$r", $hitung['luar']);
    $ws->setCellValue("F$r", $hitung['lebih']);
    $ws->setCellValue("G$r", number_format($hitung['luas'], 2, ',', '.') . ' Ha');
    $ws->getStyle("A$r:G$r")->applyFromArray($center);
    $ws->getStyle("A" . ($r - 1) . ":G$r")->applyFromArray($thin);
    $r += 2;

    // Tabel detail
    $ws->setCellValue("A$r", 'No'); $ws->setCellValue("B$r", 'NIK'); $ws->setCellValue("C$r", 'Nama Petani');
    $ws->setCellValue("D$r", 'Luas (Ha)'); $ws->setCellValue("E$r", 'Kesesuaian SK'); $ws->setCellValue("F$r", 'Posisi Peta'); $ws->setCellValue("G$r", 'Catatan Hasil Telaah');
    $ws->getStyle("A$r:G$r")->getFont()->setBold(true);
    $ws->getStyle("A$r:G$r")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE5E7EB');
    $ws->getStyle("A$r:G$r")->applyFromArray($center);
    $headRow = $r; $r++;
    $no = 1;
    foreach ($rows as $row) {
        $okSK = ($row['status_sk'] ?? '') === 'Sesuai SK PS';
        $okPeta = ($row['status_koordinat'] ?? '') === 'Dalam Peta PS';
        $rLuas = $row['luas_lahan'] !== null ? (float)$row['luas_lahan'] : null;
        $ws->setCellValue("A$r", $no++);
        $ws->setCellValueExplicit("B$r", (string)($row['nik'] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $ws->setCellValue("C$r", (string)($row['nama'] ?? ''));
        $ws->setCellValue("D$r", $rLuas !== null ? $rLuas : '');
        $ws->setCellValue("E$r", $okSK ? 'Sesuai SK' : 'Belum Sesuai');
        $ws->setCellValue("F$r", $okPeta ? 'Dalam Peta' : 'Luar Peta');
        $ws->setCellValue("G$r", (string)($row['catatan'] ?? ''));
        $ws->getStyle("A$r:B$r")->applyFromArray($center);
        $ws->getStyle("C$r")->applyFromArray($wrapLeft);
        $ws->getStyle("D$r")->applyFromArray($right);
        if ($rLuas !== null) {
            $ws->getStyle("D$r")->getNumberFormat()->setFormatCode('#,##0.00');
            if ($rLuas > 2.0) {
                $ws->getStyle("D$r")->getFont()->getColor()->setARGB('FF9E2A2B');
                $ws->getStyle("D$r")->getFont()->setBold(true);
            }
        }
        $ws->getStyle("E$r:F$r")->applyFromArray($center);
        $ws->getStyle("G$r")->applyFromArray($wrapLeft);
        $r++;
    }
    if (!empty($rows)) {
        $ws->getStyle("A$headRow:G" . ($r - 1))->applyFromArray($thin);
    }

    // TTD
    $r += 1;
    $ws->mergeCells("A$r:C$r"); $ws->setCellValue("A$r", 'Mengetahui,');
    $ws->mergeCells("E$r:G$r"); $ws->setCellValue("E$r", 'Bojonegoro, ' . tgl_indo('now'));
    $ws->getStyle("A$r")->applyFromArray($center); $ws->getStyle("E$r")->applyFromArray($center);
    $r++;
    $ws->mergeCells("A$r:C$r"); $ws->setCellValue("A$r", 'Ketua Kelompok Tani Hutan');
    $ws->mergeCells("E$r:G$r"); $ws->setCellValue("E$r", 'Tim Verifikator CDK Bojonegoro');
    $ws->getStyle("A$r")->getFont()->setBold(true); $ws->getStyle("E$r")->getFont()->setBold(true);
    $ws->getStyle("A$r")->applyFromArray($center); $ws->getStyle("E$r")->applyFromArray($center);
    if ($verifikator['jabatan'] !== '') {
        $r++;
        $ws->mergeCells("E$r:G$r"); $ws->setCellValue("E$r", $verifikator['jabatan']);
        $ws->getStyle("E$r")->applyFromArray($center);
    }
    $r += 4;
    $ws->mergeCells("A$r:C$r"); $ws->setCellValue("A$r", '( ' . ($k['nama_kth'] ?? '') . ' )');
    $ws->getStyle("A$r")->getFont()->setBold(true); $ws->getStyle("A$r")->applyFromArray($center);
    $ws->mergeCells("E$r:G$r");
    if ($verifikator['nama'] !== '') {
        $ws->setCellValue("E$r", '( ' . $verifikator['nama'] . ' )');
        $ws->getStyle("E$r")->getFont()->setBold(true);
        if ($verifikator['nip'] !== '') {
            $r++;
            $ws->mergeCells("E$r:G$r"); $ws->setCellValue("E$r", 'NIP. ' . $verifikator['nip']);
        }
    } else {
        $ws->setCellValue("E$r", '( ..................................................... )');
    }
    $ws->getStyle("E$r")->applyFromArray($center);

    foreach (['A' => 6, 'B' => 20, 'C' => 26, 'D' => 13, 'E' => 17, 'F' => 16, 'G' => 45] as $col => $w) {
        $ws->getColumnDimension($col)->setWidth($w);
    }
    $ws->getSheetView()->setZoomScale(90);
    $ws->freezePane('A' . ($headRow + 1));

    $fname = 'Lembar_Hasil_' . preg_replace('/[^\w\-]+/', '_', (string)$k['nama_kth']) . '_v' . $versiKe . '.xlsx';
    return [$ss, $fname];
}

function export_lembar_hasil_excel(PDO $pdo, int $kthId, int $versiKe = 0): void {
    [$ss, $fname] = build_lembar_hasil_spreadsheet($pdo, $kthId, $versiKe);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $fname . '"');
    header('Cache-Control: max-age=0');
    (new Xlsx($ss))->save('php://output');
    exit;
}
