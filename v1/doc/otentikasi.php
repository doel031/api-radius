<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"><title>🔒 Otentikasi HMAC SHA256 - GSMNET</title>
    <link rel="stylesheet" href="style-doc.php">
</head>
<body>
<div class="container">
    <a href="../index.php" class="back-btn">&larr; Kembali ke Hub Utama</a>
    <h1>🔒 Otentikasi & Keamanan (HMAC SHA256)</h1>
    <p>API GSMNET mewajibkan otentikasi berbasis <b>Hash-based Message Authentication Code (HMAC SHA256)</b> untuk menjamin integritas data dan mencegah <i>Replay Attack</i>.</p>
    
    <h2>HTTP Headers Wajib</h2>
    <div class="table-container">
        <table>
            <tr><th>HTTP Header</th><th>Deskripsi</th></tr>
            <tr><td><code>X-GSMNET-ClientID</code></td><td>ID unik Aplikasi Pengirim (Contoh: <code>Billing_Pusat</code>).</td></tr>
            <tr><td><code>X-GSMNET-Timestamp</code></td><td>Unix Timestamp saat request dibuat. Toleransi selisih waktu <b>300 detik</b>.</td></tr>
            <tr><td><code>X-GSMNET-Signature</code></td><td>Hasil enkripsi <code>sha256</code> dari <code>Raw_JSON + Timestamp</code>.</td></tr>
        </table>
    </div>

    <h2>💻 Contoh Payload Request (Struktur Utuh)</h2>
    <p>Berikut adalah representasi paket data yang dikirimkan melalui HTTP Client (seperti cURL atau Postman):</p>
    <pre>
// HEADERS
X-GSMNET-ClientID: Billing_Pusat
X-GSMNET-Timestamp: <?php echo time(); ?> 
X-GSMNET-Signature: 7f8cf896... (hasil kalkulasi)
Content-Type: application/json

// BODY (Raw JSON)
{
  "action": "shutdown",
  "usernames": ["2602115151"]
}</pre>

    <h2>🛠 Cara Menghasilkan Signature (PHP)</h2>
    <pre>&lt;?php
$payload = ["action" => "shutdown", "usernames" => ["2602115151"]];
$rawJsonBody = json_encode($payload);
$secretKey = "KODE_RAHASIA_ANDA"; 
$timestamp = time();

// Gabungkan JSON murni dengan Timestamp tanpa spasi
$dataToHash = $rawJsonBody . $timestamp;
$signature = hash_hmac('sha256', $dataToHash, $secretKey);
?&gt;</pre>

    <h2>📋 Standar Respon JSON</h2>
    
    <h3>1. Respon Berhasil (HTTP 200 OK)</h3>
    <pre>{
  "status": "success",
  "message": "Otentikasi berhasil",
  "timestamp": <?php echo time(); ?>,
  "client_id": "Billing_Pusat"
}</pre>

    <h3>2. Respon Gagal: Signature Tidak Valid (HTTP 401)</h3>
    <pre>{
  "status": "error",
  "error_code": "INVALID_SIGNATURE",
  "message": "Tanda tangan (signature) tidak valid. Pastikan Secret Key dan format data sudah benar."
}</pre>

    <h3>3. Respon Gagal: Timestamp Kadaluarsa (HTTP 401)</h3>
    <pre>{
  "status": "error",
  "error_code": "TIMESTAMP_EXPIRED",
  "message": "Request sudah kadaluarsa. [Server Time: <?php echo time(); ?>]"
}</pre>

    <h3>4. Respon Gagal: Akses Diblokir (HTTP 423)</h3>
    <pre>{
  "status": "error",
  "error_code": "ACCESS_BLOCKED",
  "message": "ACCESS BLOCKED! Terbanned hingga 2026-07-10 02:00:00 (Sisa 15 menit)."
}</pre>
</div>
</body>
</html>