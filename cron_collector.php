<?php
/**
 * MiksTraffic Single-Tick Collector
 * Dieksekusi via CURL oleh Bash Daemon untuk menghindari masalah ekstensi SQLite di CLI.
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/routeros_api.class.php';

try {
    $db = new MiksDB();
} catch (Exception $e) {
    die("Database Connection Error: " . $e->getMessage());
}

$routers = $db->getRouters();

foreach ($routers as $router) {
    $api = new RouterosAPI();
    $api->timeout = 2;
    
    if ($api->connect($router['host'], $router['username'], $router['password'])) {
        // Log Resources
        $resources = $api->comm("/system/resource/print");
        if (!empty($resources) && isset($resources[0])) {
            $res = $resources[0];
            $db->logResource(
                $router['id'], 
                $res['cpu-load'] ?? 0, 
                $res['free-memory'] ?? 0, 
                $res['total-memory'] ?? 0, 
                $res['uptime'] ?? ''
            );
        }

        $interfacesData = json_decode($router['monitored_interfaces'], true);
        if (is_array($interfacesData)) {
            foreach ($interfacesData as $ifaceObj) {
                $interface = $ifaceObj['name'];
                
                $traffic = $api->comm("/interface/monitor-traffic", [
                    "interface" => $interface, 
                    "once" => ""
                ]);

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
                    
                    $db->logTraffic($router['id'], $interface, 0, 0, $rx, $tx, $sfp_pwr, $lnk_rt);
                }
            }
        }
        $api->disconnect();
    }
}

// Read Last Timestamps from DB or Cache (We'll use SQLite to store these flags, or just a file)
$flagFile = __DIR__ . '/data/last_cron.json';
$flags = file_exists($flagFile) ? json_decode(file_get_contents($flagFile), true) : [
    'last_5m' => 0, 'last_1h' => 0, 'last_24h' => 0, 'last_30d' => 0
];

$now = time();
$saveFlags = false;

if ($now - $flags['last_5m'] >= 300) {
    $db->aggregate5m();
    $flags['last_5m'] = $now;
    $saveFlags = true;
}

if ($now - $flags['last_1h'] >= 3600) {
    $db->aggregateHourly();
    $db->pruneOldData();
    $flags['last_1h'] = $now;
    $saveFlags = true;
}

if ($now - $flags['last_24h'] >= 86400) {
    $db->aggregateDaily();
    $flags['last_24h'] = $now;
    $saveFlags = true;
}

if ($now - $flags['last_30d'] >= 2592000) {
    $db->aggregateMonthly();
    $flags['last_30d'] = $now;
    $saveFlags = true;
}

if ($saveFlags) {
    file_put_contents($flagFile, json_encode($flags));
}

echo "OK";
