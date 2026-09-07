<?php
// Konfigurasi aplikasi — sesuaikan kredensial MySQL Laragon bila perlu.
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'verif_pupuk');
define('DB_USER', 'root');
define('DB_PASS', ''); // default Laragon: root tanpa password

define('APP_NAME', 'Verifikasi Pupuk Subsidi Petani Hutan');
define('UPLOAD_DIR', __DIR__ . '/uploads');
define('MAX_UPLOAD_BYTES', 50 * 1024 * 1024); // 50 MB per file

if (!is_dir(UPLOAD_DIR)) { @mkdir(UPLOAD_DIR, 0775, true); }
