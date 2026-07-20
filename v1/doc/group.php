<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>📁 Endpoint Group Management - GSMNET</title>
    <link rel="stylesheet" href="style-doc.php">
</head>
<body>
<div class="container">
    <a href="../index.php" class="back-btn">&larr; Kembali ke Hub Utama</a>
    <h1>📁 Endpoint Group Management (CRUD)</h1>
    <p>Dokumentasi ini menjelaskan manajemen profil paket (Group) di tabel RADIUS. Endpoint ini mendukung operasi pendaftaran, pemantauan, pembaruan, dan penghapusan klasifikasi user secara terpusat.</p>

    <!-- CREATE SECTION -->
    <h2>1. Create: Menambah Group Baru</h2>
    <div class="endpoint-box">
        <span class="method">POST</span>
        <span class="url">/api/v1/group.php</span>
    </div>
    <div class="table-container">
        <table>
            <tr><th>Parameter</th><th>Tipe</th><th>Status</th><th>Keterangan</th></tr>
            <tr><td><code>action</code></td><td>String</td><td><span class="badge badge-req">Wajib</span></td><td>Aksi yang akan dilakukan (e.g., <code>create</code>).</td></tr>
            <tr><td><code>group_name</code></td><td>String</td><td><span class="badge badge-req">Wajib</span></td><td>Nama unik paket (e.g., <code>Voucher_10Jam</code>).</td></tr>
            <tr><td><code>device_profile</code></td><td>Integer</td><td><span class="badge badge-req">Wajib</span></td><td>ID dari profil perangkat yang terkait.</td></tr>
            <tr><td><code>time_limit</code></td><td>Integer</td><td><span class="badge badge-opt">Kondisional</span></td><td>Wajib jika type adalah <code>voucher</code> (satuan detik).</td></tr>
            <tr><td><code>quota_download</code></td><td>Integer</td><td><span class="badge badge-opt">Kondisional</span></td><td>Wajib jika type adalah <code>voucher</code> (satuan MB).</td></tr>
            <tr><td><code>quota_upload</code></td><td>Integer</td><td><span class="badge badge-opt">Kondisional</span></td><td>Wajib jika type adalah <code>voucher</code> (satuan MB).</td></tr>
        </table>
    </div>
    <h3>Skenario A: Paket Reguler (Wajib device_profile)</h3>
    <pre>// Payload JSON
{
  "action": "create",
  "group_name": "10M",
  "device_profile": "Profile_Reguler_10M",
}

// Rspon Berhasil
{
    "status": "success",
    "message": "Group 10M (regular) berhasil ditambahkan."
}</pre>

    <h3>Skenario B: Paket Voucher Kouta + limit waktu (Kondisional)</h3>
    <pre>// Payload JSON
{
  "action": "create",
  "group_name": "Paket_Voucher_5Jam",
  "device_profile": "Profile_Voucher_5Jam",
  "time_limit": 18000,
  "quota_download": 2048,
  "quota_upload": 512
}

// Rspon Berhasil
{
    "status": "success",
    "message": "Group Paket_Voucher_5Jam (voucher) berhasil ditambahkan."
}</pre>

    <h3>Skenario C: Paket Voucher Kouta (Opsional)</h3>
    <pre>// Payload JSON
{
  "action": "create",
  "group_name": "Paket_Voucher_Unlimited",
  "device_profile": "Profile_Voucher_Unlimited",
  "time_limit": 0,
  "quota_download": 2048,
  "quota_upload": 512
}

// Rspon Berhasil
{
    "status": "success",
    "message": "Group Paket_Voucher_Unlimited (voucher) berhasil ditambahkan."
}</pre>

    <!-- READ SECTION -->
    <h2>2. Read: Melihat Daftar Group</h2>
    <div class="endpoint-box blue">
        <span class="method blue">GET</span>
        <span class="url">/api/v1/group.php</span>
    </div>
    <div class="table-container">
        <table>
            <tr><th>Parameter</th><th>Tipe</th><th>Status</th><th>Keterangan</th></tr>
            <tr><td><code>action</code></td><td>String</td><td><span class="badge badge-req">Wajib</span></td><td>Aksi yang akan dilakukan (e.g., <code>create</code>).</td></tr>
            <tr><td><code>group_name</code></td><td>String</td><td><span class="badge badge-req">Wajib</span></td><td>Nama unik paket (e.g., <code>Voucher_10Jam</code>).</td></tr>
            <tr><td><code>device_profile</code></td><td>Integer</td><td><span class="badge badge-req">Wajib</span></td><td>ID dari profil perangkat yang terkait.</td></tr>
            <tr><td><code>time_limit</code></td><td>Integer</td><td><span class="badge badge-opt">Kondisional</span></td><td>Wajib jika type adalah <code>voucher</code> (satuan detik).</td></tr>
            <tr><td><code>quota_download</code></td><td>Integer</td><td><span class="badge badge-opt">Kondisional</span></td><td>Wajib jika type adalah <code>voucher</code> (satuan MB).</td></tr>
            <tr><td><code>quota_upload</code></td><td>Integer</td><td><span class="badge badge-opt">Kondisional</span></td><td>Wajib jika type adalah <code>voucher</code> (satuan MB).</td></tr>
        </table>
    </div>
    
<h3>Contoh Respon Berhasil (JSON)</h3>
<pre>{
  "status": "success",
  "total_groups": 2,
  "data": [
    {
      "group_name": "Paket_Voucher_5Jam",
      "type": "voucher",
      "device_profile": "Profile_Voucher_5Jam",
      "time_limit": 18000,
      "quota_download": 2048,
      "quota_upload": 512
    },
    {
      "group_name": "Paket_Voucher_Unlimited",
      "type": "voucher",
      "device_profile": "Profile_Voucher_Unlimited",
      "time_limit": 0,
      "quota_download": 2048,
      "quota_upload": 512
    }
  ]
}</pre>

    <!-- UPDATE SECTION -->
    <h2>3. Update: Memperbarui Limit Group</h2>
    <div class="endpoint-box" style="border-left-color: #ecc94b;">
        <span class="method" style="background:#ecc94b; color:#744210;">PUT</span>
        <span class="url">/api/v1/group.php</span>
    </div>
    <div class="table-container">
        <table>
            <tr><th>Parameter</th><th>Tipe</th><th>Status</th><th>Keterangan</th></tr>
            <tr><td><code>action</code></td><td>String</td><td><span class="badge badge-req">Wajib</span></td><td>Aksi yang akan dilakukan (e.g., <code>create</code>).</td></tr>
            <tr><td><code>group_name</code></td><td>String</td><td><span class="badge badge-req">Wajib</span></td><td>Nama unik paket (e.g., <code>Voucher_10Jam</code>).</td></tr>
            <tr><td><code>device_profile</code></td><td>Integer</td><td><span class="badge badge-req">Wajib</span></td><td>ID dari profil perangkat yang terkait.</td></tr>
            <tr><td><code>time_limit</code></td><td>Integer</td><td><span class="badge badge-opt">Kondisional</span></td><td>Wajib jika type adalah <code>voucher</code> (satuan detik).</td></tr>
            <tr><td><code>quota_download</code></td><td>Integer</td><td><span class="badge badge-opt">Kondisional</span></td><td>Wajib jika type adalah <code>voucher</code> (satuan MB).</td></tr>
            <tr><td><code>quota_upload</code></td><td>Integer</td><td><span class="badge badge-opt">Kondisional</span></td><td>Wajib jika type adalah <code>voucher</code> (satuan MB).</td></tr>
        </table>
    </div>
    <h3>Contoh Payload (Update)</h3>
    <pre>{
    "action": "update",
    "group_name": "Paket_Voucher_5Jam",
    "time_limit": 36000
}</pre>
    <h3>Contoh Respon Berhasil</h3>
    <pre>{
    "status": "success",
    "message": "Limit waktu group Paket_Voucher_5Jam berhasil diperbarui."
}</pre>

    <!-- DELETE SECTION -->
    <h2>4. Delete: Menghapus Group</h2>
    <div class="endpoint-box" style="border-left-color: #e53e3e;">
        <span class="method" style="background: #e53e3e;">DELETE</span>
        <span class="url">/api/v1/group.php</span>
    </div>
    <div class="table-container">
        <table>
            <tr><th>Parameter</th><th>Tipe</th><th>Status</th><th>Keterangan</th></tr>
            <tr><td><code>action</code></td><td>String</td><td><span class="badge badge-req">Wajib</span></td><td>Aksi yang akan dilakukan (e.g., <code>create</code>).</td></tr>
            <tr><td><code>group_name</code></td><td>String</td><td><span class="badge badge-req">Wajib</span></td><td>Nama unik paket (e.g., <code>Voucher_10Jam</code>).</td></tr>
            <tr><td><code>device_profile</code></td><td>Integer</td><td><span class="badge badge-req">Wajib</span></td><td>ID dari profil perangkat yang terkait.</td></tr>
            <tr><td><code>time_limit</code></td><td>Integer</td><td><span class="badge badge-opt">Kondisional</span></td><td>Wajib jika type adalah <code>voucher</code> (satuan detik).</td></tr>
            <tr><td><code>quota_download</code></td><td>Integer</td><td><span class="badge badge-opt">Kondisional</span></td><td>Wajib jika type adalah <code>voucher</code> (satuan MB).</td></tr>
            <tr><td><code>quota_upload</code></td><td>Integer</td><td><span class="badge badge-opt">Kondisional</span></td><td>Wajib jika type adalah <code>voucher</code> (satuan MB).</td></tr>
        </table>
    </div>
    <h3>Contoh Payload (Delete)</h3>
    <pre>{
      "action": "delete",
      "group_name": "Paket_Voucher_Lama"
}</pre>
    <h3>Contoh Respon Berhasil</h3>
    <pre>{
    "status": "success",
    "message": "Group Paket_Voucher_Lama berhasil dihapus dari sistem."
}</pre>

    <h2>📋 Respon Gagal: Data Tidak Ditemukan</h2>
    <p>Respon ini diberikan pada metode <b>PUT</b> atau <b>DELETE</b> jika nama grup yang dituju tidak ada di tabel database.</p>
    <pre>{
    "status": "error",
    "message": "Gagal update/hapus. Group 'Paket_Target' tidak ditemukan di database."
}</pre>

</div>
</body>
</html>