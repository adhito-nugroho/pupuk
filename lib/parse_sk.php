<?php
// Parser Excel/CSV Daftar Anggota SK (PhpSpreadsheet).
// Membaca file Excel/CSV hasil ekstraksi dokumen SK dan melakukan validasi awal.
require_once __DIR__ . '/helpers.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Ambil daftar nama sheet di workbook Excel (untuk konfirmasi multi-sheet).
 * Untuk CSV mengembalikan array kosong.
 */
function get_sheet_names_sk(string $path): array {
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if ($ext === 'csv') return [];
    try {
        $reader = IOFactory::createReaderForFile($path);
        // listWorksheetNames butuh path file; untuk reader tertentu perlu setReadDataOnly
        if (method_exists($reader, 'listWorksheetNames')) {
            return $reader->listWorksheetNames($path);
        }
    } catch (Throwable $e) {
        // fallback ke load
    }
    try {
        $wb = IOFactory::load($path);
        return $wb->getSheetNames();
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Ringkasan preview per sheet: jumlah baris data terdeteksi dan header row.
 * Dipakai di halaman pilih_sheet agar user tahu sheet mana yang berisi data.
 */
function preview_sheets_sk(string $path): array {
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if ($ext === 'csv') return [];
    try {
        $reader = IOFactory::createReaderForFile($path);
        if (method_exists($reader, 'setReadDataOnly')) $reader->setReadDataOnly(true);
        $wb = $reader->load($path);
    } catch (Throwable $e) {
        try { $wb = IOFactory::load($path); } catch (Throwable $e2) { return []; }
    }
    $out = [];
    foreach ($wb->getSheetNames() as $idx => $name) {
        $ws = $wb->getSheetByName($name);
        if (!$ws) continue;
        $maxRow = $ws->getHighestDataRow();
        $maxCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($ws->getHighestDataColumn());
        // cari header NIK+NAMA
        $headerRow = null;
        $colNik = null; $colNama = null;
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
                    if (strpos($v, 'NIK') !== false && $colNik === null) $colNik = $c;
                    if (strpos($v, 'NAMA') !== false && $colNama === null) $colNama = $c;
                }
                break;
            }
        }
        // hitung baris non-kosong setelah header (dibatas untuk file dengan format hingga 1M baris)
        $dataRows = 0;
        $startRow = ($headerRow ?? 1) + 1;
        $emptyStreak = 0;
        $limitRow = min($maxRow, $startRow + 5000); // batasi scan 5000 baris setelah header
        for ($r = $startRow; $r <= $limitRow; $r++) {
            $isEmpty = true;
            for ($c = 1; $c <= $maxCol; $c++) {
                $v = trim((string)$ws->getCell([$c, $r])->getCalculatedValue());
                if ($v !== '') { $isEmpty = false; break; }
            }
            if (!$isEmpty) { $dataRows++; $emptyStreak = 0; }
            else {
                $emptyStreak++;
                if ($emptyStreak >= 30 && $r > $startRow + 50) break; // anggap selesai setelah 30 baris kosong berturut
            }
        }
        // jika maxRow sangat besar (>5000) dan dataRows masih 0, coba deteksi: jangan hitung penuh
        // ambil 2 baris contoh setelah header untuk preview
        $samples = [];
        $taken = 0;
        for ($r = $startRow; $r <= $maxRow && $taken < 2; $r++) {
            $rowVals = [];
            for ($c = 1; $c <= min(6, $maxCol); $c++) {
                $rowVals[] = trim((string)$ws->getCell([$c, $r])->getCalculatedValue());
            }
            $has = implode('', $rowVals) !== '';
            if ($has) { $samples[] = $rowVals; $taken++; }
        }
        $out[] = [
            'index' => $idx,
            'name' => $name,
            'max_row' => $maxRow,
            'header_row' => $headerRow,
            'data_rows' => $dataRows,
            'has_nik_header' => $colNik !== null,
            'samples' => $samples,
        ];
    }
    return $out;
}

function parse_excel_sk(string $path, ?string $sheetName = null): array {
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $wb = IOFactory::load($path);
    if ($sheetName !== null && $sheetName !== '' && $ext !== 'csv') {
        $ws = $wb->getSheetByName($sheetName);
        if (!$ws) {
            // fallback: cari case-insensitive
            foreach ($wb->getSheetNames() as $nm) {
                if (strcasecmp($nm, $sheetName) === 0) { $ws = $wb->getSheetByName($nm); break; }
            }
        }
        if (!$ws) {
            throw new RuntimeException("Sheet '{$sheetName}' tidak ditemukan di file Excel.");
        }
    } else {
        $ws = $wb->getActiveSheet();
    }
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
    $emptyStreak = 0;

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

        // Jika satu baris kosong total, lewati (dengan deteksi akhir data untuk file dengan 1M baris terformat)
        if ($nama === '' && $nik === '' && $desa === '' && $kecamatan === '') {
            $emptyStreak++;
            if ($emptyStreak >= 30 && $r > $headerRow + 50) break;
            continue;
        }
        $emptyStreak = 0;

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
