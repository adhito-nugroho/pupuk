<?php
// Parser Excel usulan pupuk (PhpSpreadsheet).
// Fleksibel terhadap posisi kolom: baris header dideteksi dari sel yang
// mengandung kata "NIK" dan "NAMA". Kolom koordinat mendukung varian:
//  - dua kolom terpisah (X di satu kolom, Y di kolom lain, header gabungan
//    "TITIK KOORDINAT LAHAN" yang di-merge), atau
//  - satu kolom berisi "X: ... Y: ..." sekaligus.
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/template_check.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

function parse_excel_usulan(string $path): array {
    try {
        $reader = IOFactory::createReaderForFile($path);
        if (method_exists($reader, 'setReadDataOnly')) $reader->setReadDataOnly(true);
        $wb = $reader->load($path);
    } catch (Throwable $e) {
        $wb = IOFactory::load($path);
    }
    $ws = $wb->getActiveSheet();
    $maxRow = $ws->getHighestDataRow();
    $maxCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($ws->getHighestDataColumn());

    // 1) Cari baris header (berisi NIK + NAMA), scan 20 baris pertama.
    $headerRow = null; $colMap = []; $headerCells = [];
    $koordinatSatuKolom = false;
    for ($r = 1; $r <= min(20, $maxRow); $r++) {
        $cells = [];
        $keys = [];
        $adaNik = false;
        $adaNama = false;
        for ($c = 1; $c <= $maxCol; $c++) {
            $v = trim((string)$ws->getCell([$c, $r])->getCalculatedValue());
            $cells[$c] = $v;
            $key = template_key_usulan($v);
            $keys[$c] = $key;
            if ($key === 'nik') $adaNik = true;
            if ($key === 'nama') $adaNama = true;
        }
        // Header harus memiliki kolom NIK dan NAMA pada sel yang terpisah.
        if (!$adaNik || !$adaNama) continue;

        $headerRow = $r;
        $headerCells = $cells;
        foreach ($cells as $c => $v) {
            $key = $keys[$c] ?? null;
            if ($key === null) continue;

            if ($key === 'koordinat' || $key === 'x') {
                if (!isset($colMap['x'])) {
                    $colMap['x'] = $c;
                    if ($key === 'koordinat') $koordinatSatuKolom = true;

                    // Cek apakah cell ini di-merge dengan kolom berikutnya (misal M4:N4 "TITIK KOORDINAT LAHAN")
                    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
                    $cellCoord = $colLetter . $r;
                    foreach ($ws->getMergeCells() as $mRange) {
                        if (strpos($mRange, $cellCoord . ':') === 0) {
                            $mParts = explode(':', $mRange);
                            if (isset($mParts[1])) {
                                $endCol = preg_replace('/\d+/', '', $mParts[1]);
                                $endIdx = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($endCol);
                                if ($endIdx > $c && !isset($colMap['y'])) {
                                    $colMap['y'] = $endIdx;
                                }
                            }
                            break;
                        }
                    }
                } elseif (!isset($colMap['y'])) {
                    $colMap['y'] = $c;
                }
                continue;
            }
            if ($key === 'y') {
                if (!isset($colMap['y'])) $colMap['y'] = $c;
                continue;
            }
            if (!isset($colMap[$key])) $colMap[$key] = $c;
        }
        break;
    }

    if ($headerRow === null) {
        throw new RuntimeException(
            "Format berkas Excel usulan TIDAK SESUAI template resmi.\n" .
            "Baris header (wajib memuat kolom NIK dan NAMA) tidak ditemukan pada 20 baris pertama sheet \"" . $ws->getTitle() . "\".\n" .
            "Pastikan tabel usulan diawali baris judul kolom sesuai template, bukan langsung berisi data.\n" .
            "Silakan unduh template resmi di bawah, salin data Anda ke template tersebut, lalu unggah ulang. [UNDUH_TEMPLATE]"
        );
    }

    // Jika header koordinat hanya satu kolom dan tidak di-merge, periksa kolom berikutnya.
    if (isset($colMap['x']) && !isset($colMap['y'])) {
        $nextC = $colMap['x'] + 1;
        $nextLabel = $nextC <= $maxCol ? trim((string)($headerCells[$nextC] ?? '')) : '';
        $nextKey = $nextLabel !== '' ? template_key_usulan($nextLabel) : null;
        if ($nextC <= $maxCol && ($nextLabel === '' || $nextKey === 'y')) {
            $colMap['y'] = $nextC;
        } elseif ($koordinatSatuKolom) {
            // Format satu kolom berisi "X: ... Y: ..." sekaligus.
            $colMap['y'] = $colMap['x'];
        }
    }

    // Validasi seluruh kolom baku template.
    $missing = [];
    foreach (template_kolom_usulan() as $key => $label) {
        if ($key === 'x') {
            if (!isset($colMap['x'])) {
                $missing[] = $label . ' (kolom X dan Y)';
            } elseif (!isset($colMap['y'])) {
                $missing[] = $label . ' — kolom Y tidak ditemukan (template memakai dua kolom X dan Y)';
            }
        } elseif (!isset($colMap[$key])) {
            $missing[] = $label;
        }
    }

    if (!empty($missing)) {
        $terdeteksi = template_header_list($headerCells);
        throw new RuntimeException(
            "Format kolom Excel usulan TIDAK SESUAI template resmi.\n" .
            "Sheet \"" . $ws->getTitle() . "\" — header terbaca pada baris {$headerRow}.\n" .
            "Kolom yang tidak ditemukan / tidak sesuai:\n- " . implode("\n- ", $missing) . "\n" .
            "Header yang terbaca: " . ($terdeteksi !== '' ? $terdeteksi : '(tidak ada teks header)') . ".\n" .
            "Samakan nama kolom dengan template resmi (NO, NIK, NAMA, JENIS KELAMIN, RT, RW, DESA, KECAMATAN, POLA TANAM, PETAK, LUAS LAHAN, NO. PKS/SK, TITIK KOORDINAT LAHAN), lalu unggah ulang. [UNDUH_TEMPLATE]"
        );
    }

    // Cek apakah baris tepat setelah header merupakan sub-header (misal X dan Y, atau Longitude dan Latitude)
    $startRow = $headerRow + 1;
    if ($startRow <= $maxRow) {
        $subXVal = mb_strtoupper(trim((string)$ws->getCell([$colMap['x'] ?? 13, $startRow])->getCalculatedValue()), 'UTF-8');
        $subYVal = mb_strtoupper(trim((string)$ws->getCell([$colMap['y'] ?? 14, $startRow])->getCalculatedValue()), 'UTF-8');
        if (in_array($subXVal, ['X', 'LONGITUDE', 'EASTING', 'LONG', 'BUJUR']) || in_array($subYVal, ['Y', 'LATITUDE', 'NORTHING', 'LAT', 'LINTANG'])) {
            $startRow = $headerRow + 2;
        }
    }

    // Semua kolom template sudah tervalidasi di atas. Tidak ada fallback posisi
    // default agar file yang tidak sesuai template tidak dibaca secara keliru.

    // Jangan gunakan kolom no jika sama dengan kolom nik
    if (isset($colMap['no'], $colMap['nik']) && $colMap['no'] === $colMap['nik']) {
        unset($colMap['no']);
    }

    // Jika kolom Y tidak ketemu tapi X ketemu (single kolom), biarkan y = x (dipecah per baris).
    $rows = []; $errors = [];
    for ($r = $startRow; $r <= $maxRow; $r++) {
        $get = function(int $c) use ($ws, $r) {
            $v = $ws->getCell([$c, $r])->getCalculatedValue();
            if ($v === null) return '';
            if (is_bool($v)) return $v ? '1' : '0';
            return trim((string)$v);
        };
        $nik = $get($colMap['nik']); $nama = $get($colMap['nama']);
        $xRaw = $get($colMap['x']);
        $yRaw = ($colMap['y'] === $colMap['x']) ? '' : $get($colMap['y']);
        // Varian satu kolom "X: .. Y: .." sekaligus:
        if ($yRaw === '' && preg_match('/Y\s*:/i', $xRaw)) {
            $parts = preg_split('/Y\s*:/i', $xRaw);
            $xRaw = $parts[0];
            $yRaw = 'Y:' . ($parts[1] ?? '');
        }
        // Baris kosong total -> lewati (tapi hentikan bila 20 baris kosong beruntun di akhir)
        $allEmpty = ($nik === '' && $nama === '' && $xRaw === '' && $yRaw === '');
        if ($allEmpty) {
            // intip 5 baris ke depan; bila semua kosong, selesai.
            $ahead = true;
            for ($k = 1; $k <= 5 && ($r + $k) <= $maxRow; $k++) {
                if (trim((string)$ws->getCell([$colMap['nik'], $r + $k])->getCalculatedValue()) !== '' ||
                    trim((string)$ws->getCell([$colMap['nama'], $r + $k])->getCalculatedValue()) !== '') { $ahead = false; break; }
            }
            if ($ahead) break;
            continue;
        }
        $nikNorm = norm_nik($nik);
        $namaTrim = trim($nama);

        // Baris lanjutan: NIK & NAMA kosong, tetapi berisi koordinat.
        // Semua titik pada baris seperti ini milik petani pada baris data terakhir.
        if ($nikNorm === '' && $namaTrim === '') {
            if ($xRaw === '' && $yRaw === '') continue;

            if (empty($rows)) {
                $errors[] = "Baris $r: titik koordinat tambahan ditemukan sebelum data petani pertama — diabaikan.";
                continue;
            }
            if ($xRaw === '' || $yRaw === '') {
                $errors[] = "Baris $r: titik koordinat tambahan belum lengkap (X atau Y kosong) — diabaikan.";
                continue;
            }

            $coordTambahan = parse_dan_konversi_koordinat($xRaw, $yRaw);
            if ($coordTambahan['x'] === null || $coordTambahan['y'] === null) {
                $errors[] = "Baris $r: koordinat titik tambahan tidak terbaca (X='{$xRaw}', Y='{$yRaw}') — diabaikan.";
                continue;
            }

            $lastIdx = count($rows) - 1;
            if (!isset($rows[$lastIdx]['titik_tambahan'])) {
                $rows[$lastIdx]['titik_tambahan'] = [];
            }
            $rows[$lastIdx]['titik_tambahan'][] = [
                'x_raw' => $xRaw,
                'y_raw' => $yRaw,
                'x' => $coordTambahan['x'],
                'y' => $coordTambahan['y'],
                'tipe_koordinat' => $coordTambahan['tipe'] ?? 'Geografis (WGS84)',
                'is_utm' => $coordTambahan['is_utm'] ?? false,
            ];
            continue;
        }

        // Baris tanda tangan/footer (cth. "Mengetahui, Kepala Desa ...") tidak punya
        // NIK digit maupun nama — lewati agar tidak masuk sebagai data petani.
        if (strlen($nikNorm) < 10 && mb_strlen(norm_nama($nama), 'UTF-8') < 3) {
            continue;
        }
        $coord = parse_dan_konversi_koordinat($xRaw, $yRaw);
        $x = $coord['x'];
        $y = $coord['y'];
        if ($nikNorm === '') {
            $errors[] = "Baris $r: NIK kosong.";
        } elseif (strlen($nikNorm) !== 16) {
            $errors[] = "Baris $r: NIK '{$nik}' bukan 16 digit (terbaca " . strlen($nikNorm) . " digit).";
        }
        if (trim($nama) === '') {
            $errors[] = "Baris $r: NAMA kosong.";
        }

        $luasRaw = (string)$get($colMap['luas']);
        $luas = str_replace(',', '.', $luasRaw);
        if ($luasRaw === '') {
            $errors[] = "Baris $r: LUAS LAHAN kosong.";
        } elseif (!is_numeric($luas)) {
            $errors[] = "Baris $r: LUAS LAHAN '{$luasRaw}' bukan angka.";
        } elseif ((float)$luas <= 0) {
            $errors[] = "Baris $r: LUAS LAHAN harus lebih dari 0 (terbaca '{$luasRaw}').";
        }

        if ($xRaw === '' || $yRaw === '') {
            $errors[] = "Baris $r: TITIK KOORDINAT LAHAN belum lengkap (X atau Y kosong).";
        } elseif ($x === null || $y === null) {
            $errors[] = "Baris $r: koordinat X/Y tidak terbaca (X='{$xRaw}', Y='{$yRaw}').";
        }
        
        $rawNo = isset($colMap['no']) ? $get($colMap['no']) : '';
        $cleanNo = preg_replace('/\D/', '', (string)$rawNo);
        $noVal = null;
        if ($cleanNo !== '' && strlen($cleanNo) <= 7 && (int)$cleanNo > 0 && (int)$cleanNo <= 2000000) {
            $noVal = (int)$cleanNo;
        } else {
            $noVal = count($rows) + 1;
        }

        $jkRaw = (string)$get($colMap['jk']);
        $jk = norm_jk($jkRaw);
        if ($jkRaw !== '' && $jk === null) {
            $errors[] = "Baris $r: JENIS KELAMIN '{$jkRaw}' tidak dikenali (gunakan L/P).";
        }

        $rows[] = [
            'no' => $noVal,
            'nik' => $nik, 'nama' => $nama,
            'jk' => $jk ?? '',
            'rt' => (string)$get($colMap['rt']), 'rw' => (string)$get($colMap['rw']),
            'desa' => (string)$get($colMap['desa']), 'kecamatan' => (string)$get($colMap['kecamatan']),
            'pola' => (string)$get($colMap['pola']), 'petak' => (string)$get($colMap['petak']),
            'luas' => is_numeric($luas) ? (float)$luas : null,
            'no_sk' => (string)$get($colMap['no_sk']),
            'x_raw' => $xRaw, 'y_raw' => $yRaw, 'x' => $x, 'y' => $y,
            'tipe_koordinat' => $coord['tipe'] ?? 'Geografis (WGS84)',
            'is_utm' => $coord['is_utm'] ?? false,
            'titik_tambahan' => [],
        ];
    }
    
    $utmCount = 0;
    $totalTitik = 0;
    foreach ($rows as $rw) {
        if (!empty($rw['is_utm'])) $utmCount++;
        $totalTitik++;
        foreach (($rw['titik_tambahan'] ?? []) as $titik) {
            if (!empty($titik['is_utm'])) $utmCount++;
            $totalTitik++;
        }
    }
    $formatKoordinat = ($utmCount > 0) ? 'UTM Zona 49S (Otomatis Dikonversi ke Geografis WGS84)' : 'Geografis (WGS84)';

    if (empty($rows)) {
        throw new RuntimeException(
            "Berkas Excel tidak memuat data usulan petani (0 baris data ditemukan di bawah judul kolom).\n" .
            "Pastikan Anda telah mengisi data pemohon pada baris tabel di bawah header. [UNDUH_TEMPLATE]"
        );
    }

    $errorsTotal = count($errors);
    if ($errorsTotal > 100) {
        $errors = array_slice($errors, 0, 100);
    }

    return [
        'header_row' => $headerRow,
        'template_ok' => true,
        'rows' => $rows,
        'errors' => $errors,
        'errors_total' => $errorsTotal,
        'format_koordinat' => $formatKoordinat,
        'utm_count' => $utmCount,
        'total_titik' => $totalTitik
    ];
}
