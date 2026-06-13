@echo off
title MRTG_DAEMON_WA
set URL=http://localhost:8888/wa_checker.php
:loop
curl -s -k "%URL%" > nul
timeout /t 5 /nobreak > nul
goto loop
