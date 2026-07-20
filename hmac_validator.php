<?php

include_once(dirname(__FILE__) . "/config.php");

function validateHMACRequest($conn)
{
    $headers = getallheaders();

    $client_id = isset($headers['X-GSMNET-ClientID']) ? trim($headers['X-GSMNET-ClientID']) : 'UNKNOWN';
    $signature = isset($headers['X-GSMNET-Signature']) ? trim($headers['X-GSMNET-Signature']) : '';
    $timestamp = isset($headers['X-GSMNET-Timestamp']) ? intval($headers['X-GSMNET-Timestamp']) : 0;

    $client_ip = $_SERVER['REMOTE_ADDR'];
    if (isset($headers['X-Forwarded-For'])) {
        $client_ip = trim(explode(',', $headers['X-Forwarded-For'])[0]);
    }

    $current_script = basename($_SERVER['SCRIPT_NAME']);
    $safe_client_id = $conn->real_escape_string($client_id);
    $safe_ip = $conn->real_escape_string($client_ip);
    $now = date('Y-m-d H:i:s');
    $server_time = time();

    // 1. AUTO-RESET STATUS (Reset jika sudah lewat 1 hari dari percobaan terakhir)
    $conn->query("UPDATE api_failed_attempts 
                  SET attempts = 0, tier = 1, total_banned_count = 0, blocked_until = NULL 
                  WHERE client_id = '$safe_client_id' AND ip_address = '$safe_ip' 
                  AND NOW() > DATE_ADD(last_attempt, INTERVAL 1 DAY)");

    // 2. CEK STATUS BLOKIR (HTTP 423)
    $check_block = $conn->query("SELECT blocked_until FROM api_failed_attempts WHERE client_id = '$safe_client_id' AND ip_address = '$safe_ip' LIMIT 1");
    if ($check_block && $check_block->num_rows > 0) {
        $block_row = $check_block->fetch_assoc();
        if ($block_row['blocked_until'] !== null && $now < $block_row['blocked_until']) {
            http_response_code(423);
            $sisa_detik = strtotime($block_row['blocked_until']) - $server_time;
            $err_msg = "ACCESS BLOCKED! Terbanned hingga " . $block_row['blocked_until'] . " (Sisa " . ceil($sisa_detik / 60) . " menit).";

            writeAPILog($conn, $current_script, 423, 'error', $err_msg);
            echo json_encode([
                "status" => "error",
                "error_code" => "ACCESS_BLOCKED",
                "message" => $err_msg
            ]);
            exit();
        }
    }

    // 3. VALIDASI HEADER & TIMESTAMP (HTTP 401)
    if (empty($client_id) || empty($signature) || empty($timestamp)) {
        http_response_code(401);
        handleProgressiveFailed($conn, $safe_client_id, $safe_ip);
        writeAPILog($conn, $current_script, 401, 'error', 'Security headers missing.');
        echo json_encode([
            "status" => "error",
            "error_code" => "INCOMPLETE_HEADERS",
            "message" => "Otentikasi gagal. Header keamanan tidak lengkap."
        ]);
        exit();
    }

    if (abs($server_time - $timestamp) > HMAC_TIME_WINDOW) {
        http_response_code(401);
        handleProgressiveFailed($conn, $safe_client_id, $safe_ip);
        $err_msg = "Request kadaluarsa. Selisih waktu server & client > " . HMAC_TIME_WINDOW . "s. [Server Time: $server_time]";

        writeAPILog($conn, $current_script, 401, 'error', 'Timestamp Expired.');
        echo json_encode([
            "status" => "error",
            "error_code" => "TIMESTAMP_EXPIRED",
            "message" => $err_msg
        ]);
        exit();
    }

    // 4. VALIDASI IP WHITELIST (HTTP 403)
    $sql = "SELECT api_key FROM api_keys WHERE client_name = '$safe_client_id' AND allowed_ip = '$safe_ip' LIMIT 1";
    $res = $conn->query($sql);

    if (!$res || $res->num_rows === 0) {
        http_response_code(403);
        handleProgressiveFailed($conn, $safe_client_id, $safe_ip);
        $err_msg = "Unauthorized client ID atau IP ($client_ip) belum terdaftar.";

        writeAPILog($conn, $current_script, 403, 'error', $err_msg);
        echo json_encode([
            "status" => "error",
            "error_code" => "UNAUTHORIZED_IP",
            "message" => $err_msg
        ]);
        exit();
    }

    $row = $res->fetch_assoc();
    $secret_key = $row['api_key'];

    // 5. VALIDASI SIGNATURE HMAC SHA256 (HTTP 401)
    $raw_payload = file_get_contents("php://input");
    $data_to_hash = $raw_payload . $timestamp;
    $server_signature = hash_hmac('sha256', $data_to_hash, $secret_key);

    if (!hash_equals($server_signature, $signature)) {
        http_response_code(401);
        handleProgressiveFailed($conn, $safe_client_id, $safe_ip);
        writeAPILog($conn, $current_script, 401, 'error', 'Invalid HMAC Signature.');
        echo json_encode([
            "status" => "error",
            "error_code" => "INVALID_SIGNATURE",
            "message" => "Tanda tangan (signature) tidak valid. Pastikan Secret Key sesuai."
        ]);
        exit();
    }

    // Reset percobaan gagal jika sukses
    $conn->query("UPDATE api_failed_attempts SET attempts = 0 WHERE client_id = '$safe_client_id' AND ip_address = '$safe_ip'");

    return true;
}

function handleProgressiveFailed($conn, $client_id, $ip_address)
{
    $max_attempts = 5; // Batas gagal sebelum ganti status ke banned/tier up
    $now = date('Y-m-d H:i:s');

    // 1. Ambil status terakhir client/IP ini
    $query = "SELECT attempts, tier, total_banned_count FROM api_failed_attempts WHERE client_id = '$client_id' AND ip_address = '$ip_address' LIMIT 1";
    $result = $conn->query($query);

    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $new_attempts = $row['attempts'] + 1;
        $current_tier = intval($row['tier']);
        $total_banned = intval($row['total_banned_count']);

        if ($new_attempts >= $max_attempts) {
            // Naikkan hitungan total terbanned
            $total_banned++;

            // Penentuan durasi blokir berdasarkan tingkatan Tier saat ini
            switch ($current_tier) {
                case 1:
                    $block_duration = 5; // Tier 1: 5 Menit
                    $next_tier = 2;
                    break;
                case 2:
                    $block_duration = 30; // Tier 2: 30 Menit
                    $next_tier = 3;
                    break;
                case 3:
                default:
                    $block_duration = 120; // Tier 3+: 2 Jam (120 Menit)
                    $next_tier = 3; // Tetap di tier maksimal
                    break;
            }

            $blocked_until = date('Y-m-d H:i:s', strtotime("+$block_duration minutes"));

            // Eksekusi Banned & Tier Up
            $conn->query("UPDATE api_failed_attempts 
                          SET attempts = $new_attempts, 
                              tier = $next_tier, 
                              total_banned_count = $total_banned, 
                              blocked_until = '$blocked_until', 
                              last_attempt = '$now' 
                          WHERE client_id = '$client_id' AND ip_address = '$ip_address'");
        } else {
            // Gagal biasa, hanya naikkan attempts & update waktu hit terakhir
            $conn->query("UPDATE api_failed_attempts 
                          SET attempts = $new_attempts, 
                              last_attempt = '$now' 
                          WHERE client_id = '$client_id' AND ip_address = '$ip_address'");
        }
    } else {
        // Record baru jika identitas Client/IP ini belum pernah gagal sama sekali
        $conn->query("INSERT INTO api_failed_attempts (client_id, ip_address, attempts, tier, total_banned_count, blocked_until, last_attempt) 
                      VALUES ('$client_id', '$ip_address', 1, 1, 0, NULL, '$now')");
    }
}
