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

// Eksekusi Validasi Keamanan HMAC
validateHMACRequest($conn);

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents("php://input"), true);
$action = isset($input['action']) ? strtolower($input['action']) : '';

try {
    switch ($method) {
        case 'POST':
            if ($action !== 'add') {
                http_response_code(400);
                throw new Exception("Action '$action' tidak valid untuk metode POST. Gunakan 'add'.");
            }

            $group_name     = $input['group_name'] ?? null;
            $type           = $input['type'] ?? 'regular'; 
            $device_profile = $input['device_profile'] ?? null; // Profil dari MikroTik/Device
            $time_limit     = $input['time_limit'] ?? null;     // Dalam detik
            $quota_down     = $input['quota_download'] ?? null; // Dalam MB
            $quota_up       = $input['quota_upload'] ?? null;   // Dalam MB

            // Validasi Dasar
            if (!$group_name) throw new Exception("Parameter 'group_name' wajib diisi.");

            // Validasi Profil Device untuk tipe Reguler dan voucher
            if (!$device_profile) throw new Exception("Parameter 'device_profile' wajib diisi.");

            // Validasi Profil Device untuk tipe Reguler dan voucher
            if ($type === 'voucher' && (empty($time_limit) && (empty($quota_down) || empty($quota_up)))) {
                throw new Exception("Parameter 'time_limit' atau paket kuota ('quota_down' & 'quota_up') wajib diisi untuk tipe 'voucher'.");
            }
            
            $conn->begin_transaction();

            // 1. Atribut Identitas: Profil Device (Mikrotik-Group)
            // Jika reguler, gunakan device_profile yang diinput. Jika voucher, default ke nama group.
            $final_profile = $device_profile ? $device_profile : $group_name;
            $stmt1 = $conn->prepare("INSERT INTO radgroupreply (groupname, attribute, op, value) VALUES (?, 'Mikrotik-Group', '=', ?)");
            $stmt1->bind_param("ss", $group_name, $final_profile);
            $stmt1->execute();

            // 2. Limitasi Waktu (Max-All-Session)
            if ($time_limit) {
                $stmt2 = $conn->prepare("INSERT INTO radgroupreply (groupname, attribute, op, value) VALUES (?, 'Max-All-Session', ':=', ?)");
                $stmt2->bind_param("ss", $group_name, $time_limit);
                $stmt2->execute();
            }

            // 3. Limitasi Kuota Download (Xmit) - Konversi MB ke Bytes
            if ($quota_down) {
                $bytes_down = floatval($quota_down) * 1048576;
                $stmt3 = $conn->prepare("INSERT INTO radgroupreply (groupname, attribute, op, value) VALUES (?, 'Mikrotik-Xmit-Limit', ':=', ?)");
                $stmt3->bind_param("ss", $group_name, $bytes_down);
                $stmt3->execute();
            }

            // 4. Limitasi Kuota Upload (Recv) - Konversi MB ke Bytes
            if ($quota_up) {
                $bytes_up = floatval($quota_up) * 1048576;
                $stmt4 = $conn->prepare("INSERT INTO radgroupreply (groupname, attribute, op, value) VALUES (?, 'Mikrotik-Recv-Limit', ':=', ?)");
                $stmt4->bind_param("ss", $group_name, $bytes_up);
                $stmt4->execute();
            }

            $conn->commit();
            writeAPILog($conn, 'group.php', 200, 'success', "Created group: $group_name");
            echo json_encode(["status" => "success", "message" => "Group $group_name berhasil dibuat."]);
            break;

        case 'GET':
            if ($action !== 'get') {
                http_response_code(400);
                throw new Exception("Action '$action' tidak valid untuk metode GET. Gunakan 'get'.");
            }

            // 1. Validasi Ketat Parameter Filter
            $allowed_params = ['action', 'group_name'];
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

            $group_target = isset($input['group_name']) ? $conn->real_escape_string($input['group_name']) : '';

            // Menyusun base SQL query
            $sql = "SELECT 
                        groupname as group_name,
                        MAX(CASE WHEN attribute = 'Mikrotik-Group' THEN value END) AS device_profile,
                        MAX(CASE WHEN attribute = 'Max-All-Session' THEN value ELSE 0 END) AS time_limit,
                        MAX(CASE WHEN attribute = 'Mikrotik-Xmit-Limit' THEN ROUND(value / 1048576, 2) ELSE 0 END) AS quota_down,
                        MAX(CASE WHEN attribute = 'Mikrotik-Recv-Limit' THEN ROUND(value / 1048576, 2) ELSE 0 END) AS quota_up
                    FROM radgroupreply";

            // Jika group_target spesifik diminta, tambahkan filter WHERE
            if (!empty($group_target)) {
                $sql .= " WHERE groupname = '$group_target'";
            }

            // Gabungkan GROUP BY di akhir konstruksi query string
            $sql .= " GROUP BY groupname";
            
            // Eksekusi query CUKUP SATU KALI di sini
            $res = $conn->query($sql);

            // VALIDASI: Jika group_target diminta tapi tidak ada hasil di database
            if (!empty($group_target) && $res->num_rows === 0) {
                http_response_code(404);
                $error_code = "GROUP_NOT_FOUND";
                $err_msg = "Data group dengan nama '$group_target' tidak ditemukan.";

                writeAPILog($conn, 'group.php', 404, 'error', $err_msg);
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
                $has_quota_down  = isset($row['quota_down']) && floatval($row['quota_down']) > 0;
                $has_quota_up    = isset($row['quota_up']) && floatval($row['quota_up']) > 0;

                $has_limit = $has_time_limit || $has_quota_down || $has_quota_up;

                if (!empty($row['device_profile']) && $has_limit) {
                    $row['type'] = 'voucher';
                } else {
                    $row['type'] = 'reguler';
                }
                // -----------------------------------------------

                $data[] = $row;
            }

            writeAPILog($conn, 'group.php', 200, 'completed', "Action: get " . (!empty($group_target) ? "($group_target)" : "= all"));
            if (!empty($group_target)) {
                echo json_encode([
                    "status" => "success",
                    "data" => $data[0] // Hanya satu data yang dikembalikan
                ]);
            } else {
                echo json_encode([
                    "status" => "success",
                    "total_groups" => $total_groups,
                    "data" => $data
                ]);
            }
            exit();

        case 'PUT':
            if ($action !== 'update') {
                http_response_code(400);
                throw new Exception("Action '$action' tidak valid untuk metode PUT. Gunakan 'update'.");
            }

            // 1. Validasi Parameter Wajib
            $required_params = ['group_name', 'device_profile', 'type'];
            foreach ($required_params as $param) {
                if (empty($input[$param])) {
                    http_response_code(400);
                    $err_msg = "Parameter '$param' wajib diisi.";
                    writeAPILog($conn, 'group.php', 400, 'error', $err_msg);
                    echo json_encode(["status" => "error", "message" => $err_msg]);
                    exit();
                }
            }

            // Sanitasi Input
            $group_name     = $conn->real_escape_string($input['group_name']);
            $device_profile = $conn->real_escape_string($input['device_profile']);
            $type           = strtolower($input['type']); // 'reguler' atau 'voucher'
            
            // Ambil parameter opsional untuk limitasi (jika type = voucher)
            $time_limit     = isset($input['time_limit']) ? $conn->real_escape_string($input['time_limit']) : '';
            $quota_down     = isset($input['quota_down']) ? floatval($input['quota_down']) : 0;
            $quota_up       = isset($input['quota_up']) ? floatval($input['quota_up']) : 0;

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

                // Langkah 2: Jika tipenya voucher, tambahkan atribut limitasi ke dalam list insert
                if ($type === 'voucher') {
                    // Batasan Waktu (Max-All-Session)
                    if (!empty($time_limit)) {
                        $inserts[] = "('$group_name', 'Max-All-Session', ':=', '$time_limit')";
                    }
                    
                    // Batasan Kuota Download (Mikrotik-Xmit-Limit) -> Konversi kembali dari MB ke Bytes
                    if ($quota_down > 0) {
                        $bytes_down = round($quota_down * 1048576);
                        $inserts[] = "('$group_name', 'Mikrotik-Xmit-Limit', ':=', '$bytes_down')";
                    }

                    // Batasan Kuota Upload (Mikrotik-Recv-Limit) -> Konversi kembali dari MB ke Bytes
                    if ($quota_up > 0) {
                        $bytes_up = round($quota_up * 1048576);
                        $inserts[] = "('$group_name', 'Mikrotik-Recv-Limit', ':=', '$bytes_up')";
                    }
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

                writeAPILog($conn, 'group.php', 200, 'completed', "Action: update group '$group_name' as $type");
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
                writeAPILog($conn, 'group.php', 500, 'error', $err_msg);
                echo json_encode(["status" => "error", "message" => $err_msg]);
                exit();
            }
            break;

        case 'DELETE':
            if ($action !== 'delete') {
                http_response_code(400);
                throw new Exception("Action '$action' tidak valid untuk metode DELETE. Gunakan 'delete'.");
            }

            // 1. Validasi Ketat Parameter Filter
            $allowed_params = ['action', 'group_name'];
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

            $group_name = $input['group_name'] ?? null;
            if (!$group_name) throw new Exception("group_name wajib diisi untuk menghapus.");

            $stmt = $conn->prepare("DELETE FROM radgroupreply WHERE groupname = ?");
            $stmt->bind_param("s", $group_name);
            $stmt->execute();

            if ($stmt->affected_rows === 0) throw new Exception("Gagal hapus. Group tidak ditemukan.");

            writeAPILog($conn, 'group.php', 200, 'success', "Deleted: $group_name");
            echo json_encode(["status" => "success", "message" => "Group $group_name berhasil dihapus."]);
            break;

        default:
            http_response_code(405);
            echo json_encode(["status" => "error", "message" => "Method Not Allowed."]);
            break;
    }
} catch (Exception $e) {
    if ($conn->connect_errno == 0 && $method === 'POST') $conn->rollback();
    http_response_code(400);
    writeAPILog($conn, 'group.php', 400, 'error', $e->getMessage());
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
$conn->close();