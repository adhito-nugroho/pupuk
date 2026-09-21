<?php
// lib/template.php — Generator & Helper Template Excel Resmi Usulan Pupuk Perhutanan Sosial
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

/**
 * Buat instance PhpSpreadsheet template usulan pupuk resmi sesuai format standar CDK.
 */
function buat_spreadsheet_template_usulan(): Spreadsheet {
    $ss = new Spreadsheet();
    $ss->getProperties()
        ->setCreator('Cabang Dinas Kehutanan Wilayah Bojonegoro')
        ->setLastModifiedBy('Sistem Verifikasi Alokasi Pupuk')
        ->setTitle('Template Usulan Pupuk Bersubsidi Perhutanan Sosial')
        ->setSubject('Format Baku Usulan Petani Pemohon Pupuk Bersubsidi')
        ->setDescription('Format resmi file Excel usulan pupuk bersubsidi sektor kehutanan dengan kolom NIK, Nama, Luas, dan Titik Koordinat Lahan.');

    $ws = $ss->getActiveSheet();
    $ws->setTitle('USULAN PUPUK');

    // 1. Judul Dokumen (Baris 2)
    $ws->setCellValue('A2', 'DAFTAR USULAN PETANI PENERIMA PUPUK BERSUBSIDI PERHUTANAN SOSIAL');
    $ws->getStyle('A2')->getFont()->setName('Arial')->setSize(11)->setBold(true);

    // 2. Baris Header (Baris 4) — Sesuai format baku pada gambar
    $headers = [
        'A4' => 'NO',
        'B4' => 'NIK',
        'C4' => 'NAMA',
        'D4' => "JENIS KELAMIN\n(L/P)",
        'E4' => 'RT',
        'F4' => 'RW',
        'G4' => 'DESA',
        'H4' => 'KECAMATAN',
        'I4' => 'POLA TANAM',
        'J4' => 'PETAK',
        'K4' => "LUAS LAHAN\n(Ha)",
        'L4' => "NO. PKS AGROFORESTY /\nNO.SK PS",
        'M4' => 'TITIK KOORDINAT LAHAN',
    ];

    foreach ($headers as $cell => $text) {
        $ws->setCellValue($cell, $text);
    }

    // Merge Koordinat Lahan di M4:N4
    $ws->mergeCells('M4:N4');

    // Styling Header Row 4 (Hijau Sage Lembut persis gambar)
    $headerColor = 'C8E6C9';
    $ws->getStyle('A4:N4')->applyFromArray([
        'font' => [
            'name' => 'Arial',
            'size' => 9,
            'bold' => true,
            'color' => ['rgb' => '000000'],
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER,
            'wrapText' => true,
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => $headerColor],
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => '000000'],
            ],
        ],
    ]);
    $ws->getRowDimension(4)->setRowHeight(34);

    // 3. Data Contoh Realistis (Baris 5 & 6)
    $sampleData = [
        [
            'no' => 1,
            'nik' => '3522033112630018',
            'nama' => 'SUKADI',
            'jk' => 'L',
            'rt' => '14',
            'rw' => '05',
            'desa' => 'BONDOL',
            'kecamatan' => 'NGAMBON',
            'pola' => 'JAGUNG,JAGUNG,JAGUNG',
            'petak' => '18',
            'luas' => 0.25,
            'no_sk' => '9761/MENLHK-PSKL/PKPS/PSL.0/11/2019',
            'x' => '111.701717',
            'y' => '-7.292323',
        ],
        [
            'no' => 2,
            'nik' => '3522031806740001',
            'nama' => 'TOMPO',
            'jk' => 'L',
            'rt' => '10',
            'rw' => '04',
            'desa' => 'BONDOL',
            'kecamatan' => 'NGAMBON',
            'pola' => 'PADI,JAGUNG,JAGUNG',
            'petak' => '18',
            'luas' => 0.50,
            'no_sk' => '9761/MENLHK-PSKL/PKPS/PSL.0/11/2019',
            'x' => '570790',
            'y' => '9182577',
        ],
    ];

    $rowIdx = 5;
    foreach ($sampleData as $d) {
        $ws->setCellValue('A' . $rowIdx, $d['no']);
        $ws->getCell('B' . $rowIdx)->setValueExplicit($d['nik'], DataType::TYPE_STRING);
        $ws->setCellValue('C' . $rowIdx, $d['nama']);
        $ws->setCellValue('D' . $rowIdx, $d['jk']);
        $ws->setCellValue('E' . $rowIdx, $d['rt']);
        $ws->setCellValue('F' . $rowIdx, $d['rw']);
        $ws->setCellValue('G' . $rowIdx, $d['desa']);
        $ws->setCellValue('H' . $rowIdx, $d['kecamatan']);
        $ws->setCellValue('I' . $rowIdx, $d['pola']);
        $ws->setCellValue('J' . $rowIdx, $d['petak']);
        $ws->setCellValue('K' . $rowIdx, $d['luas']);
        $ws->setCellValue('L' . $rowIdx, $d['no_sk']);
        $ws->setCellValue('M' . $rowIdx, $d['x']);
        $ws->setCellValue('N' . $rowIdx, $d['y']);

        $ws->getStyle('A' . $rowIdx . ':N' . $rowIdx)->applyFromArray([
            'font' => ['name' => 'Arial', 'size' => 9],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'D0D0D0'],
                ],
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        $ws->getStyle('A' . $rowIdx)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $ws->getStyle('B' . $rowIdx)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $ws->getStyle('D' . $rowIdx)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $ws->getStyle('E' . $rowIdx . ':F' . $rowIdx)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $ws->getStyle('J' . $rowIdx)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $ws->getStyle('K' . $rowIdx)->getNumberFormat()->setFormatCode('#,##0.00');
        $ws->getStyle('K' . $rowIdx)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $ws->getStyle('M' . $rowIdx . ':N' . $rowIdx)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $ws->getRowDimension($rowIdx)->setRowHeight(20);

        $rowIdx++;
    }

    // 4. Catatan Petunjuk Format di Bawah Data (Baris 8)
    $ws->setCellValue('A8', 'CATATAN PETUNJUK PENGISIAN:');
    $ws->getStyle('A8')->getFont()->setName('Arial')->setSize(9)->setBold(true)->getColor()->setRGB('1B382B');

    $petunjuk = [
        'A9'  => '1. Kolom Wajib Diisi: NIK (16 digit), NAMA, LUAS LAHAN (Ha), dan TITIK KOORDINAT LAHAN (Kolom M dan N).',
        'A10' => '2. Format NIK: Pastikan diformat sebagai Teks (Text) agar 16 digit tidak terpotong atau berubah menjadi angka eksponensial (3.522E+15).',
        'A11' => '3. Format Koordinat: Didukung dua format: (a) Geografis WGS84 (Derajat Desimal, misal X: 111.701717, Y: -7.292323), atau (b) Proyeksi UTM Zona 49S (Meter, misal X: 570790, Y: 9182577).',
        'A12' => '4. Satuan Luas Lahan: Hektar (Ha). Gunakan tanda titik atau koma untuk desimal (maksimal 2.00 Ha per orang sesuai regulasi pupuk bersubsidi).',
    ];
    foreach ($petunjuk as $c => $txt) {
        $ws->setCellValue($c, $txt);
        $ws->getStyle($c)->getFont()->setName('Arial')->setSize(8.5)->setItalic(true)->getColor()->setRGB('555555');
    }

    // Auto-width kolom
    $colWidths = [
        'A' => 6,
        'B' => 22,
        'C' => 26,
        'D' => 14,
        'E' => 6,
        'F' => 6,
        'G' => 16,
        'H' => 16,
        'I' => 22,
        'J' => 10,
        'K' => 15,
        'L' => 32,
        'M' => 18,
        'N' => 18,
    ];
    foreach ($colWidths as $col => $w) {
        $ws->getColumnDimension($col)->setWidth($w);
    }

    return $ss;
}

/**
 * Simpan template ke file fisik di disk.
 */
function simpan_file_template_usulan(string $targetFile): bool {
    $dir = dirname($targetFile);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $ss = buat_spreadsheet_template_usulan();
    $writer = new Xlsx($ss);
    $writer->save($targetFile);
    return file_exists($targetFile) && filesize($targetFile) > 0;
}
