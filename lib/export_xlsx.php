<?php
// Export laporan akhir ke Excel (PhpSpreadsheet).
require_once __DIR__ . '/helpers.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

function build_laporan_spreadsheet(PDO $pdo, int $kthId, ?array $laporan = null): array {
    $kth = $pdo->prepare('SELECT * FROM kth WHERE id = ?');
    $kth->execute([$kthId]);
    $k = $kth->fetch();
    if (!$k) throw new RuntimeException('Data KTH tidak ditemukan.');

    if ($laporan === null) {
        $st = $pdo->prepare('SELECT * FROM laporan WHERE kth_id = ? ORDER BY id DESC LIMIT 1');
        $st->execute([$kthId]);
        $laporan = $st->fetch() ?: [];
    }

    $q = $pdo->prepare('SELECT u.no_urut, u.nama, u.nik, h.status_sk, h.status_koordinat, h.catatan
        FROM usulan_pupuk u LEFT JOIN hasil_verifikasi h ON h.usulan_id = u.id
        WHERE u.kth_id = ? ORDER BY COALESCE(u.no_urut, u.id)');
    $q->execute([$kthId]);
    $rows = $q->fetchAll();

    $ss = new Spreadsheet();
    $ws = $ss->getActiveSheet();
    $ws->setTitle('Hasil Verifikasi');
    $ws->getDefaultRowDimension()->setRowHeight(18);

    $thin = ['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]];
    $center = ['alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true]];
    $wrapLeft = ['alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true]];

    // Judul
    $ws->mergeCells('A1:F1');
    $ws->setCellValue('A1', 'HASIL VERIFIKASI DATA USULAN PUPUK BERSUBSIDI BAGI PETANI HUTAN');
    $ws->getStyle('A1')->getFont()->setBold(true)->setSize(13);
    $ws->getStyle('A1')->applyFromArray($center);
    $ws->getRowDimension(1)->setRowHeight(26);

    $ws->mergeCells('A2:F2');
    $ws->setCellValue('A2', 'KTH/LMDH: ' . $k['nama_kth'] . '   |   SK: ' . ($k['nomor_sk'] ?: '-') . '   |   Tahun: ' . ($laporan['tahun'] ?? $k['tahun_usulan'] ?? '-'));
    $ws->getStyle('A2')->getFont()->setSize(11);
    $ws->getStyle('A2')->applyFromArray($center);
    $ws->getRowDimension(2)->setRowHeight(22);

    // Ringkasan indikator (gaya tabel indikator–kategori–catatan)
    $r = 4;
    $ws->setCellValue("A$r", 'INDIKATOR'); $ws->setCellValue("B$r", 'KATEGORI'); $ws->setCellValue("C$r", 'JUMLAH');
    $ws->mergeCells("C$r:D$r"); $ws->setCellValue("E$r", 'CATATAN'); $ws->mergeCells("E$r:F$r");
    $ws->getStyle("A$r:F$r")->getFont()->setBold(true);
    $ws->getStyle("A$r:F$r")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFD9EAD3');
    $ws->getStyle("A$r:F$r")->applyFromArray($center);
    $r++;
    $indikator = [
        ['Kesesuaian SK', 'Sesuai SK PS', (int)($laporan['jumlah_sesuai_sk'] ?? 0)],
        ['Kesesuaian SK', 'Belum Sesuai SK PS', (int)($laporan['jumlah_tidak_sesuai_sk'] ?? 0)],
        ['Posisi Koordinat', 'Dalam Peta PS', (int)($laporan['jumlah_dalam_peta'] ?? 0)],
        ['Posisi Koordinat', 'Luar Peta PS', (int)($laporan['jumlah_luar_peta'] ?? 0)],
        ['Total', 'Petani pengusul', (int)($laporan['total_petani'] ?? count($rows))],
    ];
    $startInd = $r;
    foreach ($indikator as $ind) {
        $ws->setCellValue("A$r", $ind[0]);
        $ws->setCellValue("B$r", $ind[1]);
        $ws->mergeCells("C$r:D$r"); $ws->setCellValue("C$r", $ind[2]);
        $ws->mergeCells("E$r:F$r"); $ws->setCellValue("E$r", '');
        $ws->getStyle("A$r:F$r")->applyFromArray($center);
        $r++;
    }
    $ws->getStyle("A$startInd:F" . ($r - 1))->applyFromArray($thin);

    // Narasi
    $r++;
    $ws->mergeCells("A$r:F$r");
    $ws->setCellValue("A$r", 'NARASI HASIL VERIFIKASI');
    $ws->getStyle("A$r")->getFont()->setBold(true);
    $r++;
    $ws->mergeCells("A$r:F" . ($r + 2));
    $ws->setCellValue("A$r", (string)($laporan['narasi'] ?? ''));
    $ws->getStyle("A$r")->applyFromArray($wrapLeft);
    $ws->getRowDimension($r)->setRowHeight(30);
    $ws->getRowDimension($r + 1)->setRowHeight(30);
    $r += 4;

    // Tabel hasil per baris
    $ws->setCellValue("A$r", 'No'); $ws->setCellValue("B$r", 'Nama'); $ws->setCellValue("C$r", 'NIK');
    $ws->setCellValue("D$r", 'Status SK'); $ws->setCellValue("E$r", 'Status Koordinat'); $ws->setCellValue("F$r", 'Catatan');
    $ws->getStyle("A$r:F$r")->getFont()->setBold(true);
    $ws->getStyle("A$r:F$r")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFF2CC');
    $ws->getStyle("A$r:F$r")->applyFromArray($center);
    $headRow = $r; $r++;
    $no = 1;
    foreach ($rows as $row) {
        $ws->setCellValue("A$r", $no++);
        $ws->setCellValue("B$r", $row['nama']);
        $ws->setCellValueExplicit("C$r", (string)$row['nik'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $ws->setCellValue("D$r", $row['status_sk']);
        $ws->setCellValue("E$r", $row['status_koordinat']);
        $ws->setCellValue("F$r", $row['catatan']);
        $ws->getStyle("A$r:E$r")->applyFromArray($center);
        $ws->getStyle("F$r")->applyFromArray($wrapLeft);
        $ws->getRowDimension($r)->setRowHeight(20);
        $r++;
    }
    $ws->getStyle("A$headRow:F" . ($r - 1))->applyFromArray($thin);

    // Rekomendasi
    $r++;
    $ws->mergeCells("A$r:F$r");
    $ws->setCellValue("A$r", 'REKOMENDASI: ' . mb_strtoupper((string)($laporan['rekomendasi'] ?? '-'), 'UTF-8'));
    $ws->getStyle("A$r")->getFont()->setBold(true)->setSize(12);
    $ws->getStyle("A$r")->applyFromArray($center);
    $ws->getRowDimension($r)->setRowHeight(24);

    foreach (['A' => 6, 'B' => 26, 'C' => 22, 'D' => 20, 'E' => 20, 'F' => 60] as $col => $w) {
        $ws->getColumnDimension($col)->setWidth($w);
    }

    $fname = 'Hasil_Verifikasi_' . preg_replace('/[^\w\-]+/', '_', (string)$k['nama_kth']) . '.xlsx';
    return [$ss, $fname];
}

function export_laporan_excel(PDO $pdo, int $kthId, ?array $laporan = null): void {
    [$ss, $fname] = build_laporan_spreadsheet($pdo, $kthId, $laporan);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $fname . '"');
    header('Cache-Control: max-age=0');
    (new Xlsx($ss))->save('php://output');
    exit;
}
