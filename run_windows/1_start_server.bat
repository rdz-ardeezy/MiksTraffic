@echo off
title MRTG_PHP_SERVER
echo Memulai PHP Server Standalone di port 8888...
cd ..
start http://localhost:8888
php -S localhost:8888
