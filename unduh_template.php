<?php
// unduh_template.php — Unduh File Template Resmi Excel Usulan Pupuk Bersubsidi
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/template.php';

use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Bersihkan buffer output agar file biner Excel tidak korup
while (ob_get_level()) {
    ob_end_clean();
}

$filename = 'Template_Usulan_Pupuk_Perhutanan_Sosial.xlsx';
$localTemplate = __DIR__ . '/templates/' . $filename;

// Jika file statis belum ada, buat langsung
if (!file_exists($localTemplate)) {
    simpan_file_template_usulan($localTemplate);
}

if (file_exists($localTemplate)) {
    header('Content-Description: File Transfer');
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Transfer-Encoding: binary');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    header('Content-Length: ' . filesize($localTemplate));
    readfile($localTemplate);
    exit;
}

// Fallback jika penyimpanan gagal: kirim via php://output
$ss = buat_spreadsheet_template_usulan();
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
$writer = new Xlsx($ss);
$writer->save('php://output');
exit;
