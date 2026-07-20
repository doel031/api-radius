<?php

/**
 * API Endpoint: Group Management (CRUD)
 * Features:
 * - Limit Kuota Download & Upload (Mikrotik-Xmit-Limit & Mikrotik-Recv-Limit)
 * - Limit Waktu / Masa Aktif (Max-All-Session)
 * - Device Profile (Wajib untuk tipe Reguler)
 */

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
$action = isset($input['action']) ? $input['action'] : null;
$group_name = isset($input['group_name']) ? $input['group_name'] : null;
$device_profile = isset($input['device_profile']) ? $input['device_profile'] : null;
$time_limit = isset($input['time_limit']) ? floatval($input['time_limit']) : 0;
$quota_download = isset($input['quota_download']) ? floatval($input['quota_download']) : 0;
$quota_upload = isset($input['quota_upload']) ? floatval($input['quota_upload']) : 0;

// DEBUG: catat raw input yang diterima (hanya aktif kalau API_DEBUG_MODE = true)
writeAPILog($conn, '/api/v1/group.php', 0, 'DEBUG', "Incoming request method=$method action=$action", $input);

// 1. Konfigurasi method + parameter per action
$action_configs = [
    'add' => [
        'method'   => 'POST',
        'required' => ['action', 'group_name', 'device_profile'],
        'optional' => ['time_limit', 'quota_download', 'quota_upload'],
    ],
    'get'   => [
        'method'   => 'GET',
        'required' => ['action'],
        'optional' => ['group_name'],
    ],
    'update' => [
        'method'   => 'PUT',
        'required' => ['action', 'group_name'],
        'optional' => ['time_limit', 'quota_download', 'quota_upload', 'device_profile'],
    ],
    'delete' => [
        'method'   => 'DELETE',
        'required' => ['action', 'group_name'],
        'optional' => [],
    ],
];

$actual_params  = array_keys($input);
$request_method = $_SERVER['REQUEST_METHOD'];

// 2. Validasi 'action' wajib ada & harus dikenali
if (!isset($action) || !array_key_exists($action, $action_configs)) {
    http_response_code(400);
    $err_msg = "Parameter 'action' wajib ada dan harus salah satu dari: " . implode(', ', array_keys($action_configs)) . ".";
    writeAPILog($conn, '/api/v1/group.php', 400, 'error', $err_msg);
    echo json_encode(["status" => "error", "error_code" => "INVALID_ACTION", "message" => $err_msg]);
    exit();
}

$current_action  = $action;
$config          = $action_configs[$current_action];
$allowed_method  = $config['method'];
$required_params = $config['required'];
$optional_params = $config['optional'];
$allowed_params  = array_merge($required_params, $optional_params);

// 3. Validasi HTTP method sesuai action
if ($request_method !== $allowed_method) {
    http_response_code(405); // Method Not Allowed
    $err_msg = "Action '$current_action' hanya boleh diakses dengan method $allowed_method, bukan $request_method.";
    writeAPILog($conn, '/api/v1/group.php', 405, 'error', $err_msg);
    echo json_encode(["status" => "error", "error_code" => "METHOD_NOT_ALLOWED", "message" => $err_msg]);
    exit();
}

// 4. Tolak parameter yang tidak terdaftar untuk action ini
foreach ($actual_params as $param) {
    if (!in_array($param, $allowed_params, true)) {
        http_response_code(400);
        $err_msg = "Parameter '$param' tidak diizinkan untuk action '$current_action'. Hanya " . implode(', ', $allowed_params) . " yang diperbolehkan.";
        writeAPILog($conn, '/api/v1/group.php', 400, 'error', $err_msg);
        echo json_encode(["status" => "error", "error_code" => "INVALID_PARAMETER", "message" => $err_msg]);
        exit();
    }
}

// 5. Tolak jika ada parameter WAJIB yang tidak dikirim
$missing_params = array_diff($required_params, $actual_params);
if (!empty($missing_params)) {
    http_response_code(400);
    $err_msg = "Parameter wajib untuk action '$current_action' tidak ditemukan: " . implode(', ', $missing_params) . ".";
    writeAPILog($conn, '/api/v1/group.php', 400, 'error', $err_msg);
    echo json_encode(["status" => "error", "error_code" => "MISSING_REQUIRED_PARAMETER", "message" => $err_msg]);
    exit();
}


switch ($method) {
    case 'POST':

        $conn->begin_transaction();
        try {
            // 1. Atribut Identitas: Profil Device (Mikrotik-Group)
            $final_profile = $device_profile ? $device_profile : $group_name;
            $stmt1 = $conn->prepare("INSERT INTO radgroupreply (groupname, attribute, op, value) VALUES (?, 'Mikrotik-Group', '=', ?)");
            $stmt1->bind_param("ss", $group_name, $final_profile);
            $stmt1->execute();

            // 2. Limitasi Waktu (Max-All-Session)
            if ($time_limit > 0) {
                $stmt2 = $conn->prepare("INSERT INTO radgroupreply (groupname, attribute, op, value) VALUES (?, 'Max-All-Session', ':=', ?)");
                $stmt2->bind_param("ss", $group_name, $time_limit);
                $stmt2->execute();
            }

            // 3. Limitasi Kuota Download (Xmit) - Konversi MB ke Bytes
            if ($quota_download > 0) {
                $bytes_download = $quota_download * 1048576;
                $stmt3 = $conn->prepare("INSERT INTO radgroupreply (groupname, attribute, op, value) VALUES (?, 'Mikrotik-Xmit-Limit', ':=', ?)");
                $stmt3->bind_param("ss", $group_name, $bytes_download);
                $stmt3->execute();
            }

            // 4. Limitasi Kuota Upload (Recv) - Konversi MB ke Bytes
            if ($quota_upload > 0) {
                $bytes_upload = $quota_upload * 1048576;
                $stmt4 = $conn->prepare("INSERT INTO radgroupreply (groupname, attribute, op, value) VALUES (?, 'Mikrotik-Recv-Limit', ':=', ?)");
                $stmt4->bind_param("ss", $group_name, $bytes_upload);
                $stmt4->execute();
            }

            // Commit transaksi jika semua sukses
            $conn->commit();

            writeAPILog($conn, '/api/v1/group.php', 201, 'success', "Created group: $group_name");
            echo json_encode(["status" => "success", "message" => "Group $group_name berhasil dibuat."]);
        } catch (Exception $e) {
            // Rollback jika ada error
            $conn->rollback();
            http_response_code(500);
            writeAPILog($conn, '/api/v1/group.php', 500, 'error', "Gagal membuat group: " . $e->getMessage());
            echo json_encode(["status" => "error", "error_code" => "CREATE_FAILED", "message" => "Gagal membuat group: " . $e->getMessage()]);
        }
        exit();

    case 'GET':
        // Menyusun base SQL query
        $sql = "SELECT 
                        groupname as group_name,
                        MAX(CASE WHEN attribute = 'Mikrotik-Group' THEN value END) AS device_profile,
                        MAX(CASE WHEN attribute = 'Max-All-Session' THEN value ELSE 0 END) AS time_limit,
                        MAX(CASE WHEN attribute = 'Mikrotik-Xmit-Limit' THEN ROUND(value / 1048576, 2) ELSE 0 END) AS quota_download,
                        MAX(CASE WHEN attribute = 'Mikrotik-Recv-Limit' THEN ROUND(value / 1048576, 2) ELSE 0 END) AS quota_upload
                    FROM radgroupreply";

        // Jika group_name spesifik diminta, tambahkan filter WHERE
        if (!empty($group_name)) {
            $sql .= " WHERE groupname = '$group_name'";
        }

        // Gabungkan GROUP BY di akhir konstruksi query string
        $sql .= " GROUP BY groupname";

        // Eksekusi query CUKUP SATU KALI di sini
        $res = $conn->query($sql);

        // VALIDASI: Jika group_name diminta tapi tidak ada hasil di database
        if (!empty($group_name) && $res->num_rows === 0) {
            http_response_code(404);
            $error_code = "GROUP_NOT_FOUND";
            $err_msg = "Data group dengan nama '$group_name' tidak ditemukan.";

            writeAPILog($conn, '/api/v1/group.php', 404, 'error', $err_msg);
            echo json_encode([
                "status" => "error",
                "error_code" => $error_code,
                "message" => $err_msg
            ]);
            exit();
        }

        // Jika ditemukan atau jika mengambil semua data (get all)
        $total_groups = $res->num_rows;

        $data = [];
        while ($row = $res->fetch_assoc()) {

            // --- LOGIKA PENENTUAN TYPE VOUCHER / REGULER (PERBAIKAN) ---
            // Kita cek spesifik: apakah nilainya ada, bukan string kosong, dan bukan "0" atau "00:00:00"
            $has_time_limit  = isset($row['time_limit']) && $row['time_limit'] !== '' && $row['time_limit'] !== '0' && $row['time_limit'] !== '00:00:00';
            $has_quota_download  = isset($row['quota_download']) && floatval($row['quota_download']) > 0;
            $has_quota_upload    = isset($row['quota_upload']) && floatval($row['quota_upload']) > 0;

            $has_limit = $has_time_limit || $has_quota_download || $has_quota_upload;

            if (!empty($row['device_profile']) && $has_limit) {
                $row['type'] = 'voucher';
            } else {
                $row['type'] = 'reguler';
            }
            // -----------------------------------------------

            $data[] = $row;
        }

        if (!empty($group_name)) {
            echo json_encode([
                "status" => "success",
                "data" => $data[0] // Hanya satu data yang dikembalikan
                ]);
            writeAPILog($conn, '/api/v1/group.php', 200, 'completed', "Action: get " . $group_name);
        } else {
            echo json_encode([
                "status" => "success",
                "total_groups" => $total_groups,
                "data" => $data
                ]);
            writeAPILog($conn, '/api/v1/group.php', 200, 'completed', "Action: get all");
        }
        exit();

    case 'PUT':
        // Mulai Database Transaction agar jika salah satu gagal, data tidak rusak/patah
        $conn->begin_transaction();

        try {
            // Langkah 1: Hapus semua atribut lama untuk grup ini
            $delete_sql = "DELETE FROM radgroupreply WHERE groupname = '$group_name'";
            if (!$conn->query($delete_sql)) {
                throw new Exception("Gagal menghapus data lama: " . $conn->error);
            }

            // Kumpulkan data yang akan di-insert
            $inserts = [];

            // Atribut wajib untuk semua tipe (Mikrotik-Group)
            $inserts[] = "('$group_name', 'Mikrotik-Group', ':=', '$device_profile')";

            // Batasan Waktu (Max-All-Session)
            if ($time_limit > 0) {
                $inserts[] = "('$group_name', 'Max-All-Session', ':=', '$time_limit')";
            }

            // Batasan Kuota Download (Mikrotik-Xmit-Limit) -> Konversi kembali dari MB ke Bytes
            if ($quota_download > 0) {
                $bytes_download = round($quota_download * 1048576);
                $inserts[] = "('$group_name', 'Mikrotik-Xmit-Limit', ':=', '$bytes_download')";
            }

            // Batasan Kuota Upload (Mikrotik-Recv-Limit) -> Konversi kembali dari MB ke Bytes
            if ($quota_upload > 0) {
                $bytes_upload = round($quota_upload * 1048576);
                $inserts[] = "('$group_name', 'Mikrotik-Recv-Limit', ':=', '$bytes_upload')";
            }

            // Langkah 3: Eksekusi Batch Insert data baru
            if (!empty($inserts)) {
                $insert_sql = "INSERT INTO radgroupreply (groupname, attribute, op, value) VALUES " . implode(', ', $inserts);
                if (!$conn->query($insert_sql)) {
                    throw new Exception("Gagal memperbarui atribut data: " . $conn->error);
                }
            }

            // Jika semua proses sukses, commit transaksi data
            $conn->commit();

            writeAPILog($conn, '/api/v1/group.php', 201, 'completed', "Action: update group '$group_name' as $type");
            echo json_encode([
                "status" => "success",
                "message" => "Data group '$group_name' berhasil diperbarui sebagai $type."
            ]);
            exit();

        } catch (Exception $e) {
            // Jika ada error di tengah jalan, batalkan semua perubahan (rollback)
            $conn->rollback();

            http_response_code(500);
            $err_msg = "Gagal mengupdate data: " . $e->getMessage();
            writeAPILog($conn, '/api/v1/group.php', 500, 'error', $err_msg);
            echo json_encode(["status" => "error", "error_code" => "UPDATE_FAILED", "message" => $err_msg]);
            exit();
        }
        break;

    case 'DELETE':

        $stmt = $conn->prepare("DELETE FROM radgroupreply WHERE groupname = ?");
        $stmt->bind_param("s", $group_name);
        $stmt->execute();

        if ($stmt->affected_rows === 0) {
            http_response_code(400);
            $err_msg = "Gagal hapus. Group tidak ditemukan.";
            writeAPILog($conn, '/api/v1/group.php', 400, 'error', $err_msg);
            echo json_encode(["status" => "error", "error_code" => "DELETE_FAILED", "message" => $err_msg]);
            exit();
        }

        writeAPILog($conn, '/api/v1/group.php', 200, 'success', "Deleted: $group_name");
        echo json_encode(["status" => "success", "message" => "Group $group_name berhasil dihapus."]);
        break;

    default:
        http_response_code(405);
        echo json_encode(["status" => "error", "error_code" => "METHOD_NOT_ALLOWED", "message" => "Method Not Allowed."]);
        break;
}
