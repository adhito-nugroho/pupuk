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
    $wb = IOFactory::load($path);
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
                if ($v === '' ) continue;
                if (strpos($v, 'NIK') !== false && !isset($colMap['nik'])) $colMap['nik'] = $c;
                elseif (strpos($v, 'NAMA') !== false && !isset($colMap['nama'])) $colMap['nama'] = $c;
                elseif (strpos($v, 'KELAMIN') !== false || $v === 'L/P' || $v === 'JENIS KELAMIN') $colMap['jk'] = $c;
                elseif ($v === 'RT') $colMap['rt'] = $c;
                elseif ($v === 'RW') $colMap['rw'] = $c;
                elseif (strpos($v, 'DESA') !== false) $colMap['desa'] = $c;
                elseif (strpos($v, 'KECAMATAN') !== false) $colMap['kecamatan'] = $c;
                elseif (strpos($v, 'POLA') !== false) $colMap['pola'] = $c;
                elseif (strpos($v, 'PETAK') !== false) $colMap['petak'] = $c;
                elseif (strpos($v, 'LUAS') !== false) $colMap['luas'] = $c;
                elseif (strpos($v, 'SK') !== false || strpos($v, 'PKS') !== false) $colMap['no_sk'] = $c;
                elseif (strpos($v, 'KOORDINAT') !== false || $v === 'X' || strpos($v, 'TITIK') !== false) {
                    // Bisa 1 kolom gabungan atau 2 kolom X | Y (merge header).
                    if (!isset($colMap['x'])) $colMap['x'] = $c;
                    elseif (!isset($colMap['y'])) $colMap['y'] = $c;
                } elseif ($v === 'Y') {
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
        throw new RuntimeException('Baris header (NIK + NAMA) tidak ditemukan di 15 baris pertama sheet.');
    }
    // Fallback posisi default sesuai format contoh (header baris ke-4):
    // A=No B=NIK C=Nama D=JK E=RT F=RW G=Desa H=Kec I=Pola J=Petak K=Luas L=NoSK M=X N=Y
    $colMap += ['no'=>1,'nik'=>2,'nama'=>3,'jk'=>4,'rt'=>5,'rw'=>6,'desa'=>7,'kecamatan'=>8,
                'pola'=>9,'petak'=>10,'luas'=>11,'no_sk'=>12,'x'=>13,'y'=>14];

    // Jika kolom Y tidak ketemu tapi X ketemu (single kolom), biarkan y = x (dipecah per baris).
    $rows = []; $errors = [];
    for ($r = $headerRow + 1; $r <= $maxRow; $r++) {
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
        $x = clean_koordinat($xRaw);
        $y = clean_koordinat($yRaw);
        if ($xRaw !== '' && $x === null) $errors[] = "Baris $r: koordinat X tidak terbaca ('$xRaw').";
        if ($yRaw !== '' && $y === null) $errors[] = "Baris $r: koordinat Y tidak terbaca ('$yRaw').";
        $luas = str_replace(',', '.', (string)$get($colMap['luas']));
        $rows[] = [
            'no' => $get($colMap['no']) !== '' ? (int)preg_replace('/\D/', '', (string)$get($colMap['no'])) : null,
            'nik' => $nik, 'nama' => $nama,
            'jk' => mb_strtoupper((string)$get($colMap['jk']), 'UTF-8'),
            'rt' => (string)$get($colMap['rt']), 'rw' => (string)$get($colMap['rw']),
            'desa' => (string)$get($colMap['desa']), 'kecamatan' => (string)$get($colMap['kecamatan']),
            'pola' => (string)$get($colMap['pola']), 'petak' => (string)$get($colMap['petak']),
            'luas' => is_numeric($luas) ? (float)$luas : null,
            'no_sk' => (string)$get($colMap['no_sk']),
            'x_raw' => $xRaw, 'y_raw' => $yRaw, 'x' => $x, 'y' => $y,
        ];
    }
    return ['header_row' => $headerRow, 'rows' => $rows, 'errors' => $errors];
}
