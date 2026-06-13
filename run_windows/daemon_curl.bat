@echo off
title MRTG_DAEMON_CURL
set URL=http://localhost:8888/cron_collector.php
:loop
curl -s -k "%URL%" > nul
timeout /t 1 /nobreak > nul
goto loop
