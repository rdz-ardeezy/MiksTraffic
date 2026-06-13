@echo off
echo Menghentikan semua Daemon Latar Belakang...
taskkill /F /FI "WINDOWTITLE eq MRTG_DAEMON*" /T > nul 2>&1
echo Berhasil dihentikan.
pause
