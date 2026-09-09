<?php
// Mesin verifikasi: matching NIK/Nama + point-in-polygon + narasi + rekomendasi.
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/parse_shp.php';

function verifikasi_satu_kth(PDO $pdo, int $kthId): array {
    $sk = $pdo->prepare('SELECT * FROM sk_anggota WHERE kth_id = ?');
    $sk->execute([$kthId]);
    $daftarSK = $sk->fetchAll();

    $mapNik = [];
    foreach ($daftarSK as $r) {
        $mapNik[norm_nik((string)$r['nik'])] = $r;
    }

    $poly = $pdo->prepare('SELECT * FROM poligon_ps WHERE kth_id = ? ORDER BY id DESC LIMIT 1');
    $poly->execute([$kthId]);
    $polyRow = $poly->fetch();
    $rings = $polyRow ? rings_dari_geometry_json((string)$polyRow['geometry_json']) : [];

    $us = $pdo->prepare('SELECT * FROM usulan_pupuk WHERE kth_id = ? ORDER BY COALESCE(no_urut, id)');
    $us->execute([$kthId]);
    $usulan = $us->fetchAll();

    // Hapus hasil lama, hitung ulang
    $pdo->prepare('DELETE FROM hasil_verifikasi WHERE kth_id = ?')->execute([$kthId]);
    $ins = $pdo->prepare('INSERT INTO hasil_verifikasi (usulan_id, kth_id, status_sk, status_koordinat, catatan, kemiripan_nama, nama_mirip_sk) VALUES (?,?,?,?,?,?,?)');

    $cSesuai = 0; $cTidak = 0; $cDalam = 0; $cLuar = 0; $cLebihLuas = 0;
    $totalLuasUsulan = 0.0;
    foreach ($usulan as $u) {
        $nikN = norm_nik((string)$u['nik']);
        $cocok = ($nikN !== '' && isset($mapNik[$nikN])) ? $mapNik[$nikN] : null;
        if ($cocok) { $statusSK = 'Sesuai SK PS'; $cSesuai++; }
        else { $statusSK = 'Belum Sesuai SK PS'; $cTidak++; }

        $catatanPieces = [];
        $pct = null; $namaMirip = null;
        if (!$cocok) {
            [$pct, $namaMirip] = cari_nama_mirip((string)$u['nama'], $daftarSK);
            if ($pct !== null && $pct > 80.0 && $namaMirip) {
                $catatanPieces[] = 'NIK tidak cocok, tapi nama mirip ' . number_format($pct, 1) . '% dengan "' . $namaMirip . '" di SK — kemungkinan typo NIK, perlu verifikasi manual.';
            } else {
                $catatanPieces[] = 'NIK tidak terdaftar dalam lampiran SK — perlu verifikasi manual.';
            }
        }

        $x = $u['koordinat_x'] !== null ? (float)$u['koordinat_x'] : null;
        $y = $u['koordinat_y'] !== null ? (float)$u['koordinat_y'] : null;
        if ($x === null || $y === null) {
            $statusKoord = 'Luar Peta PS';
            $cLuar++;
            $catatanPieces[] = 'Titik koordinat tidak terbaca — dianggap di luar peta.';
        } elseif (!$rings) {
            $statusKoord = 'Luar Peta PS';
            $cLuar++;
            $catatanPieces[] = 'Belum ada data poligon PS — dianggap di luar peta.';
        } else {
            // Konvensi: X=lng, Y=lat
            $diDalam = point_in_geometry($x, $y, $rings);
            $statusKoord = $diDalam ? 'Dalam Peta PS' : 'Luar Peta PS';
            if ($diDalam) {
                $cDalam++;
            } else {
                $cLuar++;
                $catatanPieces[] = 'Titik koordinat berada di luar areal PS.';
            }
        }

        // Validasi luas usulan per orang (maksimal 2.0 Ha)
        $luas = $u['luas_lahan'] !== null ? (float)$u['luas_lahan'] : null;
        if ($luas !== null) {
            $totalLuasUsulan += $luas;
            if ($luas > 2.0) {
                $cLebihLuas++;
                $catatanPieces[] = 'Luas usulan (' . number_format($luas, 2, ',', '.') . ' Ha) melebihi batas maksimal 2 Ha per orang.';
            }
        }

        if ($cocok && $statusKoord === 'Dalam Peta PS' && empty($catatanPieces)) {
            $catatan = 'Sesuai.';
        } else {
            $catatan = implode(' ', $catatanPieces);
        }

        $ins->execute([$u['id'], $kthId, $statusSK, $statusKoord, trim($catatan), $pct, $namaMirip]);
    }

    $rekom = ($cTidak === 0 && $cLuar === 0 && $cLebihLuas === 0) ? 'Dapat Ditindaklanjuti' : 'Perlu Revisi';
    return [
        'total' => count($usulan), 'sesuai' => $cSesuai, 'tidak' => $cTidak,
        'dalam' => $cDalam, 'luar' => $cLuar, 'lebih_luas' => $cLebihLuas,
        'total_luas' => $totalLuasUsulan, 'rekomendasi' => $rekom,
    ];
}

function buat_narasi_default(array $kth, int $tahun, array $hitung): string {
    $luasSk = !empty($kth['luas_areal']) ? (float)$kth['luas_areal'] : 0.0;
    $totalLuas = (float)($hitung['total_luas'] ?? 0.0);
    $lebih2ha = (int)($hitung['lebih_luas'] ?? 0);

    $pctLuasStr = '';
    if ($luasSk > 0) {
        $pctLuas = round(($totalLuas / $luasSk) * 100, 2);
        $pctLuasStr = "Total luas lahan yang diusulkan adalah seluas " . number_format($totalLuas, 2, ',', '.') . " Ha atau " . number_format($pctLuas, 2, ',', '.') . "% dari total luasan dalam SK PS (" . number_format($luasSk, 2, ',', '.') . " Ha). ";
    } else {
        $pctLuasStr = "Total luas lahan yang diusulkan adalah seluas " . number_format($totalLuas, 2, ',', '.') . " Ha. ";
    }

    $catatanLuas = '';
    if ($lebih2ha > 0) {
        $catatanLuas = "Terdapat {$lebih2ha} petani dengan luas usulan melebihi ketentuan batas maksimal 2 Ha per orang. ";
    } else {
        $catatanLuas = "Seluruh usulan petani memenuhi ketentuan batas maksimal luasan (tidak lebih dari 2 Ha per orang). ";
    }

    $t = "Berdasarkan hasil verifikasi data usulan pupuk subsidi tahun {$tahun} terdapat sebanyak {$hitung['total']} petani. "
       . "Dari hasil telaah diperoleh data bahwa sejumlah {$hitung['sesuai']} petani sudah sesuai dengan SK " . ($kth['nomor_sk'] ?: '-') . ", "
       . "terdapat {$hitung['tidak']} petani yang belum masuk ke dalam SK tersebut. "
       . "Titik koordinat petani yang mengusulkan pupuk, sejumlah {$hitung['dalam']} berada dalam peta areal " . ($kth['nama_kth'] ?: '-') . " "
       . "dan {$hitung['luar']} berada di luar peta. "
       . $pctLuasStr
       . $catatanLuas;
    return trim($t);
}
