<?php
// Probe koneksi DB + inisialisasi skema.
// DIKUNCI: hanya jalan via CLI atau POST konfirmasi eksplisit.
// (Sebelumnya file ini mengeksekusi skema lewat GET biasa sehingga bisa
// tersenggol crawler/bot di server produksi.)
require_once __DIR__ . '/config.php';

$isCli = php_sapi_name() === 'cli';
$confirmed = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['confirm'] ?? '') === 'INIT-VERIF-PUPUK';

if (!$isCli && !$confirmed) {
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><title>403 — Terkunci</title></head><body style="font-family:sans-serif;padding:40px;max-width:560px;margin:0 auto">'
        . '<h3>🔒 install.php dikunci</h3>'
        . '<p>Skrip inisialisasi skema hanya boleh dijalankan via CLI (<code>php install.php</code>) atau form konfirmasi. Akses GET dinonaktifkan agar tidak tersenggol crawler di server.</p>'
        . '<form method="post" onsubmit="return confirm(\'Jalankan inisialisasi skema sekarang?\')">'
        . '<input type="hidden" name="confirm" value="INIT-VERIF-PUPUK">'
        . '<button type="submit">Saya paham — jalankan inisialisasi</button></form>'
        . '</body></html>';
    exit;
}

header('Content-Type: text/plain; charset=utf-8');
try {
    $pdo = new PDO('mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';charset=utf8mb4', DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "KONEKSI OK\n";
    // Tolak jalan bila database sudah berisi data (cegah reset tak sengaja)
    try {
        $n = (int)$pdo->query('SELECT COUNT(*) FROM verif_pupuk.kth')->fetchColumn();
        if ($n > 0 && !$isCli) {
            http_response_code(409);
            echo "DITOLAK: tabel kth sudah berisi {$n} kasus. Inisialisasi via browser dibatalkan agar data tidak tertimpa. Gunakan CLI bila memang disengaja.\n";
            exit;
        }
    } catch (Throwable $eCount) { /* tabel belum ada — lanjut */ }
    $sql = file_get_contents(__DIR__ . '/db/schema.sql');
    $pdo->exec($sql);
    echo "SCHEMA OK\n";
    $stmt = $pdo->query("SHOW TABLES FROM verif_pupuk");
    foreach ($stmt->fetchAll(PDO::FETCH_NUM) as $r) { echo "- " . $r[0] . "\n"; }
} catch (Throwable $e) {
    http_response_code(500);
    echo "GAGAL: " . $e->getMessage() . "\n";
}
