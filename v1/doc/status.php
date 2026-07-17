<!DOCTYPE html>
<html lang="id">
<head>
 <meta charset="UTF-8"><title>🔍 Endpoint Check Status - GSMNET</title>
 <link rel="stylesheet" href="style-doc.php">
</head>
<body>
<div class="container">
 <a href="../index.php" class="back-btn">&larr; Kembali ke Hub Utama</a>
 <h1>🔍 Endpoint Check Status</h1>
 <p>Endpoint fleksibel untuk memantau performa jaringan total atau memeriksa profil akun individu secara real-time.</p>

 <div class="endpoint-box blue">
 <span class="method blue">POST</span>
 <span class="url">/api/v1/status.php</span>
 </div>

 <h2>💻 Contoh Payload & Respon</h2>

 <h3>Mode A: Summary Jaringan (Kirim Payload Kosong <code>{}</code>)</h3>
 <p>Digunakan untuk Dashboard monitoring kapasitas infrastruktur.</p>
 <pre>// Request Payload: {}

// Response (HTTP 200 OK):
{
 "status": "success",
 "mode": "default_summary",
 "summary": {
    "total_pelanggan_terdaftar": 1550,
    "total_online_sekarang": 1100,
    "total_offline_sekarang": 450,
    "last_sync": "<?php echo date('Y-m-d H:i:s'); ?>"
 }
}</pre>

 <h3>Mode B: Detail User Individu</h3>
 <p>Gunakan parameter <code>username</code> untuk melihat status spesifik pelanggan.</p>
 <pre>// Request Payload:
{ "username": "2602115151" }

// Response (HTTP 200 OK):
{
 "status": "success",
 "mode": "user_detail",
 "data": {
    "username": "2602115151",
    "status": "Online",
    "ip_address": "10.20.30.45",
    "mac_address": "AA:BB:CC:DD:EE:FF",
    "uptime": "2d 05h 12m",
    "session_start": "2026-07-07 10:30:00"
 }
}</pre>

 <h3>3. Respon Gagal: User Tidak Ditemukan (HTTP 404 Not Found)</h3>
 <pre>{
  "status": "error",
  "error_code": "USER_NOT_FOUND",
  "message": "Username '2602119999' tidak terdaftar di database RADIUS."
}</pre>

</div>
</body>
</html>
