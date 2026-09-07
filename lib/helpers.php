<?php
// ============================================================
// Helpers inti: normalisasi, koordinat, point-in-polygon, matching.
// Murni PHP, tanpa dependensi spasial MySQL maupun GDAL.
// Konvensi global: pasangan koordinat selalu [lng, lat]
//   (X = longitude ~111, Y = latitude ~-7).
// ============================================================

/** NIK: hanya digit, trim. */
function norm_nik(?string $s): string {
    $s = (string)($s ?? '');
    return preg_replace('/\D+/', '', $s) ?? '';
}

/** Nama: uppercase, collapse whitespace, trim. */
function norm_nama(?string $s): string {
    $s = (string)($s ?? '');
    $s = mb_strtoupper(trim($s), 'UTF-8');
    $s = preg_replace('/\s+/', ' ', $s) ?? '';
    return trim($s);
}

/**
 * Bersihkan koordinat mentah Excel ke float.
 * Contoh: "X: 111. 701717" -> 111.701717 ; "Y: -7. 292323" -> -7.292323
 * Menangani: prefix X:/Y:/Long/Lat, spasi ganjil di tengah angka,
 * koma desimal ala Indonesia, dan angka negatif.
 * Mengembalikan null bila tidak bisa di-parse.
 */
function clean_koordinat($raw): ?float {
    if ($raw === null) return null;
    $s = trim((string)$raw);
    if ($s === '') return null;
    // Buang prefix "X:", "Y:", "Long.", "Lat." dsb di awal
    $s = preg_replace('/^\s*[A-Za-z\.]+\s*:\s*/', '', $s) ?? $s;
    // Buang semua spasi di tengah angka ("111. 701717" -> "111.701717")
    $s = preg_replace('/\s+/', '', $s) ?? $s;
    // Ambil token angka pertama (termasuk negatif & desimal koma/titik)
    if (!preg_match('/[-+]?\d+(?:[\.,]\d+)?/', $s, $m)) return null;
    $num = $m[0];
    // Normalisasi desimal: "111,701717" -> "111.701717".
    // Jika ada titik sekaligus koma, anggap koma = pemisah ribuan -> buang koma.
    if (strpos($num, ',') !== false && strpos($num, '.') !== false) {
        $num = str_replace(',', '', $num);
    } else {
        $num = str_replace(',', '.', $num);
    }
    if (!is_numeric($num)) return null;
    return (float)$num;
}

/**
 * Ray-casting: cek titik (lng,lat) di dalam satu ring poligon.
 * $ring = [[lng,lat],[lng,lat],...]. Titik tepat di vertex/edge
 * dianggap di dalam (toleran untuk batas).
 */
function point_in_ring(float $lng, float $lat, array $ring): bool {
    $n = count($ring);
    if ($n < 3) return false;
    $inside = false;
    for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
        $xi = (float)$ring[$i][0]; $yi = (float)$ring[$i][1];
        $xj = (float)$ring[$j][0]; $yj = (float)$ring[$j][1];
        // Titik tepat di vertex -> dalam
        if ($lng === $xi && $lat === $yi) return true;
        $intersect = (($yi > $lat) !== ($yj > $lat))
            && ($lng < ($xj - $xi) * ($lat - $yi) / (($yj - $yi) == 0.0 ? 1e-12 : ($yj - $yi)) + $xi);
        if ($intersect) $inside = !$inside;
    }
    return $inside;
}

/**
 * Cek titik terhadap geometri multi-ring (multi-part shapefile).
 * Aturan: di dalam jika berada di dalam SALAH SATU ring.
 * (Catatan: hole vs part tidak dibedakan — cukup untuk kebutuhan
 * verifikasi "dalam/luar areal PS".)
 */
function point_in_geometry(float $lng, float $lat, array $rings): bool {
    foreach ($rings as $ring) {
        if (!is_array($ring) || count($ring) < 3) continue;
        if (point_in_ring($lng, $lat, $ring)) return true;
    }
    return false;
}

/**
 * Kemiripan nama 0-100 pakai similar_text pada bentuk normalisasi.
 * Mengembalikan [persen, nama_sk_mirip_terbaik].
 */
function cari_nama_mirip(string $namaUsulan, array $daftarNamaSK): array {
    $norm = norm_nama($namaUsulan);
    $best = 0.0; $bestNama = null;
    foreach ($daftarNamaSK as $row) {
        $cand = norm_nama((string)($row['nama'] ?? ''));
        if ($cand === '' || $norm === '') continue;
        similar_text($norm, $cand, $pct);
        if ($pct > $best) { $best = $pct; $bestNama = (string)($row['nama'] ?? ''); }
    }
    return [$best, $bestNama];
}

/** Escape HTML sekali jalan. */
function e(?string $s): string {
    return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8');
}

/** Flash message sederhana via session. */
function flash_set(string $tipe, string $pesan): void {
    if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
    $_SESSION['flash'][] = ['tipe' => $tipe, 'pesan' => $pesan];
}
function flash_take(): array {
    if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
    $f = $_SESSION['flash'] ?? [];
    $_SESSION['flash'] = [];
    return $f;
}
