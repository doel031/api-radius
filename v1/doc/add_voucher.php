<!DOCTYPE html>
<html lang="id">
<head>
 <meta charset="UTF-8"><title>🎫 Endpoint Add Voucher - GSMNET</title>
 <link rel="stylesheet" href="style-doc.php">
</head>
<body>
<div class="container">
 <a href="../index.php" class="back-btn">&larr; Kembali ke Hub Utama</a>
 <h1>🎫 4. Endpoint Add Voucher (Voucher Hotspot)</h1>
 <p>Membuat kode kupon hotspot dengan pembatasan masa aktif (countdown) dan batasan waktu operasional (workdays).</p>

 <div class="endpoint-box">
 <span class="method">POST</span>
 <span class="url">/api/v1/add_voucher.php</span>
 </div>

 <h2>💻 Contoh Payload & Respon</h2>

 <h3>Skenario: Voucher 3 Hari (Hari Kerja Saja)</h3>
 <pre>// Request Payload:
{
 "username": "GSM-WK3D-99",
 "password": "login99",
 "profile": "Paket_Hotspot_5Mbps",
 "validity_type": "countdown",
 "validity_value": "3d",
 "login_time_range": "Wk"
}

// Response (HTTP 200 OK):
{
 "status": "success",
 "message": "Voucher GSM-WK3D-99 berhasil dibuat.",
 "details": {
    "voucher_code": "GSM-WK3D-99",
    "valid_until": "3 Days after first login",
    "access_limit": "Monday-Friday (Workdays)",
    "sync_status": "Synchronized to MikroTik"
 }
}</pre>

 <h3>2. Respon Gagal: Format Validity Salah (HTTP 400 Bad Request)</h3>
 <pre>{
  "status": "error",
  "error_code": "INVALID_VALIDITY_FORMAT",
  "message": "Format 'validity_value' tidak valid. Gunakan format seperti '1d', '7d', atau '12h'."
}</pre>

</div>
</body>
</html>
