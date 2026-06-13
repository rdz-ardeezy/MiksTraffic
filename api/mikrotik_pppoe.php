<?php
/**
 * MiksTraffic API - Get Active PPPoE Users
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../routeros_api.class.php';

$db = new MiksDB();
$routers = $db->getRouters();

$allActive = [];

foreach ($routers as $router) {
    $api = new RouterosAPI();
    $api->timeout = 2;
    if ($api->connect($router['host'], $router['username'], $router['password'])) {
        // Fetch active pppoe sessions
        $active = $api->comm("/ppp/active/print");
        
        // We also need caller-id (MAC) which is in /ppp/active
        foreach ($active as $u) {
            $allActive[] = [
                'name' => $u['name'],
                'address' => $u['address'] ?? '',
                'uptime' => $u['uptime'] ?? '',
                'mac' => $u['caller-id'] ?? '',
                'router' => $router['name']
            ];
        }
        $api->disconnect();
    }
}

echo json_encode(['status' => 'success', 'data' => $allActive]);
