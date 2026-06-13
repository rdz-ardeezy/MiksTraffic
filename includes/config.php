<?php
/**
 * MiksTraffic Configuration
 */

// MikroTik Router Credentials
define('MT_HOST', '192.168.88.1');
define('MT_USER', 'admin');
define('MT_PASS', 'password');
define('MT_PORT', 8728); // Standard API port

// Database Configuration
define('DB_PATH', __DIR__ . '/../data/miks_traffic.db');

// Monitoring Settings
define('POLLING_INTERVAL', 60); // Seconds
define('MONITOR_INTERFACES', ['ether1', 'wlan1']); // Interfaces to monitor

// App Settings
define('APP_NAME', 'MiksTraffic');
define('APP_TIMEZONE', 'Asia/Jakarta');
define('APP_KEY', 'default_secret_key_change_me_in_production');
define('DEBUG_MODE', true);

date_default_timezone_set(APP_TIMEZONE);

// WhatsApp Notification Settings
define('WA_API_URL', '');
define('WA_TARGET_NUMBER', '');
define('WA_TRAFFIC_THRESHOLD', '10'); // Mbps
define('WA_CHECK_INTERVAL', '5'); // Minutes
