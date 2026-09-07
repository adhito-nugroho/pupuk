<?php
// Probe koneksi DB Laragon (jalankan via browser/CLI, hapus bila sudah OK).
require_once __DIR__ . '/config.php';
header('Content-Type: text/plain; charset=utf-8');
try {
    $pdo = new PDO('mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';charset=utf8mb4', DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "KONEKSI OK\n";
    $sql = file_get_contents(__DIR__ . '/db/schema.sql');
    $pdo->exec($sql);
    echo "SCHEMA OK\n";
    $stmt = $pdo->query("SHOW TABLES FROM verif_pupuk");
    foreach ($stmt->fetchAll(PDO::FETCH_NUM) as $r) { echo "- " . $r[0] . "\n"; }
} catch (Throwable $e) {
    http_response_code(500);
    echo "GAGAL: " . $e->getMessage() . "\n";
}
