<?php
// Parser Excel/CSV Daftar Anggota SK (PhpSpreadsheet).
// Membaca file Excel/CSV hasil ekstraksi dokumen SK dan melakukan validasi awal.
require_once __DIR__ . '/helpers.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

function parse_excel_sk(string $path): array {
    $wb = IOFactory::load($path);
    $ws = $wb->getActiveSheet();
    $maxRow = $ws->getHighestDataRow();
    $maxCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($ws->getHighestDataColumn());

    // 1) Cari baris header (berisi NIK dan NAMA / DESA), scan 15 baris pertama
    $headerRow = null;
    $colMap = [];
    for ($r = 1; $r <= min(15, $maxRow); $r++) {
        $cells = [];
        for ($c = 1; $c <= $maxCol; $c++) {
            $v = trim((string)$ws->getCell([$c, $r])->getCalculatedValue());
            $cells[$c] = mb_strtoupper($v, 'UTF-8');
        }
        $joined = implode(' ', $cells);
        if (strpos($joined, 'NIK') !== false && (strpos($joined, 'NAMA') !== false || strpos($joined, 'DESA') !== false)) {
            $headerRow = $r;
            foreach ($cells as $c => $v) {
                if ($v === '') continue;
                if (strpos($v, 'NIK') !== false && !isset($colMap['nik'])) $colMap['nik'] = $c;
                elseif (strpos($v, 'NAMA') !== false && !isset($colMap['nama'])) $colMap['nama'] = $c;
                elseif ($v === 'L/P' || $v === 'JK' || strpos($v, 'KELAMIN') !== false) $colMap['jk'] = $c;
                elseif (strpos($v, 'DESA') !== false) $colMap['desa'] = $c;
                elseif (strpos($v, 'KECAMATAN') !== false || strpos($v, 'KEC') !== false) $colMap['kecamatan'] = $c;
                elseif (strpos($v, 'CATATAN') !== false || strpos($v, 'NOTE') !== false) $colMap['catatan'] = $c;
            }
            foreach ($cells as $c => $v) {
                if ($v === 'NO' || $v === 'NOMOR' || $v === 'NO.') { $colMap['no'] = $c; break; }
            }
            break;
        }
    }

    if ($headerRow === null) {
        // Fallback default: A=No, B=Nama, C=NIK, D=L/P, E=Desa, F=Kecamatan, G=Catatan
        $headerRow = 1;
    }
    $colMap += ['no' => 1, 'nama' => 2, 'nik' => 3, 'jk' => 4, 'desa' => 5, 'kecamatan' => 6, 'catatan' => 7];

    $rows = [];
    $seenNik = [];
    $perluDicekCount = 0;

    for ($r = $headerRow + 1; $r <= $maxRow; $r++) {
        $get = function(int $c) use ($ws, $r) {
            $v = $ws->getCell([$c, $r])->getCalculatedValue();
            return ($v !== null) ? trim((string)$v) : '';
        };

        $noRaw = $get($colMap['no']);
        $nama = norm_nama($get($colMap['nama']));
        $nikRaw = $get($colMap['nik']);
        $nik = norm_nik($nikRaw);
        $jk = mb_strtoupper($get($colMap['jk']), 'UTF-8');
        $desa = norm_nama($get($colMap['desa']));
        $kecamatan = norm_nama($get($colMap['kecamatan']));
        $catatanAwal = $get($colMap['catatan'] ?? 999);

        // Jika satu baris kosong total, lewati
        if ($nama === '' && $nik === '' && $desa === '' && $kecamatan === '') {
            continue;
        }

        $noUrut = is_numeric($noRaw) ? (int)$noRaw : count($rows) + 1;
        $perluDicek = false;
        $catatanList = [];

        if ($catatanAwal !== '') {
            $catatanList[] = $catatanAwal;
            $perluDicek = true;
        }

        // Validasi NIK 16 digit
        if (strlen($nik) !== 16 || !ctype_digit($nik)) {
            $perluDicek = true;
            $catatanList[] = 'NIK tidak valid (bukan 16 digit)';
        } elseif (isset($seenNik[$nik])) {
            // NIK duplikat
            $perluDicek = true;
            $catatanList[] = 'NIK duplikat (sama dengan baris no ' . $seenNik[$nik] . ')';
        } else {
            $seenNik[$nik] = $noUrut;
        }

        // Validasi nama tidak boleh kosong
        if ($nama === '') {
            $perluDicek = true;
            $catatanList[] = 'Nama kosong';
        }

        if ($perluDicek) {
            $perluDicekCount++;
        }

        $rows[] = [
            'no' => $noUrut,
            'nama' => $nama,
            'nik' => $nik !== '' ? $nik : $nikRaw,
            'jenis_kelamin' => in_array($jk, ['L', 'P'], true) ? $jk : '',
            'desa' => $desa,
            'kecamatan' => $kecamatan,
            'catatan' => implode(' | ', $catatanList),
            'perlu_dicek' => $perluDicek,
        ];
    }

    return [
        'header_row' => $headerRow,
        'rows' => $rows,
        'total' => count($rows),
        'perlu_dicek_count' => $perluDicekCount,
    ];
}
