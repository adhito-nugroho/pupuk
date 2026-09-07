<?php
// Endpoint simpan catatan inline (fetch dari hasil.php). Mengembalikan JSON.
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/db.php';
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['ok' => false, 'msg' => 'method']); exit; }
$hasilId = (int)($_POST['hasil_id'] ?? 0);
$catatan = (string)($_POST['catatan'] ?? '');
if (!$hasilId) { echo json_encode(['ok' => false, 'msg' => 'hasil_id kosong']); exit; }
try {
    db()->prepare('UPDATE hasil_verifikasi SET catatan = ? WHERE id = ?')->execute([$catatan, $hasilId]);
    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
}
