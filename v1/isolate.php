<?php
set_time_limit(300); 
header("Content-Type: application/json");

// 1. Load Konfigurasi & Validator HMAC Terpusat
include_once(dirname(__FILE__) . "/../config.php");
include_once(dirname(__FILE__) . "/../hmac_validator.php");

$conn = getDBConnection();

// 2. Eksekusi Validasi Keamanan HMAC (Otomatis mencatat log jika gagal/sukses)
validateHMACRequest($conn);

// 3. Ambil Input JSON
$input = json_decode(file_get_contents("php://input"), true);
$action = isset($input['action']) ? strtolower($input['action']) : ''; 
$usernames = isset($input['usernames']) ? $input['usernames'] : [];
$target_group_input = isset($input['target_group']) ? trim($input['target_group']) : '';

if (empty($action) || empty($usernames) || !is_array($usernames)) {
    http_response_code(400);
    writeAPILog($conn, 'isolate.php', 400, 'error', 'Input JSON tidak valid.');
    echo json_encode(["status" => "error", "message" => "Input tidak valid."]);
    exit();
}

if ($action === 'restore' && empty($target_group_input)) {
    http_response_code(400);
    writeAPILog($conn, 'isolate.php', 400, 'error', 'Parameter target_group wajib diisi untuk restore.');
    echo json_encode(["status" => "error", "message" => "Parameter 'target_group' wajib diisi untuk restore."]);
    exit();
}

$total_users = count($usernames);
$use_worker_queue = ($total_users > 100) ? true : false;
$results = [];

// 4. Looping Eksekusi per User
foreach ($usernames as $raw_username) {
    $username = $conn->real_escape_string($raw_username);
    
    $nas_ip = null;
    $nas_secret = API_DEFAULT_SECRET;
    
    $nas_query = "SELECT a.nasipaddress, n.secret 
                  FROM radacct a 
                  LEFT JOIN nas n ON a.nasipaddress = n.nasname 
                  WHERE a.username = '$username' AND a.acctstoptime IS NULL 
                  ORDER BY a.radacctid DESC LIMIT 1";
                  
    $nas_res = $conn->query($nas_query);
    if ($nas_res && $nas_res->num_rows > 0) {
        $row = $nas_res->fetch_assoc();
        $nas_ip = $row['nasipaddress'];
        if (!empty($row['secret'])) { $nas_secret = $row['secret']; }
    }

    $db_success = false;
    $log_error = "";
    
    if ($action === 'delete') {
        $conn->query("DELETE FROM radcheck WHERE username = '$username'");
        $conn->query("DELETE FROM radusergroup WHERE username = '$username'");
        $conn->query("DELETE FROM radreply WHERE username = '$username'");
        $db_success = true;
    } else {
        if ($action === 'shutdown') { $target_group = 'ISOLIREBILLING'; } 
        if ($action === 'off') { $target_group = 'OFF'; } 
        if ($action === 'restore') { $target_group = $conn->real_escape_string($target_group_input); }
        
        $check_exist = $conn->query("SELECT username FROM radusergroup WHERE username = '$username'");
        if ($check_exist && $check_exist->num_rows > 0) {
            $query = "UPDATE radusergroup SET groupname = '$target_group' WHERE username = '$username'";
        } else {
            $query = "INSERT INTO radusergroup (username, groupname, priority) VALUES ('$username', '$target_group', 1)";
        }
        
        if ($conn->query($query)) { $db_success = true; } else { $log_error = $conn->error; }
    }

    $session_status = "Offline / Tidak ada sesi aktif";

    if ($db_success && !empty($nas_ip)) {
        $safe_username = $conn->real_escape_string($username);
        $safe_nas_ip = $conn->real_escape_string($nas_ip);
        $safe_nas_secret = $conn->real_escape_string($nas_secret);

        if ($use_worker_queue) {
            $sql_queue = "INSERT INTO radius_kick_queue (username, nasipaddress, secret, status) 
                          VALUES ('$safe_username', '$safe_nas_ip', '$safe_nas_secret', 'pending')";
            if ($conn->query($sql_queue)) {
                $session_status = "Queued";
            } else {
                $session_status = "Failed to Queue: " . $conn->error;
            }
        } else {
            $sh_user = escapeshellarg($username);
            $sh_nas = escapeshellarg($nas_ip . ":3799");
            $sh_secret = escapeshellarg($nas_secret);
            
            $command = "echo \"User-Name=$sh_user\" | /usr/bin/radclient -c '1' -n '2' -r '1' -t '2' -x $sh_nas 'disconnect' $sh_secret 2>&1";
            exec($command, $output, $return_var);
            
            $session_status = ($return_var === 0) ? "Disconnected" : "Failed to Disconnect";
            usleep(50000); 
        }
    }

    $results[$username] = [
        "db_action_success" => $db_success,
        "session_status" => $session_status,
        "error_message" => !empty($log_error) ? $log_error : null
    ];
}

// Catat sukses ke database log sebelum output data keluar
writeAPILog($conn, 'isolate.php', 200, 'completed');

echo json_encode([
    "status" => "completed",
    "execution_mode" => $use_worker_queue ? "Worker Queue (Bulk)" : "Real-time (Direct)",
    "requested_action" => $action,
    "results" => $results
]);

$conn->close();
?>
