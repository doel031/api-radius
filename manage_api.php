<?php
// 1. Pastikan session dimulai di bagian paling atas untuk mengingat tab aktif
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load Konfigurasi Database Terpusat
include_once(dirname(__FILE__) . "/config.php");
$conn = getDBConnection();
$message = "";

// Set default view tab ke 'api-log-tab' jika session belum terbentuk
if (!isset($_SESSION['active_tab'])) {
    $_SESSION['active_tab'] = 'api-log-tab';
}

// ==========================================
// LOGIKA A: TAMBAH API KEY BARU
// ==========================================
if (isset($_POST['action']) && $_POST['action'] == 'generate') {
    $client_name = $conn->real_escape_string($_POST['client_name']);
    $allowed_ip = $conn->real_escape_string(trim($_POST['allowed_ip']));
    $new_key = bin2hex(random_bytes(16)); 
    
    // Paksa halaman tetap bertahan di tab Manajemen Kunci setelah submit form
    $_SESSION['active_tab'] = 'manage-key-tab';
    
    if (!empty($client_name) && !empty($allowed_ip)) {
        $sql = "INSERT INTO api_keys (client_name, api_key, allowed_ip) VALUES ('$client_name', '$new_key', '$allowed_ip')";
        if ($conn->query($sql)) {
            $message = "<div class='alert success'>✔ API Key berhasil dibuat untuk client: <b>$client_name</b>!</div>";
        } else {
            $message = "<div class='alert danger'>❌ Gagal menyimpan data kunci ke database.</div>";
        }
    } else {
        $message = "<div class='alert danger'>❌ Semua kolom input wajib diisi!</div>";
    }
}

// ==========================================
// LOGIKA B: HAPUS API KEY
// ==========================================
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM api_keys WHERE id = $id");
    $_SESSION['active_tab'] = 'manage-key-tab'; 
    header("Location: manage_api.php");
    exit();
}

// ==========================================
// LOGIKA C: FORCE UNBLOCK IP (BUKA BLOKIR MANUAL)
// ==========================================
if (isset($_GET['unblock'])) {
    $id = intval($_GET['unblock']);
    $conn->query("UPDATE api_failed_attempts SET attempts = 0, blocked_until = NULL WHERE id = $id");
    $_SESSION['active_tab'] = 'manage-key-tab';
    header("Location: manage_api.php");
    exit();
}

// ==========================================
// LOGIKA D: MENANGKAP PERUBAHAN TAB VIA AJAX
// ==========================================
if (isset($_GET['set_tab'])) {
    $_SESSION['active_tab'] = $conn->real_escape_string($_GET['set_tab']);
    exit();
}

// ==========================================
// LOGIKA E: RESPONSE HANYA DATA TABEL (UNTUK AJAX REFRESH)
// ==========================================
if (isset($_GET['fetch_part'])) {
    $part = $_GET['fetch_part'];
    if ($part == 'logs') {
        $logs_res = $conn->query("SELECT * FROM api_request_logs ORDER BY id DESC LIMIT 50");
        if ($logs_res && $logs_res->num_rows > 0) {
            while($log = $logs_res->fetch_assoc()) {
                $json_data = json_decode($log['payload'], true);
                $pretty_json = $json_data ? json_encode($json_data, JSON_PRETTY_PRINT) : htmlspecialchars($log['payload']);
                $status_class = ($log['http_status'] == 200) ? 'status-200' : 'status-error';
                $error_col = $log['error_details'] ? htmlspecialchars($log['error_details']) : '<span style="color:#48bb78;">✔ Success / OK</span>';
                echo "<tr>
                        <td class='time-col'>
                            <b>{$log['created_at']}</b><br>
                            <span style='color:#2d3748; font-weight:500;'>ID: " . htmlspecialchars($log['client_id']) . "</span><br>
                            <span class='ip-badge' style='font-size:11px; padding:1px 4px; margin-top:2px; display:inline-block;'>" . htmlspecialchars($log['ip_address']) . "</span>
                        </td>
                        <td>
                            <code style='color:#2b6cb0; font-weight:bold; font-size:12px; background:#ebf8ff; padding:2px 4px; border-radius:3px;'>" . htmlspecialchars($log['endpoint']) . "</code><br>
                            <span style='color:#718096; font-size:12px; display:inline-block; margin-top:5px;'>Action: <b>" . htmlspecialchars($log['action'] ?? 'N/A') . "</b></span>
                        </td>
                        <td><span class='status-badge {$status_class}'>HTTP {$log['http_status']}</span></td>
                        <td><pre>{$pretty_json}</pre></td>
                        <td style='font-size: 13px; color: #e53e3e; font-weight: 500; max-width: 220px; word-break: break-word;'>{$error_col}</td>
                      </tr>";
            }
        } else {
            echo "<tr><td colspan='5' style='text-align: center; color: #718096; padding: 20px;'>Belum ada history payload API terekam di database.</td></tr>";
        }
    }
    if ($part == 'banned') {
        $blocked_res = $conn->query("SELECT * FROM api_failed_attempts WHERE total_banned_count > 0 ORDER BY id DESC");
        if ($blocked_res && $blocked_res->num_rows > 0) {
            while($block = $blocked_res->fetch_assoc()) {
                echo "<tr>
                        <td><strong style='color:#c53030;'>" . htmlspecialchars($block['client_id']) . "</strong></td>
                        <td><span class='ip-badge' style='background:#fed7d7; color:#9b2c2c; border: 1px solid #feb2b2;'>" . htmlspecialchars($block['ip_address']) . "</span></td>
                        <td><b>Tier {$block['tier']}</b></td>
                        <td><b style='color:#dd6b20;'>{$block['total_banned_count']} Kali Kena Penalti</b></td>
                        <td style='color:#e53e3e; font-weight:bold; font-family: monospace;'>{$block['blocked_until']}</td>
                        <td><a href='manage_api.php?unblock={$block['id']}' class='btn-unblock' onclick=\"return confirm('Force unblock IP ini sekarang?')\">Buka Gembok (Unblock)</a></td>
                      </tr>";
            }
        } else {
            echo "<tr><td colspan='6' style='text-align: center; color: #48bb78; font-weight: 500; padding: 20px;'>✔ Sistem Aman & Bersih. Tidak ada IP Client yang sedang menjalani masa karantina/banned.</td></tr>";
        }
    }
    exit();
}

// Penarikan data awal untuk load pertama halaman
$api_keys_res = $conn->query("SELECT * FROM api_keys ORDER BY id DESC");
$logs_res     = $conn->query("SELECT * FROM api_request_logs ORDER BY id DESC LIMIT 50");
$blocked_res  = $conn->query("SELECT * FROM api_failed_attempts WHERE total_banned_count > 0 ORDER BY id DESC");

$active_tab = $_SESSION['active_tab'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard API Control Panel - GSMNET</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f4f6f9; padding: 20px 15px; color: #333; }
        .container { max-width: 1100px; background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); margin: 0 auto; }
        
        h2 { padding-bottom: 15px; color: #2c3e50; margin-bottom: 20px; font-size: 22px; border-bottom: 2px solid #e2e8f0; }
        h3 { color: #2c3e50; margin-bottom: 15px; font-size: 17px; display: flex; align-items: center; justify-content: space-between; }
        
        .tab-menu { display: flex; border-bottom: 2px solid #e2e8f0; margin-bottom: 25px; gap: 5px; }
        .tab-btn { background: none; border: none; padding: 12px 20px; font-size: 14px; font-weight: 600; color: #718096; cursor: pointer; transition: all 0.2s ease; border-bottom: 2px solid transparent; margin-bottom: -2px; }
        .tab-btn:hover { color: #3498db; }
        .tab-btn.active { color: #3498db; border-bottom: 2px solid #3498db; }
        
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        
        /* Tombol Refresh Utility */
        .btn-refresh { background: #edf2f7; color: #4a5568; padding: 6px 12px; border: 1px solid #cbd5e0; border-radius: 4px; font-size: 12px; font-weight: bold; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; transition: all 0.2s; }
        .btn-refresh:hover { background: #e2e8f0; color: #2d3748; }
        
        .form-group { margin-bottom: 15px; }
        label { display: block; font-weight: bold; margin-bottom: 5px; font-size: 13px; color: #4a5568; }
        input[type="text"] { width: 100%; padding: 10px; border: 1px solid #cbd5e0; border-radius: 4px; font-size: 14px; color: #2d3748; }
        
        .btn-submit { background: #3498db; color: #fff; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; font-size: 14px; }
        .btn-submit:hover { background: #2980b9; }
        
        .table-container { width: 100%; overflow-x: auto; margin-top: 10px; border: 1px solid #e2e8f0; border-radius: 6px; }
        table { width: 100%; border-collapse: collapse; min-width: 700px; background: #fff; }
        th, td { border-bottom: 1px solid #edf2f7; padding: 12px 15px; text-align: left; font-size: 14px; vertical-align: top; }
        th { background: #f7fafc; color: #4a5568; font-weight: 600; font-size: 13px; text-transform: uppercase; }
        
        .alert { padding: 12px; margin-bottom: 20px; border-radius: 4px; font-weight: bold; font-size: 14px; }
        .success { background: #c6f6d5; color: #22543d; border: 1px solid #9ae6b4; }
        .danger { background: #fed7d7; color: #742a2a; border: 1px solid #feb2b2; }
        
        .key-badge { background: #ebf8ff; color: #2b6cb0; padding: 4px 8px; font-family: monospace; border-radius: 4px; border: 1px solid #bee3f8; font-size: 13px; }
        .ip-badge { background: #fef3c7; color: #92400e; padding: 2px 6px; border-radius: 4px; font-family: monospace; font-size: 13px; font-weight: bold; }
        
        .status-badge { padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: bold; color: #fff; text-align: center; display: inline-block; }
        .status-200 { background: #48bb78; }
        .status-error { background: #f56565; }
        
        .btn-delete { color: #e53e3e; text-decoration: none; font-weight: bold; }
        .btn-unblock { color: #3182ce; text-decoration: none; font-weight: bold; }
        
        pre { background: #1a202c; color: #f7fafc; padding: 10px; border-radius: 6px; font-family: monospace; font-size: 11px; max-height: 140px; overflow-y: auto; white-space: pre-wrap; word-break: break-all; }
        .time-col { font-size: 12px; color: #718096; white-space: nowrap; }
    </style>
</head>
<body>
<div class="container">
    <h2>🛠 Core RADIUS API Gateway Dashboard</h2>
    <?php echo $message; ?>
    
    <div class="tab-menu">
        <button class="tab-btn <?php echo ($active_tab == 'api-log-tab') ? 'active' : ''; ?>" onclick="switchTab(event, 'api-log-tab')">📋 Log Aktivitas API</button>
        <button class="tab-btn <?php echo ($active_tab == 'manage-key-tab') ? 'active' : ''; ?>" onclick="switchTab(event, 'manage-key-tab')">🔑 Manajemen Kunci & Blokir</button>
    </div>

    <div id="api-log-tab" class="tab-content <?php echo ($active_tab == 'api-log-tab') ? 'active' : ''; ?>">
        <h3>
            <span>Audit Trail Logs Payload Terkini (Maks. 50 Data)</span>
            <button class="btn-refresh" onclick="refreshTable('logs', 'log-tbody')">🔄 Refresh Log</button>
        </h3>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Waktu / Client / IP</th>
                        <th>Endpoint / Action</th>
                        <th>HTTP Status</th>
                        <th>Payload Terkirim (JSON)</th>
                        <th>Detail Error / Keterangan</th>
                    </tr>
                </thead>
                <tbody id="log-tbody">
                    <?php if ($logs_res && $logs_res->num_rows > 0): ?>
                        <?php while($log = $logs_res->fetch_assoc()): ?>
                            <tr>
                                <td class="time-col">
                                    <b><?php echo $log['created_at']; ?></b><br>
                                    <span style="color:#2d3748; font-weight:500;">ID: <?php echo htmlspecialchars($log['client_id']); ?></span><br>
                                    <span class="ip-badge" style="font-size:11px; padding:1px 4px; margin-top:2px; display:inline-block;"><?php echo htmlspecialchars($log['ip_address']); ?></span>
                                </td>
                                <td>
                                    <code style="color:#2b6cb0; font-weight:bold; font-size:12px; background:#ebf8ff; padding:2px 4px; border-radius:3px;"><?php echo htmlspecialchars($log['endpoint']); ?></code><br>
                                    <span style="color:#718096; font-size:12px; display:inline-block; margin-top:5px;">Action: <b><?php echo htmlspecialchars($log['action'] ?? 'N/A'); ?></b></span>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo ($log['http_status'] == 200) ? 'status-200' : 'status-error'; ?>">
                                        HTTP <?php echo $log['http_status']; ?>
                                    </span>
                                </td>
                                <td>
                                    <pre><?php 
                                        $json_data = json_decode($log['payload'], true);
                                        echo $json_data ? json_encode($json_data, JSON_PRETTY_PRINT) : htmlspecialchars($log['payload']); 
                                    ?></pre>
                                </td>
                                <td style="font-size: 13px; color: #e53e3e; font-weight: 500; max-width: 220px; word-break: break-word;">
                                    <?php echo $log['error_details'] ? htmlspecialchars($log['error_details']) : '<span style="color:#48bb78;">✔ Success / OK</span>'; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5" style="text-align: center; color: #718096; padding: 20px;">Belum ada history payload API terekam di database.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="manage-key-tab" class="tab-content <?php echo ($active_tab == 'manage-key-tab') ? 'active' : ''; ?>">
        <h3>Registrasi / Generate Kunci Akses Baru</h3>
        <form method="POST" action="" style="margin-top: 15px; margin-bottom: 35px; background: #f7fafc; padding: 15px; border-radius: 6px; border: 1px solid #edf2f7;">
            <input type="hidden" name="action" value="generate">
            <div class="form-group">
                <label>Nama Pengenal Client System (X-GSMNET-ClientID):</label>
                <input type="text" name="client_name" placeholder="Contoh: Billing_GSMNET_Pusat" required>
            </div>
            <div class="form-group">
                <label>IP Publik Whitelist Server Client:</label>
                <input type="text" name="allowed_ip" placeholder="Contoh: 103.51.204.15" required>
            </div>
            <button type="submit" class="btn-submit">Generate New API Secret</button>
        </form>

        <h3 style="border-left: 4px solid #3498db; padding-left: 8px;">Kunci Akses Terdaftar (IP Whitelist)</h3>
        <div class="table-container" style="margin-bottom: 40px;">
            <table>
                <thead>
                    <tr><th>Client ID</th><th>Secret Key (HMAC Base)</th><th>IP Whitelist</th><th>Aksi</th></tr>
                </thead>
                <tbody>
                    <?php if ($api_keys_res && $api_keys_res->num_rows > 0): ?>
                        <?php while($row = $api_keys_res->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($row['client_name']); ?></strong></td>
                                <td><span class="key-badge"><?php echo $row['api_key']; ?></span></td>
                                <td><span class="ip-badge"><?php echo htmlspecialchars($row['allowed_ip']); ?></span></td>
                                <td><a href="manage_api.php?delete=<?php echo $row['id']; ?>" class="btn-delete" onclick="return confirm('Apakah Anda yakin ingin mencabut izin akses client ini?')">Hapus Kunci</a></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="4" style="text-align: center; color: #718096; padding: 15px;">Belum ada kredensial API yang terdaftar.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <h3 style="border-left: 4px solid #e53e3e; padding-left: 8px; color: #c53030;">
            <span>🚫 Daftar IP Client Terbanned Bertingkat (Progressive Lock)</span>
            <button class="btn-refresh" onclick="refreshTable('banned', 'banned-tbody')">🔄 Refresh Banned</button>
        </h3>
        <div class="table-container">
            <table>
                <thead>
                    <tr><th>Client ID</th><th>IP Address</th><th>Posisi Tier Terakhir</th><th>Frekuensi Banned</th><th>Banned Berakhir Pada</th><th>Aksi Kontrol</th></tr>
                </thead>
                <tbody id="banned-tbody">
                    <?php if ($blocked_res && $blocked_res->num_rows > 0): ?>
                        <?php while($block = $blocked_res->fetch_assoc()): ?>
                            <tr>
                                <td><strong style="color:#c53030;"><?php echo htmlspecialchars($block['client_id']); ?></strong></td>
                                <td><span class="ip-badge" style="background:#fed7d7; color:#9b2c2c; border: 1px solid #feb2b2;"><?php echo htmlspecialchars($block['ip_address']); ?></span></td>
                                <td><b>Tier <?php echo $block['tier']; ?></b></td>
                                <td><b style="color:#dd6b20;"><?php echo $block['total_banned_count']; ?> Kali Kena Penalti</b></td>
                                <td style="color:#e53e3e; font-weight:bold; font-family: monospace;"><?php echo $block['blocked_until']; ?></td>
                                <td><a href="manage_api.php?unblock=<?php echo $block['id']; ?>" class="btn-unblock" onclick="return confirm('Force unblock IP ini sekarang?')">Buka Gembok (Unblock)</a></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" style="text-align: center; color: #48bb78; font-weight: 500; padding: 20px;">✔ Sistem Aman & Bersih. Tidak ada IP Client yang sedang menjalani masa karantina/banned.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function switchTab(evt, tabId) {
    var tabContent = document.getElementsByClassName("tab-content");
    for (var i = 0; i < tabContent.length; i++) {
        tabContent[i].style.display = "none";
        tabContent[i].classList.remove("active");
    }

    var tabButtons = document.getElementsByClassName("tab-btn");
    for (var i = 0; i < tabButtons.length; i++) {
        tabButtons[i].classList.remove("active");
    }

    document.getElementById(tabId).style.display = "block";
    document.getElementById(tabId).classList.add("active");
    evt.currentTarget.classList.add("active");

    fetch('manage_api.php?set_tab=' + tabId);
}

// FUNGSI UTAMA AJAX REFRESH
function refreshTable(partName, tbodyId) {
    var button = event.currentTarget;
    var originalText = button.innerHTML;
    button.innerHTML = "⌛ Loading...";
    button.disabled = true;

    fetch('manage_api.php?fetch_part=' + partName)
        .then(response => response.text())
        .then(data => {
            document.getElementById(tbodyId).innerHTML = data;
            button.innerHTML = originalText;
            button.disabled = false;
        })
        .catch(error => {
            console.error('Error fetching data:', error);
            button.innerHTML = "❌ Gagal";
            button.disabled = false;
            setTimeout(() => { button.innerHTML = originalText; }, 2000);
        });
}
</script>
</body>
</html>
