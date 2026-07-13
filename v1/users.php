<?php
/**
 * API Endpoint: User Management (CRUD)
 * Features:
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

            if (!empty($input['username']) && !empty($input['password'])) {
                // Cek apakah user sudah ada
                $check = $db->prepare("SELECT id FROM radcheck WHERE username = :username");
                $check->execute([':username' => $input['username']]);
                
                if ($check->rowCount() > 0) {
                    http_response_code(400);
                    echo json_encode(["status" => false, "message" => "Username sudah terdaftar."]);
                } else {
                    $query = "INSERT INTO radcheck (username, attribute, op, value) VALUES (:username, 'Cleartext-Password', ':=', :password)";
                    $stmt = $db->prepare($query);
                    
                    if ($stmt->execute([':username' => $input['username'], ':password' => $input['password']])) {
                        http_response_code(201);
                        echo json_encode(["status" => true, "message" => "User berhasil dibuat."]);
                    } else {
                        http_response_code(500);
                        echo json_encode(["status" => false, "message" => "Gagal membuat user."]);
                    }
                }
            } else {
                http_response_code(400);
                echo json_encode(["status" => false, "message" => "Data tidak lengkap (butuh username & password)."]);
            }
            break;

        case 'GET':
            if ($action !== 'get') {
                http_response_code(400);
                throw new Exception("Action '$action' tidak valid untuk metode GET. Gunakan 'get'.");
            }

            if (isset($_GET['username'])) {
                // Ambil spesifik user
                $query = "SELECT id, username, value as password FROM radcheck WHERE username = :username AND attribute = 'Cleartext-Password'";
                $stmt = $db->prepare($query);
                $stmt->execute([':username' => $_GET['username']]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result) {
                    echo json_encode(["status" => true, "data" => $result]);
                } else {
                    http_response_code(404);
                    echo json_encode(["status" => false, "message" => "User tidak ditemukan."]);
                }
            } else {
                // Ambil semua user
                $query = "SELECT id, username, value as password FROM radcheck WHERE attribute = 'Cleartext-Password'";
                $stmt = $db->prepare($query);
                $stmt->execute();
                $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode(["status" => true, "data" => $result]);
            }
            break;

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