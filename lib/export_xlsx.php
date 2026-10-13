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

/**
 * Format Dinas "HASIL VERIFIKASI DATA USULAN PUPUK BERSUBSIDI BAGI PETANI HUTAN"
 * persis contoh Excel: No | Indikator | Kategori (2 sub-kolom) | Catatan,
 * baris Legalitas Kelompok, Daftar Pesanggem, Rekomendasi, + 3 penandatangan.
 */
function build_verifikasi_dinas_spreadsheet(PDO $pdo, int $kthId, int $versiKe = 0): array {
    $kth = $pdo->prepare('SELECT * FROM kth WHERE id = ?');
    $kth->execute([$kthId]);
    $k = $kth->fetch();
    if (!$k) throw new RuntimeException('Data KTH tidak ditemukan.');

    if ($versiKe <= 0) {
        $versiKe = (int)($k['versi_aktif'] ?? 1);
        if ($versiKe <= 0) $versiKe = 1;
    }

    // --- Hitung live ---
    $h = $pdo->prepare('SELECT COUNT(*) total,
        SUM(status_sk = "Sesuai SK PS") sesuai,
        SUM(status_sk != "Sesuai SK PS") tidak,
        SUM(status_koordinat = "Dalam Peta PS") dalam,
        SUM(status_koordinat != "Dalam Peta PS") luar
        FROM hasil_verifikasi WHERE kth_id = ? AND versi_ke = ?');
    $h->execute([$kthId, $versiKe]);
    $live = $h->fetch() ?: ['total' => 0, 'sesuai' => 0, 'tidak' => 0, 'dalam' => 0, 'luar' => 0];
    $total = (int)($live['total'] ?? 0);
    $sesuai = (int)($live['sesuai'] ?? 0);
    $tidak = (int)($live['tidak'] ?? 0);
    $dalam = (int)($live['dalam'] ?? 0);
    $luar = (int)($live['luar'] ?? 0);

    $qLuas = $pdo->prepare('SELECT SUM(luas_lahan) total_luas, COUNT(CASE WHEN luas_lahan > 2.0 THEN 1 END) lebih_2ha FROM usulan_pupuk WHERE kth_id = ? AND versi_ke = ?');
    $qLuas->execute([$kthId, $versiKe]);
    $rowLuas = $qLuas->fetch() ?: [];
    $totalLuas = (float)($rowLuas['total_luas'] ?? 0.0);
    $lebih2ha = (int)($rowLuas['lebih_2ha'] ?? 0);
    $luasSk = !empty($k['luas_areal']) ? (float)$k['luas_areal'] : 0.0;
    $pctLuas = $luasSk > 0 ? round(($totalLuas / $luasSk) * 100, 2) : 0;

    // --- Laporan tersimpan (narasi + rekomendasi) ---
    $lap = [];
    try {
        $cekV = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'laporan' AND COLUMN_NAME = 'versi_ke'");
        $cekV->execute();
        if ((int)$cekV->fetchColumn() > 0) {
            $stV = $pdo->prepare('SELECT * FROM laporan WHERE kth_id = ? AND versi_ke = ? ORDER BY id DESC LIMIT 1');
            $stV->execute([$kthId, $versiKe]);
            $lap = $stV->fetch() ?: [];
        }
    } catch (Throwable $eV) { $lap = []; }
    if (empty($lap)) {
        try {
            $st = $pdo->prepare('SELECT * FROM laporan WHERE kth_id = ? ORDER BY id DESC LIMIT 1');
            $st->execute([$kthId]);
            $lap = $st->fetch() ?: [];
        } catch (Throwable $eL) { $lap = []; }
    }
    $tahun = (string)($lap['tahun'] ?? $k['tahun_usulan'] ?? date('Y'));

    if (!empty($lap['narasi'])) {
        $narasi = (string)$lap['narasi'];
    } else {
        if (!function_exists('buat_narasi_default')) require_once __DIR__ . '/verify.php';
        $narasi = buat_narasi_default($k, (int)$tahun ?: (int)date('Y'), [
            'total' => $total, 'sesuai' => $sesuai, 'tidak' => $tidak,
            'dalam' => $dalam, 'luar' => $luar,
            'lebih_luas' => $lebih2ha, 'total_luas' => $totalLuas,
        ]);
    }
    $rekomRaw = trim((string)($lap['rekomendasi'] ?? ''));
    if ($rekomRaw === '') {
        $rekomRaw = ($tidak === 0 && $luar === 0 && $lebih2ha === 0 && $total > 0) ? 'Dapat Ditindaklanjuti' : 'Perlu Revisi';
    }
    $isTindak = (stripos($rekomRaw, 'tindak') !== false);
    $rekomCatatan = $isTindak ? 'Dapat direkomendasikan.' : 'Perlu revisi / verifikasi lanjutan sebelum direkomendasikan.';

    // --- Lokasi desa/kecamatan ---
    $namaDesa = ''; $namaKec = '';
    try {
        $qLoc = $pdo->prepare('SELECT desa, kecamatan FROM usulan_pupuk WHERE kth_id = ? AND versi_ke = ? AND (desa IS NOT NULL AND desa != "") LIMIT 1');
        $qLoc->execute([$kthId, $versiKe]);
        $loc = $qLoc->fetch() ?: [];
        $namaDesa = (string)($loc['desa'] ?? $k['desa'] ?? '');
        $namaKec  = (string)($loc['kecamatan'] ?? $k['kecamatan'] ?? '');
    } catch (Throwable $eL) {}
    $title = function (string $s): string {
        $s = trim(mb_strtolower($s, 'UTF-8'));
        return $s === '' ? '' : mb_convert_case($s, MB_CASE_TITLE, 'UTF-8');
    };
    $desaT = $title($namaDesa); $kecT = $title($namaKec);

    // --- Catatan legalitas ---
    $nomorSk = trim((string)($k['nomor_sk'] ?? ''));
    $namaKth = trim((string)($k['nama_kth'] ?? ''));
    $adaSk = ($nomorSk !== '' && strtolower($nomorSk) !== '-');
    if ($adaSk) {
        $luasInt = (int)round($luasSk);
        $terbilang = $luasInt > 0 ? ' (' . terbilang_id($luasInt) . ')' : '';
        $luasTxt = $luasSk > 0 ? 'Seluas ± ' . number_format($luasSk, 0, ',', '.') . $terbilang . ' Hektare ' : '';
        $wilTxt = trim(($desaT !== '' ? 'Di Desa ' . $desaT . ' ' : '') . ($kecT !== '' ? 'Kecamatan ' . $kecT . ' ' : ''));
        $catLegal = 'Sudah terdapat legalitas SK Menlhk Nomor SK: ' . $nomorSk
            . ' tentang Pemberian Persetujuan Pengelolaan Hutan Kemasyarakatan Kepada ' . $namaKth
            . ' ' . $luasTxt . 'Berada Dalam Kawasan Hutan Produksi Tetap Pada Kawasan Hutan Dengan Pengelolaan Khusus '
            . ($wilTxt !== '' ? $wilTxt . ' ' : '') . 'Kabupaten Bojonegoro Provinsi Jawa Timur.';
        $catLegal = trim(preg_replace('/\s+/', ' ', $catLegal));
    } else {
        $catLegal = 'Belum terdapat legalitas SK Perhutanan Sosial (SK PS belum tercatat) untuk ' . $namaKth . '.';
    }

    $ttd = pengaturan_penandatangan_dinas($pdo);

    $ss = new Spreadsheet();
    $ws = $ss->getActiveSheet();
    $ws->setTitle('Hasil Verifikasi');
    $ws->getDefaultRowDimension()->setRowHeight(18);
    $ws->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
    $ws->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4);
    $ws->getPageSetup()->setFitToWidth(1);
    $ws->getPageSetup()->setFitToHeight(1);
    $ws->getPageMargins()->setLeft(0.35); $ws->getPageMargins()->setRight(0.35);
    $ws->getPageMargins()->setTop(0.4); $ws->getPageMargins()->setBottom(0.4);

    $thin = ['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF000000']]]];
    $center = ['alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true]];
    $centerTop = ['alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true]];
    $leftWrap = ['alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true]];
    $leftMid = ['alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true]];

    // Judul
    $ws->mergeCells('A1:E1');
    $ws->setCellValue('A1', 'HASIL VERIFIKASI DATA');
    $ws->getStyle('A1')->getFont()->setBold(true)->setSize(13);
    $ws->getStyle('A1')->applyFromArray($center);
    $ws->mergeCells('A2:E2');
    $ws->setCellValue('A2', 'USULAN PUPUK BERSUBSIDI BAGI PETANI HUTAN');
    $ws->getStyle('A2')->getFont()->setBold(true)->setSize(13);
    $ws->getStyle('A2')->applyFromArray($center);
    $ws->getRowDimension(1)->setRowHeight(22);
    $ws->getRowDimension(2)->setRowHeight(22);
    $ws->getRowDimension(3)->setRowHeight(12);
    $ws->getRowDimension(4)->setRowHeight(12);

    // Header tabel
    $ws->setCellValue('A5', 'No'); $ws->setCellValue('B5', 'Indikator');
    $ws->mergeCells('C5:D5'); $ws->setCellValue('C5', 'Kategori');
    $ws->setCellValue('E5', 'Catatan');
    $ws->getStyle('A5:E5')->getFont()->setBold(true)->setSize(11);
    $ws->getStyle('A5:E5')->applyFromArray($center);
    $ws->getRowDimension(5)->setRowHeight(22);

    // Blok 1 — Legalitas Kelompok
    $ws->mergeCells('A6:A7'); $ws->setCellValue('A6', '1');
    $ws->mergeCells('B6:B7'); $ws->setCellValue('B6', 'Legalitas Kelompok');
    $ws->setCellValue('C6', 'Sudah ada SK PS'); $ws->setCellValue('D6', 'Belum ada SK PS');
    $ws->setCellValue('C7', $adaSk ? "✓" : ''); $ws->setCellValue('D7', $adaSk ? '' : "✓");
    $ws->mergeCells('E6:E7'); $ws->setCellValue('E6', $catLegal);
    $ws->getStyle('A6:B7')->applyFromArray($center);
    $ws->getStyle('C6:D6')->getFont()->setBold(true)->setSize(10);
    $ws->getStyle('C6:D7')->applyFromArray($center);
    $ws->getStyle('C7:D7')->getFont()->setBold(true)->setSize(18);
    $ws->getStyle('E6')->applyFromArray($leftWrap);
    $ws->getRowDimension(6)->setRowHeight(20);
    $ws->getRowDimension(7)->setRowHeight(105);

    // Blok 2 — Daftar Pesanggem
    $ws->mergeCells('A8:A9'); $ws->setCellValue('A8', '2');
    $ws->mergeCells('B8:B9'); $ws->setCellValue('B8', 'Daftar Pesanggem');
    $ws->setCellValue('C8', 'Sesuai SK PS'); $ws->setCellValue('D8', 'Belum Sesuai SK PS');
    $ws->setCellValue('C9', $sesuai . ' petani'); $ws->setCellValue('D9', $tidak . ' petani');
    $ws->mergeCells('E8:E9'); $ws->setCellValue('E8', $narasi);
    $ws->getStyle('A8:B9')->applyFromArray($center);
    $ws->getStyle('C8:D8')->getFont()->setBold(true)->setSize(10);
    $ws->getStyle('C8:D9')->applyFromArray($center);
    $ws->getStyle('C9:D9')->getFont()->setBold(true)->setSize(11);
    $ws->getStyle('E8')->applyFromArray($leftWrap);
    $ws->getRowDimension(8)->setRowHeight(20);
    $ws->getRowDimension(9)->setRowHeight(150);

    // Blok 3 — Rekomendasi
    $ws->mergeCells('A10:B11'); $ws->setCellValue('A10', 'Rekomendasi :');
    $ws->setCellValue('C10', 'Revisi'); $ws->setCellValue('D10', 'Ditindaklanjuti');
    $ws->setCellValue('C11', !$isTindak ? "✓" : ''); $ws->setCellValue('D11', $isTindak ? "✓" : '');
    $ws->mergeCells('E10:E11'); $ws->setCellValue('E10', $rekomCatatan);
    $ws->getStyle('A10')->applyFromArray($leftMid);
    $ws->getStyle('C10:D10')->getFont()->setBold(true)->setSize(10);
    $ws->getStyle('C10:D11')->applyFromArray($center);
    $ws->getStyle('C11:D11')->getFont()->setBold(true)->setSize(18);
    $ws->getStyle('E10')->applyFromArray($leftWrap);
    $ws->getRowDimension(10)->setRowHeight(20);
    $ws->getRowDimension(11)->setRowHeight(45);

    $ws->getStyle('A5:E11')->applyFromArray($thin);
    $ws->getStyle('A5:E11')->getFont()->getColor()->setARGB('FF000000');

    // TTD — 3 kolom: A:B kiri, C:D tengah, E kanan
    $r = 13;
    $ws->mergeCells("A$r:B$r"); $ws->setCellValue("A$r", 'Mengetahui,');
    $ws->mergeCells("C$r:D$r"); $ws->setCellValue("C$r", 'Penyuluh Kehutanan');
    $ws->setCellValue("E$r", 'Verifikator');
    $ws->getStyle("A$r:B$r")->applyFromArray($leftMid);
    $ws->getStyle("C$r:D$r")->applyFromArray($leftMid);
    $ws->getStyle("E$r")->applyFromArray($leftMid);
    $r++;
    $ws->mergeCells("A$r:B$r"); $ws->setCellValue("A$r", 'Kepala Seksi RLPM');
    $ws->mergeCells("C$r:D$r"); $ws->setCellValue("C$r", 'Wilayah Binaan');
    $ws->setCellValue("E$r", '');
    $ws->getStyle("A$r:B$r")->applyFromArray($leftMid);
    $ws->getStyle("C$r:D$r")->applyFromArray($leftMid);
    $r += 4; // ruang tanda tangan
    $ws->mergeCells("A$r:B$r"); $ws->setCellValue("A$r", $ttd['kasi_nama']);
    $ws->mergeCells("C$r:D$r"); $ws->setCellValue("C$r", $ttd['penyuluh_nama']);
    $ws->setCellValue("E$r", $ttd['verifikator_nama']);
    $ws->getStyle("A$r:E$r")->applyFromArray($leftMid);
    $r++;
    $ws->mergeCells("A$r:B$r"); $ws->setCellValue("A$r", 'NIP. ' . $ttd['kasi_nip']);
    $ws->mergeCells("C$r:D$r"); $ws->setCellValue("C$r", 'NIP. ' . $ttd['penyuluh_nip']);
    $ws->setCellValue("E$r", 'NIP. ' . $ttd['verifikator_nip']);
    $ws->getStyle("A$r:E$r")->applyFromArray($leftMid);

    $ws->getColumnDimension('A')->setWidth(7);
    $ws->getColumnDimension('B')->setWidth(23);
    $ws->getColumnDimension('C')->setWidth(20);
    $ws->getColumnDimension('D')->setWidth(22);
    $ws->getColumnDimension('E')->setWidth(85);
    $ws->getSheetView()->setZoomScale(85);
    $ws->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(5, 5);

    $fname = 'Hasil_Verifikasi_Dinas_' . preg_replace('/[^\w\-]+/', '_', (string)$k['nama_kth']) . '_v' . $versiKe . '.xlsx';
    return [$ss, $fname];
}

function export_verifikasi_dinas_excel(PDO $pdo, int $kthId, int $versiKe = 0): void {
    [$ss, $fname] = build_verifikasi_dinas_spreadsheet($pdo, $kthId, $versiKe);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $fname . '"');
    header('Cache-Control: max-age=0');
    (new Xlsx($ss))->save('php://output');
    exit;
}
