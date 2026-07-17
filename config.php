<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'raduser');
define('DB_PASS', 'radpass');
define('DB_NAME', 'raddb');

define('API_DEFAULT_SECRET', 'testing123'); 
define('HMAC_TIME_WINDOW', 300); // Toleransi waktu request maksimal 5 menit (300 detik)
define('API_DEBUG_MODE', true);

$GLOBALS['__log_buffer'] = [
    'level'        => 'INFO',   // level akhir yang akan dipakai (bisa naik jadi WARNING/ERROR)
    'action'       => null,
    'error'        => null,
    'payload'      => null,
    'debug_notes'  => [],       // kumpulan catatan debug, digabung jadi 1 string nanti
    'http_status'  => 200,
];

// urutan severity, dipakai untuk menentukan level akhir (yang tertinggi yang menang)
function levelPriority($level) {
    $order = ['DEBUG' => 0, 'INFO' => 1, 'WARNING' => 2, 'ERROR' => 3];
    return $order[$level] ?? 1;
}

function getDBConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Database connection failed"]);
        exit();
    }
    return $conn;
}

/**
 * Catat log ke buffer (BELUM insert ke DB)
 */
function writeAPILog($conn, $endpoint, $http_status, $level, $message, $payload = null) {
    $buf = &$GLOBALS['__log_buffer'];

    // DEBUG note selalu dikumpulkan (tapi hanya benar-benar dipakai kalau API_DEBUG_MODE true)
    if ($level === 'DEBUG') {
        if (API_DEBUG_MODE) {
            $buf['debug_notes'][] = $message . (is_array($payload) ? ' | ' . json_encode($payload) : '');
        }
        return; // DEBUG tidak pernah jadi level utama, cuma numpang catatan
    }

    // level utama: pakai yang paling tinggi severity-nya sepanjang request
    if (levelPriority($level) >= levelPriority($buf['level'])) {
        $buf['level'] = $level;
    }

    if ($level === 'WARNING' || $level === 'ERROR') {
        $buf['error'] = $message; // simpan pesan error/warning terakhir yang paling relevan
    } else {
        $buf['action'] = $message;
    }

    if ($payload !== null) {
        $buf['payload'] = is_array($payload) ? json_encode($payload) : $payload;
    }

    $buf['http_status'] = $http_status;
    $buf['endpoint']    = $endpoint;
}

/**
 * Tulis SATU baris log ke DB, gabungan dari semua writeAPILog() sepanjang request ini.
 * WAJIB dipanggil sekali di akhir script (sebelum $conn->close()).
 */
function flushAPILog($conn) {
    $buf = $GLOBALS['__log_buffer'];

    // VALIDASI: pastikan log_level HANYA salah satu dari 4 nilai yang diizinkan
    $validLevels = ['DEBUG', 'INFO', 'WARNING', 'ERROR'];
    $level = strtoupper(trim($buf['level'] ?? 'INFO'));
    if (!in_array($level, $validLevels)) {
        $level = 'INFO'; // fallback aman kalau ada nilai aneh/tak terduga
    }

    $method   = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';
    $ip       = $_SERVER['REMOTE_ADDR'] ?? '';
    $clientId = $_SERVER['HTTP_X_GSMNET_CLIENTID'] ?? 'UNKNOWN';
    $endpoint = $buf['endpoint'] ?? ($_SERVER['REQUEST_URI'] ?? 'unknown');
    $httpStatus = $buf['http_status'] ?? 200;

    $debugInfo = !empty($buf['debug_notes']) ? implode(" || ", $buf['debug_notes']) : null;

    try {
        $stmt = $conn->prepare("
            INSERT INTO api_request_logs
            (client_id, ip_address, endpoint, method, log_level, http_status, action, payload, debug_info, error_details, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $stmt->bind_param(
            "sssssissss",
            $clientId, $ip, $endpoint, $method, $level, $httpStatus,
            $buf['action'], $buf['payload'], $debugInfo, $buf['error']
        );
        $stmt->execute();
        $stmt->close();
    } catch (\mysqli_sql_exception $e) {
        // JANGAN biarkan kegagalan logging bikin seluruh request fatal error
        error_log("flushAPILog gagal: " . $e->getMessage());
    }
}

?>
