<!DOCTYPE html>
<html lang="id">
<head>
 <meta charset="UTF-8"><title>👤 Endpoint Add User - GSMNET</title>
 <link rel="stylesheet" href="style-doc.php">
</head>
<body>
<div class="container">
 <a href="../index.php" class="back-btn">&larr; Kembali ke Hub Utama</a>
 <h1>👤 Endpoint User Management</h1>
 <p>Mendaftarkan kredensial akun pelanggan baru tipe PPPoE/Hotspot secara sinkron ke dalam database FreeRADIUS.</p>

  <!-- 1. CREATE SECTION -->
  <h2>1. Create: Registrasi User Baru</h2>
  <p>Gunakan metode <b>POST</b> dengan action <code>add</code> untuk mendaftarkan unit user baru ke dalam sistem.</p>
 <div class="endpoint-box">
 <span class="method">POST</span>
 <span class="url">/api/v1/users.php</span>
 </div>

 <h2>Payload Parameter JSON</h2>
 <div class="table-container">
 <table>
 <tr><th>Parameter</th><th>Tipe</th><th>Status</th><th>Keterangan</th></tr>
 <tr><td><code>action</code></td><td>String</td><td><span class="badge badge-req">Wajib</span></td><td>Aksi yang akan dilakukan (Contoh: <code>add</code>).</td></tr>
 <tr><td><code>username</code></td><td>String</td><td><span class="badge badge-req">Wajib</span></td><td>Username unik pelanggan (PPPoE/Hotspot).</td></tr>
 <tr><td><code>password</code></td><td>String</td><td><span class="badge badge-req">Wajib</span></td><td>Kata sandi akun.</td></tr>
 <tr><td><code>group</code></td><td>String</td><td><span class="badge badge-req">Wajib</span></td><td>Nama profil paket (Contoh: <code>Paket_Home_20Mbps</code>).</td></tr>
 </table>
 </div>

 <h2>💻 Contoh Payload Request</h2>
 <h3>Skenario: Registrasi User Tunggal</h3>
 <pre>{
 "username": "2602115152",
 "password": "password_rahasia",
 "group": "Paket_Home_20Mbps"
}</pre>

 <h2>📋 Contoh Respon</h2>
 <h3>1. Respon Berhasil (HTTP 200 OK)</h3>
 <pre>{
 "status": "success",
 "type": "single",
 "message": "User 2602115152 berhasil dibuat di grup Paket_Home_20Mbps.",
 "data": {
    "username": "2602115152",
    "created_at": "<?php echo date('Y-m-d H:i:s'); ?>"
 }
}</pre>

 <h3>2. Respon Gagal: User Sudah Ada (HTTP 409 Conflict)</h3>
 <pre>{
  "status": "error",
  "error_code": "DUPLICATE_USER",
  "message": "Gagal membuat user. Username '2602115152' sudah digunakan."
}</pre>

 <h3>3. Respon Gagal: Grup Tidak Ditemukan (HTTP 404 Not Found)</h3>
 <pre>{
  "status": "error",
  "error_code": "INVALID_GROUP",
  "message": "Grup 'Paket_Sultan_1Gbps' tidak ditemukan dalam sistem."
}</pre>

  <!-- 2. Read SECTION -->
  <h2>2. Read: Lihat User</h2>
  <p>Gunakan metode <b>GET</b> dengan action <code>get</code> untuk melihat unit user.</p>
 <div class="endpoint-box">
 <span class="method blue">GET</span>
 <span class="url">/api/v1/users.php</span>
 </div>

 <h2>Payload Parameter JSON</h2>
 <div class="table-container">
 <table>
 <tr><th>Parameter</th><th>Tipe</th><th>Status</th><th>Keterangan</th></tr>
 <tr><td><code>action</code></td><td>String</td><td><span class="badge badge-req">Wajib</span></td><td>Aksi yang akan dilakukan (Contoh: <code>get</code>).</td></tr>
 <tr><td><code>username</code></td><td>String</td><td><span class="badge badge-opt">Opsional</span></td><td>Username unik pelanggan (PPPoE/Hotspot).</td></tr>
 </table>
 </div>

 <h2>💻 Contoh Payload Request</h2>
 <h3>Skenario: Lihat User Tunggal</h3>
 <pre>{
 "action": "get",
 "username": "2602115152"
}</pre>

 <h3>Skenario: Lihat Semua User</h3>
 <pre>{
 "action": "get"
}</pre>

  <!-- 3. Update SECTION -->
  <h2>3. Update: Perbarui User</h2>
  <p>Gunakan metode <b>PUT</b> dengan action <code>update</code> untuk memperbarui informasi user.</p>
 <div class="endpoint-box">
 <span class="method green">PUT</span>
 <span class="url">/api/v1/users.php</span>
 </div>

 <h2>Payload Parameter JSON</h2>
 <div class="table-container">
 <table>
 <tr><th>Parameter</th><th>Tipe</th><th>Status</th><th>Keterangan</th></tr>
 <tr><td><code>action</code></td><td>String</td><td><span class="badge badge-req">Wajib</span></td><td>Aksi yang akan dilakukan (Contoh: <code>get</code>).</td></tr>
 <tr><td><code>username</code></td><td>String</td><td><span class="badge badge-req">Wajib</span></td><td>Username unik pelanggan (PPPoE/Hotspot).</td></tr>
 <tr><td><code>password</code></td><td>String</td><td><span class="badge badge-opt">Opsional</span></td><td>Kata sandi pelanggan.</td></tr>
 <tr><td><code>group_name</code></td><td>String</td><td><span class="badge badge-opt">Opsional</span></td><td>Nama grup pelanggan.</td></tr>
 </table>
 </div>

 <h2>💻 Contoh Payload Request</h2>
 <h3>Skenario: Perbarui User</h3>
 <pre>{
 "action": "update",
 "username": "2602115152",
 "password": "new_password",
 "group_name": "new_group"
}</pre>

  <!-- 4. Delete SECTION -->
  <h2>4. Delete: Hapus User</h2>
  <p>Gunakan metode <b>DELETE</b> dengan action <code>delete</code> untuk menghapus user.</p>
 <div class="endpoint-box">
 <span class="method red">DELETE</span>
 <span class="url">/api/v1/users.php</span>
 </div>

 <h2>Payload Parameter JSON</h2>
 <div class="table-container">
 <table>
 <tr><th>Parameter</th><th>Tipe</th><th>Status</th><th>Keterangan</th></tr>
 <tr><td><code>action</code></td><td>String</td><td><span class="badge badge-req">Wajib</span></td><td>Aksi yang akan dilakukan (Contoh: <code>get</code>).</td></tr>
 <tr><td><code>username</code></td><td>String</td><td><span class="badge badge-req">Wajib</span></td><td>Username unik pelanggan (PPPoE/Hotspot).</td></tr>
 </table>
 </div>

 <h2>💻 Contoh Payload Request</h2>
 <h3>Skenario: Hapus User</h3>
 <pre>{
 "action": "delete",
 "username": "2602115152"
}</pre>
</div>
</body>
</html>
