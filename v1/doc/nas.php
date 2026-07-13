<?php
/**
 * Dokumentasi API Endpoint: NAS (Network Access Server)
 * Lokasi: api/v1/doc/nas.php
 */
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>📡 Endpoint NAS Management - GSMNET</title>
    <link rel="stylesheet" href="style-doc.php">
</head>
<body>
<div class="container">
    <a href="../index.php" class="back-btn">&larr; Kembali ke Hub Utama</a>
    <h1>📡 Endpoint NAS Management (RADIUS Gateway)</h1>
    <p>Endpoint ini digunakan untuk mengelola daftar perangkat router (NAS) yang diizinkan berinteraksi dengan database RADIUS. Demi menjaga stabilitas layanan, setiap modifikasi data memerlukan waktu sinkronisasi sistem agar seluruh parameter siap digunakan secara optimal.</p>

    <!-- 1. CREATE SECTION -->
    <h2>1. Create: Registrasi Perangkat Baru</h2>
    <p>Gunakan metode <b>POST</b> dengan action <code>add</code> untuk mendaftarkan unit router baru ke dalam whitelist sistem.</p>
    <div class="endpoint-box">
        <span class="method">POST</span>
        <span class="url">/api/v1/nas.php</span>
    </div>
    
    <div class="table-container">
        <table>
            <tr><th>Parameter</th><th>Tipe</th><th>Status</th><th>Keterangan</th></tr>
            <tr><td><code>action</code></td><td>String</td><td><span class="badge badge-req">Wajib</span></td><td>Isi dengan <code>add</code>.</td></tr>
            <tr><td><code>nasname</code></td><td>String</td><td><span class="badge badge-req">Wajib</span></td><td>IP Address atau Hostname NAS.</td></tr>
            <tr><td><code>secret</code></td><td>String</td><td><span class="badge badge-req">Wajib</span></td><td>Shared Secret RADIUS untuk autentikasi perangkat.</td></tr>
            <tr><td><code>shortname</code></td><td>String</td><td><span class="badge badge-opt">Opsional</span></td><td>Nama alias/pendek perangkat.</td></tr>
            <tr><td><code>type</code></td><td>String</td><td><span class="badge badge-opt">Opsional</span></td><td>Tipe perangkat (misalnya: mikrotik, cisco).</td></tr>
            <tr><td><code>ports</code></td><td>Integer</td><td><span class="badge badge-opt">Opsional</span></td><td>Port UDP untuk CoA/Disconnect (Default: NULL).</td></tr>
            <tr><td><code>server</code></td><td>String</td><td><span class="badge badge-opt">Opsional</span></td><td>Server RADIUS yang digunakan (Default: NULL).</td></tr>
            <tr><td><code>community</code></td><td>String</td><td><span class="badge badge-opt">Opsional</span></td><td>Community string untuk SNMP (Default: NULL).</td></tr>
            <tr><td><code>description</code></td><td>String</td><td><span class="badge badge-opt">Opsional</span></td><td>Deskripsi perangkat.</td></tr>
        </table>
    </div>

    <h3>Contoh Payload & Balasan (Create)</h3>
    <pre>// Request
{
  "action": "add",
  "nasname": "10.10.10.25",
  "shortname": "Router_Pusat",
  "secret": "SharedSecret123"
}

// Response (HTTP 200 OK)
{
  "status": "success",
  "message": "NAS 10.10.10.25 berhasil ditambahkan.",
  "config_service": "Konfigurasi sudah siap dipakai."
}</pre>

    <!-- 2. READ SECTION -->
    <h2>2. Read: Monitoring & Pengambilan Data</h2>
    <p>Gunakan metode <b>GET</b> dengan action <code>get</code> untuk melihat daftar perangkat yang aktif.</p>
    <div class="endpoint-box blue">
        <span class="method blue">GET</span>
        <span class="url">/api/v1/nas.php</span>
    </div>
    
    <div class="table-container">
        <table>
            <tr><th>Parameter</th><th>Tipe</th><th>Status</th><th>Keterangan</th></tr>
            <tr><td><code>action</code></td><td>String</td><td><span class="badge badge-req">Wajib</span></td><td>Isi dengan <code>get</code>.</td></tr>
            <tr><td><code>nasname</code></td><td>String</td><td><span class="badge badge-opt">Opsional</span></td><td>IP Address atau Hostname NAS.</td></tr>
        </table>
    </div>

    <h3>Contoh Payload & Balasan (Read)</h3>
    <pre>// Request
{
  "action": "get",
  "nasname": "10.10.10.25"
}

// Response (HTTP 200 OK)
{
  "status": "success",
  "data": [
    {
      "id": "15",
      "nasname": "10.10.10.25",
      "shortname": "Router_Pusat",
      "type": "mikrotik",
      "ports": "3799",
      "secret": "SharedSecret123",
      "server": "radius_primary"
    }
  ]
}</pre>

    <!-- 3. UPDATE SECTION -->
    <h2>3. Update: Pembaruan Parameter Perangkat</h2>
    <p>Gunakan metode <b>PUT</b> dengan action <code>update</code> untuk mengubah informasi teknis perangkat.</p>
    <div class="endpoint-box" style="border-left-color: #ecc94b;">
        <span class="method" style="background:#ecc94b; color:#744210;">PUT</span>
        <span class="url">/api/v1/nas.php</span>
    </div>
    
    <div class="table-container">
        <table>
            <tr><th>Parameter</th><th>Tipe</th><th>Status</th><th>Keterangan</th></tr>
            <tr><td><code>action</code></td><td>String</td><td><span class="badge badge-req">Wajib</span></td><td>Isi dengan <code>update</code>.</td></tr>
            <tr><td><code>nasname</code></td><td>String</td><td><span class="badge badge-opt">Opsional</span></td><td>IP Address atau Hostname NAS.</td></tr>
            <tr><td><code>secret</code></td><td>String</td><td><span class="badge badge-opt">Opsional</span></td><td>Shared Secret RADIUS untuk autentikasi perangkat.</td></tr>
            <tr><td><code>shortname</code></td><td>String</td><td><span class="badge badge-opt">Opsional</span></td><td>Nama alias/pendek perangkat.</td></tr>
            <tr><td><code>type</code></td><td>String</td><td><span class="badge badge-opt">Opsional</span></td><td>Tipe perangkat (misalnya: mikrotik, cisco).</td></tr>
            <tr><td><code>ports</code></td><td>Integer</td><td><span class="badge badge-opt">Opsional</span></td><td>Port UDP untuk CoA/Disconnect (Default: NULL).</td></tr>
            <tr><td><code>server</code></td><td>String</td><td><span class="badge badge-opt">Opsional</span></td><td>Server RADIUS yang digunakan (Default: NULL).</td></tr>
            <tr><td><code>community</code></td><td>String</td><td><span class="badge badge-opt">Opsional</span></td><td>Community string untuk SNMP (Default: NULL).</td></tr>
            <tr><td><code>description</code></td><td>String</td><td><span class="badge badge-opt">Opsional</span></td><td>Deskripsi perangkat.</td></tr>
        </table>
    </div>

    <h3>Contoh Payload & Balasan (Update)</h3>
    <pre>// Request
{
  "action": "update",
  "id": 15,
  "secret": "SecretBaru2026",
  "ports": 1700
}

// Response (HTTP 200 OK)
{
  "status": "success",
  "message": "Data NAS ID 15 berhasil diperbarui.",
  "config_service": "Konfigurasi sudah siap dipakai."
}</pre>

    <!-- 4. DELETE SECTION -->
    <h2>4. Delete: Penghapusan Hak Akses</h2>
    <p>Gunakan metode <b>DELETE</b> dengan action <code>delete</code> untuk mencabut izin akses perangkat secara permanen.</p>
    <div class="endpoint-box" style="border-left-color: #e53e3e;">
        <span class="method" style="background:#e53e3e;">DELETE</span>
        <span class="url">/api/v1/nas.php</span>
    </div>
    
    <div class="table-container">
        <table>
            <tr><th>Parameter</th><th>Tipe</th><th>Status</th><th>Keterangan</th></tr>
            <tr><td><code>action</code></td><td>String</td><td><span class="badge badge-req">Wajib</span></td><td>Isi dengan <code>delete</code>.</td></tr>
            <tr><td><code>id</code></td><td>Integer</td><td><span class="badge badge-req">Wajib</span></td><td>ID perangkat NAS yang akan dihapus.</td></tr>
        </table>
    </div>

    <h3>Contoh Payload & Balasan (Delete)</h3>
    <pre>// Request
{
  "action": "delete",
  "id": 15
}

// Response (HTTP 200 OK)
{
  "status": "success",
  "message": "NAS berhasil dihapus.",
  "config_service": "Konfigurasi sudah siap dipakai."
}</pre>

    <hr style="border: 0; border-top: 1px solid #e2e8f0; margin: 30px 0;">

    <h2>📋 Respon Gagal (Error Handling)</h2>
    <p>Sistem menggunakan kode error standar untuk mempermudah <i>debugging</i> pada sisi klien.</p>
    <pre>// Contoh Respon Gagal: ID Tidak Ditemukan (HTTP 400)
{
  "status": "error",
  "error_code": "INVALID_NAS_ID",
  "message": "ID NAS tidak ditemukan atau tidak valid."
}

// Contoh Respon Gagal: Sinkronisasi Tertunda (HTTP 200)
{
  "status": "success",
  "message": "Data berhasil diproses.",
  "config_service": "Konfigurasi belum siap dipakai, silahkan apply konfigurasi manual."
}</pre>

    <div class="explanation-box" style="margin-top: 25px; border-left: 4px solid #3182ce;">
        <strong>Informasi Keamanan:</strong>
        <ul>
            <li>Seluruh permintaan wajib menyertakan header <code>X-GSMNET-Signature</code> (HMAC SHA256).</li>
            <li>Status <code>"Konfigurasi sudah siap dipakai"</code> menandakan parameter baru telah aktif di sistem gateway.</li>
        </ul>
    </div>
</div>
</body>
</html>