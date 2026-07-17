<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include_once(dirname(__FILE__) . "/config.php");
$conn = getDBConnection();

// ==========================================
// LOGIKA: RESPONSE DATA TABEL LOGS (UNTUK AJAX REFRESH)
// ==========================================
if (isset($_GET['fetch_part']) && $_GET['fetch_part'] == 'logs') {
    $logs_res = $conn->query("SELECT * FROM api_request_logs ORDER BY id DESC LIMIT 50");
    if ($logs_res && $logs_res->num_rows > 0) {
        while($log = $logs_res->fetch_assoc()) {
            $json_data = json_decode($log['payload'], true);
            $pretty_json = $json_data ? json_encode($json_data, JSON_PRETTY_PRINT) : htmlspecialchars($log['payload']);
            $status_class = ($log['http_status'] == 200) ? 'status-200' : 'status-error';
            $error_col = $log['error_details'] ? htmlspecialchars($log['error_details']) : '<span style="color:#48bb78;">✔ Success / OK</span>';
            
            // Penentuan Kategori Log Level secara otomatis berdasarkan data database
            $http = intval($log['http_status']);
            $has_error = !empty($log['error_details']);
            
            if ($http >= 500 || $has_error) {
                $level = 'error';
            } elseif ($http >= 400 && $http < 500) {
                $level = 'warning';
            } elseif ($http == 200 && $log['method'] == 'GET') {
                $level = 'debug';
            } else {
                $level = 'info';
            }

            echo "<tr class='searchable-row' data-level='{$level}'>
                    <td class='time-col' data-label='Waktu / Client / IP'>
                        <b>{$log['created_at']}</b><br>
                        <span class='client-name' style='color:#2d3748; font-weight:500;'>ID: " . htmlspecialchars($log['client_id']) . "</span><br>
                        <span class='ip-badge ip-text' style='font-size:11px; padding:1px 4px; margin-top:2px; display:inline-block;'>" . htmlspecialchars($log['ip_address']) . "</span>
                    </td>
                    <td data-label='Endpoint / Action'>
                        <code class='endpoint-text' style='color:#2b6cb0; font-weight:bold; font-size:12px; background:#ebf8ff; padding:2px 4px; border-radius:3px;'>" . htmlspecialchars($log['endpoint']) . "</code>
                        <span class='method-badge method-{$log['method']}'>" . htmlspecialchars($log['method']) . "</span><br>
                        <span style='color:#718096; font-size:12px; display:inline-block; margin-top:5px;'>Action: <b>" . htmlspecialchars($log['action'] ?? 'N/A') . "</b></span>
                    </td>
                    <td data-label='HTTP Status'><span class='status-badge {$status_class}'>HTTP {$log['http_status']}</span></td>
                    <td data-label='Payload Terkirim' class='payload-cell'><pre>{$pretty_json}</pre></td>
                    <td data-label='Detail Error / Keterangan' class='error-text-cell' style='font-size: 13px; color: #e53e3e; font-weight: 500; word-break: break-word;'>{$error_col}</td>
                </tr>";
        }
    } else {
        echo "<tr><td colspan='5' style='text-align: center; color: #718096; padding: 20px;'>Belum ada history payload API terekam di database.</td></tr>";
    }
    exit();
}

$logs_res = $conn->query("SELECT * FROM api_request_logs ORDER BY id DESC LIMIT 50");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log Aktivitas API - GSMNET</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f4f6f9; padding: 15px; color: #333; }
        .container { max-width: 1100px; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); margin: 0 auto; }
        h2 { padding-bottom: 15px; color: #2c3e50; margin-bottom: 20px; font-size: 20px; border-bottom: 2px solid #e2e8f0; text-align: center; }
        
        .nav-menu { display: flex; border-bottom: 2px solid #e2e8f0; margin-bottom: 20px; gap: 5px; justify-content: center; }
        .nav-link { text-decoration: none; padding: 10px 15px; font-size: 14px; font-weight: 600; color: #718096; transition: all 0.2s ease; border-bottom: 2px solid transparent; margin-bottom: -2px; }
        .nav-link:hover { color: #3498db; }
        .nav-link.active { color: #3498db; border-bottom: 2px solid #3498db; }
        
        .toolbar { display: flex; flex-direction: column; gap: 12px; margin-bottom: 15px; }
        .search-box { width: 100%; padding: 10px 12px; border: 1px solid #cbd5e0; border-radius: 6px; font-size: 14px; color: #2d3748; outline: none; transition: border 0.2s; }
        .search-box:focus { border-color: #3498db; box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.15); }
        .btn-refresh { background: #edf2f7; color: #4a5568; padding: 10px 15px; border: 1px solid #cbd5e0; border-radius: 6px; font-size: 13px; font-weight: bold; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 5px; transition: all 0.2s; width: 100%; }
        .btn-refresh:hover { background: #e2e8f0; }

        /* CSS FILTER LOG LEVEL BUTTONS */
        .filter-container { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 15px; width: 100%; }
        .filter-btn { padding: 8px 14px; font-size: 12px; font-weight: bold; border-radius: 6px; border: 1px solid #cbd5e0; cursor: pointer; background: #fff; color: #4a5568; transition: all 0.15s ease-in-out; flex: 1; text-align: center; min-width: 75px; }
        
        .filter-btn.active[data-target="all"] { background: #4a5568; color: #fff; border-color: #4a5568; }
        .filter-btn.active[data-target="debug"] { background: #2b6cb0; color: #fff; border-color: #2b6cb0; }
        .filter-btn.active[data-target="info"] { background: #2f855a; color: #fff; border-color: #2f855a; }
        .filter-btn.active[data-target="warning"] { background: #dd6b20; color: #fff; border-color: #dd6b20; }
        .filter-btn.active[data-target="error"] { background: #c53030; color: #fff; border-color: #c53030; }

        .table-container { 
            width: 100%; 
            max-height: 650px; 
            overflow-y: auto; 
            overflow-x: auto;
            border: 1px solid #e2e8f0; 
            border-radius: 6px; 
            position: relative;
        }
        table { width: 100%; border-collapse: collapse; background: #fff; }
        th, td { border-bottom: 1px solid #edf2f7; padding: 12px; text-align: left; font-size: 14px; vertical-align: top; }
        
        th { 
            background: #f7fafc; 
            color: #4a5568; 
            font-weight: 600; 
            font-size: 12px; 
            text-transform: uppercase; 
            position: sticky;
            top: 0;
            z-index: 10;
            box-shadow: inset 0 -1px 0 #e2e8f0;
        }
        
        .ip-badge { background: #fef3c7; color: #92400e; padding: 2px 6px; border-radius: 4px; font-family: monospace; font-size: 12px; font-weight: bold; }
        .status-badge { padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: bold; color: #fff; display: inline-block; }
        .status-200 { background: #48bb78; }
        .status-error { background: #f56565; }
        pre { background: #1a202c; color: #f7fafc; padding: 8px; border-radius: 6px; font-family: monospace; font-size: 11px; max-height: 150px; overflow-y: auto; white-space: pre-wrap; word-break: break-all; width: 100%; }
        .time-col { font-size: 12px; color: #718096; }

        .method-badge { display:inline-block; padding:2px 6px; border-radius:4px; font-size:11px; font-weight:bold; margin-left:6px; }
        .method-GET    { background: #e6fffa; color: #234e52; }
        .method-POST   { background: #ebf8ff; color: #2c5282; }
        .method-PUT    { background: #fffaf0; color: #7b341e; }
        .method-PATCH  { background: #faf5ff; color: #553c9a; }
        .method-DELETE { background: #fff5f5; color: #822727; }

        @media (min-width: 768px) {
            h2 { font-size: 22px; text-align: left; }
            .nav-menu { justify-content: flex-start; }
            .toolbar { flex-direction: row; align-items: center; justify-content: space-between; gap: 15px; }
            .search-box { max-width: 400px; }
            .btn-refresh { width: auto; }
            .filter-container { width: auto; flex: none; }
            .filter-btn { flex: none; min-width: 85px; }
        }

        @media (max-width: 767px) {
            .table-container { max-height: none; overflow-y: visible; border: none; }
            table, thead, tbody, th, td, tr { display: block; }
            thead tr { position: absolute; top: -9999px; left: -9999px; }
            
            tr { 
                border: 1px solid #cbd5e0; 
                border-radius: 8px; 
                margin-bottom: 20px; 
                padding: 12px; 
                background: #fff; 
                box-shadow: 0 2px 5px rgba(0,0,0,0.04); 
            }
            
            td { 
                border: none; 
                padding: 10px 5px; 
                position: relative; 
                padding-left: 38%; 
                text-align: right; 
                font-size: 13px; 
                border-bottom: 1px dashed #edf2f7; 
            }
            
            td:last-child { border-bottom: none; }
            
            td::before { 
                content: attr(data-label); 
                position: absolute; 
                left: 8px; 
                width: 32%; 
                text-align: left; 
                font-weight: 700; 
                color: #4a5568; 
                font-size: 11px; 
                text-transform: uppercase; 
                white-space: nowrap; 
            }

            td.payload-cell, td.error-text-cell {
                padding-left: 5px;
                text-align: left;
                padding-top: 30px; 
            }
            
            td.payload-cell::before, td.error-text-cell::before {
                top: 8px;
                left: 5px;
                width: 100%;
            }

            pre { text-align: left; margin-top: 8px; max-height: 180px; }
            .method-badge { margin-left: 0; margin-top: 3px; }
        }
    </style>
</head>
<body>
<div class="container">
    <h2>🛠 Core RADIUS API Gateway Dashboard</h2>
    
    <div class="nav-menu">
        <a href="api_logs.php" class="nav-link active">📋 Log Aktivitas API</a>
        <a href="manage_api.php" class="nav-link">🔑 Manajemen Kunci & Blokir</a>
    </div>

    <div class="toolbar">
        <input type="text" id="logSearch" class="search-box" placeholder="🔍 Cari Client ID, Endpoint, IP, atau Status..." onkeyup="applyFilters()">
        
        <div class="filter-container">
            <button class="filter-btn active" data-target="all" onclick="toggleLevelFilter(this)">ALL</button>
            <button class="filter-btn" data-target="debug" onclick="toggleLevelFilter(this)">DEBUG</button>
            <button class="filter-btn" data-target="info" onclick="toggleLevelFilter(this)">INFO</button>
            <button class="filter-btn" data-target="warning" onclick="toggleLevelFilter(this)">WARNING</button>
            <button class="filter-btn" data-target="error" onclick="toggleLevelFilter(this)">ERROR</button>
        </div>

        <button class="btn-refresh" onclick="refreshTable('logs', 'log-tbody')">🔄 Refresh Log</button>
    </div>

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
                        <?php
                            $http = intval($log['http_status']);
                            $has_error = !empty($log['error_details']);
                            
                            if ($http >= 500 || $has_error) {
                                $level = 'error';
                            } elseif ($http >= 400 && $http < 500) {
                                $level = 'warning';
                            } elseif ($http == 200 && $log['method'] == 'GET') {
                                $level = 'debug';
                            } else {
                                $level = 'info';
                            }
                        ?>
                        <tr class="searchable-row" data-level="<?php echo $level; ?>">
                            <td class="time-col" data-label="Waktu / Client / IP">
                                <b><?php echo $log['created_at']; ?></b><br>
                                <span class="client-name" style="color:#2d3748; font-weight:500;">ID: <?php echo htmlspecialchars($log['client_id']); ?></span><br>
                                <span class="ip-badge ip-text" style="font-size:11px; padding:1px 4px; margin-top:2px; display:inline-block;"><?php echo htmlspecialchars($log['ip_address']); ?></span>
                            </td>
                            <td data-label="Endpoint / Action">
                                <code class="endpoint-text" style='color:#2b6cb0; font-weight:bold; font-size:12px; background:#ebf8ff; padding:2px 4px; border-radius:3px;'><?= htmlspecialchars($log['endpoint']) ?></code>
                                <span class='method-badge method-<?= $log['method']?>'><?= htmlspecialchars($log['method']) ?></span><br>
                                <span style='color:#718096; font-size:12px; display:inline-block; margin-top:5px;'>Action: <b><?= htmlspecialchars($log['action'] ?? 'N/A') ?></b></span>
                            </td>
                            <td data-label="HTTP Status">
                                <span class="status-badge <?php echo ($log['http_status'] == 200) ? 'status-200' : 'status-error'; ?>">HTTP <?php echo $log['http_status']; ?></span>
                            </td>
                            <td data-label="Payload Terkirim" class="payload-cell">
                                <pre><?php 
                                    $json_data = json_decode($log['payload'], true);
                                    echo $json_data ? json_encode($json_data, JSON_PRETTY_PRINT) : htmlspecialchars($log['payload']); 
                                ?></pre>
                            </td>
                            <td data-label="Detail Error / Keterangan" class="error-text-cell">
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

<script>
let currentSelectedLevel = "all";

function refreshTable(partName, tbodyId) {
    var button = event.currentTarget;
    var originalText = button.innerHTML;
    button.innerHTML = "⌛ Loading...";
    button.disabled = true;

    fetch('api_logs.php?fetch_part=' + partName)
        .then(response => response.text())
        .then(data => {
            document.getElementById(tbodyId).innerHTML = data;
            button.innerHTML = originalText;
            button.disabled = false;
            applyFilters(); 
        })
        .catch(error => {
            console.error('Error:', error);
            button.innerHTML = "❌ Gagal";
            button.disabled = false;
            setTimeout(() => { button.innerHTML = originalText; }, 2000);
        });
}

// Mengatur perpindahan tombol aktif log level
function toggleLevelFilter(buttonElement) {
    const buttons = document.getElementsByClassName("filter-btn");
    for (let i = 0; i < buttons.length; i++) {
        buttons[i].classList.remove("active");
    }
    buttonElement.classList.add("active");
    currentSelectedLevel = buttonElement.getAttribute("data-target");
    
    applyFilters();
}

// Fungsi penggabung filter pencarian kata kunci text & filter log level secara bersamaan
function applyFilters() {
    const searchText = document.getElementById("logSearch").value.toLowerCase();
    const rows = document.getElementsByClassName("searchable-row");

    for (let i = 0; i < rows.length; i++) {
        const row = rows[i];
        const rowText = row.textContent || row.innerText;
        const rowLevel = row.getAttribute("data-level");

        const matchesSearch = rowText.toLowerCase().indexOf(searchText) > -1;
        const matchesLevel = (currentSelectedLevel === "all") || (rowLevel === currentSelectedLevel);

        if (matchesSearch && matchesLevel) {
            row.style.display = "";
        } else {
            row.style.display = "none";
        }
    }
}
</script>
</body>
</html>