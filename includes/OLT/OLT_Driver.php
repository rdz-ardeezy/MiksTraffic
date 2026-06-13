<?php
/**
 * OLT Driver Interface
 */
interface OLT_Driver
{
    public function connect($ip, $user, $password);
    public function disconnect();
    public function getSystemInfo();
    public function getGlobalStats();
    public function getPonOnuDetails($pon);
    public function getOnuSignal($mac);
    public function getOnuStatus($mac);
    public function rebootOnu($onuId, $onuName);
    public function updateOnuName($onuId, $pon, $onuName);
}
