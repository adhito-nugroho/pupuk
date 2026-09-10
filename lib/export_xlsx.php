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
        $st = $pdo->prepare('SELECT * FROM laporan WHERE kth_id = ? ORDER BY id DESC LIMIT 1');
        $st->execute([$kthId]);
        $laporan = $st->fetch() ?: [];
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
