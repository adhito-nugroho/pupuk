@echo off
setlocal enabledelayedexpansion

:: =======================================================
:: KONFIGURASI DATABASE & SSH SERVER
:: =======================================================
set DB_NAME=verif_pupuk
set DB_USER_LOCAL=root
set DB_PASS_LOCAL=

set SERVER_USER=adit
set SERVER_IP=127.0.0.1
set SERVER_PORT=2222
set DB_USER_REMOTE=root
set DB_PASS_REMOTE=

set DUMP_FILE=db_export_pupuk_temp.sql
set "REMOTE_MYSQL=C:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysql.exe"

echo ======================================================
echo VERIFIKASI PUPUK -- DEPLOY DATABASE MYSQL KE SERVER
echo ======================================================

:: 1. Export database dari MySQL Lokal
echo [1/3] Meng-export database lokal (%DB_NAME%)...

powershell -NoProfile -Command "$candidates = @(Get-ChildItem -Path 'C:\laragon\bin\mysql\*\bin\mysqldump.exe','D:\laragon\bin\mysql\*\bin\mysqldump.exe','C:\Program Files\MySQL\*\bin\mysqldump.exe' -ErrorAction SilentlyContinue | Select-Object -ExpandProperty FullName); if (-not $candidates) { Write-Error 'mysqldump tidak ditemukan'; exit 1 }; $d = $candidates[0]; Write-Host ('Pakai: ' + $d); if ('%DB_PASS_LOCAL%' -eq '') { & $d -u %DB_USER_LOCAL% --databases %DB_NAME% --routines --events --single-transaction --result-file='%DUMP_FILE%' } else { & $d -u %DB_USER_LOCAL% -p%DB_PASS_LOCAL% --databases %DB_NAME% --routines --events --single-transaction --result-file='%DUMP_FILE%' }; if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }"

if errorlevel 1 (
    echo Gagal export database lokal! Pastikan MySQL Laragon lokal aktif.
    pause
    exit /b 1
)

if not exist %DUMP_FILE% (
    echo File dump tidak dibuat! Pastikan MySQL Laragon lokal aktif.
    pause
    exit /b 1
)

for %%A in (%DUMP_FILE%) do if %%~zA lss 100 (
    echo File dump terlalu kecil / kosong. Export gagal.
    del %DUMP_FILE%
    pause
    exit /b 1
)

echo Export database lokal berhasil. Ukuran: 
for %%A in (%DUMP_FILE%) do echo   %%~zA bytes

:: 2. Upload file SQL ke Server via SCP (ke home folder user adit)
echo.
echo [2/3] Mengirim file dump SQL ke server...
scp -P %SERVER_PORT% %DUMP_FILE% %SERVER_USER%@%SERVER_IP%:%DUMP_FILE%

if errorlevel 1 (
    echo Gagal mengunggah file SQL ke server! Pastikan tunnel SSH aktif di port %SERVER_PORT%.
    del %DUMP_FILE%
    pause
    exit /b 1
)

:: 3. Import SQL ke MySQL Server via SSH
echo.
echo [3/3] Meng-import database di MySQL Server...

if "%DB_PASS_REMOTE%"=="" (
    ssh -p %SERVER_PORT% %SERVER_USER%@%SERVER_IP% "%REMOTE_MYSQL% -u %DB_USER_REMOTE% < %DUMP_FILE% & del %DUMP_FILE%"
) else (
    ssh -p %SERVER_PORT% %SERVER_USER%@%SERVER_IP% "%REMOTE_MYSQL% -u %DB_USER_REMOTE% -p%DB_PASS_REMOTE% < %DUMP_FILE% & del %DUMP_FILE%"
)

if errorlevel 1 (
    echo.
    echo Gagal import database di server!
    if exist %DUMP_FILE% del %DUMP_FILE%
    pause
    exit /b 1
)

if exist %DUMP_FILE% del %DUMP_FILE%

echo.
echo ======================================================
echo MIGRASI DATABASE %DB_NAME% KE SERVER BERHASIL!
echo ======================================================
pause
