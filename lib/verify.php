<?php
// Mesin verifikasi: matching NIK/Nama + point-in-polygon + narasi + rekomendasi.
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/parse_shp.php';

function verifikasi_satu_kth(PDO $pdo, int $kthId, int $versiKe = 0): array {
    if ($versiKe <= 0) {
        $stV = $pdo->prepare('SELECT versi_aktif FROM kth WHERE id = ?');
        $stV->execute([$kthId]);
        $rowV = $stV->fetch();
        $versiKe = (int)($rowV['versi_aktif'] ?? 1);
        if ($versiKe <= 0) $versiKe = 1;
    }

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

    $us = $pdo->prepare('SELECT * FROM usulan_pupuk WHERE kth_id = ? AND versi_ke = ? ORDER BY COALESCE(no_urut, id)');
    $us->execute([$kthId, $versiKe]);
    $usulan = $us->fetchAll();

    // Seluruh titik per petani (titik utama + titik tambahan).
    $titikByUsulan = ambil_titik_usulan($pdo, $kthId, $versiKe);
    $updTitik = null;
    try {
        $updTitik = $pdo->prepare('UPDATE usulan_titik SET status_koordinat = ? WHERE id = ?');
    } catch (Throwable $eTitik) {
        $updTitik = null;
    }

    // Ambil data lama agar koreksi manual + catatan verifikator tidak hilang
    // saat hitung ulang (sebelumnya DELETE menghapus semuanya).
    $lama = [];
    try {
        $stLama = $pdo->prepare('SELECT usulan_id, status_sk, status_koordinat, catatan, koordinat_koreksi_x, koordinat_koreksi_y, dikoreksi_pada FROM hasil_verifikasi WHERE kth_id = ? AND versi_ke = ?');
        $stLama->execute([$kthId, $versiKe]);
        foreach ($stLama->fetchAll() as $lr) {
            $lama[(int)$lr['usulan_id']] = $lr;
        }
    } catch (Throwable $eLama) { $lama = []; }

    // Hapus hasil lama untuk versi ini, hitung ulang
    $pdo->prepare('DELETE FROM hasil_verifikasi WHERE kth_id = ? AND versi_ke = ?')->execute([$kthId, $versiKe]);
    $ins = $pdo->prepare('INSERT INTO hasil_verifikasi (usulan_id, kth_id, versi_ke, status_sk, status_koordinat, catatan, kemiripan_nama, nama_mirip_sk, koordinat_koreksi_x, koordinat_koreksi_y, dikoreksi_pada) VALUES (?,?,?,?,?,?,?,?,?,?,?)');

    $cSesuai = 0; $cTidak = 0; $cDalam = 0; $cLuar = 0; $cLebihLuas = 0;
    $totalLuasUsulan = 0.0;
    foreach ($usulan as $u) {
        $nikN = norm_nik((string)$u['nik']);
        $cocok = ($nikN !== '' && isset($mapNik[$nikN])) ? $mapNik[$nikN] : null;
        if ($cocok) { $statusSK = 'Sesuai SK PS'; $cSesuai++; }
        else { $statusSK = 'Belum Sesuai SK PS'; $cTidak++; }

        $catatanPieces = [];
        $pct = null; $namaMirip = null;

        $nikFormatErr = cek_format_nik((string)$u['nik']);
        if ($nikFormatErr) $catatanPieces[] = $nikFormatErr;

        if (!$cocok) {
            [$pct, $namaMirip] = cari_nama_mirip((string)$u['nama'], $daftarSK);
            if ($pct !== null && $pct > 80.0 && $namaMirip) {
                $catatanPieces[] = 'NIK tidak cocok, tapi nama mirip ' . number_format($pct, 1) . '% dengan "' . $namaMirip . '" di SK — kemungkinan typo NIK, perlu verifikasi manual.';
            } else {
                $catatanPieces[] = 'NIK tidak terdaftar dalam lampiran SK — perlu verifikasi manual.';
            }
        }

        // Koordinat koreksi manual (jika ada) dipakai sebagai posisi efektif
        // untuk titik utama — koordinat asli di usulan_pupuk tetap utuh.
        $rowLama = $lama[(int)$u['id']] ?? null;
        $korX = $rowLama && $rowLama['koordinat_koreksi_x'] !== null ? (float)$rowLama['koordinat_koreksi_x'] : null;
        $korY = $rowLama && $rowLama['koordinat_koreksi_y'] !== null ? (float)$rowLama['koordinat_koreksi_y'] : null;

        $titikPetani = $titikByUsulan[(int)$u['id']] ?? [];
        if (empty($titikPetani)) {
            // Kompatibilitas data lama yang belum memiliki baris usulan_titik.
            $titikPetani = [[
                'id' => null,
                'urutan' => 1,
                'x_raw' => $u['koordinat_x_raw'] ?? null,
                'y_raw' => $u['koordinat_y_raw'] ?? null,
                'x' => $u['koordinat_x'] ?? null,
                'y' => $u['koordinat_y'] ?? null,
            ]];
        }

        $statusTitik = [];
        $titikLuar = [];
        $titikKosong = [];
        $adaFormatTitik = false;
        $koordFormatErr = null;

        foreach ($titikPetani as $t) {
            $urutanTitik = (int)($t['urutan'] ?? 1);
            $isUtama = $urutanTitik === 1;
            $tX = $t['x'] !== null ? (float)$t['x'] : null;
            $tY = $t['y'] !== null ? (float)$t['y'] : null;

            $formatErr = cek_format_koordinat($t['x_raw'] ?? null, $t['y_raw'] ?? null, $tX, $tY);
            if ($formatErr !== null) {
                $adaFormatTitik = true;
                if ($isUtama) {
                    $koordFormatErr = $formatErr;
                } else {
                    $catatanPieces[] = 'Titik ke-' . $urutanTitik . ': ' . $formatErr;
                }
            }

            $efX = $isUtama ? ($korX ?? $tX) : $tX;
            $efY = $isUtama ? ($korY ?? $tY) : $tY;

            if ($efX === null || $efY === null) {
                $statusTitikSatu = 'Luar Peta PS';
                $titikKosong[] = $urutanTitik;
            } elseif (!$rings) {
                $statusTitikSatu = 'Luar Peta PS';
            } else {
                $diDalam = point_in_geometry($efX, $efY, $rings);
                $statusTitikSatu = $diDalam ? 'Dalam Peta PS' : 'Luar Peta PS';
                if (!$diDalam) $titikLuar[] = $urutanTitik;
            }
            $statusTitik[] = $statusTitikSatu;

            if ($updTitik && !empty($t['id'])) {
                $updTitik->execute([$statusTitikSatu, (int)$t['id']]);
            }
        }

        $statusKoord = (!in_array('Luar Peta PS', $statusTitik, true))
            ? 'Dalam Peta PS'
            : 'Luar Peta PS';
        if ($statusKoord === 'Dalam Peta PS') {
            $cDalam++;
        } else {
            $cLuar++;
        }

        if ($koordFormatErr) $catatanPieces[] = $koordFormatErr;

        if (!empty($titikKosong)) {
            $labelTitik = count($titikPetani) > 1
                ? 'Titik ke-' . implode(', ke-', $titikKosong)
                : 'Titik koordinat';
            if (!$adaFormatTitik) {
                $catatanPieces[] = $labelTitik . ' tidak terbaca — dianggap di luar peta.';
            }
        }

        if (!$rings) {
            $catatanPieces[] = 'Belum ada data poligon PS — dianggap di luar peta.';
        } elseif (!empty($titikLuar)) {
            if (count($titikPetani) > 1) {
                $catatanPieces[] = 'Titik ke-' . implode(', ke-', $titikLuar) . ' berada di luar areal PS.';
            } else {
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

        // Pertahankan catatan manual verifikator bila status tidak berubah
        // (status sama => kemungkinan catatan lama adalah edit manual).
        if ($rowLama && ($rowLama['status_sk'] ?? null) === $statusSK
            && ($rowLama['status_koordinat'] ?? null) === $statusKoord
            && trim((string)($rowLama['catatan'] ?? '')) !== '') {
            $catatan = (string)$rowLama['catatan'];
            $tambahanFormat = array_filter($catatanPieces, fn($m) =>
                (str_starts_with($m, 'Format NIK') || str_starts_with($m, 'Format titik koordinat'))
                && mb_strpos($catatan, $m) === false
            );
            if (!empty($tambahanFormat)) {
                $catatan = trim($catatan . ' ' . implode(' ', $tambahanFormat));
            }
        }

        $ins->execute([$u['id'], $kthId, $versiKe, $statusSK, $statusKoord, trim($catatan), $pct, $namaMirip, $korX, $korY, ($rowLama['dikoreksi_pada'] ?? null)]);
    }

    $rekom = ($cTidak === 0 && $cLuar === 0 && $cLebihLuas === 0) ? 'Dapat Ditindaklanjuti' : 'Perlu Revisi';

    // Sinkronkan ringkasan hasil ke tabel kth_versi_usulan jika ada
    $updV = $pdo->prepare('
        UPDATE kth_versi_usulan 
        SET total_petani = ?, total_luas = ?, jumlah_sesuai_sk = ?, jumlah_tidak_sesuai_sk = ?, 
            jumlah_dalam_peta = ?, jumlah_luar_peta = ?, rekomendasi = ?
        WHERE kth_id = ? AND versi_ke = ?
    ');
    $updV->execute([
        count($usulan), $totalLuasUsulan, $cSesuai, $cTidak, $cDalam, $cLuar, $rekom, $kthId, $versiKe
    ]);

    return [
        'versi_ke' => $versiKe,
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
