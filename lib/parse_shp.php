<?php
// Parser Shapefile (.zip berisi .shp + .dbf + .shx) murni PHP.
// Library: gasparesganga/php-shapefile. Tanpa GDAL/ogr2ogr.
// Output: array features [{lembaga, no_sk, desa, kec, kab, skema, luas, rings}]
// dengan rings = list of rings, tiap ring = [[lng,lat],...] (X=lng, Y=lat).
require_once __DIR__ . '/helpers.php';

/**
 * Folder temp yang TERBUKTI bisa ditulis (probe tulis file, bukan is_writable
 * yang tidak akurat di Windows). Prioritas: uploads/tmp milik aplikasi,
 * fallback ke sys temp OS. Lempar bila semuanya gagal.
 */
function app_temp_dir(): string {
    $cands = [];
    if (defined('UPLOAD_DIR')) $cands[] = UPLOAD_DIR . '/tmp';
    $cands[] = sys_get_temp_dir();
    foreach ($cands as $base) {
        if (!is_dir($base)) @mkdir($base, 0775, true);
        if (!is_dir($base)) continue;
        $probe = $base . '/.w_' . bin2hex(random_bytes(4));
        if (@file_put_contents($probe, 'x') !== false) { @unlink($probe); return $base; }
    }
    throw new RuntimeException('Tidak ada folder temp yang bisa ditulis (uploads/tmp maupun sys temp).');
}

function parse_shapefile_zip(string $zipPath, ?string $filterLembaga = null): array {
    if (!class_exists(\Shapefile\ShapefileReader::class)) {
        throw new RuntimeException('Library php-shapefile belum terpasang. Jalankan: composer install');
    }
    // Temp di dalam folder uploads milik aplikasi (is_writable() tidak bisa
    // diandalkan di Windows — buktikan dengan tulis file sungguhan).
    $tmp = app_temp_dir() . '/shp_' . bin2hex(random_bytes(6));
    if (!mkdir($tmp, 0775, true) && !is_dir($tmp)) {
        throw new RuntimeException('Gagal membuat folder temp.');
    }
    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        throw new RuntimeException('File ZIP tidak valid / corrupt.');
    }
    // Keamanan: tolak entri dengan traversal path
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        if (strpos($name, '..') !== false || strpos($name, ':') !== false) {
            $zip->close();
            throw new RuntimeException('Isi ZIP mencurigakan (path traversal).');
        }
    }
    $zip->extractTo($tmp);
    $zip->close();

    // Kumpulkan file .shp (rekursif, karena kadang di dalam subfolder)
    $shpFiles = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if (strtolower($f->getExtension()) === 'shp') $shpFiles[] = $f->getPathname();
    }
    if (!$shpFiles) throw new RuntimeException('ZIP tidak berisi file .shp.');

    $features = []; $catatan = [];
    foreach ($shpFiles as $shpPath) {
        $base = substr($shpPath, 0, -4);
        $ada = function(string $e) use ($base): bool {
            return is_file($base . $e) || is_file($base . strtoupper($e));
        };
        // .shp + .dbf wajib; .shx opsional (lib bisa jalan tanpa SHX via OPTION_IGNORE_FILE_SHX)
        if (!$ada('.shp') || !$ada('.dbf')) {
            $catatan[] = basename($shpPath) . ': pasangan .shp/.dbf tidak lengkap — dilewati.';
            continue;
        }
        try {
            $reader = new \Shapefile\ShapefileReader($shpPath);
        } catch (Throwable $e) {
            $catatan[] = basename($shpPath) . ': gagal dibaca (' . $e->getMessage() . ')';
            continue;
        }
        $n = 0;
        try {
            while ($rec = $reader->fetchRecord()) {
                if ($rec->isDeleted()) continue;
                $n++;
                try { $dbf = $rec->getDataArray() ?: []; } catch (Throwable $e) { $dbf = []; }
                // Normalisasi key DBF ke UPPERCASE (lib kadang mengembalikan lower/upper campur)
                $D = [];
                foreach ((array)$dbf as $k => $v) {
                    $D[mb_strtoupper(trim((string)$k), 'UTF-8')] = is_string($v) ? trim($v) : $v;
                }
                $rings = ekstrak_rings($rec);
                $features[] = [
                    'lembaga' => (string)($D['LEMBAGA'] ?? ''),
                    'no_sk'   => (string)($D['NO_SK'] ?? ''),
                    'desa'    => (string)($D['NAMA_DESA'] ?? ''),
                    'kec'     => (string)($D['NAMA_KEC'] ?? ''),
                    'kab'     => (string)($D['NAMA_KAB'] ?? ''),
                    'skema'   => (string)($D['SKEMA'] ?? ''),
                    'luas_sk' => (string)($D['LUAS_SK'] ?? ''),
                    'layer'   => basename($shpPath),
                    'rings'   => $rings,
                ];
            }
        } catch (Throwable $e) {
            $catatan[] = basename($shpPath) . ': berhenti di record ke-' . ($n + 1) . ' (' . $e->getMessage() . ')';
        }
        $catatan[] = basename($shpPath) . ": $n fitur terbaca.";
    }

    // Filter opsional ke satu LEMBAGA (bila shapefile berisi banyak kelompok, mis. 105 grup)
    $dipakai = $features;
    $filterInfo = null;
    if ($filterLembaga !== null && trim($filterLembaga) !== '') {
        $key = trim($filterLembaga);
        $cocok = array_values(array_filter($features, function($f) use ($key) {
            $lem = (string)($f['lembaga'] ?? '');
            if ($lem === '') return false;
            return mb_stripos($lem, $key, 0, 'UTF-8') !== false
                || mb_stripos($key, $lem, 0, 'UTF-8') !== false;
        }));
        if ($cocok) { $dipakai = $cocok; $filterInfo = 'Filter "' . $key . '": ' . count($cocok) . ' fitur cocok.'; }
        else $filterInfo = 'Filter "' . $key . '" tidak cocok dengan LEMBAGA manapun — memakai seluruh ' . count($features) . ' fitur.';
    }

    // Bersihkan temp
    try {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) { $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname()); }
        @rmdir($tmp);
    } catch (Throwable $e) { }

    $totalRings = 0; $totalPts = 0;
    foreach ($dipakai as $f) { $totalRings += count($f['rings']); foreach ($f['rings'] as $rg) $totalPts += count($rg); }
    return [
        'features' => $dipakai,
        'semua_features' => $features,
        'total_fitur' => count($dipakai),
        'total_ring' => $totalRings,
        'total_titik' => $totalPts,
        'catatan' => $catatan,
        'filter_info' => $filterInfo,
    ];
}

/**
 * Ambil semua ring [[lng,lat],...] dari satu record geometri.
 * Mendukung Polygon dan MultiPolygon (termasuk varian Z/M — Z/M diabaikan,
 * hanya X=lng dan Y=lat yang dipakai).
 */
function ekstrak_rings($rec): array {
    $rings = [];
    $tampung = function($linestring) use (&$rings) {
        try {
            $arr = $linestring->getArray();
            $pts = $arr['points'] ?? [];
        } catch (Throwable $e) { return; }
        $ring = [];
        foreach ((array)$pts as $pt) {
            if (is_array($pt) && isset($pt['x'], $pt['y'])) $ring[] = [(float)$pt['x'], (float)$pt['y']];
        }
        if (count($ring) >= 3) $rings[] = $ring;
    };
    try {
        if ($rec instanceof \Shapefile\Geometry\MultiPolygon) {
            foreach ($rec->getPolygons() as $poly) {
                foreach ($poly->getRings() as $ring) $tampung($ring);
            }
        } elseif ($rec instanceof \Shapefile\Geometry\Polygon) {
            foreach ($rec->getRings() as $ring) $tampung($ring);
        }
    } catch (Throwable $e) { }
    return $rings;
}

/** Ambil daftar ring datar [[lng,lat],...] dari geometry_json tersimpan. */
function rings_dari_geometry_json(string $json): array {
    $d = json_decode($json, true);
    if (!is_array($d)) return [];
    // Format baru: {"features":[{rings:...}]} atau list features langsung
    $feats = $d['features'] ?? $d;
    if (is_array($feats) && isset($feats[0]) && is_array($feats[0]) && array_key_exists('rings', $feats[0])) {
        $out = [];
        foreach ($feats as $f) foreach ((array)($f['rings'] ?? []) as $rg) $out[] = $rg;
        return $out;
    }
    // Format lama: list of rings langsung
    if (is_array($d) && isset($d[0]) && is_array($d[0]) && isset($d[0][0]) && is_array($d[0][0])) return $d;
    return [];
}
