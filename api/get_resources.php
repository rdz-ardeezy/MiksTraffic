<?php
/**
 * MiksTraffic API - Get Resource History
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

$router_id = $_GET['router_id'] ?? null;
$period = $_GET['period'] ?? 'live'; // live, 5m

if (!$router_id) {
    die(json_encode(['status' => 'error', 'message' => 'Missing router_id']));
}

try {
    $db = new MiksDB();
    $dbConnection = $db->getDb();

    // Calculate Offset for SQLite
    $tz = new DateTimeZone(APP_TIMEZONE);
    $offsetSeconds = $tz->getOffset(new DateTime());
    $offsetHours = $offsetSeconds / 3600;
    $sqliteOffset = ($offsetHours >= 0 ? '+' : '-') . abs($offsetHours) . ' hours';

    $data = [];

    switch ($period) {
        case 'hourly':
            $data = $db->getResourceHistoryHourly($router_id, 24);
            break;

        case 'daily':
            $data = $db->getResourceHistoryDaily($router_id, 30);
            break;

        case 'monthly':
            $data = $db->getResourceHistoryMonthly($router_id, 24);
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
            
            $stmt = $dbConnection->prepare("
                SELECT (strftime('%s', timestamp) / $interval) * $interval as grp,
                       AVG(cpu_load) as cpu_load,
                       AVG(free_memory) as free_memory,
                       datetime((strftime('%s', timestamp) / $interval) * $interval, 'unixepoch', '$sqliteOffset') as local_ts
                FROM resource_log
                WHERE router_id = ? AND timestamp >= datetime('now', '-" . ($seconds + $interval) . " seconds')
                GROUP BY grp
                ORDER BY grp DESC
            ");
            $stmt->execute([$router_id]);
            $dbData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $dbMap = [];
            foreach ($dbData as $row) { $dbMap[$row['grp']] = $row; }

            $now = time();
            $currentGrp = floor($now / $interval) * $interval;
            for ($i = 59; $i >= 0; $i--) {
                $grp = $currentGrp - ($i * $interval);
                if (isset($dbMap[$grp])) {
                    $row = $dbMap[$grp];
                    $data[] = [
                        'cpu_load' => $row['cpu_load'],
                        'free_memory' => $row['free_memory'],
                        'timestamp' => $row['local_ts']
                    ];
                } else {
                    $localTs = date('Y-m-d H:i:s', $grp + $offsetSeconds);
                    $data[] = [
                        'cpu_load' => 0,
                        'free_memory' => 0,
                        'timestamp' => $localTs
                    ];
                }
            }
            break;

        default:
            $data = $db->getResourceHistory($router_id, 60);
            break;
    }

    echo json_encode([
        'status' => 'success',
        'router_id' => $router_id,
        'period' => $period,
        'data' => $data
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
