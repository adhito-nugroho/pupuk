<?php
// Download laporan Excel.
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/lib/export_xlsx.php';

$kthId = (int)($_GET['kth_id'] ?? 0);
$vParam = (int)($_GET['v'] ?? 0);
$mode = strtolower(trim((string)($_GET['mode'] ?? $_GET['format'] ?? 'laporan')));
if (!$kthId) { http_response_code(400); echo 'kth_id wajib.'; exit; }
if ($mode === 'lembar' || $mode === 'lembar_hasil' || $mode === 'hasil') {
    export_lembar_hasil_excel(db(), $kthId, $vParam);
}
export_laporan_excel(db(), $kthId, null, $vParam);
