<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::check();
$db = new MiksDB();

$id = $_GET['id'] ?? null;
$router = $id ? $db->getRouter($id) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $host = $_POST['host'];
    $user = $_POST['username'];
    $pass = $_POST['password'];
    $port = (int)$_POST['port'];
    $interfaces = $_POST['monitored_interfaces_json']; // Hidden field with JSON

    if ($id) {
        $db->updateRouter($id, $name, $host, $user, $pass, $port, $interfaces);
    } else {
        $db->addRouter($name, $host, $user, $pass, $port, $interfaces);
    }
    header('Location: index.php');
    exit();
}

$selected_interfaces = $router ? json_decode($router['monitored_interfaces'], true) : [];
if (!is_array($selected_interfaces)) {
    // Fallback for old comma-separated data
    $selected_interfaces = [];
    if (!empty($router['monitored_interfaces'])) {
        foreach (explode(',', $router['monitored_interfaces']) as $iface) {
            $selected_interfaces[] = ['name' => trim($iface), 'label' => trim($iface)];
        }
    }
}
?>
<?php
require_once __DIR__ . '/../layout/header.php';
?>

<style>
    .iface-row { 
        display: grid; grid-template-columns: 1fr 1.5fr auto; gap: 1rem; align-items: center; 
        background: rgba(255,255,255,0.03); padding: 0.75rem; border-radius: 0.5rem; margin-bottom: 0.75rem;
    }
    .iface-name { font-weight: 600; color: var(--primary-color); }
    .btn-remove { color: #ef4444; background: none; border: none; cursor: pointer; }
    #discovery-section { display: none; margin-top: 1rem; padding: 1rem; background: rgba(16, 185, 129, 0.05); border-radius: 0.5rem; }
    .loader { display: none; margin-left: 1rem; }
</style>

<div class="admin-container" style="padding-top: 4rem; padding-bottom: 4rem;">
    <div style="margin-bottom: 2rem;">
        <h1 style="font-family: 'Outfit', sans-serif;"><?php echo $id ? 'Edit' : 'Add New'; ?> MikroTik</h1>
        <p style="color: var(--text-muted);">Enter RouterOS credentials and settings</p>
    </div>

    <div class="glass-card">
        <form method="POST" id="router-form">
            <div class="form-grid">
                <div class="form-group">
                    <label>Friendly Name</label>
                    <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($router['name'] ?? ''); ?>" required placeholder="e.g. Core Router">
                </div>
                <div class="form-group">
                    <label>IP Address / Host</label>
                    <input type="text" id="host" name="host" class="form-control" value="<?php echo htmlspecialchars($router['host'] ?? ''); ?>" required placeholder="192.168.1.1">
                </div>
                <div class="form-group">
                    <label>API Username</label>
                    <input type="text" id="username" name="username" class="form-control" value="<?php echo htmlspecialchars($router['username'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label>API Password</label>
                    <input type="password" id="password" name="password" class="form-control" value="<?php echo htmlspecialchars($router['password'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label>API Port</label>
                    <input type="number" id="port" name="port" class="form-control" value="<?php echo htmlspecialchars($router['port'] ?? 8728); ?>" required>
                </div>
                <div class="form-group" style="display: flex; align-items: flex-end;">
                    <button type="button" id="btn-test-conn" class="btn btn-test" style="width: 100%;"><i class="fas fa-plug"></i> Test Connection & Fetch Interfaces</button>
                    <span class="loader" id="conn-loader" style="margin-left: 1rem;"><i class="fas fa-circle-notch fa-spin"></i></span>
                </div>
            </div>

            <div id="discovery-section" style="margin-top: 2rem; padding: 1.5rem; background: rgba(16, 185, 129, 0.03); border: 1px solid rgba(16, 185, 129, 0.1); border-radius: 0.75rem;">
                <label style="display: block; margin-bottom: 0.75rem; font-weight: 600; color: #10b981;">Select Interface to Add</label>
                <div style="display: flex; gap: 1rem;">
                    <select id="interface-selector" class="form-control" style="flex: 1;">
                        <option value="">-- Select Interface --</option>
                    </select>
                    <button type="button" id="btn-add-iface" class="btn btn-secondary">Add to List</button>
                </div>
            </div>

            <div class="interface-manager" style="margin-top: 3rem; padding-top: 2rem; border-top: 1px solid var(--glass-border);">
                <h3 style="font-family: 'Outfit', sans-serif; margin-bottom: 1.5rem; font-size: 1.25rem;">Monitored Interfaces</h3>
                <div id="selected-interfaces-list">
                    <!-- Dynamic Rows -->
                </div>
                <input type="hidden" name="monitored_interfaces_json" id="monitored_interfaces_json">
            </div>

            <div style="margin-top: 3rem; border-top: 1px solid var(--glass-border); padding-top: 2rem; display: flex; gap: 1rem;">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Configuration</button>
                <a href="index.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>

    <script>
        const selectedList = <?php echo json_encode($selected_interfaces); ?>;
        const listContainer = document.getElementById('selected-interfaces-list');
        const jsonInput = document.getElementById('monitored_interfaces_json');

        function renderList() {
            listContainer.innerHTML = '';
            selectedList.forEach((item, index) => {
                const row = document.createElement('div');
                row.className = 'iface-row';
                row.innerHTML = `
                    <div class="iface-name">${item.name}</div>
                    <input type="text" class="form-control" placeholder="Custom Label (e.g. ISP 1)" value="${item.label}" onchange="updateLabel(${index}, this.value)">
                    <button type="button" class="btn-remove" onclick="removeInterface(${index})"><i class="fas fa-times"></i></button>
                `;
                listContainer.appendChild(row);
            });
            jsonInput.value = JSON.stringify(selectedList);
        }

        function updateLabel(index, val) {
            selectedList[index].label = val;
            jsonInput.value = JSON.stringify(selectedList);
        }

        function removeInterface(index) {
            selectedList.splice(index, 1);
            renderList();
        }

        document.getElementById('btn-test-conn').addEventListener('click', async () => {
            const data = {
                host: document.getElementById('host').value,
                username: document.getElementById('username').value,
                password: document.getElementById('password').value,
                port: document.getElementById('port').value
            };

            const loader = document.getElementById('conn-loader');
            const discovery = document.getElementById('discovery-section');
            const selector = document.getElementById('interface-selector');

            loader.style.display = 'inline-block';
            
            try {
                const response = await fetch('../api/test_router.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                const result = await response.json();

                if (result.status === 'success') {
                    alert('Connected successfully!');
                    discovery.style.display = 'block';
                    selector.innerHTML = '<option value="">-- Select Interface --</option>';
                    result.interfaces.forEach(iface => {
                        const opt = document.createElement('option');
                        opt.value = iface.name;
                        opt.textContent = `${iface.name} (${iface.type}) ${iface.comment ? '- ' + iface.comment : ''}`;
                        selector.appendChild(opt);
                    });
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (e) {
                alert('Connection failed: ' + e.message);
            } finally {
                loader.style.display = 'none';
            }
        });

        document.getElementById('btn-add-iface').addEventListener('click', () => {
            const selector = document.getElementById('interface-selector');
            const name = selector.value;
            if (!name) return;

            if (selectedList.some(i => i.name === name)) {
                alert('Interface already in list');
                return;
            }

            selectedList.push({ name: name, label: name });
            renderList();
        });

        renderList();
    </script>
<?php require_once __DIR__ . '/../layout/footer.php'; ?>
