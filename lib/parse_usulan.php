<?php
// Parser Excel usulan pupuk (PhpSpreadsheet).
// Fleksibel terhadap posisi kolom: baris header dideteksi dari sel yang
// mengandung kata "NIK" dan "NAMA". Kolom koordinat mendukung varian:
//  - dua kolom terpisah (X di satu kolom, Y di kolom lain, header gabungan
//    "TITIK KOORDINAT LAHAN" yang di-merge), atau
//  - satu kolom berisi "X: ... Y: ..." sekaligus.
require_once __DIR__ . '/helpers.php';

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

    // 1) Cari baris header (berisi NIK + NAMA), scan 15 baris pertama.
    $headerRow = null; $colMap = [];
    for ($r = 1; $r <= min(15, $maxRow); $r++) {
        $cells = [];
        for ($c = 1; $c <= $maxCol; $c++) {
            $v = trim((string)$ws->getCell([$c, $r])->getCalculatedValue());
            $cells[$c] = mb_strtoupper($v, 'UTF-8');
        }
        $joined = implode(' ', $cells);
        if (strpos($joined, 'NIK') !== false && strpos($joined, 'NAMA') !== false) {
            $headerRow = $r;
            foreach ($cells as $c => $v) {
                if ($v === '') continue;
                if (strpos($v, 'NIK') !== false && !isset($colMap['nik'])) $colMap['nik'] = $c;
                elseif (strpos($v, 'NAMA') !== false && !isset($colMap['nama'])) $colMap['nama'] = $c;
                elseif (strpos($v, 'KELAMIN') !== false || $v === 'L/P' || $v === 'JENIS KELAMIN') $colMap['jk'] = $c;
                elseif ($v === 'RT') $colMap['rt'] = $c;
                elseif ($v === 'RW') $colMap['rw'] = $c;
                elseif (strpos($v, 'DESA') !== false) $colMap['desa'] = $c;
                elseif (strpos($v, 'KECAMATAN') !== false || strpos($v, 'KEC') !== false) $colMap['kecamatan'] = $c;
                elseif (strpos($v, 'POLA') !== false) $colMap['pola'] = $c;
                elseif (strpos($v, 'PETAK') !== false) $colMap['petak'] = $c;
                elseif (strpos($v, 'LUAS') !== false) $colMap['luas'] = $c;
                elseif (strpos($v, 'SK') !== false || strpos($v, 'PKS') !== false) $colMap['no_sk'] = $c;
                elseif (strpos($v, 'KOORDINAT') !== false || strpos($v, 'TITIK') !== false || strpos($v, 'EASTING') !== false || strpos($v, 'LONG') !== false || $v === 'X') {
                    if (!isset($colMap['x'])) {
                        $colMap['x'] = $c;
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
                } elseif (strpos($v, 'NORTHING') !== false || strpos($v, 'LAT') !== false || $v === 'Y') {
                    $colMap['y'] = $c;
                }
            }
            // Nomor urut: kolom pertama bila headernya NO/NOMOR
            foreach ($cells as $c => $v) {
                if ($v === 'NO' || $v === 'NOMOR' || $v === 'NO.') { $colMap['no'] = $c; break; }
            }
            break;
        }
    }

    if ($headerRow === null) {
        throw new RuntimeException(
            "Format berkas Excel tidak sesuai standar usulan pupuk.\n" .
            "Baris judul/header kolom (NIK, NAMA, LUAS LAHAN, TITIK KOORDINAT) tidak ditemukan pada 15 baris pertama sheet.\n" .
            "Pastikan Anda mengunggah file Excel dengan format tabel yang benar. [UNDUH_TEMPLATE]"
        );
    }

    // Jika kolom X ada tapi Y belum terpetakan, cek kolom berikutnya
    if (isset($colMap['x']) && !isset($colMap['y'])) {
        $nextC = $colMap['x'] + 1;
        if ($nextC <= $maxCol && !in_array($nextC, $colMap, true)) {
            $colMap['y'] = $nextC;
        }
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

    // Validasi kelengkapan kolom wajib
    $missing = [];
    if (!isset($colMap['nik'])) $missing[] = 'NIK';
    if (!isset($colMap['nama'])) $missing[] = 'NAMA';
    if (!isset($colMap['luas'])) $missing[] = 'LUAS LAHAN (Ha)';
    if (!isset($colMap['x']) && !isset($colMap['y'])) $missing[] = 'TITIK KOORDINAT LAHAN (X & Y)';

    if (!empty($missing)) {
        throw new RuntimeException(
            "Format kolom Excel tidak sesuai standar usulan pupuk.\n" .
            "Kolom wajib berikut tidak ditemukan pada baris header: " . implode(', ', $missing) . ".\n" .
            "Pastikan format tabel Excel Anda mengikuti standar resmi (NO, NIK, NAMA, JENIS KELAMIN, RT, RW, DESA, KECAMATAN, POLA TANAM, PETAK, LUAS LAHAN, NO. PKS/SK, TITIK KOORDINAT LAHAN). [UNDUH_TEMPLATE]"
        );
    }

    // Fallback posisi default untuk kolom pendukung
    $colMap += ['no'=>1,'nik'=>2,'nama'=>3,'jk'=>4,'rt'=>5,'rw'=>6,'desa'=>7,'kecamatan'=>8,
                'pola'=>9,'petak'=>10,'luas'=>11,'no_sk'=>12,'x'=>13,'y'=>14];

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
        // Baris tanda tangan/footer (cth. "Mengetahui, Kepala Desa ...") tidak punya
        // NIK digit maupun nama — lewati agar tidak masuk sebagai data petani.
        if (strlen(norm_nik($nik)) < 10 && mb_strlen(norm_nama($nama), 'UTF-8') < 3) {
            continue;
        }
        $coord = parse_dan_konversi_koordinat($xRaw, $yRaw);
        $x = $coord['x'];
        $y = $coord['y'];
        if ($xRaw !== '' && $x === null) $errors[] = "Baris $r: koordinat X tidak terbaca ('$xRaw').";
        if ($yRaw !== '' && $y === null) $errors[] = "Baris $r: koordinat Y tidak terbaca ('$yRaw').";
        $luas = str_replace(',', '.', (string)$get($colMap['luas']));
        
        $rawNo = isset($colMap['no']) ? $get($colMap['no']) : '';
        $cleanNo = preg_replace('/\D/', '', (string)$rawNo);
        $noVal = null;
        if ($cleanNo !== '' && strlen($cleanNo) <= 7 && (int)$cleanNo > 0 && (int)$cleanNo <= 2000000) {
            $noVal = (int)$cleanNo;
        } else {
            $noVal = count($rows) + 1;
        }

        $rows[] = [
            'no' => $noVal,
            'nik' => $nik, 'nama' => $nama,
            'jk' => mb_strtoupper((string)$get($colMap['jk']), 'UTF-8'),
            'rt' => (string)$get($colMap['rt']), 'rw' => (string)$get($colMap['rw']),
            'desa' => (string)$get($colMap['desa']), 'kecamatan' => (string)$get($colMap['kecamatan']),
            'pola' => (string)$get($colMap['pola']), 'petak' => (string)$get($colMap['petak']),
            'luas' => is_numeric($luas) ? (float)$luas : null,
            'no_sk' => (string)$get($colMap['no_sk']),
            'x_raw' => $xRaw, 'y_raw' => $yRaw, 'x' => $x, 'y' => $y,
            'tipe_koordinat' => $coord['tipe'] ?? 'Geografis (WGS84)',
            'is_utm' => $coord['is_utm'] ?? false,
        ];
    }
    
    $utmCount = 0;
    foreach ($rows as $rw) {
        if (!empty($rw['is_utm'])) $utmCount++;
    }
    $formatKoordinat = ($utmCount > 0) ? 'UTM Zona 49S (Otomatis Dikonversi ke Geografis WGS84)' : 'Geografis (WGS84)';

    if (empty($rows)) {
        throw new RuntimeException(
            "Berkas Excel tidak memuat data usulan petani (0 baris data ditemukan di bawah judul kolom).\n" .
            "Pastikan Anda telah mengisi data pemohon pada baris tabel di bawah header. [UNDUH_TEMPLATE]"
        );
    }

    return [
        'header_row' => $headerRow,
        'rows' => $rows,
        'errors' => $errors,
        'format_koordinat' => $formatKoordinat,
        'utm_count' => $utmCount
    ];
}
