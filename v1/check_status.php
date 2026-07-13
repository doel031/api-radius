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
$username_input  = isset($input['username']) ? trim($input['username']) : '';
$usernames_input = isset($input['usernames']) ? $input['usernames'] : [];

// Fungsi bantu untuk memetakan nama grup RADIUS ke status administratif GSMNET
function mapAccountStatus($groupname) {
    if ($groupname === 'ISOLIREBILLING') return 'isolir';
    if ($groupname === 'OFF') return 'off';
    return 'aktif';
}

// =========================================================================
// MODE 1: DEFAULT VIEW (Scan Otomatis Semua Grup & Paket Terdaftar)
// =========================================================================
if (empty($username_input) && empty($usernames_input)) {
    
    // a. Hitung total user yang sedang ONLINE real-time
    $total_online = 0;
    $res_online = $conn->query("SELECT COUNT(DISTINCT username) as online FROM radacct WHERE acctstoptime IS NULL");
    if ($res_online) { $total_online = intval($res_online->fetch_assoc()['online']); }

    // b. Hitung total seluruh akun yang terdaftar di sistem radusergroup
    $total_accounts = 0;
    $res_total = $conn->query("SELECT COUNT(DISTINCT username) as total FROM radusergroup");
    if ($res_total) { $total_accounts = intval($res_total->fetch_assoc()['total']); }

    // c. Kalkulasi total user yang OFFLINE (Total Akun - Yang Sedang Online)
    $total_offline = max(0, $total_accounts - $total_online);

    // d. SCANNING DINAMIS: Ambil semua groupname yang ada di tabel radusergroup
    $grup_details = [];
    $res_grup = $conn->query("SELECT groupname, COUNT(username) as jumlah FROM radusergroup GROUP BY groupname ORDER BY jumlah DESC");
    
    if ($res_grup) {
        while ($row = $res_grup->fetch_assoc()) {
            $grup_name = $row['groupname'];
            
            // Pemetaan (Mapping) khusus untuk grup sistem agar rapi di JSON Billing
            if ($grup_name === 'ISOLIREBILLING') {
                $grup_name = 'isolir';
            } elseif ($grup_name === 'OFF') {
                $grup_name = 'off';
            }
            
            // Otomatis memasukkan grup apa pun (termasuk jika ada grup tambahan baru)
            $grup_details[$grup_name] = intval($row['jumlah']);
        }
    }

    writeAPILog($conn, 'check_status.php', 200, 'completed', 'Dinamis scanning semua paket dan grup RADIUS');
    
    echo json_encode([
        "status" => "success",
        "mode" => "default_summary",
        "summary" => [
            "total_pelanggan_terdaftar" => $total_accounts,
            "total_online_sekarang"     => $total_online,
            "total_offline_sekarang"    => $total_offline
        ],
        "detail_per_paket" => $grup_details
    ]);
    
    $conn->close();
    exit();
}

// =========================================================================
// MODE 2: SINGLE USER CHECK
// =========================================================================
if (!empty($username_input)) {
    $username = $conn->real_escape_string($username_input);
    
    $res_profile = $conn->query("SELECT groupname FROM radusergroup WHERE username = '$username' LIMIT 1");
    if (!$res_profile || $res_profile->num_rows === 0) {
        http_response_code(404);
        writeAPILog($conn, 'check_status.php', 404, 'error', 'User tidak terdaftar.');
        echo json_encode(["status" => "error", "message" => "Username tidak terdaftar di database RADIUS."]);
        exit();
    }
    
    $groupname = $res_profile->fetch_assoc()['groupname'];
    $account_status = mapAccountStatus($groupname);

    $res_session = $conn->query("SELECT nasipaddress, acctstarttime, framedipaddress, callingstationid FROM radacct WHERE username = '$username' AND acctstoptime IS NULL ORDER BY radacctid DESC LIMIT 1");
    
    $is_online = false;
    $session_details = null;

    if ($res_session && $res_session->num_rows > 0) {
        $is_online = true;
        $session = $res_session->fetch_assoc();
        $session_details = [
            "nas_ip" => $session['nasipaddress'],
            "ip_pelanggan" => $session['framedipaddress'],
            "mac_address" => $session['callingstationid'],
            "login_time" => $session['acctstarttime'],
            "online_duration_seconds" => (time() - strtotime($session['acctstarttime']))
        ];
    }

    writeAPILog($conn, 'check_status.php', 200, 'completed', 'Fetch single user status');
    
    echo json_encode([
        "status" => "success",
        "mode" => "single_user",
        "username" => $username,
        "current_group" => $groupname,
        "account_status" => $account_status,
        "network_status" => $is_online ? "online" : "offline",
        "session_details" => $session_details
    ]);
    $conn->close();
    exit();
}

// =========================================================================
// MODE 3: BULK USER CHECK
// =========================================================================
if (!empty($usernames_input) && is_array($usernames_input)) {
    $results = [];
    
    foreach ($usernames_input as $raw_user) {
        $username = $conn->real_escape_string(trim($raw_user));
        if (empty($username)) continue;

        $res_p = $conn->query("SELECT groupname FROM radusergroup WHERE username = '$username' LIMIT 1");
        if (!$res_p || $res_p->num_rows === 0) {
            $results[$username] = ["registered" => false, "account_status" => "not_found", "network_status" => "offline"];
            continue;
        }
        
        $groupname = $res_p->fetch_assoc()['groupname'];
        $res_s = $conn->query("SELECT radacctid FROM radacct WHERE username = '$username' AND acctstoptime IS NULL LIMIT 1");
        $online_status = ($res_s && $res_s->num_rows > 0) ? "online" : "offline";

        $results[$username] = [
            "registered" => true,
            "current_group" => $groupname,
            "account_status" => mapAccountStatus($groupname),
            "network_status" => $online_status
        ];
    }

    writeAPILog($conn, 'check_status.php', 200, 'completed', 'Fetch bulk users status');
    
    echo json_encode([
        "status" => "success",
        "mode" => "bulk_user",
        "results" => $results
    ]);
    $conn->close();
    exit();
}
