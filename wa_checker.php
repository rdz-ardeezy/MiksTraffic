<?php
/**
 * MiksTraffic - WA Checker Endpoint
 * Dieksekusi via CURL oleh Bash Daemon
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

$wa_url = defined('WA_API_URL') ? WA_API_URL : '';
$wa_number = defined('WA_TARGET_NUMBER') ? WA_TARGET_NUMBER : '';
$threshold_mbps = defined('WA_TRAFFIC_THRESHOLD') ? (float)WA_TRAFFIC_THRESHOLD : 10;
$check_interval = defined('WA_CHECK_INTERVAL') ? (int)WA_CHECK_INTERVAL : 5;

if (empty($wa_url) || empty($wa_number)) {
    die("WA settings empty. Skipping.\n");
}

$flagFile = __DIR__ . '/data/wa_state.json';
$state = file_exists($flagFile) ? json_decode(file_get_contents($flagFile), true) : [];

try {
    $db = new MiksDB();
    $conn = $db->getDb();
    
    // Get routers
    $stmt = $conn->query("SELECT id, name FROM routers");
    $routers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $now = time();
    $stateChanged = false;
    
    foreach ($routers as $router) {
        $stmtInt = $conn->prepare("SELECT interface, rx_rate, tx_rate, timestamp FROM traffic_log WHERE router_id = ? GROUP BY interface ORDER BY timestamp DESC");
        $stmtInt->execute([$router['id']]);
        $latestData = $stmtInt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($latestData as $data) {
            $iface = $data['interface'];
            $rx_mbps = $data['rx_rate'] / 1000000;
            $tx_mbps = $data['tx_rate'] / 1000000;
            
            $state_key = $router['id'] . '_' . $iface;
            $last_alert = $state[$state_key] ?? 0;
            
            if ($rx_mbps < $threshold_mbps && $tx_mbps < $threshold_mbps) {
                if ($now - $last_alert >= ($check_interval * 60)) {
                    $rx_str = number_format($rx_mbps, 2);
                    $tx_str = number_format($tx_mbps, 2);
                    $message = "⚠️ *TRAFFIC ALERT* ⚠️\n\nRouter: {$router['name']}\nInterface: {$iface}\n\nTraffic sangat rendah (Di bawah {$threshold_mbps} Mbps)!\nRX: {$rx_str} Mbps\nTX: {$tx_str} Mbps\n\nWaktu: " . date('Y-m-d H:i:s');
                    
                    $payload = json_encode([
                        'number' => $wa_number,
                        'message' => $message
                    ]);
                    
                    $ch = curl_init($wa_url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
                    $result = curl_exec($ch);
                    curl_close($ch);
                    
                    $state[$state_key] = $now;
                    $stateChanged = true;
                    echo "Sent alert for {$iface}. Result: {$result}\n";
                }
            } else {
                if ($last_alert > 0 && ($now - $last_alert) > 60) {
                    $state[$state_key] = 0;
                    $stateChanged = true;
                }
            }
        }
    }
    
    if ($stateChanged) {
        file_put_contents($flagFile, json_encode($state));
    }
    
    echo "OK";
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
