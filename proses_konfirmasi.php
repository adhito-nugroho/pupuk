<?php
// Simpan daftar anggota SK terkonfirmasi + jalankan verifikasi.
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/lib/verify.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: index.php'); exit; }

$kthId = (int)($_POST['kth_id'] ?? 0);
if (!$kthId) { flash_set('error', 'kth_id hilang.'); header('Location: index.php'); exit; }

// Update metadata SK jika diubah di form konfirmasi
$metaNomorSk = trim((string)($_POST['meta_nomor_sk'] ?? ''));
$metaNamaKph = trim((string)($_POST['meta_nama_kph'] ?? ''));
$metaLuasAreal = trim((string)($_POST['meta_luas_areal'] ?? ''));
$metaTanggalSk = trim((string)($_POST['meta_tanggal_sk'] ?? ''));

$nama = $_POST['nama'] ?? [];
$nik = $_POST['nik'] ?? [];
$jk = $_POST['jk'] ?? [];
$desa = $_POST['desa'] ?? [];
$kec = $_POST['kecamatan'] ?? [];

$bersih = [];
$tolak = 0;

for ($i = 0; $i < count($nik); $i++) {
    $nm = norm_nama((string)($nama[$i] ?? ''));
    $nk = norm_nik((string)($nik[$i] ?? ''));
    if ($nm === '' && $nk === '') continue; // baris kosong diabaikan
    if ($nm === '' || strlen($nk) !== 16) {
        $tolak++;
        continue;
    }
    $bersih[] = [
        'nama' => $nm,
        'nik' => $nk,
        'jk' => mb_strtoupper(trim((string)($jk[$i] ?? '')), 'UTF-8') ?: null,
        'desa' => norm_nama((string)($desa[$i] ?? '')) ?: null,
        'kecamatan' => norm_nama((string)($kec[$i] ?? '')) ?: null
    ];
}

if (!$bersih) {
    flash_set('error', 'Tidak ada baris valid tersimpan' . ($tolak ? " ($tolak baris ditolak: nama kosong atau NIK bukan 16 digit)." : '.'));
    header('Location: konfirmasi_sk.php?kth_id=' . $kthId);
    exit;
}

$pdo = db();
try {
    $pdo->beginTransaction();

    // Update metadata KTH
    $luasVal = ($metaLuasAreal !== '' && is_numeric($metaLuasAreal)) ? (float)$metaLuasAreal : null;
    $tglVal = ($metaTanggalSk !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $metaTanggalSk)) ? $metaTanggalSk : null;
    $pdo->prepare('UPDATE kth SET nomor_sk = ?, nama_kph = ?, luas_areal = ?, tanggal_sk = ? WHERE id = ?')
        ->execute([$metaNomorSk ?: null, $metaNamaKph ?: null, $luasVal, $tglVal, $kthId]);

    // Hapus data anggota lama, simpan data baru terkonfirmasi
    $pdo->prepare('DELETE FROM sk_anggota WHERE kth_id = ?')->execute([$kthId]);
    $ins = $pdo->prepare('INSERT INTO sk_anggota (kth_id, nama, nik, jenis_kelamin, desa, kecamatan) VALUES (?,?,?,?,?,?)');
    foreach ($bersih as $b) {
        $ins->execute([$kthId, $b['nama'], $b['nik'], $b['jk'], $b['desa'], $b['kecamatan']]);
    }

    // Jalankan verifikasi matching NIK/Nama + point-in-polygon
    $hitung = verifikasi_satu_kth($pdo, $kthId);

    // Siapkan draf laporan bila belum ada
    $ada = $pdo->prepare('SELECT id FROM laporan WHERE kth_id = ? LIMIT 1');
    $ada->execute([$kthId]);
    $kth = $pdo->prepare('SELECT * FROM kth WHERE id = ?');
    $kth->execute([$kthId]);
    $k = $kth->fetch();

    if (!$ada->fetch()) {
        $tahun = $k['tahun_usulan'] ?? date('Y');
        $narasi = buat_narasi_default($k, (int)$tahun, $hitung);
        $pdo->prepare('INSERT INTO laporan (kth_id, tahun, total_petani, jumlah_sesuai_sk, jumlah_tidak_sesuai_sk, jumlah_dalam_peta, jumlah_luar_peta, narasi, rekomendasi) VALUES (?,?,?,?,?,?,?,?,?)')
            ->execute([$kthId, (string)$tahun, $hitung['total'], $hitung['sesuai'], $hitung['tidak'], $hitung['dalam'], $hitung['luar'], $narasi, $hitung['rekomendasi']]);
    }

    unset($_SESSION['sk_parse'][$kthId]); // staging selesai
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    flash_set('error', 'Gagal menyimpan konfirmasi anggota SK: ' . $e->getMessage());
    header('Location: konfirmasi_sk.php?kth_id=' . $kthId);
    exit;
}

$pesan = 'Berhasil menyimpan ' . count($bersih) . ' anggota resmi SK' . ($tolak ? " ($tolak baris tidak valid dilewati)." : '.')
    . " Hasil verifikasi: {$hitung['sesuai']} sesuai SK, {$hitung['tidak']} belum sesuai; {$hitung['dalam']} dalam peta PS, {$hitung['luar']} luar peta."
    . ($hitung['lebih_luas'] > 0 ? " (⚠️ {$hitung['lebih_luas']} petani mengusulkan > 2 Ha)." : '')
    . " Rekomendasi: {$hitung['rekomendasi']}.";

flash_set('ok', $pesan);
header('Location: hasil.php?kth_id=' . $kthId);
exit;
