@echo off
title PELUNCUR PRESENSI PRAMUKA MAN 4 BANJAR - ONLINE GRATIS
color 1F

echo ======================================================================
echo    PRESENSI PRAMUKA MAN 4 BANJAR - ONLINE KE INTERNET (100%% GRATIS)
echo ======================================================================
echo.
echo Pastikan Apache dan MySQL di XAMPP sudah dalam keadaan START (HIJAU)!
echo.
echo Pilih metode online gratis untuk uji coba:
echo.
echo [1] PINGGY (INSTAN - Tanpa download apapun, langsung muncul Link + QR Code)
echo [2] CLOUDFLARE TUNNEL (Sangat stabil, tanpa batas waktu, auto-download)
echo [3] KELUAR
echo.
set /p opt="Pilih nomor [1/2/3]: "

if "%opt%"=="1" goto pinggy
if "%opt%"=="2" goto cloudflare
if "%opt%"=="3" exit
goto start

:pinggy
cls
echo ======================================================================
echo  Menghubungkan ke Internet melalui Pinggy (SSH HTTPS Tunnel)...
echo ======================================================================
echo.
echo CATATAN PENTING:
echo 1. Tunggu beberapa detik sampai muncul URL berawalan https:// dan QR Code.
echo 2. Buka URL tersebut dengan menambahkan: /absen_man4banjar/ di belakangnya!
echo    Contoh: https://xxxx.a.free.pinggy.link/absen_man4banjar/
echo 3. JANGAN TUTUP jendela ini selama uji coba absensi berlangsung!
echo.
ssh -o StrictHostKeyChecking=no -p 443 -R0:localhost:80 qr@a.pinggy.io
pause
exit

:cloudflare
cls
echo ======================================================================
echo  Menghubungkan ke Internet melalui Cloudflare Tunnel (HTTPS)...
echo ======================================================================
echo.
if not exist "%~dp0cloudflared.exe" (
    echo Mengunduh file kecil Cloudflare Tunnel (cloudflared.exe)...
    powershell -Command "[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12; (New-Object System.Net.WebClient).DownloadFile('https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-windows-amd64.exe', '%~dp0cloudflared.exe')"
)

if exist "%~dp0cloudflared.exe" (
    echo.
    echo Cloudflare Tunnel siap! Membuka jalur HTTPS publik...
    echo Cari baris bertuliskan: https://[nama-acak].trycloudflare.com
    echo Lalu tambahkan /absen_man4banjar/ di belakang link tersebut.
    echo.
    echo JANGAN TUTUP jendela ini selama uji coba absensi berlangsung!
    echo ======================================================================
    echo.
    "%~dp0cloudflared.exe" tunnel --url http://localhost:80
) else (
    echo Gagal mengunduh cloudflared.exe secara otomatis.
    echo Silakan pilih opsi [1] Pinggy yang tidak membutuhkan unduhan.
)
pause
exit

