#!/bin/bash
# Daemon CURL untuk MiksTraffic
# Skrip ini memanggil cron_collector.php setiap detik.
# Dijalankan oleh systemd agar tetap hidup di belakang layar.

URL="http://localhost/MRTG/cron_collector.php"

echo "Memulai CURL Daemon untuk: $URL"

while true; do
    curl -s -k "$URL" > /dev/null
    sleep 1
done
