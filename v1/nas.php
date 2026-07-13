<?php
/**
 * API Endpoint: Manajemen NAS (Network Access Server)
 * Lokasi: api/v1/nas.php
 * Fitur: CRUD + Auto Restart FreeRADIUS
 */

header("Content-Type: application/json");

// 1. Load Konfigurasi & Validator HMAC Terpusat
include_once(dirname(__FILE__) . "/../config.php");
include_once(dirname(__FILE__) . "/../hmac_validator.php");

$conn = getDBConnection();

// 2. Eksekusi Validasi Keamanan HMAC
validateHMACRequest($conn);

// 3. Ambil Input JSON
$input = json_decode(file_get_contents("php://input"), true);
$action = isset($input['action']) ? strtolower($input['action']) : '';

if (empty($action)) {
    http_response_code(400);
    $err_msg = "Parameter 'action' wajib diisi (add, get, update, delete).";
    writeAPILog($conn, 'nas.php', 400, 'error', $err_msg);
    echo json_encode(["status" => "error", "error_code" => "MISSING_ACTION", "message" => $err_msg]);
    exit();
}

$restart_required = false;

try {
    switch ($method) {
        case 'POST':
            if ($action !== 'add') {
                http_response_code(400);
                throw new Exception("Action '$action' tidak valid untuk metode POST. Gunakan 'add'.");
            }

            $nasname = $conn->real_escape_string($input['nasname'] ?? '');
            $shortname = $conn->real_escape_string($input['shortname'] ?? '');
            $type = $conn->real_escape_string($input['type'] ?? 'other');
            $secret = $conn->real_escape_string($input['secret'] ?? '');
            $ports = isset($input['ports']) ? intval($input['ports']) : NULL;
            $community = $conn->real_escape_string($input['community'] ?? '');
            $server = $conn->real_escape_string($input['server'] ?? '');

            if (empty($nasname) || empty($secret)) {
                $error_code = "INCOMPLETE_NAS_DATA";
                throw new Exception("Parameter 'nasname' dan 'secret' wajib diisi.");
            }

            // --- TAMBAHKAN FILTER CEK DUPLIKASI DISINI ---
            $check_duplicate = $conn->query("SELECT id FROM nas WHERE nasname = '$nasname' LIMIT 1");
            if ($check_duplicate && $check_duplicate->num_rows > 0) {
                $error_code = "DUPLICATE_NASNAME";
                throw new Exception("Gagal mendaftarkan perangkat. NAS dengan IP/Hostname '$nasname' sudah terdaftar dalam sistem.");
            }

            $sql = "INSERT INTO nas (nasname, shortname, type, secret, ports, community, server, description) 
                    VALUES ('$nasname', '$shortname', '$type', '$secret', " . ($ports ? $ports : "NULL") . ", 
                    " . (!empty($community) ? "'$community'" : "NULL") . ", 
                    " . (!empty($server) ? "'$server'" : "NULL") . ", 'Added via API')";
            
            if ($conn->query($sql)) {
                $response_msg = "NAS $nasname berhasil ditambahkan.";
                $restart_required = true;
            } else {
                $error_code = "DATABASE_INSERT_FAILED";
                throw new Exception($conn->error);
            }
            break;

        case 'GET':
            if ($action !== 'get') {
                http_response_code(400);
                throw new Exception("Action '$action' tidak valid untuk metode GET. Gunakan 'get'.");
            }

            // 1. Validasi Ketat Parameter Filter
            $allowed_params = ['action', 'nasname'];
            $actual_params = array_keys($input);
            
            // Periksa apakah ada parameter yang tidak terdaftar di whitelist
            foreach ($actual_params as $param) {
                if (!in_array($param, $allowed_params)) {
                    http_response_code(400); // Bad Request
                    $err_msg = "Parameter '$param' tidak diizinkan";
                    writeAPILog($conn, 'nas.php', 400, 'error', $err_msg);
                    echo json_encode(["status" => "error", "message" => $err_msg]);
                    exit();
                }
            }

            $nasname = $conn->real_escape_string($input['nasname'] ?? '');
            
            // Query dasar untuk mengambil data NAS
            $sql = "SELECT id, nasname, shortname, type, ports, community, server, secret, description FROM nas";
            
            // Jika nasname spesifik diminta, tambahkan filter WHERE
            if (!empty($nasname)) {
                $sql .= " WHERE nasname = '$nasname'";
            }

            $res = $conn->query($sql);
            
            // VALIDASI: Jika nasname diminta tapi tidak ada hasil di database
            if (!empty($nasname) && $res->num_rows === 0) {
                http_response_code(404);
                $error_code = "NAS_NOT_FOUND";
                $err_msg = "Data perangkat dengan IP/Hostname '$nasname' tidak ditemukan.";
                
                writeAPILog($conn, 'nas.php', 404, 'error', $err_msg);
                echo json_encode([
                    "status" => "error",
                    "error_code" => $error_code,
                    "message" => $err_msg
                ]);
                exit();
            }

            // Jika ditemukan atau jika mengambil semua data (get all)
            $data = [];
            while ($row = $res->fetch_assoc()) {
                $data[] = $row;
            }

            writeAPILog($conn, 'nas.php', 200, 'completed', "Action: get" . (!empty($nasname) ? " ($nasname)" : " all"));
            echo json_encode([
                "status" => "success", 
                "data" => $data
            ]);
            exit();

        case 'PUT':
            if ($action !== 'update') {
                http_response_code(400);
                throw new Exception("Action '$action' tidak valid untuk metode PUT. Gunakan 'update'.");
            }

            $id = isset($input['id']) ? intval($input['id']) : 0;
            
            if ($id <= 0) {
                $error_code = "INVALID_NAS_ID";
                throw new Exception("ID NAS tidak valid atau tidak disertakan dalam request.");
            }

            // 1. FILTER EKSISTENSI: Pastikan ID ada sebelum diupdate
            $check_id = $conn->query("SELECT nasname FROM nas WHERE id = $id LIMIT 1");
            if (!$check_id || $check_id->num_rows === 0) {
                $error_code = "NAS_NOT_FOUND";
                throw new Exception("Gagal memperbarui data. Perangkat dengan ID '$id' tidak ditemukan.");
            }
            $current_data = $check_id->fetch_assoc();

            $updates = [];
            
            // 2. FILTER DUPLIKASI NASNAME: Jika nasname diubah, cek apakah IP baru sudah dipakai ID lain
            if (isset($input['nasname']) && $input['nasname'] !== $current_data['nasname']) {
                $new_nasname = $conn->real_escape_string($input['nasname']);
                $check_dup = $conn->query("SELECT id FROM nas WHERE nasname = '$new_nasname' AND id != $id LIMIT 1");
                if ($check_dup && $check_dup->num_rows > 0) {
                    $error_code = "DUPLICATE_NASNAME";
                    throw new Exception("Gagal memperbarui. IP/Hostname '$new_nasname' sudah digunakan oleh perangkat lain.");
                }
                $updates[] = "nasname = '$new_nasname'";
            }

            // Parameter lainnya
            if (isset($input['nasname'])) $updates[] = "nasname = '" . $conn->real_escape_string($input['nasname']) . "'";
            if (isset($input['shortname'])) $updates[] = "shortname = '" . $conn->real_escape_string($input['shortname']) . "'";
            if (isset($input['secret'])) $updates[] = "secret = '" . $conn->real_escape_string($input['secret']) . "'";
            if (isset($input['ports'])) $updates[] = "ports = " . intval($input['ports']);
            if (isset($input['community'])) $updates[] = "community = '" . $conn->real_escape_string($input['community']) . "'";
            if (isset($input['server'])) $updates[] = "server = '" . $conn->real_escape_string($input['server']) . "'";

            if (empty($updates)) { $error_code = "NO_DATA_TO_UPDATE"; throw new Exception("Tidak ada data untuk diupdate."); }

            $sql = "UPDATE nas SET " . implode(', ', $updates) . " WHERE id = $id";
            if ($conn->query($sql)) {
                $response_msg = "Data NAS ID $id berhasil diperbarui.";
                $restart_required = true;
            } else { $error_code = "DATABASE_UPDATE_FAILED"; throw new Exception($conn->error); }
            break;

        case 'delete':
            if ($action !== 'delete') {
                http_response_code(400);
                throw new Exception("Action '$action' tidak valid untuk metode DELETE. Gunakan 'delete'.");
            }

            // 1. Validasi Ketat Parameter Filter
            $allowed_params = ['action', 'id'];
            $actual_params = array_keys($input);
            
            // Periksa apakah ada parameter yang tidak terdaftar di whitelist
            foreach ($actual_params as $param) {
                if (!in_array($param, $allowed_params)) {
                    http_response_code(400); // Bad Request
                    $err_msg = "Parameter '$param' tidak diizinkan";
                    writeAPILog($conn, 'nas.php', 400, 'error', $err_msg);
                    echo json_encode(["status" => "error", "message" => $err_msg]);
                    exit();
                }
            }

            $id = intval($input['id'] ?? 0);
            if ($id <= 0) { $error_code = "INVALID_NAS_ID"; throw new Exception("ID NAS wajib diisi."); }

            // 1. FILTER EKSISTENSI: Pastikan ID ada sebelum didelete
            $check_id = $conn->query("SELECT nasname FROM nas WHERE id = $id LIMIT 1");
            if (!$check_id || $check_id->num_rows === 0) {
                $error_code = "NAS_NOT_FOUND";
                throw new Exception("Gagal menghapus data. Perangkat dengan ID '$id' tidak ditemukan.");
            }
            $current_data = $check_id->fetch_assoc();

            $sql = "DELETE FROM nas WHERE id = $id";
            if ($conn->query($sql)) {
                $response_msg = "NAS berhasil dihapus.";
                $restart_required = true;
            } else { $error_code = "DATABASE_DELETE_FAILED"; throw new Exception($conn->error); }
            break;

        default:
            $error_code = "UNKNOWN_ACTION";
            throw new Exception("Action '$action' tidak dikenali.");
    }

    // =========================================================================
    // LOGIKA RESTART FREERADIUS (Hanya jika ada perubahan data)
    // =========================================================================
    $config_status = "Layanan tidak memerlukan restart.";
    if ($restart_required) {
        exec("sudo /usr/local/bin/restart_freeradius.sh 2>&1", $output, $returnCode);
        $config_status = ($returnCode === 0) ? "Konfigurasi sudah siap dipakai." : "Konfigurasi belum siap dipakai, silahkan apply konfigurasi manual.";
    }

    writeAPILog($conn, 'nas.php', 200, 'completed', "Action: $action | $config_status");
    echo json_encode([
        "status" => "success",
        "message" => $response_msg,
        "config_service" => $config_status
    ]);

} catch (Exception $e) {
    http_response_code(400);
    $final_err_code = isset($error_code) ? $error_code : "GENERAL_ERROR";
    writeAPILog($conn, 'nas.php', 400, 'error', $e->getMessage());
    echo json_encode(["status" => "error", "error_code" => $final_err_code, "message" => $e->getMessage()]);
}

$conn->close();
?>