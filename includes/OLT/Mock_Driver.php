<?php

require_once __DIR__ . '/OLT_Driver.php';

class Mock_Driver implements OLT_Driver
{
    public function connect($ip, $user, $password) { return true; }
    public function disconnect() { return true; }
    public function getSystemInfo() {
        return ['name' => 'Mock OLT', 'model' => 'MOCK-V1', 'version' => '1.0.0', 'address' => '127.0.0.1'];
    }
    public function getGlobalStats() {
        return [
            'total_onus' => 20,
            'online_onus' => 18,
            'offline_onus' => 2,
            'low_onus' => 1,
            'pon_ports' => [
                ['name' => 'PON 1', 'online' => 10, 'offline' => 1],
                ['name' => 'PON 2', 'online' => 8, 'offline' => 1]
            ]
        ];
    }
    public function getPonOnuDetails($pon) {
        return [
            ['onu_id' => '1', 'name' => 'User1', 'mac' => 'AA:BB:CC:DD:EE:01', 'status' => 'Online', 'rx' => '-19.2', 'pon' => $pon],
            ['onu_id' => '2', 'name' => 'User2', 'mac' => 'AA:BB:CC:DD:EE:02', 'status' => 'Offline', 'rx' => '--', 'pon' => $pon]
        ];
    }
    public function getOnuSignal($mac) { return -19.5; }
    public function getOnuStatus($mac) { return 'online'; }
    public function rebootOnu($onuId, $onuName) { return true; }
    public function updateOnuName($onuId, $pon, $onuName) { return ['success' => true]; }
}
