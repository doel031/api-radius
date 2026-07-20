<?php

header("Content-Type: application/json");

// Load Konfigurasi & Validator HMAC
include_once(dirname(__FILE__) . "/../config.php");
include_once(dirname(__FILE__) . "/../hmac_validator.php");

$conn = getDBConnection();

register_shutdown_function(function () use ($conn) {
    flushAPILog($conn);
    $conn->close();   // ditutup di sini, setelah flush selesai
});

// Eksekusi Validasi Keamanan HMAC
validateHMACRequest($conn);

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents("php://input"), true);
$action = isset($input['action']) ? strtolower($input['action']) : '';

// DEBUG: catat raw input yang diterima (hanya aktif kalau API_DEBUG_MODE = true)
writeAPILog($conn, '/api/v1/users.php', 0, 'DEBUG', "Incoming request method=$method action=$action", $input);

if (empty($action)) {
    http_response_code(400);
    $err_msg = "Parameter 'action' wajib diisi (add, get, update, delete).";
    // writeAPILog($conn, '/api/v1/users.php', 400, 'WARNING', $err_msg);
    echo json_encode(["status" => "error", "error_code" => "MISSING_ACTION", "message" => $err_msg]);
    exit();
}

// --- 1. FILTER BERDASARKAN METHOD ---
switch ($method) {

    // ==========================================
    // FILTER: METHOD POST
    // ==========================================
    case 'POST':
        if ($action !== 'add') {
            http_response_code(400);
            $err_msg = "Action '$action' tidak valid untuk metode POST. Gunakan 'add'.";
            // writeAPILog($conn, '/api/v1/users.php', 400, 'WARNING', $err_msg);
            echo json_encode(["status" => "error", "error_code" => "INVALID_ACTION", "message" => $err_msg]);
            exit();
        }

        if (!empty($input['username']) && !empty($input['password']) && !empty($input['group_name'])) {

            $username = mysqli_real_escape_string($conn, $input['username']);
            $password = mysqli_real_escape_string($conn, $input['password']);
            $group_name = mysqli_real_escape_string($conn, $input['group_name']);

            // Matikan autocommit untuk memulai Transaksi
            mysqli_begin_transaction($conn);

            // 1. Cek apakah username sudah ada
            $checkQuery = "SELECT id FROM radcheck WHERE username = '$username'";
            $checkResult = mysqli_query($conn, $checkQuery);

            if (mysqli_num_rows($checkResult) > 0) {
                mysqli_rollback($conn);
                http_response_code(400);
                echo json_encode(["status" => "error", "error_code" => "USERNAME_EXISTS", "message" => "Username sudah terdaftar."]);
                exit;
            }

            // 2. Insert ke tabel radcheck
            $queryCheck = "INSERT INTO radcheck (username, attribute, op, value) VALUES ('$username', 'Cleartext-Password', ':=', '$password')";
            $execCheck = mysqli_query($conn, $queryCheck);

            // 3. Insert ke tabel radusergroup
            $queryGroup = "INSERT INTO radusergroup (username, group_name, priority) VALUES ('$username', '$group_name', 1)";
            $execGroup = mysqli_query($conn, $queryGroup);

            // Validasi transaksi
            if ($execCheck && $execGroup) {
                mysqli_commit($conn);
                http_response_code(201);
                echo json_encode(["status" => "success", "message" => "User dan Group berhasil ditambahkan."]);
            } else {
                mysqli_rollback($conn);
                http_response_code(500);
                echo json_encode(["status" => "error", "message" => "Gagal menyimpan data ke database."]);
            }

        } else {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Data tidak lengkap (butuh username, password, dan group_name)."]);
        }
        break;

        // ==========================================
        // FILTER: METHOD GET
        // ==========================================
    case 'GET':
        if ($action !== 'get') {
            http_response_code(400);
            $err_msg = "Action '$action' tidak valid untuk metode POST. Gunakan 'get'.";
            // writeAPILog($conn, '/api/v1/users.php', 400, 'WARNING', $err_msg);
            echo json_encode(["status" => "error", "error_code" => "INVALID_ACTION", "message" => $err_msg]);
            exit();
        }

        $allowed_params = ['action', 'username'];
        $actual_params  = array_keys($input);

        foreach ($actual_params as $param) {
            if (!in_array($param, $allowed_params)) {
                http_response_code(400);
                $err_msg = "Parameter '$param' tidak diizinkan";
                // writeAPILog($conn, '/api/v1/users.php', 400, 'WARNING', $err_msg);
                echo json_encode(["status" => "error", "error_code" => "INVALID_PARAMETER", "message" => $err_msg]);
                exit();
            }
        }

        $username = $conn->real_escape_string($input['username'] ?? '');

        $query = "SELECT radcheck.username as username, radcheck.value as password, radusergroup.groupname as group_name
                    FROM radcheck
                    LEFT JOIN radusergroup ON radcheck.username = radusergroup.username 
                    WHERE radcheck.attribute = 'Cleartext-Password'";

        // Karena GET request ini membaca action dari body JSON, username juga dilewatkan di JSON body
        if (!empty($input['username'])) {
            $query .= " AND radcheck.username = '$username'";
        }

        $result = mysqli_query($conn, $query);
        $total_users = mysqli_num_rows($result);
        $data = [];

        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
        }

        if (!empty($username)) {
            echo json_encode([
                "status" => "success",
                "data" => $data[0] // Hanya satu data yang dikembalikan
            ]);
            // writeAPILog($conn, '/api/v1/users.php', 200, 'INFO', "Action: get (. $username .)");
        } else {
            echo json_encode([
                "status" => "success",
                "total_users" => $total_users,
                "data" => $data
            ]);
            // writeAPILog($conn, '/api/v1/users.php', 200, 'INFO', "Action: get (all)");
        }
        exit();

        // ==========================================
        // FILTER: METHOD PUT
        // ==========================================
    case 'PUT':
        if ($action !== 'update') {
            http_response_code(400);
            $err_msg = "Action '$action' tidak valid untuk metode PUT. Gunakan 'update'.";
            // writeAPILog($conn, '/api/v1/users.php', 400, 'WARNING', $err_msg);
            echo json_encode(["status" => "error", "error_code" => "INVALID_ACTION", "message" => $err_msg]);
            exit();
        }

        $username = mysqli_real_escape_string($conn, $input['username']);
        $password = mysqli_real_escape_string($conn, $input['password']) ?? null;
        $group_name = mysqli_real_escape_string($conn, $input['group_name']) ?? null;

        if (empty($username)) {
            http_response_code(400);
            $err_msg = "Parameter username tidak ada.";
            // writeAPILog($conn, '/api/v1/users.php', 400, 'WARNING', $err_msg);
            echo json_encode(["status" => "error", "error_code" => "INVALID_PARAMETER", "message" => $err_msg]);
            exit();
        }

        $check_username = $conn->query("SELECT username FROM radcheck WHERE username = '$username' LIMIT 1");
        if (!$check_username || $check_username->num_rows === 0) {
            $error_code = "USER_NOT_FOUND";
            $error_level = "WARNING";
            throw new Exception("Gagal memperbarui data. Pengguna dengan Username '$username' tidak ditemukan.");
        }

        if (empty($password) && empty($group_name)) {
            http_response_code(400);
            $err_msg = "Parameter password atau group_name tidak ada.";
            // writeAPILog($conn, '/api/v1/users.php', 400, 'WARNING', $err_msg);
            echo json_encode(["status" => "error", "error_code" => "INVALID_PARAMETER", "message" => $err_msg]);
            exit();
        }

        mysqli_begin_transaction($conn);

        if (!empty($password)) {
            // 1. Update Password di radcheck
            $queryCheck = "UPDATE radcheck SET value = '$password' WHERE username = '$username' AND attribute = 'Cleartext-Password'";
            $execCheck = mysqli_query($conn, $queryCheck);
        }

        if (!empty($group_name)) {
            // 2. Update Group di radusergroup
            $queryGroup = "UPDATE radusergroup SET groupname = '$group_name' WHERE username = '$username'";
            $execGroup = mysqli_query($conn, $queryGroup);
        }

        mysqli_commit($conn);
        echo json_encode(["status" => "success", "message" => "Data user dan group berhasil diperbarui."]);

        break;

        // ==========================================
        // FILTER: METHOD DELETE
        // ==========================================
    case 'DELETE':
        if ($action !== 'delete') {
            http_response_code(400);
            $err_msg = "Action '$action' tidak valid untuk metode DELETE. Gunakan 'delete'.";
            // writeAPILog($conn, '/api/v1/users.php', 400, 'WARNING', $err_msg);
            echo json_encode(["status" => "error", "error_code" => "INVALID_ACTION", "message" => $err_msg]);
            exit();
        }

        $username = mysqli_real_escape_string($conn, $input['username']);
        if (empty($username)) {
            http_response_code(400);
            $err_msg = "Parameter username tidak ada.";
            // writeAPILog($conn, '/api/v1/users.php', 400, 'WARNING', $err_msg);
            echo json_encode(["status" => "error", "error_code" => "INVALID_PARAMETER", "message" => $err_msg]);
            exit();
        }

        $check_username = $conn->query("SELECT username FROM radcheck WHERE username = '$username' LIMIT 1");
        if (!$check_username || $check_username->num_rows === 0) {
            http_response_code(400);
            $err_msg = "Username tidak ada.";
            // writeAPILog($conn, '/api/v1/users.php', 400, 'WARNING', $err_msg);
            echo json_encode(["status" => "error", "error_code" => "USER_NOT_FOUND", "message" => $err_msg]);
            exit();
        }

        $username = mysqli_real_escape_string($conn, $input['username']);

        mysqli_begin_transaction($conn);

        // 1. Hapus dari radcheck
        $queryCheck = "DELETE FROM radcheck WHERE username = '$username'";
        $execCheck = mysqli_query($conn, $queryCheck);

        // 2. Hapus dari radusergroup
        $queryGroup = "DELETE FROM radusergroup WHERE username = '$username'";
        $execGroup = mysqli_query($conn, $queryGroup);

        if ($execCheck && $execGroup) {
            mysqli_commit($conn);
            echo json_encode(["status" => "success", "message" => "User berhasil dihapus dari radcheck dan radusergroup."]);
        } else {
            mysqli_rollback($conn);
            http_response_code(500);
            echo json_encode(["status" => "error", "error_code" => "FAILED_DELETE", "message" => "Gagal menghapus data."]);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(["status" => "error", "error_code" => "INVALID_METHOD", "message" => "Method tidak diizinkan."]);
        break;
}
