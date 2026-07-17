<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"><title>📡 Endpoint Isolate - GSMNET</title>
  <link rel="stylesheet" href="style-doc.php">
</head>
<body>
<div class="container">
 <a href="../index.php" class="back-btn">&larr; Kembali ke Hub Utama</a>
 <h1>📡 Endpoint Isolate (Manajemen Akun)</h1>
 <p>Digunakan untuk isolir tunggakan (shutdown), penonaktifan kontrak (off), memulihkan paket (restore), atau menghapus akun massal (delete).</p>

 <div class="endpoint-box blue">
 <span class="method blue">POST</span>
 <span class="url">/api/v1/isolate.php</span>
 </div>

 <h2>Payload Parameter JSON</h2>
 <div class="table-container">
 <table>
 <tr><th>Parameter</th><th>Tipe</th><th>Status</th><th>Keterangan</th></tr>
 <tr><td><code>action</code></td><td>String</td><td><span class="badge badge-req">Wajib</span></td><td><code>shutdown</code>, <code>off</code>, <code>restore</code>, <code>delete</code>.</td></tr>
 <tr><td><code>usernames</code></td><td>Array</td><td><span class="badge badge-req">Wajib</span></td><td>Kumpulan username. Contoh: <code>["2602115151"]</code></td></tr>
 <tr><td><code>target_group</code></td><td>String</td><td><span class="badge badge-opt">Kondisional</span></td><td>Wajib diisi hanya jika action bernilai <code>restore</code>.</td></tr>
 </table>
 </div>

 <h2>💻 Contoh Payload Request</h2>
 <h3>Skenario: Isolir Pelanggan (Shutdown)</h3>
 <pre>{
  "action": "shutdown",
  "usernames": ["2602115151", "2602115152"]
}</pre>

 <h3>Skenario: Pulihkan Pelanggan (Restore)</h3>
 <pre>{
  "action": "restore",
  "usernames": ["2602115151"],
  "target_group": "Paket_Home_20Mbps"
}</pre>

 <h2>📋 Contoh Respon</h2>
 <h3>1. Respon Berhasil (HTTP 200 OK)</h3>
 <pre>{
 "status": "completed",
 "execution_mode": "Real-time (Direct)",
 "results": {
  "2602115151": { 
    "db_action_success": true, 
    "session_status": "Disconnected", 
    "message": "User successfully isolated and session killed." 
  },
  "2602115152": { 
    "db_action_success": true, 
    "session_status": "No Active Session", 
    "message": "User status updated in DB, no active session found to kill." 
  }
 }
}</pre>

 <h3>2. Respon Gagal: Parameter Tidak Lengkap (HTTP 400 Bad Request)</h3>
 <pre>{
  "status": "error",
  "error_code": "INCOMPLETE_PARAMETERS",
  "message": "Parameter 'action' dan 'usernames' wajib diisi."
}</pre>

 <h3>3. Respon Gagal: Target Group Kosong pada Restore (HTTP 400 Bad Request)</h3>
 <pre>{
  "status": "error",
  "error_code": "MISSING_TARGET_GROUP",
  "message": "Parameter 'target_group' wajib ada jika action adalah 'restore'."
}</pre>

</div>
</body>
</html>
