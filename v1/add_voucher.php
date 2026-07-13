<?php
header("Content-Type: application/json");

// 1. Load Konfigurasi & Validator HMAC Terpusat
include_once(dirname(__FILE__) . "/../config.php");
include_once(dirname(__FILE__) . "/../hmac_validator.php");

$conn = getDBConnection();

// 2. Eksekusi Validasi Keamanan HMAC
validateHMACRequest($conn);

// 3. Ambil Input JSON
$input = json_decode(file_get_contents("php://input"), true);

$username_input   = isset($input['username']) ? trim($input['username']) : '';
$password_input   = isset($input['password']) ? trim($input['password']) : '';
$profile_mikrotik = isset($input['profile']) ? trim($input['profile']) : ''; // Contoh: Paket_Hotspot_3Mbps
$validity_type    = isset($input['validity_type']) ? trim($input['validity_type']) : 'countdown'; // 'countdown' atau 'quota_uptime'
$validity_value   = isset($input['validity_value']) ? trim($input['validity_value']) : ''; // Contoh: "3d" (3 hari), "7d" (1 minggu), "1h" (1 jam)
$login_time_range = isset($input['login_time_range']) ? trim($input['login_time_range']) : ''; 

// Validasi Kelengkapan Parameter Obligatory
if (empty($username_input) || empty($password_input) || empty($profile_mikrotik) || empty($validity_value)) {
    http_response_code(400);
    writeAPILog($conn, 'add_voucher.php', 400, 'error', 'Parameter data voucher tidak lengkap.');
    echo json_encode(["status" => "error", "message" => "Data input tidak lengkap. 'username', 'password', 'profile', dan 'validity_value' wajib diisi."]);
    exit();
}

$username = $conn->real_escape_string($username_input);
$password = $conn->real_escape_string($password_input);
$profile  = $conn->real_escape_string($profile_mikrotik);

// Konversi format validity_value (e.g., "3d", "7d", "1h", "30m") ke satuan DETIK
$total_seconds = 0;
$unit = substr($validity_value, -1);
$numeric_value = intval(substr($validity_value, 0, -1));

switch ($unit) {
    case 'd': $total_seconds = $numeric_value * 86400; break; // Hari ke detik
    case 'h': $total_seconds = $numeric_value * 3600; break;  // Jam ke detik
    case 'm': $total_seconds = $numeric_value * 60; break;    // Menit ke detik
    default:
        // Jika hanya angka saja, anggap itu menit (fallback)
        $total_seconds = intval($validity_value) * 60;
        $validity_value = $validity_value . "m";
        break;
}

if ($total_seconds <= 0) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Format 'validity_value' tidak valid. Gunakan contoh: '3d' untuk 3 hari atau '7d' untuk 1 minggu."]);
    exit();
}

$creationdate = date('Y-m-d H:i:s');
$conn->begin_transaction();

try {
    // a. Cek duplikasi voucher
    $check_user = $conn->query("SELECT id FROM radcheck WHERE username = '$username' LIMIT 1");
    if ($check_user && $check_user->num_rows > 0) { throw new Exception("Kode voucher / Username sudah terdaftar."); }

    // b. Simpan Kredensial Utama
    $conn->query("INSERT INTO radcheck (username, attribute, op, value) VALUES ('$username', 'Cleartext-Password', ':=', '$password')");
    
    // c. Atribut Proteksi Multi-Login Device
    $conn->query("INSERT INTO radcheck (username, attribute, op, value) VALUES ('$username', 'Simultaneous-Use', ':=', '1')");

    // d. PENENTUAN LOGIKA MASA AKTIF
    if ($validity_type === 'countdown') {
        // MASA AKTIF BERJALAN SEJAK FIRST LOGIN (Gunakan Access-Period)
        // Atribut ini otomatis mencatat waktu login pertama dan menghitung mundur waktu kedaluwarsa fisik voucher
        $conn->query("INSERT INTO radcheck (username, attribute, op, value) VALUES ('$username', 'Access-Period', ':=', '$total_seconds')");
    } else {
        // MASA AKTIF MURNI UPTIME KONEKSI (Gunakan Max-All-Session lama)
        $conn->query("INSERT INTO radcheck (username, attribute, op, value) VALUES ('$username', 'Max-All-Session', ':=', '$total_seconds')");
    }

    // e. Kondisional: Rentang jam operasional login tertentu (e.g., Al0600-1800)
    if (!empty($login_time_range)) {
        $safe_range = $conn->real_escape_string($login_time_range);
        $conn->query("INSERT INTO radcheck (username, attribute, op, value) VALUES ('$username', 'Login-Time', '==', '$safe_range')");
    }

    // f. Relasikan Voucher ke Profil/Grup MikroTik Hotspot
    $conn->query("INSERT INTO radusergroup (username, groupname, priority) VALUES ('$username', '$profile', 1)");

    // g. Tambahkan log tabel user pelengkap RADIUS
    $conn->query("INSERT INTO userbillinfo (username, creditcardtype, changeuserbillinfo, lead, creationdate, creationby) VALUES ('$username', 'Other', 0, 'Hotspot_Voucher', '$creationdate', 'admin_api')");
    $billinfo_id = $conn->insert_id;
    $conn->query("INSERT INTO userinfo (id, username, changeuserinfo, creationdate, creationby) VALUES ($billinfo_id, '$username', 0, '$creationdate', 'admin_api')");

    $conn->commit();
    writeAPILog($conn, 'add_voucher.php', 200, 'completed', "Voucher $username ($validity_value) dengan mode $validity_type berhasil dibuat");

    echo json_encode([
        "status" => "success",
        "message" => "Voucher Hotspot berhasil dibuat.",
        "details" => [
            "username" => $username,
            "profile_group" => $profile,
            "validity_mode" => $validity_type,
            "duration_string" => $validity_value,
            "total_seconds" => $total_seconds,
            "login_time_restriction" => !empty($login_time_range) ? $login_time_range : "24 Jam"
        ]
    ]);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(409);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}

$conn->close();
?>
