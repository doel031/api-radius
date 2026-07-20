<?php

/**
 * Mencatat aktivitas request API ke tabel api_request_logs
 *
 * @param mysqli $conn
 * @param string $client_id      ID client dari header X-GSMNET-ClientID
 * @param string $action         Nama aksi (misal: 'create_user', 'isolate_bulk')
 * @param mixed  $payload        Data request (array akan otomatis di-encode JSON)
 * @param int    $http_status    Kode HTTP response (200, 400, 401, dst)
 * @param string|null $error_details  Pesan error jika ada, null jika sukses
 */
function logApiRequest($conn, $client_id, $action, $payload, $http_status, $error_details = null)
{
    // ambil method otomatis dari request yang sedang berjalan
    $method   = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';
    $endpoint = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $ip       = $_SERVER['REMOTE_ADDR'] ?? '';

    // pastikan payload dalam bentuk JSON string
    $payloadJson = is_array($payload) ? json_encode($payload) : $payload;

    $stmt = $conn->prepare("
        INSERT INTO api_request_logs 
        (client_id, ip_address, endpoint, method, action, http_status, payload, error_details, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");

    $stmt->bind_param(
        "ssssisss",
        $client_id,
        $ip,
        $endpoint,
        $method,
        $action,
        $http_status,
        $payloadJson,
        $error_details
    );

    $stmt->execute();
    $stmt->close();
}
