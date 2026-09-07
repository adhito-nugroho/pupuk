<?php
// Hapus satu kasus + seluruh data turunannya.
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/helpers.php';

$kthId = (int)($_GET['kth_id'] ?? 0);
if ($kthId) {
    db()->prepare('DELETE FROM kth WHERE id = ?')->execute([$kthId]);
    flash_set('ok', "Kasus #$kthId dihapus.");
}
header('Location: index.php');
exit;
