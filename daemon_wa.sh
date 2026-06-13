#!/bin/bash
URL="http://localhost/MRTG/wa_checker.php"
echo "Memulai WA Daemon untuk: $URL"
while true; do
    curl -s -k "$URL" > /dev/null
    sleep 60
done
