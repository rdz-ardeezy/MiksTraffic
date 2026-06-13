<?php
/**
 * MiksTraffic Database Handler
 */

class MiksDB {
    private $db;

    public function __construct() {
        try {
            $this->db = new PDO('sqlite:' . DB_PATH);
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->initSchema();
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }

    public function getDb() {
        return $this->db;
    }

    private function initSchema() {
        $queries = [
            "CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT UNIQUE NOT NULL,
                password TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )",
            "CREATE TABLE IF NOT EXISTS routers (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                host TEXT NOT NULL,
                username TEXT NOT NULL,
                password TEXT NOT NULL,
                port INTEGER DEFAULT 8728,
                monitored_interfaces TEXT, -- Comma separated
                status TEXT DEFAULT 'active',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )",
            "CREATE TABLE IF NOT EXISTS traffic_log (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                router_id INTEGER,
                interface TEXT NOT NULL,
                rx_bytes INTEGER DEFAULT 0,
                tx_bytes INTEGER DEFAULT 0,
                rx_rate INTEGER DEFAULT 0,
                tx_rate INTEGER DEFAULT 0,
                sfp_power REAL DEFAULT 0,
                link_rate TEXT,
                timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (router_id) REFERENCES routers(id)
            )",
            "CREATE TABLE IF NOT EXISTS traffic_log_5m (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                router_id INTEGER,
                interface TEXT NOT NULL,
                avg_rx_rate INTEGER,
                avg_tx_rate INTEGER,
                avg_sfp_power REAL,
                timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
            )",
            "CREATE TABLE IF NOT EXISTS traffic_log_hourly (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                router_id INTEGER,
                interface TEXT NOT NULL,
                avg_rx_rate INTEGER,
                avg_tx_rate INTEGER,
                timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
            )",
            "CREATE TABLE IF NOT EXISTS traffic_log_daily (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                router_id INTEGER,
                interface TEXT NOT NULL,
                avg_rx_rate INTEGER,
                avg_tx_rate INTEGER,
                timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
            )",
            "CREATE TABLE IF NOT EXISTS traffic_log_monthly (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                router_id INTEGER,
                interface TEXT NOT NULL,
                avg_rx_rate INTEGER,
                avg_tx_rate INTEGER,
                timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
            )",
            "CREATE TABLE IF NOT EXISTS olts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                description TEXT,
                type TEXT NOT NULL,
                host TEXT NOT NULL,
                user TEXT NOT NULL,
                password TEXT,
                port INTEGER DEFAULT 23,
                last_stats TEXT,
                last_updated DATETIME,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )",
            "CREATE TABLE IF NOT EXISTS password_resets (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL,
                token TEXT NOT NULL,
                expires_at DATETIME NOT NULL
            )",
            "CREATE INDEX IF NOT EXISTS idx_router ON traffic_log(router_id)",
            "CREATE INDEX IF NOT EXISTS idx_interface ON traffic_log(interface)",
            "CREATE INDEX IF NOT EXISTS idx_timestamp ON traffic_log(timestamp)",
            "CREATE INDEX IF NOT EXISTS idx_5m_router ON traffic_log_5m(router_id)",
            "CREATE INDEX IF NOT EXISTS idx_5m_timestamp ON traffic_log_5m(timestamp)",
            "CREATE TABLE IF NOT EXISTS resource_log (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                router_id INTEGER,
                cpu_load INTEGER DEFAULT 0,
                free_memory INTEGER DEFAULT 0,
                total_memory INTEGER DEFAULT 0,
                uptime TEXT,
                timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (router_id) REFERENCES routers(id)
            )",
            "CREATE TABLE IF NOT EXISTS resource_log_5m (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                router_id INTEGER,
                avg_cpu_load INTEGER,
                avg_free_memory INTEGER,
                timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
            )",
            "CREATE TABLE IF NOT EXISTS resource_log_hourly (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                router_id INTEGER,
                avg_cpu_load INTEGER,
                avg_free_memory INTEGER,
                timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
            )",
            "CREATE TABLE IF NOT EXISTS resource_log_daily (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                router_id INTEGER,
                avg_cpu_load INTEGER,
                avg_free_memory INTEGER,
                timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
            )",
            "CREATE TABLE IF NOT EXISTS resource_log_monthly (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                router_id INTEGER,
                avg_cpu_load INTEGER,
                avg_free_memory INTEGER,
                timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
            )",
            "CREATE INDEX IF NOT EXISTS idx_res_router ON resource_log(router_id)",
            "CREATE INDEX IF NOT EXISTS idx_res_timestamp ON resource_log(timestamp)"
        ];

        foreach ($queries as $query) {
            $this->db->exec($query);
        }

        // Migration for SFP and Link Rate
        $res = $this->db->query("PRAGMA table_info(traffic_log)");
        $cols = $res->fetchAll(PDO::FETCH_COLUMN, 1);
        
        if (!in_array('sfp_power', $cols)) {
            $this->db->exec("ALTER TABLE traffic_log ADD COLUMN sfp_power REAL DEFAULT 0");
        }
        if (!in_array('link_rate', $cols)) {
            $this->db->exec("ALTER TABLE traffic_log ADD COLUMN link_rate TEXT");
        }
        if (!in_array('router_id', $cols)) {
            try {
                $this->db->exec("ALTER TABLE traffic_log ADD COLUMN router_id INTEGER");
            } catch (\PDOException $e) {}
        }

        // Migration for OLT Cache
        $res = $this->db->query("PRAGMA table_info(olts)");
        $cols = $res->fetchAll(PDO::FETCH_COLUMN, 1);
        
        if (!in_array('last_stats', $cols)) {
            $this->db->exec("ALTER TABLE olts ADD COLUMN last_stats TEXT");
        }
        if (!in_array('last_updated', $cols)) {
            $this->db->exec("ALTER TABLE olts ADD COLUMN last_updated DATETIME");
        }

        // Create default admin if not exists (admin/admin123)
        $stmt = $this->db->query("SELECT COUNT(*) FROM users");
        if ($stmt->fetchColumn() == 0) {
            $pass = password_hash('admin123', PASSWORD_DEFAULT);
            $this->db->prepare("INSERT INTO users (username, password) VALUES (?, ?)")->execute(['admin', $pass]);
        }
    }

    public function encrypt($data) {
        if (empty($data)) return $data;
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
        $encrypted = openssl_encrypt($data, 'aes-256-cbc', APP_KEY, 0, $iv);
        return 'ENC::' . base64_encode($encrypted . '::' . $iv);
    }

    public function decrypt($data) {
        if (empty($data) || strpos($data, 'ENC::') !== 0) return $data;
        $decoded = base64_decode(substr($data, 5));
        if (strpos($decoded, '::') === false) return $data;
        list($encrypted_data, $iv) = explode('::', $decoded, 2);
        $decrypted = openssl_decrypt($encrypted_data, 'aes-256-cbc', APP_KEY, 0, $iv);
        return $decrypted !== false ? $decrypted : $data;
    }

    // Router Management
    public function addRouter($name, $host, $user, $pass, $port, $interfaces) {
        $stmt = $this->db->prepare("INSERT INTO routers (name, host, username, password, port, monitored_interfaces) VALUES (?, ?, ?, ?, ?, ?)");
        return $stmt->execute([$name, $this->encrypt($host), $this->encrypt($user), $this->encrypt($pass), $port, $interfaces]);
    }

    public function updateRouter($id, $name, $host, $user, $pass, $port, $interfaces) {
        $stmt = $this->db->prepare("UPDATE routers SET name=?, host=?, username=?, password=?, port=?, monitored_interfaces=? WHERE id=?");
        return $stmt->execute([$name, $this->encrypt($host), $this->encrypt($user), $this->encrypt($pass), $port, $interfaces, $id]);
    }

    public function deleteRouter($id) {
        $stmt = $this->db->prepare("DELETE FROM routers WHERE id=?");
        return $stmt->execute([$id]);
    }

    public function getRouters() {
        $routers = $this->db->query("SELECT * FROM routers ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($routers as &$router) {
            $router['host'] = $this->decrypt($router['host']);
            $router['username'] = $this->decrypt($router['username']);
            $router['password'] = $this->decrypt($router['password']);
        }
        return $routers;
    }

    public function getRouter($id) {
        $stmt = $this->db->prepare("SELECT * FROM routers WHERE id = ?");
        $stmt->execute([$id]);
        $router = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($router) {
            $router['host'] = $this->decrypt($router['host']);
            $router['username'] = $this->decrypt($router['username']);
            $router['password'] = $this->decrypt($router['password']);
        }
        return $router;
    }

    // User Management
    public function getUserByUsername($username) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getUserById($id) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updateAdminCredentials($id, $username, $password = null) {
        if ($password) {
            $stmt = $this->db->prepare("UPDATE users SET username = ?, password = ? WHERE id = ?");
            return $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $id]);
        } else {
            $stmt = $this->db->prepare("UPDATE users SET username = ? WHERE id = ?");
            return $stmt->execute([$username, $id]);
        }
    }

    public function getAllAdmins() {
        return $this->db->query("SELECT id, username, created_at FROM users ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addAdmin($username, $password) {
        $stmt = $this->db->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
        return $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
    }

    public function deleteAdmin($id) {
        $count = $this->db->query("SELECT COUNT(*) FROM users")->fetchColumn();
        if ($count <= 1) return false; // Prevent deleting the last admin
        
        $stmt = $this->db->prepare("DELETE FROM users WHERE id = ?");
        return $stmt->execute([$id]);
    }

    // Password Recovery
    public function createPasswordResetToken($username) {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', time() + 3600); // 1 hour expiry
        $stmt = $this->db->prepare("INSERT INTO password_resets (username, token, expires_at) VALUES (?, ?, ?)");
        if ($stmt->execute([$username, $token, $expires])) {
            return $token;
        }
        return false;
    }

    public function verifyPasswordResetToken($token) {
        // Clean up expired tokens first
        $this->db->exec("DELETE FROM password_resets WHERE expires_at < CURRENT_TIMESTAMP");
        
        $stmt = $this->db->prepare("SELECT * FROM password_resets WHERE token = ?");
        $stmt->execute([$token]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function deletePasswordResetToken($token) {
        $stmt = $this->db->prepare("DELETE FROM password_resets WHERE token = ?");
        return $stmt->execute([$token]);
    }

    // Logging Update
    public function logTraffic($router_id, $interface, $rx_bytes, $tx_bytes, $rx_rate, $tx_rate, $sfp_power = 0, $link_rate = '') {
        $stmt = $this->db->prepare("INSERT INTO traffic_log (router_id, interface, rx_bytes, tx_bytes, rx_rate, tx_rate, sfp_power, link_rate) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        return $stmt->execute([$router_id, $interface, $rx_bytes, $tx_bytes, $rx_rate, $tx_rate, $sfp_power, $link_rate]);
    }

    public function getTrafficHistory($router_id, $interface, $limit = 60) {
        $stmt = $this->db->prepare("SELECT id, router_id, interface, rx_bytes, tx_bytes, rx_rate, tx_rate, sfp_power, link_rate, (timestamp || 'Z') as timestamp FROM traffic_log WHERE router_id = ? AND interface = ? ORDER BY timestamp DESC LIMIT ?");
        $stmt->execute([$router_id, $interface, $limit]);
        return array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function logResource($router_id, $cpu, $free_ram, $total_ram, $uptime) {
        $stmt = $this->db->prepare("INSERT INTO resource_log (router_id, cpu_load, free_memory, total_memory, uptime) VALUES (?, ?, ?, ?, ?)");
        return $stmt->execute([$router_id, $cpu, $free_ram, $total_ram, $uptime]);
    }

    public function getResourceHistory($router_id, $limit = 60) {
        $stmt = $this->db->prepare("SELECT id, router_id, cpu_load, free_memory, total_memory, uptime, (timestamp || 'Z') as timestamp FROM resource_log WHERE router_id = ? ORDER BY timestamp DESC LIMIT ?");
        $stmt->execute([$router_id, $limit]);
        return array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function getResourceHistory5m($router_id, $limit = 288) {
        $stmt = $this->db->prepare("SELECT id, router_id, avg_cpu_load as cpu_load, avg_free_memory as free_memory, (timestamp || 'Z') as timestamp FROM resource_log_5m WHERE router_id = ? ORDER BY timestamp DESC LIMIT ?");
        $stmt->execute([$router_id, $limit]);
        return array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function getResourceHistoryHourly($router_id, $limit = 24) {
        $stmt = $this->db->prepare("SELECT id, router_id, avg_cpu_load as cpu_load, avg_free_memory as free_memory, (timestamp || 'Z') as timestamp FROM resource_log_hourly WHERE router_id = ? ORDER BY timestamp DESC LIMIT ?");
        $stmt->execute([$router_id, $limit]);
        return array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function getResourceHistoryDaily($router_id, $limit = 30) {
        $stmt = $this->db->prepare("SELECT id, router_id, avg_cpu_load as cpu_load, avg_free_memory as free_memory, (timestamp || 'Z') as timestamp FROM resource_log_daily WHERE router_id = ? ORDER BY timestamp DESC LIMIT ?");
        $stmt->execute([$router_id, $limit]);
        return array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function getResourceHistoryMonthly($router_id, $limit = 24) {
        $stmt = $this->db->prepare("SELECT id, router_id, avg_cpu_load as cpu_load, avg_free_memory as free_memory, (timestamp || 'Z') as timestamp FROM resource_log_monthly WHERE router_id = ? ORDER BY timestamp DESC LIMIT ?");
        $stmt->execute([$router_id, $limit]);
        return array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Aggregate 1s data into 5m averages
     */
    public function aggregate5m() {
        // Traffic
        $this->db->exec("INSERT INTO traffic_log_5m (router_id, interface, avg_rx_rate, avg_tx_rate)
            SELECT router_id, interface, AVG(rx_rate), AVG(tx_rate)
            FROM traffic_log
            WHERE timestamp >= datetime('now', '-5 minutes')
            GROUP BY router_id, interface");
            
        // Resources
        $this->db->exec("INSERT INTO resource_log_5m (router_id, avg_cpu_load, avg_free_memory)
            SELECT router_id, AVG(cpu_load), AVG(free_memory)
            FROM resource_log
            WHERE timestamp >= datetime('now', '-5 minutes')
            GROUP BY router_id");
    }

    public function aggregateHourly() {
        // Traffic
        $this->db->exec("INSERT INTO traffic_log_hourly (router_id, interface, avg_rx_rate, avg_tx_rate)
            SELECT router_id, interface, AVG(avg_rx_rate), AVG(avg_tx_rate)
            FROM traffic_log_5m
            WHERE timestamp >= datetime('now', '-1 hour')
            GROUP BY router_id, interface");
            
        // Resources
        $this->db->exec("INSERT INTO resource_log_hourly (router_id, avg_cpu_load, avg_free_memory)
            SELECT router_id, AVG(avg_cpu_load), AVG(avg_free_memory)
            FROM resource_log_5m
            WHERE timestamp >= datetime('now', '-1 hour')
            GROUP BY router_id");
    }

    public function aggregateDaily() {
        // Traffic
        $this->db->exec("INSERT INTO traffic_log_daily (router_id, interface, avg_rx_rate, avg_tx_rate)
            SELECT router_id, interface, AVG(avg_rx_rate), AVG(avg_tx_rate)
            FROM traffic_log_hourly
            WHERE timestamp >= datetime('now', '-1 day')
            GROUP BY router_id, interface");
            
        // Resources
        $this->db->exec("INSERT INTO resource_log_daily (router_id, avg_cpu_load, avg_free_memory)
            SELECT router_id, AVG(avg_cpu_load), AVG(avg_free_memory)
            FROM resource_log_hourly
            WHERE timestamp >= datetime('now', '-1 day')
            GROUP BY router_id");
    }

    /**
     * Cleanup old data to save space
     */
    public function pruneOldData() {
        // Keep raw data for only 2 hours
        $this->db->exec("DELETE FROM traffic_log WHERE timestamp < datetime('now', '-2 hours')");
        // Keep 5m data for 7 days
        $this->db->exec("DELETE FROM traffic_log_5m WHERE timestamp < datetime('now', '-7 days')");
        // Keep resource raw data for 2 hours
        $this->db->exec("DELETE FROM resource_log WHERE timestamp < datetime('now', '-2 hours')");
        // Keep resource 5m data for 7 days
        $this->db->exec("DELETE FROM resource_log_5m WHERE timestamp < datetime('now', '-7 days')");
        // Keep resource hourly data for 30 days
        $this->db->exec("DELETE FROM resource_log_hourly WHERE timestamp < datetime('now', '-30 days')");
        // Keep resource daily data for 2 years
        $this->db->exec("DELETE FROM resource_log_daily WHERE timestamp < datetime('now', '-2 years')");
        // Keep resource monthly data for 5 years
        $this->db->exec("DELETE FROM resource_log_monthly WHERE timestamp < datetime('now', '-5 years')");
        // Keep hourly data for 30 days
        $this->db->exec("DELETE FROM traffic_log_hourly WHERE timestamp < datetime('now', '-30 days')");
        // Keep daily data for 2 years (24 months)
        $this->db->exec("DELETE FROM traffic_log_daily WHERE timestamp < datetime('now', '-2 years')");
        // Keep monthly data for 5 years
        $this->db->exec("DELETE FROM traffic_log_monthly WHERE timestamp < datetime('now', '-5 years')");
    }

    public function aggregateMonthly() {
        // Traffic
        $this->db->exec("INSERT INTO traffic_log_monthly (router_id, interface, avg_rx_rate, avg_tx_rate)
            SELECT router_id, interface, AVG(avg_rx_rate), AVG(avg_tx_rate)
            FROM traffic_log_daily
            WHERE timestamp >= datetime('now', '-1 month')
            GROUP BY router_id, interface");
            
        // Resources
        $this->db->exec("INSERT INTO resource_log_monthly (router_id, avg_cpu_load, avg_free_memory)
            SELECT router_id, AVG(avg_cpu_load), AVG(avg_free_memory)
            FROM resource_log_daily
            WHERE timestamp >= datetime('now', '-1 month')
            GROUP BY router_id");
    }

    public function getRouterInterfaces($router_id) {
        $stmt = $this->db->prepare("SELECT DISTINCT interface FROM traffic_log WHERE router_id = ?");
        $stmt->execute([$router_id]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    // OLT Management
    public function getOlts() {
        $olts = $this->db->query("SELECT * FROM olts ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($olts as &$olt) {
            $olt['host'] = $this->decrypt($olt['host']);
            $olt['user'] = $this->decrypt($olt['user']);
            $olt['password'] = $this->decrypt($olt['password']);
        }
        return $olts;
    }

    public function getOlt($id) {
        $stmt = $this->db->prepare("SELECT * FROM olts WHERE id = ?");
        $stmt->execute([$id]);
        $olt = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($olt) {
            $olt['host'] = $this->decrypt($olt['host']);
            $olt['user'] = $this->decrypt($olt['user']);
            $olt['password'] = $this->decrypt($olt['password']);
        }
        return $olt;
    }

    public function saveOlt($data) {
        $host = $this->encrypt($data['host']);
        $user = $this->encrypt($data['user']);
        $password = $this->encrypt($data['password']);

        if (!empty($data['id'])) {
            $stmt = $this->db->prepare("UPDATE olts SET name = ?, description = ?, type = ?, host = ?, user = ?, password = ?, port = ? WHERE id = ?");
            return $stmt->execute([$data['name'], $data['description'], $data['type'], $host, $user, $password, $data['port'], $data['id']]);
        } else {
            $stmt = $this->db->prepare("INSERT INTO olts (name, description, type, host, user, password, port) VALUES (?, ?, ?, ?, ?, ?, ?)");
            return $stmt->execute([$data['name'], $data['description'], $data['type'], $host, $user, $password, $data['port']]);
        }
    }

    public function deleteOlt($id) {
        $stmt = $this->db->prepare("DELETE FROM olts WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function updateOltStats($id, $stats) {
        $stmt = $this->db->prepare("UPDATE olts SET last_stats = ?, last_updated = CURRENT_TIMESTAMP WHERE id = ?");
        return $stmt->execute([json_encode($stats), $id]);
    }
}
