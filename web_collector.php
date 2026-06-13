<?php
/**
 * MiksTraffic Web-Based Collector
 * Gunakan ini jika Daemon CLI gagal karena driver SQLite tidak ditemukan.
 * Jalankan halaman ini di tab browser terpisah dan biarkan tetap terbuka.
 */

// Mencegah timeout
set_time_limit(0);
ignore_user_abort(false); // Berhenti jika tab ditutup di browser

header('Content-Type: text/plain');
header('Cache-Control: no-cache');

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/routeros_api.class.php';

// Inisialisasi Database
try {
    $db = new MiksDB();
} catch (Exception $e) {
    die("Database Connection Error: " . $e->getMessage());
}

$last_aggregation = time();
$last_hourly = time();
$last_daily = time();
$last_monthly = time();

echo "==========================================\n";
echo "   TUNGKAL PUNYE TRAFFIC WEB-COLLECTOR    \n";
echo "==========================================\n";
echo "Status: Running...\n";
echo "Recording data every 1 second.\n";
echo "JANGAN TUTUP HALAMAN INI agar history tetap terekam.\n";
echo "==========================================\n\n";

// Paksa output keluar ke browser secara real-time
if (function_exists('ob_end_flush')) { @ob_end_flush(); }
ob_implicit_flush(true);

while (true) {
    $start_time = microtime(true);
    
    try {
        $routers = $db->getRouters();

        foreach ($routers as $router) {
            $api = new RouterosAPI();
            $api->timeout = 2;
            
            if ($api->connect($router['host'], $router['username'], $router['password'])) {
                $interfacesData = json_decode($router['monitored_interfaces'], true);
                if (is_array($interfacesData)) {
                    foreach ($interfacesData as $ifaceObj) {
                        $interface = $ifaceObj['name'];
                        
                        // Poll rate
                        $traffic = $api->comm("/interface/monitor-traffic", [
                            "interface" => $interface, 
                            "once" => ""
                        ]);

                        // Poll Ethernet/SFP status
                        $ethStatus = $api->comm("/interface/ethernet/monitor", [
                            "numbers" => $interface, 
                            "once" => ""
                        ]);

                        if (!empty($traffic) && isset($traffic[0])) {
                            $rx = (int)($traffic[0]['rx-bits-per-second'] ?? 0);
                            $tx = (int)($traffic[0]['tx-bits-per-second'] ?? 0);
                            
                            $sfp_pwr = 0;
                            $lnk_rt = 'connected';
                            
                            if (!empty($ethStatus) && is_array($ethStatus) && !isset($ethStatus['!trap'])) {
                                $sfp_pwr = isset($ethStatus[0]['sfp-rx-power']) ? (float)$ethStatus[0]['sfp-rx-power'] : 0;
                                $lnk_rt = $ethStatus[0]['rate'] ?? 'connected';
                            }
                            
                            // Log 1s data ke Database
                            $db->logTraffic($router['id'], $interface, 0, 0, $rx, $tx, $sfp_pwr, $lnk_rt);
                        }
                    }
                }
                $api->disconnect();
            }
        }
    } catch (Exception $e) {
        echo "\nError during cycle: " . $e->getMessage() . "\n";
    }

    // Aggregation setiap 5 menit
    if (time() - $last_aggregation >= 300) {
        echo "\n[" . date('H:i:s') . "] Running aggregation 5m...";
        $db->aggregate5m();
        $last_aggregation = time();
        echo " OK";
    }

    // Aggregation setiap 1 jam
    if (time() - $last_hourly >= 3600) {
        echo "\n[" . date('H:i:s') . "] Running aggregation 1h & pruning...";
        $db->aggregateHourly();
        $db->pruneOldData();
        $last_hourly = time();
        echo " OK";
    }

    // Aggregation setiap hari
    if (time() - $last_daily >= 86400) {
        echo "\n[" . date('H:i:s') . "] Running aggregation 24h...";
        $db->aggregateDaily();
        $last_daily = time();
        echo " OK";
    }

    // Aggregation setiap bulan
    if (time() - $last_monthly >= 2592000) {
        echo "\n[" . date('H:i:s') . "] Running aggregation 30d...";
        $db->aggregateMonthly();
        $last_monthly = time();
        echo " OK";
    }

    // Indikator visual ke browser
    echo ".";
    
    // Maintain 1s frequency
    $execution_time = microtime(true) - $start_time;
    $sleep_time = 1000000 - ($execution_time * 1000000);
    if ($sleep_time > 0) {
        usleep($sleep_time);
    }
}
