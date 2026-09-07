@echo off
setlocal enabledelayedexpansion

:: =======================================================
:: KONFIGURASI SERVER (CLOUDFLARE SSH TUNNEL & WINDOWS)
:: =======================================================
set SERVER_USER=adit
set SERVER_IP=127.0.0.1
set SERVER_PORT=2222
set "REMOTE_DIR=C:\laragon\www\website-cdk\pupuk"
set BRANCH=main
set "REPO_URL=https://github.com/adhito-nugroho/pupuk.git"

:: Path penuh git di server (SSH Windows PATH sering minimal)
set "REMOTE_GIT=C:\laragon\bin\git\cmd\git.exe"

echo ======================================================
echo VERIFIKASI PUPUK — DEPLOY GIT (PUSH & SERVER PULL)
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

git push -u origin %BRANCH%
if errorlevel 1 (
    echo Gagal melakukan git push dari laptop!
    pause
    exit /b 1
)

:: 2. Git Pull / Clone di Server via SSH
echo.
echo [2/2] Memperbarui kode di server via SSH...
echo *(Jika diminta password SSH, masukkan password akun server)*
echo.

ssh -p %SERVER_PORT% %SERVER_USER%@%SERVER_IP% "if exist %REMOTE_DIR%\.git (cd /d %REMOTE_DIR% && %REMOTE_GIT% pull origin %BRANCH%) else (if not exist C:\laragon\www\website-cdk mkdir C:\laragon\www\website-cdk && %REMOTE_GIT% clone %REPO_URL% %REMOTE_DIR%)"

if errorlevel 1 (
    echo.
    echo Proses update di server gagal. Pastikan tunnel Cloudflare dan folder server sesuai.
    pause
    exit /b 1
)

echo.
echo ======================================================
echo DEPLOYMENT GIT KE SERVER SELESAI!
echo ======================================================
pause
