<?php
/**
 * MiksTraffic API - Test Connection & Fetch Interfaces
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../routeros_api.class.php';

$data = json_decode(file_get_contents('php://input'), true);

$host = $data['host'] ?? '';
$user = $data['username'] ?? '';
$pass = $data['password'] ?? '';
$port = (int)($data['port'] ?? 8728);

if (!$host || !$user) {
    echo json_encode(['status' => 'error', 'message' => 'Missing host or username']);
    exit();
}

$api = new RouterosAPI();
$api->port = $port;

if ($api->connect($host, $user, $pass)) {
    // Fetch all interfaces
    $interfaces = $api->comm("/interface/print");
    
    $result = [];
    if (is_array($interfaces)) {
        foreach ($interfaces as $iface) {
            $result[] = [
                'name' => $iface['name'],
                'type' => $iface['type'],
                'comment' => $iface['comment'] ?? ''
            ];
        }
    }
    
    $api->disconnect();
    echo json_encode([
        'status' => 'success',
        'message' => 'Connected successfully',
        'interfaces' => $result
    ]);
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to connect to MikroTik. Please check your credentials.'
    ]);
}
