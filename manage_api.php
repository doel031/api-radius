<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include_once(dirname(__FILE__) . "/config.php");
$conn = getDBConnection();
$message = "";

if (isset($_POST['action']) && $_POST['action'] == 'generate') {
    $client_name = $conn->real_escape_string($_POST['client_name']);
    $allowed_ip = $conn->real_escape_string(trim($_POST['allowed_ip']));
    $new_key = bin2hex(random_bytes(16)); 
    
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

if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM api_keys WHERE id = $id");
    header("Location: manage_api.php");
    exit();
}

if (isset($_GET['unblock'])) {
    $id = intval($_GET['unblock']);
    $conn->query("UPDATE api_failed_attempts SET attempts = 0, blocked_until = NULL WHERE id = $id");
    header("Location: manage_api.php");
    exit();
}

if (isset($_GET['fetch_part']) && $_GET['fetch_part'] == 'banned') {
    $blocked_res = $conn->query("SELECT * FROM api_failed_attempts WHERE total_banned_count > 0 ORDER BY id DESC");
    if ($blocked_res && $blocked_res->num_rows > 0) {
        while($block = $blocked_res->fetch_assoc()) {
            echo "<tr>
                    <td data-label='Client ID'><strong style='color:#c53030;'>" . htmlspecialchars($block['client_id']) . "</strong></td>
                    <td data-label='IP Address'><span class='ip-badge' style='background:#fed7d7; color:#9b2c2c; border: 1px solid #feb2b2;'>" . htmlspecialchars($block['ip_address']) . "</span></td>
                    <td data-label='Tier'><b>Tier {$block['tier']}</b></td>
                    <td data-label='Frekuensi Banned'><b style='color:#dd6b20;'>{$block['total_banned_count']} Kali</b></td>
                    <td data-label='Banned Sampai' style='color:#e53e3e; font-weight:bold; font-family: monospace;'>{$block['blocked_until']}</td>
                    <td data-label='Aksi Kontrol'><a href='manage_api.php?unblock={$block['id']}' class='btn-unblock' onclick=\"return confirm('Force unblock IP ini sekarang?')\">Buka Gembok (Unblock)</a></td>
                  </tr>";
        }
    } else {
        echo "<tr><td colspan='6' style='text-align: center; color: #48bb78; font-weight: 500; padding: 20px;'>✔ Sistem Aman & Bersih. Tidak ada IP Client yang dibanned.</td></tr>";
    }
    exit();
}

$api_keys_res = $conn->query("SELECT * FROM api_keys ORDER BY id DESC");
$blocked_res  = $conn->query("SELECT * FROM api_failed_attempts WHERE total_banned_count > 0 ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Kunci Akses API - GSMNET</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f4f6f9; padding: 15px; color: #333; }
        .container { max-width: 1100px; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); margin: 0 auto; }
        h2 { padding-bottom: 15px; color: #2c3e50; margin-bottom: 20px; font-size: 20px; border-bottom: 2px solid #e2e8f0; text-align: center; }
        h3 { color: #2c3e50; margin-bottom: 15px; font-size: 16px; display: flex; align-items: center; justify-content: space-between; gap: 10px; }
        
        .nav-menu { display: flex; border-bottom: 2px solid #e2e8f0; margin-bottom: 20px; gap: 5px; justify-content: center; }
        .nav-link { text-decoration: none; padding: 10px 15px; font-size: 14px; font-weight: 600; color: #718096; transition: all 0.2s ease; border-bottom: 2px solid transparent; margin-bottom: -2px; }
        .nav-link:hover { color: #3498db; }
        .nav-link.active { color: #3498db; border-bottom: 2px solid #3498db; }
        
        .form-group { margin-bottom: 15px; }
        label { display: block; font-weight: bold; margin-bottom: 5px; font-size: 13px; color: #4a5568; }
        input[type="text"] { width: 100%; padding: 12px 10px; border: 1px solid #cbd5e0; border-radius: 6px; font-size: 14px; color: #2d3748; }
        .btn-submit { background: #3498db; color: #fff; padding: 12px 20px; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; font-size: 14px; width: 100%; }
        .btn-submit:hover { background: #2980b9; }
        
        .table-container { width: 100%; overflow-x: auto; margin-top: 10px; border: 1px solid #e2e8f0; border-radius: 6px; }
        table { width: 100%; border-collapse: collapse; background: #fff; }
        th, td { border-bottom: 1px solid #edf2f7; padding: 12px; text-align: left; font-size: 14px; vertical-align: middle; }
        th { background: #f7fafc; color: #4a5568; font-weight: 600; font-size: 12px; text-transform: uppercase; }
        
        .alert { padding: 12px; margin-bottom: 20px; border-radius: 6px; font-weight: bold; font-size: 14px; }
        .success { background: #c6f6d5; color: #22543d; border: 1px solid #9ae6b4; }
        .danger { background: #fed7d7; color: #742a2a; border: 1px solid #feb2b2; }
        
        .key-badge { background: #ebf8ff; color: #2b6cb0; padding: 4px 8px; font-family: monospace; border-radius: 4px; border: 1px solid #bee3f8; font-size: 13px; word-break: break-all; display: inline-block; }
        .ip-badge { background: #fef3c7; color: #92400e; padding: 2px 6px; border-radius: 4px; font-family: monospace; font-size: 13px; font-weight: bold; }
        .btn-delete { color: #e53e3e; text-decoration: none; font-weight: bold; }
        .btn-unblock { color: #3182ce; text-decoration: none; font-weight: bold; }
        .btn-refresh { background: #edf2f7; color: #4a5568; padding: 6px 12px; border: 1px solid #cbd5e0; border-radius: 4px; font-size: 12px; font-weight: bold; cursor: pointer; transition: all 0.2s; }

        @media (min-width: 768px) {
            h2 { font-size: 22px; text-align: left; }
            .nav-menu { justify-content: flex-start; }
            .btn-submit { width: auto; }
        }

        /* RESPONSIVE LAYOUT UNTUK MOBILE SMARTPHONE */
        @media (max-width: 767px) {
            table, thead, tbody, th, td, tr { display: block; }
            thead tr { position: absolute; top: -9999px; left: -9999px; }
            tr { border: 1px solid #cbd5e0; border-radius: 8px; margin-bottom: 15px; padding: 10px; background: #fff; }
            td { border: none; padding: 8px 5px; position: relative; padding-left: 40%; text-align: right; font-size: 13px; border-bottom: 1px dashed #edf2f7; }
            td:last-child { border-bottom: none; }
            td::before { content: attr(data-label); position: absolute; left: 8px; width: 35%; text-align: left; font-weight: 700; color: #4a5568; font-size: 12px; text-transform: uppercase; white-space: nowrap; }
        }
    </style>
</head>
<body>
<div class="container">
    <h2>🛠 Core RADIUS API Gateway Dashboard</h2>
    <?php echo $message; ?>
    
    <div class="nav-menu">
        <a href="api_logs.php" class="nav-link">📋 Log Aktivitas API</a>
        <a href="manage_api.php" class="nav-link active">🔑 Manajemen Kunci & Blokir</a>
    </div>

    <h3>Registrasi / Generate Kunci Akses Baru</h3>
    <form method="POST" action="" style="margin-top: 10px; margin-bottom: 30px; background: #f7fafc; padding: 15px; border-radius: 6px; border: 1px solid #edf2f7;">
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
    <div class="table-container" style="margin-bottom: 30px;">
        <table>
            <thead>
                <tr><th>Client ID</th><th>Secret Key</th><th>IP Whitelist</th><th>Aksi</th></tr>
            </thead>
            <tbody>
                <?php if ($api_keys_res && $api_keys_res->num_rows > 0): ?>
                    <?php while($row = $api_keys_res->fetch_assoc()): ?>
                        <tr>
                            <td data-label="Client ID"><strong><?php echo htmlspecialchars($row['client_name']); ?></strong></td>
                            <td data-label="Secret Key"><span class="key-badge"><?php echo $row['api_key']; ?></span></td>
                            <td data-label="IP Whitelist"><span class="ip-badge"><?php echo htmlspecialchars($row['allowed_ip']); ?></span></td>
                            <td data-label="Aksi"><a href="manage_api.php?delete=<?php echo $row['id']; ?>" class="btn-delete" onclick="return confirm('Apakah Anda yakin ingin mencabut izin akses client ini?')">Hapus Kunci</a></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="4" style="text-align: center; color: #718096; padding: 15px;">Belum ada kredensial API yang terdaftar.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <h3 style="border-left: 4px solid #e53e3e; padding-left: 8px; color: #c53030;">
        <span>🚫 Daftar IP Client Terbanned Bertingkat</span>
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
                            <td data-label="Client ID"><strong style="color:#c53030;"><?php echo htmlspecialchars($block['client_id']); ?></strong></td>
                            <td data-label="IP Address"><span class="ip-badge" style="background:#fed7d7; color:#9b2c2c; border: 1px solid #feb2b2;"><?php echo htmlspecialchars($block['ip_address']); ?></span></td>
                            <td data-label="Posisi Tier"><b>Tier <?php echo $block['tier']; ?></b></td>
                            <td data-label="Frekuensi Banned"><b style="color:#dd6b20;"><?php echo $block['total_banned_count']; ?> Kali Kena Penalti</b></td>
                            <td data-label="Banned Sampai" style="color:#e53e3e; font-weight:bold; font-family: monospace;"><?php echo $block['blocked_until']; ?></td>
                            <td data-label="Aksi Kontrol"><a href="manage_api.php?unblock=<?php echo $block['id']; ?>" class="btn-unblock" onclick="return confirm('Force unblock IP ini sekarang?')">Buka Gembok (Unblock)</a></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="6" style="text-align: center; color: #48bb78; font-weight: 500; padding: 20px;">✔ Sistem Aman & Bersih. Tidak ada IP Client yang sedang menjalani masa karantina/banned.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
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
            console.error('Error:', error);
            button.innerHTML = "❌ Gagal";
            button.disabled = false;
            setTimeout(() => { button.innerHTML = originalText; }, 2000);
        });
}
</script>
</body>
</html>