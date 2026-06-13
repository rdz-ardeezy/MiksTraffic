<?php
/**
 * MiksTraffic OLT API
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/OLT/OLT_Factory.php';

session_start();

$db = new MiksDB();
$action = $_GET['action'] ?? ($_POST['action'] ?? 'list');

try {
    if ($action === 'get_stats') {
        $id = $_GET['id'] ?? '';
        $force = ($_GET['force_refresh'] ?? 'false') === 'true';
        $olt = $db->getOlt($id);
        if (!$olt) throw new Exception("OLT not found");

        $is_online = false;
        $hostParts = parse_url((preg_match('/^https?:\/\//', $olt['host']) ? '' : 'http://') . $olt['host']);
        $checkHost = $hostParts['host'] ?? $olt['host'];
        $checkPort = $hostParts['port'] ?? (strpos($olt['host'], 'https://') === 0 ? 443 : 80);
        
        $fp = @fsockopen($checkHost, $checkPort, $errno, $errstr, 1);
        if ($fp) {
            $is_online = true;
            fclose($fp);
        }

        // Return cache if available and not forced
        if (!$force && !empty($olt['last_stats'])) {
            echo json_encode([
                'status' => 'success', 
                'data' => json_decode($olt['last_stats'], true), 
                'cached' => true,
                'is_online' => $is_online,
                'updated_at' => $olt['last_updated']
            ]);
            exit;
        }

        $driver = OLT_Factory::getDriver($olt['type']);
        if ($driver->connect($olt['host'], $olt['user'], $olt['password'])) {
            $stats = $driver->getGlobalStats();
            $info = $driver->getSystemInfo();
            $driver->disconnect();
            
            // Save to cache
            $db->updateOltStats($id, $stats);
            
            echo json_encode(['status' => 'success', 'data' => $stats, 'info' => $info, 'cached' => false]);
        } else {
            throw new Exception("Connection Failed");
        }
        exit;
    }

    if ($action === 'get_all_details') {
        $id = $_GET['id'] ?? '';
        $olt = $db->getOlt($id);
        if (!$olt) throw new Exception("OLT not found");

        $driver = OLT_Factory::getDriver($olt['type']);
        if ($driver->connect($olt['host'], $olt['user'], $olt['password'])) {
            $stats = $driver->getGlobalStats();
            $info = $driver->getSystemInfo();
            $pons = $stats['pon_ports'] ?? [];

            $allOnus = [];
            foreach ($pons as $p) {
                $details = $driver->getPonOnuDetails($p['name']);
                foreach ($details as &$d) {
                    $d['olt_id'] = $olt['id'];
                }
                $allOnus = array_merge($allOnus, $details);
            }
            $driver->disconnect();

            // Save stats to cache too while we are at it
            $db->updateOltStats($id, $stats);

            echo json_encode(['status' => 'success', 'info' => $info, 'onus' => $allOnus, 'stats' => $stats]);
        } else {
            throw new Exception("Connection Failed");
        }
        exit;
    }

    if ($action === 'reboot_onu') {
        $id = $_GET['id'] ?? '';
        $onuId = $_POST['onu_id'] ?? '';
        $onuName = $_POST['onu_name'] ?? '';
        
        $olt = $db->getOlt($id);
        if (!$olt) throw new Exception("OLT not found");

        $driver = OLT_Factory::getDriver($olt['type']);
        if ($driver->connect($olt['host'], $olt['user'], $olt['password'])) {
            $success = $driver->rebootOnu($onuId, $onuName);
            $driver->disconnect();
            echo json_encode(['status' => $success ? 'success' : 'error', 'message' => $success ? 'Reboot command sent' : 'Failed to send reboot']);
        } else {
            throw new Exception("Connection Failed");
        }
        exit;
    }

    if ($action === 'get_all_onus') {
        $olts = $db->getOlts();
        $allOnus = [];
        foreach ($olts as $olt) {
            try {
                $driver = OLT_Factory::getDriver($olt['type']);
                if ($driver->connect($olt['host'], $olt['user'], $olt['password'])) {
                    $stats = $driver->getGlobalStats();
                    $pons = $stats['pon_ports'] ?? [];
                    foreach ($pons as $p) {
                        $details = $driver->getPonOnuDetails($p['name']);
                        foreach ($details as &$d) {
                            $d['olt_id'] = $olt['id'];
                            $d['olt_name'] = $olt['name'];
                            $d['olt_host'] = $olt['host'];
                            $d['olt_desc'] = $olt['description'];
                        }
                        $allOnus = array_merge($allOnus, $details);
                    }
                    $driver->disconnect();
                    $db->updateOltStats($olt['id'], $stats);
                }
            } catch (Exception $e) { continue; }
        }
        echo json_encode(['status' => 'success', 'data' => $allOnus]);
        exit;
    }

    if ($action === 'list') {
        echo json_encode($db->getOlts());
        exit;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
