<?php

/**
 * API Endpoint: Manajemen NAS (Network Access Server)
 * Lokasi: api/v1/nas.php
 * Fitur: CRUD + Auto Restart FreeRADIUS
 */

header("Content-Type: application/json");

include_once(dirname(__FILE__) . "/../config.php");
include_once(dirname(__FILE__) . "/../hmac_validator.php");

$conn = getDBConnection();

register_shutdown_function(function () use ($conn) {
    flushAPILog($conn);
    $conn->close();   // ditutup di sini, setelah flush selesai
});

validateHMACRequest($conn);

// FIX: definisikan $method dari request asli
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents("php://input"), true);
$action = isset($input['action']) ? strtolower($input['action']) : '';

// DEBUG: catat raw input yang diterima (hanya aktif kalau API_DEBUG_MODE = true)
writeAPILog($conn, '/api/v1/nas.php', 0, 'DEBUG', "Incoming request method=$method action=$action", $input);

if (empty($action)) {
    http_response_code(400);
    $err_msg = "Parameter 'action' wajib diisi (add, get, update, delete).";
    writeAPILog($conn, '/api/v1/nas.php', 400, 'WARNING', $err_msg);
    echo json_encode(["status" => "error", "error_code" => "MISSING_ACTION", "message" => $err_msg]);
    exit();
}

$restart_required = false;

try {
    switch ($method) {
        case 'POST':
            if ($action !== 'add') {
                http_response_code(400);
                $err_msg = "Action '$action' tidak valid untuk metode POST. Gunakan 'add'.";
                writeAPILog($conn, '/api/v1/nas.php', 400, 'WARNING', $err_msg);
                echo json_encode(["status" => "error", "error_code" => "INVALID_ACTION", "message" => $err_msg]);
                exit();
            }

            $nasname   = $conn->real_escape_string($input['nasname'] ?? '');
            $shortname = $conn->real_escape_string($input['shortname'] ?? '');
            $type      = $conn->real_escape_string($input['type'] ?? 'other');
            $secret    = $conn->real_escape_string($input['secret'] ?? '');
            $ports     = isset($input['ports']) ? intval($input['ports']) : null;
            $community = $conn->real_escape_string($input['community'] ?? '');
            $server    = $conn->real_escape_string($input['server'] ?? '');

            if (empty($nasname) || empty($secret)) {
                http_response_code(400);
                $err_msg = "Parameter 'nasname' dan 'secret' wajib diisi.";
                writeAPILog($conn, '/api/v1/nas.php', 400, 'WARNING', $err_msg);
                echo json_encode(["status" => "error", "error_code" => "INCOMPLETE_DATA", "message" => $err_msg]);
                exit();
            }

            writeAPILog($conn, '/api/v1/nas.php', 0, 'DEBUG', "Cek duplikasi nasname=$nasname sebelum insert");

            $check_duplicate = $conn->query("SELECT id FROM nas WHERE nasname = '$nasname' LIMIT 1");
            if ($check_duplicate && $check_duplicate->num_rows > 0) {
                http_response_code(400);
                $err_msg = "NAS dengan IP/Hostname '$nasname' sudah terdaftar dalam sistem.";
                writeAPILog($conn, '/api/v1/nas.php', 400, 'WARNING', $err_msg);
                echo json_encode(["status" => "error", "error_code" => "DUPLICATE_DATA", "message" => $err_msg]);
                exit();
            }

            $sql = "INSERT INTO nas (nasname, shortname, type, secret, ports, community, server, description) 
                    VALUES ('$nasname', '$shortname', '$type', '$secret', " . ($ports ? $ports : "NULL") . ", 
                    " . (!empty($community) ? "'$community'" : "NULL") . ", 
                    " . (!empty($server) ? "'$server'" : "NULL") . ", 'Added via API')";

            if ($conn->query($sql)) {
                $response_msg = "NAS $nasname berhasil ditambahkan.";
                $restart_required = true;
            } else {
                http_response_code(500);
                $err_msg = "Gagal menambahkan NAS. Silakan coba lagi nanti.";
                writeAPILog($conn, '/api/v1/nas.php', 500, 'ERROR', $err_msg);
                echo json_encode(["status" => "error", "error_code" => "DATABASE_INSERT_FAILED", "message" => $err_msg]);
                exit();
            }
            break;

        case 'GET':
            if ($action !== 'get') {
                http_response_code(400);
                $err_msg = "Action '$action' tidak valid untuk metode GET. Gunakan 'get'.";
                writeAPILog($conn, '/api/v1/nas.php', 400, 'WARNING', $err_msg);
                echo json_encode(["status" => "error", "error_code" => "INVALID_ACTION", "message" => $err_msg]);
                exit();
            }

            $allowed_params = ['action', 'nasname'];
            $actual_params  = array_keys($input);

            foreach ($actual_params as $param) {
                if (!in_array($param, $allowed_params)) {
                    http_response_code(400);
                    $err_msg = "Parameter '$param' tidak diizinkan";
                    writeAPILog($conn, '/api/v1/nas.php', 400, 'WARNING', $err_msg);
                    echo json_encode(["status" => "error", "error_code" => "INVALID_PARAMETER", "message" => $err_msg]);
                    exit();
                }
            }

            $nasname = $conn->real_escape_string($input['nasname'] ?? '');
            $sql = "SELECT nasname, shortname, type, ports, community, server, secret, description FROM nas";
            if (!empty($nasname)) {
                $sql .= " WHERE nasname = '$nasname'";
            }

            writeAPILog($conn, '/api/v1/nas.php', 0, 'DEBUG', "Query GET NAS", ['sql' => $sql]);

            $res = $conn->query($sql);
            $total_nas = $res->num_rows;

            if (!empty($nasname) && $res->num_rows === 0) {
                http_response_code(404);
                $err_msg = "Data perangkat dengan IP/Hostname '$nasname' tidak ditemukan.";
                writeAPILog($conn, '/api/v1/nas.php', 404, 'WARNING', $err_msg);
                echo json_encode(["status" => "error", "error_code" => "DATA_NOT_FOUND", "message" => $err_msg]);
                exit();
            }

            $data = [];
            while ($row = $res->fetch_assoc()) {
                $data[] = $row;
            }

            if (empty($nasname)) {
                writeAPILog($conn, '/api/v1/nas.php', 200, 'INFO', "Action: get all");
                echo json_encode(["status" => "success", "total_nas" => $total_nas, "data" => $data]);
            } else {
                writeAPILog($conn, '/api/v1/nas.php', 200, 'INFO', "Action: get" . $nasname);
                echo json_encode(["status" => "success", "data" => $data[0]]);
            }
            exit();

        case 'PUT':
            if ($action !== 'update') {
                http_response_code(400);
                $err_msg = "Action '$action' tidak valid untuk metode PUT. Gunakan 'update'.";
                writeAPILog($conn, '/api/v1/nas.php', 400, 'WARNING', $err_msg);
                echo json_encode(["status" => "error", "error_code" => "INVALID_ACTION", "message" => $err_msg]);
                exit();
            }

            $nasname = $input['nasname'] ?? '';
            if (empty($nasname)) {
                http_response_code(400);
                $err_msg = "Nama NAS tidak valid atau tidak disertakan dalam request.";
                writeAPILog($conn, '/api/v1/nas.php', 400, 'WARNING', $err_msg);
                echo json_encode(["status" => "error", "error_code" => "INVALID_NAS_NAME", "message" => $err_msg]);
                exit();
            }

            $check_nasname = $conn->query("SELECT nasname FROM nas WHERE nasname = '$nasname' LIMIT 1");
            if (!$check_nasname || $check_nasname->num_rows === 0) {
                http_response_code(404);
                $err_msg = "Data perangkat dengan Nama NAS '$nasname' tidak ditemukan.";
                writeAPILog($conn, '/api/v1/nas.php', 404, 'WARNING', $err_msg);
                echo json_encode(["status" => "error", "error_code" => "DATA_NOT_FOUND", "message" => $err_msg]);
                exit();
            }
            $current_data = $check_nasname->fetch_assoc();

            $updates = [];

            if (isset($input['nasname']) && $input['nasname'] !== $current_data['nasname']) {
                $new_nasname = $conn->real_escape_string($input['nasname']);
                $check_dup = $conn->query("SELECT nasname FROM nas WHERE nasname = '$new_nasname' AND nasname != '$nasname' LIMIT 1");
                if ($check_dup && $check_dup->num_rows > 0) {
                    http_response_code(404);
                    $err_msg = "Gagal memperbarui. Nama NAS '$new_nasname' sudah digunakan oleh perangkat lain.";
                    writeAPILog($conn, '/api/v1/nas.php', 404, 'WARNING', $err_msg);
                    echo json_encode(["status" => "error", "error_code" => "DUPLICATE_DATA", "message" => $err_msg]);
                    exit();
                }
                $updates[] = "nasname = '$new_nasname'";
            }

            if (isset($input['shortname'])) {
                $updates[] = "shortname = '" . $conn->real_escape_string($input['shortname']) . "'";
            }
            if (isset($input['secret'])) {
                $updates[] = "secret = '" . $conn->real_escape_string($input['secret']) . "'";
            }
            if (isset($input['ports'])) {
                $updates[] = "ports = " . intval($input['ports']);
            }
            if (isset($input['community'])) {
                $updates[] = "community = '" . $conn->real_escape_string($input['community']) . "'";
            }
            if (isset($input['server'])) {
                $updates[] = "server = '" . $conn->real_escape_string($input['server']) . "'";
            }

            if (empty($updates)) {
                http_response_code(400);
                $err_msg = "Tidak ada data untuk diupdate.";
                writeAPILog($conn, '/api/v1/nas.php', 400, 'WARNING', $err_msg);
                echo json_encode(["status" => "error", "error_code" => "NO_DATA_TO_UPDATE", "message" => $err_msg]);
                exit();
            }

            $sql = "UPDATE nas SET " . implode(', ', $updates) . " WHERE nasname = '$nasname'";
            writeAPILog($conn, '/api/v1/nas.php', 0, 'DEBUG', "Query UPDATE NAS nasname=$nasname", ['sql' => $sql]);

            if ($conn->query($sql)) {
                $response_msg = "Data NAS Nama $nasname berhasil diperbarui.";
                $restart_required = true;
            } else {
                http_response_code(500);
                $err_msg = "Gagal memperbarui data.";
                writeAPILog($conn, '/api/v1/nas.php', 500, 'ERROR', $err_msg);
                echo json_encode(["status" => "error", "error_code" => "DATABASE_UPDATE_FAILED", "message" => $err_msg]);
                exit();
            }
            break;

        case 'DELETE':
            if ($action !== 'delete') {
                http_response_code(400);
                $err_msg = "Action '$action' tidak valid untuk metode DELETE. Gunakan 'delete'.";
                writeAPILog($conn, '/api/v1/nas.php', 400, 'WARNING', $err_msg);
                echo json_encode(["status" => "error", "error_code" => "INVALID_ACTION", "message" => $err_msg]);
                exit();
            }

            $allowed_params = ['action', 'nasname'];
            $actual_params  = array_keys($input);

            foreach ($actual_params as $param) {
                if (!in_array($param, $allowed_params)) {
                    http_response_code(400);
                    $err_msg = "Parameter '$param' tidak diizinkan";
                    writeAPILog($conn, '/api/v1/nas.php', 400, 'WARNING', $err_msg);
                    echo json_encode(["status" => "error", "error_code" => "INVALID_PARAMETER", "message" => $err_msg]);
                    exit();
                }
            }

            $nasname = $input['nasname'] ?? '';
            if (empty($nasname)) {
                http_response_code(400);
                $err_msg = "Parameter 'nasname' wajib diisi";
                writeAPILog($conn, '/api/v1/nas.php', 400, 'WARNING', $err_msg);
                echo json_encode(["status" => "error", "error_code" => "INVALID_PARAMETER", "message" => $err_msg]);
                exit();
            }

            $check_nasname = $conn->query("SELECT nasname FROM nas WHERE nasname = '$nasname' LIMIT 1");
            if (!$check_nasname || $check_nasname->num_rows === 0) {
                http_response_code(400);
                $err_msg = "Gagal menghapus data. Perangkat dengan Nama NAS '$nasname' tidak ditemukan.";
                writeAPILog($conn, '/api/v1/nas.php', 400, 'WARNING', $err_msg);
                echo json_encode(["status" => "error", "error_code" => "DATA_NOT_FOUND", "message" => $err_msg]);
                exit();
            }
            $current_data = $check_nasname->fetch_assoc();

            $sql = "DELETE FROM nas WHERE nasname = '$nasname'";
            if ($conn->query($sql)) {
                $response_msg = "NAS Nama $nasname berhasil dihapus.";
                $restart_required = true;
            } else {
                http_response_code(400);
                $err_msg = "Gagal menghapus data.";
                writeAPILog($conn, '/api/v1/nas.php', 400, 'WARNING', $err_msg);
                echo json_encode(["status" => "error", "error_code" => "DATABASE_DELETE_FAILED", "message" => $err_msg]);
                exit();
            }
            break;

        default:
            http_response_code(405);
            $err_msg = "Metode HTTP '$method' tidak diizinkan. Gunakan POST, GET, PUT, atau DELETE.";
            writeAPILog($conn, '/api/v1/nas.php', 405, 'WARNING', $err_msg);
            echo json_encode(["status" => "error", "error_code" => "METHOD_NOT_ALLOWED", "message" => $err_msg]);
            exit();
    }

    // Restart FreeRADIUS jika ada perubahan data
    $config_status = "Layanan tidak memerlukan restart.";
    if ($restart_required) {
        writeAPILog($conn, '/api/v1/nas.php', 0, 'DEBUG', "Menjalankan restart_freeradius.sh");
        exec("sudo /usr/local/bin/restart_freeradius.sh 2>&1", $output, $returnCode);

        if ($returnCode === 0) {
            $config_status = "Konfigurasi sudah siap dipakai.";
        } else {
            $config_status = "Konfigurasi belum siap dipakai, silahkan apply konfigurasi manual.";
            // restart gagal itu masalah infrastruktur -> ERROR, walau CRUD DB-nya sukses
            writeAPILog($conn, '/api/v1/nas.php', 500, 'ERROR', "Restart FreeRADIUS gagal (exit code $returnCode)", $output);
        }
    }

    writeAPILog($conn, '/api/v1/nas.php', 200, 'INFO', "Action: $action | $config_status");
    echo json_encode([
        "status" => "success",
        "message" => $response_msg,
        "config_service" => $config_status
    ]);

} catch (Exception $e) {
    $final_err_code  = isset($error_code) ? $error_code : "GENERAL_ERROR";
    $final_err_level = isset($error_level) ? $error_level : "ERROR";

    // status code: kalau belum di-set eksplisit sebelumnya, default 400
    if (http_response_code() < 400) {
        http_response_code(400);
    }

    writeAPILog($conn, '/api/v1/nas.php', http_response_code(), $final_err_level, $e->getMessage());
    echo json_encode(["status" => "error", "error_code" => $final_err_code, "message" => $e->getMessage()]);
}
