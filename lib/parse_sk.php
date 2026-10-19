<?php
// Parser Excel/CSV Daftar Anggota SK (PhpSpreadsheet).
// Membaca file Excel/CSV hasil ekstraksi dokumen SK dan melakukan validasi awal.
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/template_check.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Cari baris header daftar anggota SK dan petakan kolomnya.
 * Header dianggap sesuai bila memuat kolom NIK dan NAMA.
 */
function analisis_header_sk($ws, int $maxRow, int $maxCol): array {
    $kandidat = null;
    $contohBaris = null;
    $contohRow = null;

    for ($r = 1; $r <= min(20, $maxRow); $r++) {
        $cells = [];
        $colMap = [];
        $hasNik = false;
        $hasNama = false;
        $adaTeks = false;

        for ($c = 1; $c <= $maxCol; $c++) {
            $v = trim((string)$ws->getCell([$c, $r])->getCalculatedValue());
            $cells[$c] = $v;
            if ($v !== '') $adaTeks = true;

            $key = template_key_sk($v);
            if ($key === null) continue;
            if ($key === 'nik') $hasNik = true;
            if ($key === 'nama') $hasNama = true;
            if (!isset($colMap[$key])) $colMap[$key] = $c;
        }

        if ($contohBaris === null && $adaTeks) {
            $contohBaris = $cells;
            $contohRow = $r;
        }
        if (!$hasNik && !$hasNama) continue;

        $info = [
            'row' => $r,
            'cells' => $cells,
            'col_map' => $colMap,
            'has_nik' => $hasNik,
            'has_nama' => $hasNama,
            'valid' => ($hasNik && $hasNama),
        ];
        if ($info['valid']) return $info;
        if ($kandidat === null) $kandidat = $info;
    }

    if ($kandidat !== null) return $kandidat;

    return [
        'row' => null,
        'cells' => [],
        'col_map' => [],
        'has_nik' => false,
        'has_nama' => false,
        'valid' => false,
        'contoh_baris' => $contohBaris,
        'contoh_row' => $contohRow,
    ];
}

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
        $analisis = analisis_header_sk($ws, $maxRow, $maxCol);
        $headerRow = $analisis['row'];
        $colNik = $analisis['has_nik'] ? ($analisis['col_map']['nik'] ?? null) : null;
        $colNama = $analisis['has_nama'] ? ($analisis['col_map']['nama'] ?? null) : null;
        $kolomHilang = [];
        foreach (['nik' => 'NIK', 'nama' => 'NAMA'] as $key => $label) {
            if (!isset($analisis['col_map'][$key])) $kolomHilang[] = $label;
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
            'has_nik_header' => $analisis['valid'],
            'missing_header' => $kolomHilang,
            'header_labels' => template_header_list($analisis['cells']),
            'samples' => $samples,
        ];
    }
    return $out;
}

function parse_excel_sk(string $path, ?string $sheetName = null): array {
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    // Gunakan ReadDataOnly agar file dengan style hingga 1M baris tidak makan memori
    try {
        $reader = IOFactory::createReaderForFile($path);
        if (method_exists($reader, 'setReadDataOnly')) $reader->setReadDataOnly(true);
        $wb = $reader->load($path);
    } catch (Throwable $e) {
        $wb = IOFactory::load($path);
    }
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

    // 1) Cari dan validasi baris header daftar anggota SK.
    $analisis = analisis_header_sk($ws, $maxRow, $maxCol);
    if (!$analisis['valid']) {
        $missing = [];
        if (!$analisis['has_nik']) $missing[] = 'NIK';
        if (!$analisis['has_nama']) $missing[] = 'NAMA';

        if ($analisis['row'] === null) {
            $contoh = template_header_list($analisis['contoh_baris'] ?? []);
            throw new RuntimeException(
                "Format daftar anggota SK TIDAK SESUAI template.\n" .
                "Sheet \"" . $ws->getTitle() . "\" — baris header (wajib memuat kolom NIK dan NAMA) tidak ditemukan pada 20 baris pertama.\n" .
                "Baris pertama yang terbaca: " . ($contoh !== '' ? $contoh : '(sheet kosong)') . ".\n" .
                "Pastikan file berisi tabel lampiran SK dengan header kolom NIK dan NAMA. Bila file memiliki beberapa sheet, pilih sheet yang benar."
            );
        }

        $terdeteksi = template_header_list($analisis['cells']);
        throw new RuntimeException(
            "Format daftar anggota SK TIDAK SESUAI template.\n" .
            "Sheet \"" . $ws->getTitle() . "\" — header terbaca pada baris {$analisis['row']}.\n" .
            "Kolom wajib yang tidak ditemukan: " . implode(', ', $missing) . ".\n" .
            "Header yang terbaca: " . ($terdeteksi !== '' ? $terdeteksi : '(tidak ada teks header)') . ".\n" .
            "Template daftar anggota SK minimal memuat kolom NIK dan NAMA (didukung juga NO, JENIS KELAMIN, DESA, KECAMATAN, CATATAN). Perbaiki nama header atau pilih sheet yang benar, lalu unggah ulang."
        );
    }

    $headerRow = (int)$analisis['row'];
    $colMap = $analisis['col_map'];

    $headerWarnings = [];
    foreach (['jk' => 'JENIS KELAMIN (L/P)', 'desa' => 'DESA', 'kecamatan' => 'KECAMATAN'] as $key => $label) {
        if (!isset($colMap[$key])) {
            $headerWarnings[] = "Kolom {$label} tidak ditemukan (opsional; pencocokan tetap memakai NIK dan NAMA).";
        }
    }

    $rows = [];
    $seenNik = [];
    $perluDicekCount = 0;
    $emptyStreak = 0;

    for ($r = $headerRow + 1; $r <= $maxRow; $r++) {
        $get = function(int $c) use ($ws, $r) {
            $v = $ws->getCell([$c, $r])->getCalculatedValue();
            return ($v !== null) ? trim((string)$v) : '';
        };

        $noRaw = isset($colMap['no']) ? $get($colMap['no']) : '';
        $nama = norm_nama($get($colMap['nama']));
        $nikRaw = $get($colMap['nik']);
        $nik = norm_nik($nikRaw);
        $jk = isset($colMap['jk']) ? mb_strtoupper($get($colMap['jk']), 'UTF-8') : '';
        $desa = isset($colMap['desa']) ? norm_nama($get($colMap['desa'])) : '';
        $kecamatan = isset($colMap['kecamatan']) ? norm_nama($get($colMap['kecamatan'])) : '';
        $catatanAwal = isset($colMap['catatan']) ? $get($colMap['catatan']) : '';

        // Jika satu baris kosong total, lewati (dengan deteksi akhir data untuk file dengan 1M baris terformat)
        if ($nama === '' && $nik === '' && $desa === '' && $kecamatan === '') {
            $emptyStreak++;
            if ($emptyStreak >= 100 && $r > $headerRow + 50) {
                $adaLanjutan = false;
                for ($k = 1; $k <= 50 && ($r + $k) <= $maxRow; $k++) {
                    $peek = trim((string)$ws->getCell([$colMap['nik'] ?? 3, $r + $k])->getCalculatedValue())
                         . trim((string)$ws->getCell([$colMap['nama'] ?? 2, $r + $k])->getCalculatedValue());
                    if ($peek !== '') {
                        $adaLanjutan = true;
                        break;
                    }
                }
                if (!$adaLanjutan) break;
            }
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
            $catatanList[] = 'NIK tidak valid: "' . $nikRaw . '" (harus 16 digit, terbaca ' . strlen($nik) . ' digit)';
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
        'template_ok' => true,
        'header_warnings' => $headerWarnings,
        'rows' => $rows,
        'total' => count($rows),
        'perlu_dicek_count' => $perluDicekCount,
    ];
}
