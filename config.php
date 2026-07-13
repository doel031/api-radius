<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'raduser');
define('DB_PASS', 'radpass');
define('DB_NAME', 'raddb');

define('API_DEFAULT_SECRET', 'testing123'); 
define('HMAC_TIME_WINDOW', 300); // Toleransi waktu request maksimal 5 menit (300 detik)

function getDBConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Database connection failed"]);
        exit();
    }
    return $conn;
}

// Tambahkan fungsi ini di bagian paling bawah api/config.php

/**
 * Fungsi untuk mencatat histori payload penting ke database
 */
function writeAPILog($conn, $endpoint, $http_status, $status_response, $error_details = null) {
    $headers = getallheaders();
    $client_id = isset($headers['X-GSMNET-ClientID']) ? $conn->real_escape_string($headers['X-GSMNET-ClientID']) : 'UNKNOWN';
    
    // Ambil IP Publik
    $client_ip = $_SERVER['REMOTE_ADDR'];
    if (isset($headers['X-Forwarded-For'])) {
        $client_ip = trim(explode(',', $headers['X-Forwarded-For'])[0]);
    }
    $safe_ip = $conn->real_escape_string($client_ip);

    // Ambil Payload RAW JSON
    $raw_payload = file_get_contents("php://input");
    $safe_payload = $conn->real_escape_string($raw_payload);
    
    // Deteksi Action dari Payload secara dinamis
    $input_data = json_decode($raw_payload, true);
    $action = isset($input_data['action']) ? $conn->real_escape_string($input_data['action']) : null;
    if (!$action && isset($input_data['users'])) {
        $action = 'bulk_add_user';
    } elseif (!$action && isset($input_data['username'])) {
        $action = 'single_add_user';
    }

    $safe_endpoint = $conn->real_escape_string($endpoint);
    $safe_status_resp = $conn->real_escape_string($status_response);
    $safe_error = $error_details ? $conn->real_escape_string($error_details) : null;
    
    // Insert ke tabel log
    $sql_log = "INSERT INTO api_request_logs (client_id, endpoint, action, payload, ip_address, http_status, status_response, error_details) 
                VALUES ('$client_id', '$safe_endpoint', " . ($action ? "'$action'" : "NULL") . ", '$safe_payload', '$safe_ip', $http_status, '$safe_status_resp', " . ($safe_error ? "'$safe_error'" : "NULL") . ")";
    
    $conn->query($sql_log);
}

?>
