<!DOCTYPE html>
<html lang="id">
<head>
 <meta charset="UTF-8"><title>👤 Endpoint Add User - GSMNET</title>
 <link rel="stylesheet" href="style-doc.php">
</head>
<body>
<div class="container">
 <a href="../index.php" class="back-btn">&larr; Kembali ke Hub Utama</a>
 <h1>👤 2. Endpoint Add User (Registrasi Pelanggan)</h1>
 <p>Mendaftarkan kredensial akun pelanggan baru tipe PPPoE/Hotspot secara sinkron ke dalam database FreeRADIUS.</p>

 <div class="endpoint-box">
 <span class="method">POST</span>
 <span class="url">/api/v1/add_user.php</span>
 </div>

 <h2>Payload Parameter JSON</h2>
 <div class="table-container">
 <table>
 <tr><th>Parameter</th><th>Tipe</th><th>Status</th><th>Keterangan</th></tr>
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
 <p>Diberikan jika data berhasil divalidasi dan disimpan ke dalam tabel <code>radcheck</code> dan <code>radusergroup</code>.</p>
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
 <p>Terjadi jika username yang dikirimkan sudah terdaftar dalam sistem.</p>
 <pre>{
  "status": "error",
  "error_code": "DUPLICATE_USER",
  "message": "Gagal membuat user. Username '2602115152' sudah digunakan."
}</pre>

 <h3>3. Respon Gagal: Grup Tidak Ditemukan (HTTP 404 Not Found)</h3>
 <p>Terjadi jika parameter <code>group</code> tidak sesuai dengan profil yang ada di database.</p>
 <pre>{
  "status": "error",
  "error_code": "INVALID_GROUP",
  "message": "Grup 'Paket_Sultan_1Gbps' tidak ditemukan dalam sistem."
}</pre>

</div>
</body>
</html>
