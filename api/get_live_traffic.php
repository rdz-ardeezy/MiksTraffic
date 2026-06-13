<?php
/**
 * MiksTraffic API - Get LIVE Traffic from MikroTik
 */

header('Content-Type: application/json');
file_put_contents(__DIR__ . '/../data/mrtg_debug.txt', 'called at ' . date('Y-m-d H:i:s') . "\n", FILE_APPEND);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../routeros_api.class.php';

$router_id = $_GET['router_id'] ?? null;

if (!$router_id) {
    die(json_encode(['status' => 'error', 'message' => 'Missing router_id']));
}

try {
    $db = new MiksDB();
    $router = $db->getRouter($router_id);

    if (!$router) {
        throw new Exception("Router not found");
    }

    $api = new RouterosAPI();
    $api->port = $router['port'];

    if ($api->connect($router['host'], $router['username'], $router['password'])) {
        // Fetch System Resources
        $resources = $api->comm("/system/resource/print");
        $resourceData = [
            'cpu' => 0,
            'ram_free' => 0,
            'ram_total' => 0,
            'uptime' => '',
            'version' => ''
        ];
        if (!empty($resources) && isset($resources[0])) {
            $res = $resources[0];
            $resourceData = [
                'cpu' => $res['cpu-load'] ?? 0,
                'ram_free' => $res['free-memory'] ?? 0,
                'ram_total' => $res['total-memory'] ?? 0,
                'uptime' => $res['uptime'] ?? '',
                'version' => $res['version'] ?? ''
            ];
        }
        file_put_contents('/tmp/mrtg_debug.txt', print_r($resources, true));

        $interfacesData = json_decode($router['monitored_interfaces'], true);
        $result = [];

        if (is_array($interfacesData)) {
            foreach ($interfacesData as $ifaceObj) {
                $ifaceName = $ifaceObj['name'];
                
                // Get current rate
                $traffic = $api->comm("/interface/monitor-traffic", [
                    "interface" => $ifaceName,
                    "once"      => ""
                ]);

                // Get SFP Power and Link status
                $ethStatus = $api->comm("/interface/ethernet/monitor", [
                    "numbers" => $ifaceName,
                    "once"    => ""
                ]);

                // Handle errors or non-ethernet interfaces gracefully
                $sfpPower = null;
                $linkRate = 'virtual/vlan';
                
                if (!empty($ethStatus) && is_array($ethStatus) && !isset($ethStatus['!trap'])) {
                    $sfpPower = isset($ethStatus[0]['sfp-rx-power']) ? (float)$ethStatus[0]['sfp-rx-power'] : null;
                    $linkRate = $ethStatus[0]['rate'] ?? 'connected';
                }

                if (!empty($traffic) && isset($traffic[0])) {
                    $result[$ifaceName] = [
                        'rx_rate' => (int)($traffic[0]['rx-bits-per-second'] ?? 0),
                        'tx_rate' => (int)($traffic[0]['tx-bits-per-second'] ?? 0),
                        'sfp_power' => $sfpPower,
                        'link_rate' => $linkRate,
                        'timestamp' => date('Y-m-d H:i:s')
                    ];
                }
            }
        }

        $api->disconnect();
        echo json_encode([
            'status' => 'success',
            'router_id' => $router_id,
            'traffic' => $result,
            'resources' => $resourceData
        ]);
    } else {
        throw new Exception("Failed to connect to MikroTik");
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
