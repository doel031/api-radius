<?php
header("Content-Type: application/json");

// 1. Load Konfigurasi & Validator HMAC Terpusat
include_once(dirname(__FILE__) . "/../config.php");
include_once(dirname(__FILE__) . "/../hmac_validator.php");

$conn = getDBConnection();

// 2. Eksekusi Validasi Keamanan HMAC
validateHMACRequest($conn);

// 3. Ambil Input JSON dan Deteksi Format Otomatis
$input = json_decode(file_get_contents("php://input"), true);
$users_to_process = [];
$is_bulk = false;

if (isset($input['users']) && is_array($input['users'])) {
    $users_to_process = $input['users'];
    $is_bulk = true;
} elseif (isset($input['username']) && isset($input['password']) && isset($input['group'])) {
    $users_to_process[] = [
        'username' => $input['username'],
        'password' => $input['password'],
        'group'    => $input['group']
    ];
    $is_bulk = false;
} else {
    http_response_code(400);
    writeAPILog($conn, 'add_user.php', 400, 'error', 'Format JSON tidak dikenal.');
    echo json_encode(["status" => "error", "message" => "Format JSON tidak dikenal."]);
    exit();
}

$creationdate = date('Y-m-d H:i:s');
$results = [];

// 4. Looping Eksekusi Data User
foreach ($users_to_process as $userData) {
    $username_input = isset($userData['username']) ? trim($userData['username']) : '';
    $password_input = isset($userData['password']) ? trim($userData['password']) : '';
    $group_input    = isset($userData['group']) ? trim($userData['group']) : '';
    
    if (empty($username_input) || empty($password_input) || empty($group_input)) {
        $results[$username_input ? $username_input : 'unknown_user_'.rand(100,999)] = [
            "success" => false,
            "message" => "Data tidak lengkap."
        ];
        continue;
    }
    
    $username = $conn->real_escape_string($username_input);
    $password = $conn->real_escape_string($password_input);
    $group    = $conn->real_escape_string($group_input);
    
    $conn->begin_transaction();
    
    try {
        $check_user = $conn->query("SELECT id FROM radcheck WHERE username = '$username' LIMIT 1");
        if ($check_user && $check_user->num_rows > 0) {
            throw new Exception("Username sudah terdaftar.");
        }

        $sql_radcheck = "INSERT INTO radcheck (username, attribute, op, value) VALUES ('$username', 'Cleartext-Password', ':=', '$password')";
        if (!$conn->query($sql_radcheck)) { throw new Exception("Gagal simpan radcheck"); }

        $sql_group = "INSERT INTO radusergroup (username, groupname, priority) VALUES ('$username', '$group', 1)";
        if (!$conn->query($sql_group)) { throw new Exception("Gagal simpan radusergroup"); }

        $sql_billinfo = "INSERT INTO userbillinfo (username, creditcardtype, changeuserbillinfo, lead, creationdate, creationby) 
                         VALUES ('$username', 'Other', 0, 'Internet', '$creationdate', 'admin_api')";
        if (!$conn->query($sql_billinfo)) { throw new Exception("Gagal simpan userbillinfo"); }
        
        $billinfo_id = $conn->insert_id;

        if ($billinfo_id > 0) {
            $sql_userinfo = "INSERT INTO userinfo (id, username, changeuserinfo, creationdate, creationby) 
                             VALUES ($billinfo_id, '$username', 0, '$creationdate', 'admin_api')";
        } else {
            $sql_userinfo = "INSERT INTO userinfo (username, changeuserinfo, creationdate, creationby) 
                             VALUES ('$username', 0, '$creationdate', 'admin_api')";
        }
        if (!$conn->query($sql_userinfo)) { throw new Exception("Gagal simpan userinfo"); }

        $conn->commit();
        $results[$username] = ["success" => true, "message" => "Berhasil didaftarkan."];

    } catch (Exception $e) {
        $conn->rollback();
        $results[$username] = ["success" => false, "message" => $e->getMessage()];
    }
}

// Catat sukses ke database log
writeAPILog($conn, 'add_user.php', 200, 'completed');

if ($is_bulk) {
    echo json_encode(["status" => "completed", "type" => "bulk", "results" => $results]);
} else {
    $final_user = array_key_first($results);
    if ($results[$final_user]['success']) {
        echo json_encode(["status" => "success", "type" => "single", "message" => "User $final_user berhasil dibuat di grup $group_input."]);
    } else {
        http_response_code(409);
        echo json_encode(["status" => "error", "type" => "single", "message" => $results[$final_user]['message']]);
    }
}

$conn->close();
?>
