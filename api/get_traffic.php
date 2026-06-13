<?php
/**
 * MiksTraffic API - Get Traffic Data
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

$router_id = $_GET['router_id'] ?? null;
$interface = $_GET['interface'] ?? null;
$period = $_GET['period'] ?? 'live'; // live, 5m, 1h, 24h, 7d, 30d

if (!$router_id || !$interface) {
    die(json_encode(['status' => 'error', 'message' => 'Missing router_id or interface']));
}

try {
    $db = new MiksDB();
    $dbConnection = $db->getDb();

    // Calculate Offset for SQLite (e.g. '+7 hours')
    $tz = new DateTimeZone(APP_TIMEZONE);
    $offsetSeconds = $tz->getOffset(new DateTime());
    $offsetHours = $offsetSeconds / 3600;
    $sqliteOffset = ($offsetHours >= 0 ? '+' : '-') . abs($offsetHours) . ' hours';

    $data = [];

    switch ($period) {
        case 'hourly':
            $stmt = $dbConnection->prepare("
                SELECT strftime('%Y-%m-%d %H:00:00', timestamp, '$sqliteOffset') as time_label, 
                       AVG(avg_rx_rate) as rx_rate, 
                       AVG(avg_tx_rate) as tx_rate,
                       MAX(timestamp) as timestamp
                FROM traffic_log_hourly 
                WHERE router_id = ? AND interface = ? AND timestamp >= datetime('now', '-24 hours')
                GROUP BY time_label 
            ");
            $stmt->execute([$router_id, $interface]);
            $dbData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $dbMap = [];
            foreach ($dbData as $row) {
                $dbMap[$row['time_label']] = $row;
            }
            
            // Generate 24 points
            for ($i = 23; $i >= 0; $i--) {
                $t = strtotime("-$i hours");
                $label = date('Y-m-d H:00:00', $t);
                if (isset($dbMap[$label])) {
                    $data[] = $dbMap[$label];
                } else {
                    $data[] = ['time_label' => $label, 'rx_rate' => null, 'tx_rate' => null, 'timestamp' => date('Y-m-d H:00:00', $t)];
                }
            }
            break;
            
        case 'daily':
            $stmt = $dbConnection->prepare("
                SELECT strftime('%Y-%m-%d', timestamp, '$sqliteOffset') as time_label, 
                       AVG(avg_rx_rate) as rx_rate, 
                       AVG(avg_tx_rate) as tx_rate,
                       MAX(timestamp) as timestamp
                FROM traffic_log_daily 
                WHERE router_id = ? AND interface = ? AND timestamp >= datetime('now', '-30 days')
                GROUP BY time_label 
            ");
            $stmt->execute([$router_id, $interface]);
            $dbData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $dbMap = [];
            foreach ($dbData as $row) {
                $dbMap[$row['time_label']] = $row;
            }
            
            // Generate 30 points
            for ($i = 29; $i >= 0; $i--) {
                $t = strtotime("-$i days");
                $label = date('Y-m-d', $t);
                if (isset($dbMap[$label])) {
                    $data[] = $dbMap[$label];
                } else {
                    $data[] = ['time_label' => $label, 'rx_rate' => null, 'tx_rate' => null, 'timestamp' => date('Y-m-d 00:00:00', $t)];
                }
            }
            break;
            
        case 'monthly':
            $stmt = $dbConnection->prepare("
                SELECT strftime('%Y-%m', timestamp, '$sqliteOffset') as time_label, 
                       AVG(avg_rx_rate) as rx_rate, 
                       AVG(avg_tx_rate) as tx_rate,
                       MAX(timestamp) as timestamp
                FROM traffic_log_monthly 
                WHERE router_id = ? AND interface = ? AND timestamp >= datetime('now', '-24 months')
                GROUP BY time_label 
            ");
            $stmt->execute([$router_id, $interface]);
            $dbData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $dbMap = [];
            foreach ($dbData as $row) {
                $dbMap[$row['time_label']] = $row;
            }
            
            // Generate 24 points
            for ($i = 23; $i >= 0; $i--) {
                $t = strtotime("first day of -$i months");
                $label = date('Y-m', $t);
                if (isset($dbMap[$label])) {
                    $data[] = $dbMap[$label];
                } else {
                    $data[] = ['time_label' => $label, 'rx_rate' => null, 'tx_rate' => null, 'timestamp' => date('Y-m-01 00:00:00', $t)];
                }
            }
            break;

        case 'live':
        case 'live_5m':
        case 'live_15m':
        case 'live_30m':
        case 'live_1h':
            $interval = 1;
            if ($period === 'live_5m') $interval = 5;
            if ($period === 'live_15m') $interval = 15;
            if ($period === 'live_30m') $interval = 30;
            if ($period === 'live_1h') $interval = 60;
            
            $limit = 60;
            $seconds = $interval * $limit;
            
            // Get actual data
            $stmt = $dbConnection->prepare("
                SELECT (strftime('%s', timestamp) / $interval) * $interval as grp,
                       AVG(rx_rate) as rx_rate,
                       AVG(tx_rate) as tx_rate,
                       datetime((strftime('%s', timestamp) / $interval) * $interval, 'unixepoch', '$sqliteOffset') as local_ts
                FROM traffic_log
                WHERE router_id = ? AND interface = ? AND timestamp >= datetime('now', '-" . ($seconds + $interval) . " seconds')
                GROUP BY grp
                ORDER BY grp DESC
            ");
            $stmt->execute([$router_id, $interface]);
            $dbData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $dbMap = [];
            foreach ($dbData as $row) {
                $dbMap[$row['grp']] = $row;
            }

            $data = [];
            $now = time();
            $currentGrp = floor($now / $interval) * $interval;
            
            for ($i = 59; $i >= 0; $i--) {
                $grp = $currentGrp - ($i * $interval);
                if (isset($dbMap[$grp])) {
                    $row = $dbMap[$grp];
                    $data[] = [
                        'rx_rate' => $row['rx_rate'],
                        'tx_rate' => $row['tx_rate'],
                        'timestamp' => $row['local_ts']
                    ];
                } else {
                    $data[] = [
                        'rx_rate' => null,
                        'tx_rate' => null,
                        'timestamp' => date('Y-m-d H:i:s', $grp)
                    ];
                }
            }
            break;

        default:
            $data = $db->getTrafficHistory($router_id, $interface, 60);
            break;
    }
    
    
    // Strip leading nulls to make the graph stretch to the left edge
    while (!empty($data) && $data[0]['rx_rate'] === null) {
        array_shift($data);
    }
    // Strip trailing nulls to make the graph stretch to the right edge
    while (!empty($data) && end($data)['rx_rate'] === null) {
        array_pop($data);
    }
    
    echo json_encode([
        'status' => 'success',
        'router_id' => $router_id,
        'interface' => $interface,
        'period' => $period,
        'data' => $data
    ]);
} catch (Exception $e) {
    http_response_code(500);
    
    // Strip leading nulls to make the graph stretch to the left edge
    while (!empty($data) && $data[0]['rx_rate'] === null) {
        array_shift($data);
    }
    // Strip trailing nulls to make the graph stretch to the right edge
    while (!empty($data) && end($data)['rx_rate'] === null) {
        array_pop($data);
    }
    
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
