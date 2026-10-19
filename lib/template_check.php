<?php
function template_norm_header($value): string {
    $s = mb_strtoupper(trim((string)$value), 'UTF-8');
    $s = str_replace(["\r", "\n", "\t", "\xC2\xA0"], ' ', $s);
    $s = preg_replace('/\s+/', ' ', $s) ?? $s;
    $s = str_replace(['.', ':', ';'], '', $s);
    $s = preg_replace('/\s*\/\s*/', '/', $s) ?? $s;
    return trim($s);
}

function template_header_list(array $cells): string {
    $parts = [];
    foreach ($cells as $col => $label) {
        $label = trim((string)$label);
        if ($label === '') continue;
        $letter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex((int)$col);
        $parts[] = $letter . ': ' . preg_replace('/\s+/', ' ', $label);
    }
    return implode(', ', $parts);
}

function template_key_usulan(string $value): ?string {
    $v = template_norm_header($value);
    if ($v === '') return null;

    if ($v === 'NO' || $v === 'NOMOR' || $v === 'NO URUT' || $v === 'NOURUT') return 'no';
    if (str_contains($v, 'NIK')) return 'nik';
    if (str_contains($v, 'NAMA')) return 'nama';
    if (str_contains($v, 'KELAMIN') || $v === 'L/P' || $v === 'JK' || $v === 'JENISKELAMIN') return 'jk';
    if ($v === 'RT' || $v === 'RUKUN TETANGGA') return 'rt';
    if ($v === 'RW' || $v === 'RUKUN WARGA') return 'rw';
    if (str_contains($v, 'DESA') || str_contains($v, 'KELURAHAN')) return 'desa';
    if (str_contains($v, 'KECAMATAN')) return 'kecamatan';
    if (str_contains($v, 'POLA')) return 'pola';
    if (str_contains($v, 'PETAK')) return 'petak';
    if (str_contains($v, 'LUAS')) return 'luas';
    if (str_contains($v, 'PKS') || str_contains($v, 'SK')) return 'no_sk';
    if (str_contains($v, 'KOORDINAT') || str_contains($v, 'TITIK')) return 'koordinat';
    if (str_contains($v, 'LONGITUDE') || str_contains($v, 'EASTING')
        || $v === 'LONG' || $v === 'BUJUR' || preg_match('/^X\b/', $v)) return 'x';
    if (str_contains($v, 'LATITUDE') || str_contains($v, 'NORTHING')
        || $v === 'LAT' || $v === 'LINTANG' || preg_match('/^Y\b/', $v)) return 'y';

    return null;
}

function template_key_sk(string $value): ?string {
    $v = template_norm_header($value);
    if ($v === '') return null;

    if ($v === 'NO' || $v === 'NOMOR' || $v === 'NO URUT' || $v === 'NOURUT') return 'no';
    if (str_contains($v, 'NIK') || str_contains($v, 'NOMOR INDUK')) return 'nik';
    if (str_contains($v, 'NAMA')) return 'nama';
    if (str_contains($v, 'KELAMIN') || $v === 'L/P' || $v === 'JK' || $v === 'JENISKELAMIN') return 'jk';
    if (str_contains($v, 'DESA') || str_contains($v, 'KELURAHAN')) return 'desa';
    if (str_contains($v, 'KECAMATAN')) return 'kecamatan';
    if (str_contains($v, 'CATATAN') || str_contains($v, 'KETERANGAN')) return 'catatan';

    return null;
}

function template_kolom_usulan(): array {
    return [
        'no'        => 'NO',
        'nik'       => 'NIK',
        'nama'      => 'NAMA',
        'jk'        => 'JENIS KELAMIN (L/P)',
        'rt'        => 'RT',
        'rw'        => 'RW',
        'desa'      => 'DESA',
        'kecamatan' => 'KECAMATAN',
        'pola'      => 'POLA TANAM',
        'petak'     => 'PETAK',
        'luas'      => 'LUAS LAHAN (Ha)',
        'no_sk'     => 'NO. PKS AGROFORESTY / NO.SK PS',
        'x'         => 'TITIK KOORDINAT LAHAN',
    ];
}

function template_kolom_sk(): array {
    return [
        'no'        => 'NO',
        'nama'      => 'NAMA',
        'nik'       => 'NIK',
        'jk'        => 'JENIS KELAMIN (L/P)',
        'desa'      => 'DESA',
        'kecamatan' => 'KECAMATAN',
        'catatan'   => 'CATATAN',
    ];
}
