<?php
// Letak file ini di api/v1/index.php
include_once(dirname(__FILE__) . "/../config.php");
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$baseUrl = $protocol . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) . "/";
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RADIUS API Gateway Hub - GSMNET</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background-color: #f7fafc; color: #2d3748; padding: 50px 20px; }
        .hub-container { max-width: 800px; margin: 0 auto; }
        h1 { font-size: 28px; color: #1a202c; margin-bottom: 10px; border-bottom: 3px solid #3182ce; padding-bottom: 10px; }
        p.subtitle { color: #718096; margin-bottom: 30px; font-size: 16px; }
        .grid-menu { display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 20px; }
        .menu-card { background: #fff; border: 1px solid #e2e8f0; padding: 25px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.02); transition: all 0.2s ease; text-decoration: none; color: inherit; display: flex; flex-direction: column; }
        .menu-card:hover { transform: translateY(-3px); box-shadow: 0 10px 15px rgba(0,0,0,0.05); border-color: #3182ce; }
        .menu-card h2 { font-size: 18px; color: #2b6cb0; margin-bottom: 8px; display: flex; align-items: center; gap: 10px; }
        .menu-card p { font-size: 14px; color: #4a5568; line-height: 1.5; }
        .footer { text-align: center; margin-top: 50px; color: #a0aec0; font-size: 13px; border-top: 1px solid #e2e8f0; padding-top: 20px; }
    </style>
</head>
<body>

<div class="hub-container">
    <h1>GSMNET Core RADIUS API Gateway v1</h1>
    <p class="subtitle">Silakan pilih modul dokumentasi di bawah ini untuk melihat detail spesifikasi parameter, security header, dan contoh integrasi.</p>

    <div class="grid-menu">
        <a href="doc/otentikasi.php" class="menu-card">
            <h2>🔒 Otentikasi Keamanan</h2>
            <p>Spesifikasi enkripsi tanda tangan HMAC SHA256, parameter header wajib, dan contoh pembuatan signature di sisi client billing.</p>
        </a>

       <a href="doc/nas.php" class="menu-card">
    <h2>📡 Endpoint NAS Management</h2>
    <p>Pengelolaan whitelist perangkat RADIUS Gateway (CRUD) dengan fitur sinkronisasi otomatis untuk memastikan konfigurasi perangkat siap digunakan secara aman.</p>
</a>

	<a href="doc/group.php" class="menu-card">
    	    <h2>📁 Endpoint Group Management</h2>
	    <p>Manajemen lengkap (CRUD) pendaftaran profil paket baru untuk membedakan perlakuan antara user reguler dan user voucher.</p>
	</a>

    <a href="doc/users.php" class="menu-card">
        <h2>👤 Endpoint User Management</h2>
        <p>Manajemen lengkap (CRUD) pendaftaran profil paket baru untuk membedakan perlakuan antara user reguler dan user voucher.</p>
    </a>

	<a href="doc/isolate.php" class="menu-card">
            <h2>📡 Endpoint Isolate</h2>
            <p>Manajemen status pemutusan akun otomatis (tunggakan/off), pemulihan paket pelanggan, dan interaksi kick-session.</p>
        </a>

        <a href="doc/status.php" class="menu-card">
            <h2>🔍 Endpoint Check Status</h2>
            <p>Monitoring kapasitas infrastruktur jaringan internal secara global, verifikasi status online/offline, dan informasi detail session target user.</p>
        </a>

    </div>

    <div class="footer">
        Core RADIUS Hub API &copy; 2026 gsmnet.co.id. Base URL API: <code><?php echo $baseUrl; ?></code>
    </div>
</div>

</body>
</html>
