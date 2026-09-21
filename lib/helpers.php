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
 * Menangani:
 * - Format desimal standar: "111.701717", "-7.292323"
 * - Format desimal koma Indonesia: "111,701717", "-7,292323"
 * - Format metrik UTM ribuan koma/titik: "570790,170926", "9182577,773106", "570.790,170926", "9.182.577,773106"
 * - Format prefix: "X: ...", "Y: ...", "Long: ...", "Lat: ..."
 * - Spasi ganjil di tengah angka.
 */
function clean_koordinat($raw): ?float {
    if ($raw === null) return null;
    $s = trim((string)$raw);
    if ($s === '') return null;
    // Buang prefix "X:", "Y:", "Long.", "Lat.", "Easting:", dsb di awal
    $s = preg_replace('/^\s*[A-Za-z\.\-_]+\s*:\s*/', '', $s) ?? $s;
    // Buang semua spasi di tengah angka
    $s = preg_replace('/\s+/', '', $s) ?? $s;
    // Ambil deretan angka beserta minus/plus, titik, dan koma
    if (!preg_match('/[-+]?[\d\.,]+/', $s, $m)) return null;
    $num = $m[0];

    $lastDot = strrpos($num, '.');
    $lastComma = strrpos($num, ',');

    if ($lastDot !== false && $lastComma !== false) {
        if ($lastComma > $lastDot) {
            // Format Indo: 9.182.577,773106 -> koma adalah desimal
            $num = str_replace('.', '', substr($num, 0, $lastComma)) . '.' . substr($num, $lastComma + 1);
        } else {
            // Format US: 9,182,577.773106 -> titik adalah desimal
            $num = str_replace(',', '', substr($num, 0, $lastDot)) . '.' . substr($num, $lastDot + 1);
        }
    } elseif ($lastComma !== false) {
        // Hanya ada koma -> ubah ke titik desimal
        $num = str_replace(',', '.', $num);
    } elseif ($lastDot !== false) {
        // Jika ada lebih dari 1 titik (misal 9.182.577 tanpa desimal)
        if (substr_count($num, '.') > 1) {
            $num = str_replace('.', '', $num);
        }
    }

    if (!is_numeric($num)) return null;
    return (float)$num;
}

/**
 * Konversi koordinat proyeksi UTM WGS84 ke Geografis (Longitude & Latitude Desimal).
 * Menggunakan rumus konversi ellipsoid WGS84 presisi tinggi (Karney / USGS).
 * Default zona 49S (wilayah Jawa Timur / Bojonegoro).
 */
function utm_to_latlng(float $easting, float $northing, int $zone = 49, bool $southHemi = true): array {
    $a = 6378137.0; // WGS84 semi-major axis
    $f = 1 / 298.257223563; // flattening
    $b = $a * (1 - $f);
    $eSq = ($a * $a - $b * $b) / ($a * $a);
    $ePrimeSq = ($a * $a - $b * $b) / ($b * $b);
    $k0 = 0.9996;

    $x = $easting - 500000.0;
    $y = $southHemi ? ($northing - 10000000.0) : $northing;

    $m = $y / $k0;
    $e1 = (1 - sqrt(1 - $eSq)) / (1 + sqrt(1 - $eSq));
    $mu = $m / ($a * (1 - $eSq / 4 - 3 * pow($eSq, 2) / 64 - 5 * pow($eSq, 3) / 256));

    $phi1 = $mu + (3 * $e1 / 2 - 27 * pow($e1, 3) / 32) * sin(2 * $mu)
                 + (21 * pow($e1, 2) / 16 - 55 * pow($e1, 4) / 32) * sin(4 * $mu)
                 + (151 * pow($e1, 3) / 96) * sin(6 * $mu)
                 + (1097 * pow($e1, 4) / 512) * sin(8 * $mu);

    $sinPhi1 = sin($phi1);
    $cosPhi1 = cos($phi1);
    $tanPhi1 = tan($phi1);

    $n1 = $a / sqrt(1 - $eSq * $sinPhi1 * $sinPhi1);
    $t1 = $tanPhi1 * $tanPhi1;
    $c1 = $ePrimeSq * $cosPhi1 * $cosPhi1;
    $r1 = $a * (1 - $eSq) / pow(1 - $eSq * $sinPhi1 * $sinPhi1, 1.5);
    $d = $x / ($n1 * $k0);

    $lat = $phi1 - ($n1 * $tanPhi1 / $r1) * (
        pow($d, 2) / 2
        - (5 + 3 * $t1 + 10 * $c1 - 4 * pow($c1, 2) - 9 * $ePrimeSq) * pow($d, 4) / 24
        + (61 + 90 * $t1 + 298 * $c1 + 45 * pow($t1, 2) - 252 * $ePrimeSq - 3 * pow($c1, 2)) * pow($d, 6) / 720
    );

    $lng0 = ($zone - 1) * 6 - 180 + 3; // central meridian
    $lng = deg2rad($lng0) + (
        $d
        - (1 + 2 * $t1 + $c1) * pow($d, 3) / 6
        + (5 - 2 * $c1 + 28 * $t1 - 3 * pow($c1, 2) + 8 * $ePrimeSq + 24 * pow($t1, 2)) * pow($d, 5) / 120
    ) / $cosPhi1;

    return [
        'lng' => round(rad2deg($lng), 7),
        'lat' => round(rad2deg($lat), 7)
    ];
}

/**
 * Deteksi cerdas format koordinat (Geografis WGS84 desimal atau UTM Metrik)
 * dan konversikan ke Longitude & Latitude derajat desimal.
 * Juga menangani kasus kolom terbalik (X memuat Latitude/Northing dan Y memuat Longitude/Easting).
 */
function parse_dan_konversi_koordinat($xRaw, $yRaw, int $defaultZone = 49): array {
    $xClean = clean_koordinat($xRaw);
    $yClean = clean_koordinat($yRaw);

    if ($xClean === null || $yClean === null) {
        return [
            'x' => $xClean,
            'y' => $yClean,
            'tipe' => null,
            'is_utm' => false
        ];
    }

    // 1. Deteksi format UTM (Universal Transverse Mercator Zona Selatan / Jawa)
    // Easting ~ 100.000 s.d. 950.000 meter. Northing ~ 8.000.000 s.d. 10.000.000 meter.
    $isUtmNormal = ($xClean >= 100000 && $xClean <= 950000) && ($yClean >= 8000000 && $yClean <= 10000000);
    $isUtmSwapped = ($yClean >= 100000 && $yClean <= 950000) && ($xClean >= 8000000 && $xClean <= 10000000);

    if ($isUtmNormal || $isUtmSwapped) {
        $easting  = $isUtmNormal ? $xClean : $yClean;
        $northing = $isUtmNormal ? $yClean : $xClean;

        $geo = utm_to_latlng($easting, $northing, $defaultZone, true);
        return [
            'x' => $geo['lng'],
            'y' => $geo['lat'],
            'tipe' => 'UTM Zona ' . $defaultZone . 'S',
            'is_utm' => true,
            'utm_easting' => $easting,
            'utm_northing' => $northing
        ];
    }

    // 2. Deteksi Format Geografis WGS84 (Derajat Desimal)
    // Longitude Indonesia ~ 90 s.d. 145. Latitude ~ -15 s.d. 15.
    $isGeoNormal = ($xClean >= 90 && $xClean <= 145) && ($yClean >= -15 && $yClean <= 15);
    $isGeoSwapped = ($yClean >= 90 && $yClean <= 145) && ($xClean >= -15 && $xClean <= 15);

    if ($isGeoSwapped) {
        return [
            'x' => $yClean, // Longitude
            'y' => $xClean, // Latitude
            'tipe' => 'Geografis (WGS84)',
            'is_utm' => false
        ];
    }

    return [
        'x' => $xClean,
        'y' => $yClean,
        'tipe' => 'Geografis (WGS84)',
        'is_utm' => false
    ];
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

/** Flash message sederhana via session + fallback query parameter. */
function flash_set(string $tipe, string $pesan): void {
    if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) @session_start();
    $_SESSION['flash'][] = ['tipe' => $tipe, 'pesan' => $pesan];
}
function flash_take(): array {
    if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) @session_start();
    $f = $_SESSION['flash'] ?? [];
    $_SESSION['flash'] = [];
    if (!empty($_GET['err'])) {
        $f[] = ['tipe' => 'error', 'pesan' => trim((string)$_GET['err'])];
    }
    if (!empty($_GET['msg'])) {
        $f[] = ['tipe' => 'ok', 'pesan' => trim((string)$_GET['msg'])];
    }
    return $f;
}

/**
 * Ambil seluruh riwayat versi usulan untuk suatu KTH (diurutkan dari versi terbaru).
 */
function ambil_daftar_versi(PDO $pdo, int $kthId): array {
    $st = $pdo->prepare('SELECT * FROM kth_versi_usulan WHERE kth_id = ? ORDER BY versi_ke DESC');
    $st->execute([$kthId]);
    $list = $st->fetchAll();
    if (empty($list)) {
        // Cek apakah ada data usulan_pupuk / hasil_verifikasi yang sudah ada untuk KTH ini
        $totPetani = 0;
        $totLuas = 0.0;
        $sesuai = 0;
        $tidak = 0;
        $dalam = 0;
        $luar = 0;
        $rekom = 'Perlu Revisi';

        try {
            $h = $pdo->prepare('SELECT COUNT(*) total, COALESCE(SUM(luas_lahan), 0) luas FROM usulan_pupuk WHERE kth_id = ?');
            $h->execute([$kthId]);
            $uInfo = $h->fetch() ?: [];
            $totPetani = (int)($uInfo['total'] ?? 0);
            $totLuas = (float)($uInfo['luas'] ?? 0);

            if ($totPetani > 0) {
                $h2 = $pdo->prepare('SELECT 
                    COALESCE(SUM(status_sk="Sesuai SK PS"), 0) sesuai, 
                    COALESCE(SUM(status_sk!="Sesuai SK PS"), 0) tidak, 
                    COALESCE(SUM(status_koordinat="Dalam Peta PS"), 0) dalam, 
                    COALESCE(SUM(status_koordinat!="Dalam Peta PS"), 0) luar 
                    FROM hasil_verifikasi WHERE kth_id = ?');
                $h2->execute([$kthId]);
                $res = $h2->fetch() ?: [];
                $sesuai = (int)($res['sesuai'] ?? 0);
                $tidak = (int)($res['tidak'] ?? 0);
                $dalam = (int)($res['dalam'] ?? 0);
                $luar = (int)($res['luar'] ?? 0);
                $rekom = ($tidak === 0 && $luar === 0) ? 'Dapat Ditindaklanjuti' : 'Perlu Revisi';

                // Simpan ke kth_versi_usulan jika tabel sudah siap
                $ins = $pdo->prepare('INSERT INTO kth_versi_usulan (kth_id, versi_ke, label_versi, nama_file_asli, path_file, total_petani, total_luas, jumlah_sesuai_sk, jumlah_tidak_sesuai_sk, jumlah_dalam_peta, jumlah_luar_peta, rekomendasi, catatan_perbaikan, dibuat_pada) VALUES (?, 1, "Usulan Awal (v1)", "usulan_awal.xlsx", "", ?, ?, ?, ?, ?, ?, ?, "Data usulan awal.", NOW())');
                $ins->execute([$kthId, $totPetani, $totLuas, $sesuai, $tidak, $dalam, $luar, $rekom]);

                $st->execute([$kthId]);
                $listInserted = $st->fetchAll();
                if (!empty($listInserted)) {
                    return $listInserted;
                }
            }
        } catch (Throwable $e) {
            // Abaikan kesalahan bila skema belum siap
        }

        return [[
            'id' => 0,
            'kth_id' => $kthId,
            'versi_ke' => 1,
            'label_versi' => 'Usulan Awal (v1)',
            'nama_file_asli' => 'usulan_awal.xlsx',
            'path_file' => '',
            'total_petani' => $totPetani,
            'total_luas' => $totLuas,
            'jumlah_sesuai_sk' => $sesuai,
            'jumlah_tidak_sesuai_sk' => $tidak,
            'jumlah_dalam_peta' => $dalam,
            'jumlah_luar_peta' => $luar,
            'rekomendasi' => $rekom,
            'catatan_perbaikan' => '',
            'dibuat_pada' => date('Y-m-d H:i:s'),
        ]];
    }
    return $list;
}

/**
 * Dapatkan nomor versi aktif / yang diminta oleh request.
 */
function ambil_versi_terpilih(array $kth, ?int $vParam = null): int {
    if ($vParam !== null && $vParam > 0) {
        return $vParam;
    }
    $vAktif = (int)($kth['versi_aktif'] ?? 1);
    return $vAktif > 0 ? $vAktif : 1;
}

/**
 * Sinkronisasi & konversi otomatis seluruh koordinat bertipe UTM yang sudah terlanjur tersimpan di database
 * menjadi koordinat derajat desimal WGS84 (Longitude & Latitude).
 * 
 * Jika ada baris yang dikonversi, fungsi ini dapat otomatis memicu verifikasi ulang
 * untuk KTH dan versi terkait agar status 'Dalam Peta PS' / 'Luar Peta PS' langsung terbarukan.
 *
 * @param PDO $pdo
 * @param int|null $onlyKthId Jika diset, hanya KTH tertentu yang disinkronkan.
 * @param bool $reverifikasi Otomatis jalankan verifikasi_satu_kth() pada KTH terdampak.
 * @return array Ringkasan hasil [total_diupdate, kth_terpengaruh, daftar_kth]
 */
function sinkronkan_koordinat_utm_ke_wgs84(PDO $pdo, ?int $onlyKthId = null, bool $reverifikasi = true): array {
    $sql = 'SELECT id, kth_id, versi_ke, koordinat_x_raw, koordinat_y_raw, koordinat_x, koordinat_y FROM usulan_pupuk';
    $params = [];
    if ($onlyKthId !== null && $onlyKthId > 0) {
        $sql .= ' WHERE kth_id = ?';
        $params[] = $onlyKthId;
    }
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll();

    $updCount = 0;
    $affectedKthVersi = [];
    $updStmt = $pdo->prepare('UPDATE usulan_pupuk SET koordinat_x = ?, koordinat_y = ? WHERE id = ?');

    foreach ($rows as $r) {
        $rawX = !empty($r['koordinat_x_raw']) ? (string)$r['koordinat_x_raw'] : ($r['koordinat_x'] !== null ? (string)$r['koordinat_x'] : '');
        $rawY = !empty($r['koordinat_y_raw']) ? (string)$r['koordinat_y_raw'] : ($r['koordinat_y'] !== null ? (string)$r['koordinat_y'] : '');

        if ($rawX === '' || $rawY === '') continue;

        $conv = parse_dan_konversi_koordinat($rawX, $rawY);
        $curX = $r['koordinat_x'] !== null ? (float)$r['koordinat_x'] : null;
        $curY = $r['koordinat_y'] !== null ? (float)$r['koordinat_y'] : null;
        $newX = $conv['x'];
        $newY = $conv['y'];

        if ($newX !== null && $newY !== null) {
            $isOldUtmOrWrong = ($curX === null || $curY === null || $curX > 180 || $curX < -180 || $curY > 90 || $curY < -90);
            $isDiff = abs(($curX ?? 0) - $newX) > 0.000001 || abs(($curY ?? 0) - $newY) > 0.000001;

            if ($isOldUtmOrWrong || ($conv['is_utm'] && $isDiff)) {
                $updStmt->execute([$newX, $newY, (int)$r['id']]);
                $updCount++;
                $vKe = (int)($r['versi_ke'] ?? 1);
                if ($vKe <= 0) $vKe = 1;
                $kKey = $r['kth_id'] . '_' . $vKe;
                $affectedKthVersi[$kKey] = [
                    'kth_id' => (int)$r['kth_id'],
                    'versi_ke' => $vKe,
                ];
            }
        }
    }

    // Jika diminta re-verifikasi dan ada baris yang diperbarui
    if ($reverifikasi && $updCount > 0) {
        require_once __DIR__ . '/verify.php';
        foreach ($affectedKthVersi as $item) {
            $kId = $item['kth_id'];
            $vKe = $item['versi_ke'];
            try {
                $h = verifikasi_satu_kth($pdo, $kId, $vKe);

                // Sinkronkan ke kth_versi_usulan jika ada
                try {
                    $rekom = ($h['tidak'] === 0 && $h['luar'] === 0) ? 'Dapat Ditindaklanjuti' : 'Perlu Revisi';
                    $pdo->prepare('UPDATE kth_versi_usulan SET jumlah_sesuai_sk = ?, jumlah_tidak_sesuai_sk = ?, jumlah_dalam_peta = ?, jumlah_luar_peta = ?, rekomendasi = ? WHERE kth_id = ? AND versi_ke = ?')
                        ->execute([$h['sesuai'], $h['tidak'], $h['dalam'], $h['luar'], $rekom, $kId, $vKe]);
                } catch (Throwable $eV) {}

                // Sinkronkan ke laporan jika ada
                try {
                    $pdo->prepare('UPDATE laporan SET total_petani=?, jumlah_sesuai_sk=?, jumlah_tidak_sesuai_sk=?, jumlah_dalam_peta=?, jumlah_luar_peta=? WHERE kth_id=? AND versi_ke=?')
                        ->execute([$h['total'], $h['sesuai'], $h['tidak'], $h['dalam'], $h['luar'], $kId, $vKe]);
                } catch (Throwable $eL) {}
            } catch (Throwable $eVer) {
                // Abaikan kesalahan parsial
            }
        }
    }

    return [
        'total_diupdate' => $updCount,
        'kth_terpengaruh' => count($affectedKthVersi),
        'daftar_kth' => array_values($affectedKthVersi),
    ];
}
