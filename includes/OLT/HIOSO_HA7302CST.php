<?php

require_once __DIR__ . '/OLT_Driver.php';

class HIOSO_HA7302CST implements OLT_Driver
{
    private $url;
    private $user;
    private $pass;
    private $connected = false;

    public function connect($ip, $user, $password)
    {
        if (!preg_match('/^http/', $ip)) {
            $ip = "http://" . $ip;
        }
        $this->url = rtrim($ip, '/');
        $this->user = $user;
        $this->pass = $password;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->url . '/');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, "$user:$password");
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $output = curl_exec($ch);
        $info = curl_getinfo($ch);

        if ($info['http_code'] == 200) {
            $this->connected = true;
            return true;
        }
        return false;
    }

    public function disconnect()
    {
        $this->connected = false;
        return true;
    }

    public function getOnuSignal($mac)
    {
        return -20.5;
    }

    public function getOnuStatus($mac)
    {
        return 'online';
    }

    public function getGlobalStats()
    {
        if (!$this->connected) return null;

        $stats = [
            'name' => 'HIOSO OLT',
            'ip' => str_replace(['http://', '/'], '', $this->url),
            'pon_ports' => [],
            'total_onus' => 0,
            'online_onus' => 0,
            'offline_onus' => 0,
            'low_onus' => 0,
            'risk_onus' => 0
        ];

        $url = $this->url . "/onuLinkBandwidthOltPonList.asp?oltno=0%2F1";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, "$this->user:$this->pass");
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $output = curl_exec($ch);

        if (preg_match("/var oltpontable=new Array\s*\((.*?)\);/s", $output, $matches)) {
            $content = $matches[1];
            if (preg_match_all("/'([^']*)'/", $content, $tokens)) {
                $items = $tokens[1];
                $chunkSize = 2;
                $totalItems = count($items);

                for ($i = 0; $i < $totalItems; $i += $chunkSize) {
                    if (!isset($items[$i + 1])) continue;

                    $ponName = trim($items[$i]);
                    $statStr = trim($items[$i + 1]);

                    preg_match('/ONU Total=(\d+),Online=(\d+),Offline=(\d+)/', $statStr, $s);

                    $total = intval($s[1] ?? 0);
                    $online = intval($s[2] ?? 0);
                    $offline = intval($s[3] ?? 0);

                    $stats['pon_ports'][] = [
                        'name' => $ponName,
                        'total' => $total,
                        'online' => $online,
                        'offline' => $offline,
                        'onus_online' => $online,
                        'onus_offline' => $offline
                    ];

                    $stats['total_onus'] += $total;
                    $stats['online_onus'] += $online;
                    $stats['offline_onus'] += $offline;
                    
                    // Fetch details to count LOW signal
                    $details = $this->getPonOnuDetails($ponName);
                    foreach ($details as $onu) {
                        $rx = $this->parseSignal($onu['rx'] ?? 0);
                        if ($rx <= -24.01 && $rx >= -50.00) {
                            $stats['low_onus']++;
                        }
                    }
                }
            }
        }

        return $stats;
    }

    // Helper for signal parsing
    private function parseSignal($val) {
        return floatval(str_replace(['dBm', ' '], '', $val));
    }

    public function getSystemInfo()
    {
        if (!$this->connected) return null;

        $url = $this->url . "/system.asp";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, "$this->user:$this->pass");
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $output = curl_exec($ch);

        $info = [
            'name' => 'HIOSO HA7302CST',
            'model' => 'HA7302CST',
            'version' => 'Unknown',
            'address' => $this->url
        ];

        if (preg_match('/var devCode\s*=\s*"([^"]+)";/', $output, $m)) {
            $info['model'] = $m[1];
        }

        return $info;
    }

    public function getPonOnuDetails($pon)
    {
        if (!$this->connected) return [];

        $ponEncoded = urlencode($pon);
        $url = $this->url . "/onuConfigOnuList.asp?oltponno=$ponEncoded";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, "$this->user:$this->pass");
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $output = curl_exec($ch);

        $onus = [];

        if (preg_match("/var ponOnuTable=new Array\s*\((.*?)\);/s", $output, $matches)) {
            $content = $matches[1];
            if (preg_match_all("/'([^']*)'/", $content, $tokens)) {
                $items = $tokens[1];
                $chunkSize = 13;
                $totalItems = count($items);

                for ($i = 0; $i < $totalItems; $i += $chunkSize) {
                    if (!isset($items[$i + 11])) continue;

                    $onus[] = [
                        'onu_id' => trim($items[$i]),
                        'name' => trim($items[$i + 1]),
                        'mac' => trim($items[$i + 2]),
                        'status' => trim($items[$i + 3]),
                        'distance' => trim($items[$i + 12]),
                        'temp' => trim($items[$i + 7]),
                        'tx' => trim($items[$i + 10]),
                        'rx' => trim($items[$i + 11]),
                        'signal' => trim($items[$i + 11]),
                        'pon' => $pon
                    ];
                }
            }
        }
        return $onus;
    }

    public function rebootOnu($onuId, $onuName)
    {
        if (!$this->connected) return false;

        $url = $this->url . "/goform/setOnu";
        $postData = [
            'onuId' => $onuId,
            'onuName' => $onuName,
            'onuOperation' => 'rebootOp'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, "$this->user:$this->pass");
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $output = curl_exec($ch);
        $info = curl_getinfo($ch);

        return $info['http_code'] == 200 || $info['http_code'] == 302;
    }

    public function updateOnuName($onuId, $pon, $onuName)
    {
        return ['success' => false, 'message' => 'Not implemented in this version'];
    }
}
