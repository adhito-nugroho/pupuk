@echo off
setlocal enabledelayedexpansion

:: =======================================================
:: KONFIGURASI SERVER (CLOUDFLARE SSH TUNNEL & WINDOWS)
:: =======================================================
set SERVER_USER=adit
set SERVER_IP=127.0.0.1
set SERVER_PORT=2222
set "PARENT_DIR=C:\laragon\www\website-cdk"
set "PROJECT_DIR=pupuk"
set BRANCH=main
set "REPO_URL=https://github.com/adhito-nugroho/pupuk.git"

:: Path penuh git di server (SSH Windows PATH sering minimal)
set "REMOTE_GIT=C:\laragon\bin\git\cmd\git.exe"

echo ======================================================
echo VERIFIKASI PUPUK -- DEPLOY GIT (PUSH DAN SERVER PULL)
echo ======================================================

:: 1. Commit (jika ada perubahan) lalu Push ke remote
echo [1/2] Mendorong perubahan lokal ke Repository GitHub...

git add .

set "NEED_COMMIT=0"
for /f %%i in ('git diff --cached --name-only') do set "NEED_COMMIT=1"

if "!NEED_COMMIT!"=="0" (
    echo Tidak ada perubahan lokal baru. Cek push/pull...
) else (
    set /p msg="Masukkan pesan commit (tekan Enter untuk default 'update aplikasi'): "
    if "!msg!"=="" set "msg=update aplikasi"

    git commit -m "!msg!"
    if errorlevel 1 (
        echo Gagal melakukan git commit!
        pause
        exit /b 1
    )
)

git push origin %BRANCH%
if errorlevel 1 (
    echo Gagal melakukan git push dari laptop!
    pause
    exit /b 1
)

:: 2. Git Pull / Clone di Server via SSH & Jalankan Migrasi
echo.
echo [2/2] Memperbarui kode dan menjalankan migrasi database di server via SSH...
echo *(Jika diminta password SSH, masukkan password akun server)*
echo.

ssh -p %SERVER_PORT% %SERVER_USER%@%SERVER_IP% "cd /d %PARENT_DIR% && (if exist %PROJECT_DIR%\.git (echo [SERVER] Repo ditemukan. Menjalankan git pull... && cd %PROJECT_DIR% && %REMOTE_GIT% pull origin %BRANCH%) else (echo [SERVER] Mengkloning repo baru ke %PARENT_DIR%\%PROJECT_DIR%... && %REMOTE_GIT% clone %REPO_URL% %PROJECT_DIR% && cd %PROJECT_DIR%)) && (if exist migrasi.php (php migrasi.php 2>nul || (for /d %%p in (C:\laragon\bin\php\php*) do if exist %%p\php.exe (%%p\php.exe migrasi.php))))"

if errorlevel 1 (
    echo.
    echo Proses update di server gagal.
    pause
    exit /b 1
)

echo.
echo ======================================================
echo DEPLOYMENT GIT KE SERVER SELESAI!
echo ======================================================
pause
