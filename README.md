# RadiusAPI - Modern FreeRADIUS REST API Engine

[![Laravel](https://img.shields.io/badge/Laravel-11.x-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.2+-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://php.net)
[![FreeRADIUS](https://img.shields.io/badge/FreeRADIUS-3.x-006699?style=for-the-badge&logo=serverfault&logoColor=white)](https://freeradius.org)
[![MariaDB](https://img.shields.io/badge/MariaDB-11.x-003545?style=for-the-badge&logo=mariadb&logoColor=white)](https://mariadb.org)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg?style=for-the-badge)](https://opensource.org/licenses/MIT)

**RadiusAPI** adalah RESTful API Engine berbasis Laravel 11 yang dirancang khusus untuk mengelola infrastruktur AAA (*Authentication, Authorization, and Accounting*) pada server FreeRADIUS secara terpusat, aman, dan *real-time*.

Engine ini menjembatani sistem eksternal (seperti Billing ISP, CRM, Mobile Apps, atau Portal Pelanggan) dengan database native FreeRADIUS dan perangkat Network Access Server (MikroTik / Cisco router) tanpa perlu melakukan manipulasi database manual atau akses root langsung.

---

## Daftar Isi

- [Arsitektur & Alur Kerja](#arsitektur--alur-kerja)
  - [1. Alur Autentikasi HMAC](#1-alur-autentikasi-hmac)
  - [2. Alur Manajemen NAS & Hot Reload FreeRADIUS](#2-alur-manajemen-nas--hot-reload-freeradius)
  - [3. Alur Isolasi & Pemulihan User (Live Disconnect / PoD)](#3-alur-isolasi--pemulihan-user-live-disconnect--pod)
- [Fitur Utama](#fitur-utama)
- [Daftar Endpoint API (v1)](#daftar-endpoint-api-v1)
- [Spesifikasi Autentikasi HMAC](#spesifikasi-autentikasi-hmac)
  - [Format Signature](#format-signature)
  - [Contoh Kode Generator Signature (PHP)](#contoh-kode-generator-signature-php)
  - [Contoh Request cURL](#contoh-request-curl)
- [Struktur Skema Database](#struktur-skema-database)
- [Dashboard & Dokumentasi Interaktif](#dashboard--dokumentasi-interaktif)
- [Persyaratan Sistem & Instalasi](#persyaratan-sistem--instalasi)

---

## Arsitektur & Alur Kerja

```
[Aplikasi Klien / Billing ISP]
              │
              ▼ (1. HTTPS Request + HMAC Signature)
    [Laravel API Engine]
              │
              ├─► [Middleware: HmacAuthenticate]
              │     ├─ Validasi X-API-KEY & Status Aktif di `api_keys`
              │     ├─ Proteksi Replay Attack (Toleransi waktu ±300 detik)
              │     ├─ Validasi IP Whitelist (jika dikonfigurasi)
              │     ├─ Verifikasi Signature HMAC-SHA256
              │     └─ Pencatatan Audit Log ke `api_logs`
              │
              ├─► [Database FreeRADIUS (MariaDB: raddb)]
              │     ├─ `nas`            ── Router / MikroTik NAS
              │     ├─ `radcheck`       ── Kredensial User (Password)
              │     ├─ `radusergroup`   ── Pemetaan User ke Profil
              │     ├─ `radgroupreply`  ── Parameter & Limit Atribut Profil
              │     └─ `radacct`        ── Sesi Aktif & Accounting
              │
              ├─► [Event Trigger: Reload FreeRADIUS]
              │     └─ Eksekusi `/usr/local/bin/restart_freeradius.sh` saat NAS berubah
              │
              └─► [Real-Time Kick: Packet of Disconnect (PoD)]
                    └─ Eksekusi `radclient` langsung ke IP NAS jika user online
```

### 1. Alur Autentikasi HMAC
1. Setiap request wajib menyertakan 3 header: `X-API-KEY`, `X-TIMESTAMP`, dan `X-SIGNATURE`.
2. Middleware memeriksa selisih timestamp klien dengan server (`abs(time() - timestamp) <= 300 detik`).
3. Sistem mengambil secret key dari database `api_keys` berdasarkan API Key / Client ID.
4. String canonical `{$METHOD}&{$PATH}&{$TIMESTAMP}&{$PAYLOAD}` di-hash menggunakan HMAC-SHA256 dengan secret key.
5. Jika signature cocok dan IP klien diizinkan (IP Whitelist), request diteruskan ke controller. Setiap request (sukses maupun gagal) dicatat ke tabel `api_logs`.

### 2. Alur Manajemen NAS & Hot Reload FreeRADIUS
1. Ketika data router (NAS) ditambah, diubah, atau dihapus melalui `/api/v1/nas`, model Laravel memicu event `created`, `updated`, atau `deleted`.
2. Event tersebut secara otomatis mengeksekusi script sudo:
   ```bash
   sudo /usr/local/bin/restart_freeradius.sh
   ```
3. Script menjalankan `systemctl reload freeradius` sehingga konfigurasi klien baru langsung diterapkan tanpa memutus koneksi sesi pengguna yang sedang aktif (*zero downtime reload*).

### 3. Alur Isolasi & Pemulihan User (Live Disconnect / PoD)
1. **Isolasi (`/api/v1/isolate`):** User dipindahkan ke grup profil isolasi (default: `ISOLATE`).
2. **Restore (`/api/v1/isolate/restore`):** User dikembalikan ke profil paket semula secara dinamis.
3. **Pengecekan Sesi Aktif:** Sistem langsung memeriksa tabel `radacct` (`acctstoptime IS NULL`).
4. **Packet of Disconnect (PoD):** Jika pengguna sedang online, sistem secara otomatis mengeksekusi perintah `radclient` ke IP dan port CoA/PoD NAS:
   ```bash
   echo "User-Name=<username>,Acct-Session-Id=<session_id>" | radclient -x <nas_ip>:<port> disconnect <secret>
   ```
   Sesi user langsung terputus secara *real-time* di MikroTik, memaksa perangkat melakukan *re-connect* dan langsung mendapatkan profil bandwidth/aturan isolasi yang baru.

---

## Fitur Utama

- **Manajemen Router / NAS Lengkap (CRUD):** Tambah, lihat, cari, ubah, dan hapus data NAS client FreeRADIUS dengan verifikasi hot-reload otomatis.
- **Manajemen Paket / Group Fleksibel:**
  - **Profil Normal:** Mendukung mapping grup MikroTik via atribut `devicegroup` (`Mikrotik-Group`).
  - **Profil Voucher / Kuota / Limitasi:** Mendukung pembatasan durasi (`time` ➔ `Max-All-Session`), upload byte limit (`upload` ➔ `Mikrotik-Xmit-Limit`), dan download byte limit (`download` ➔ `Mikrotik-Recv-Limit`).
- **Manajemen Pengguna PPPoE & Hotspot:** Operasi atomik untuk sinkronisasi kredensial pada `radcheck` dan assignment grup pada `radusergroup`.
- **Engine Isolir & Restore Otomatis:** Bulk isolasi pengguna yang menunggak dan pemulihan otomatis saat pembayaran lunas, dilengkapi pemutusan sesi seketika (PoD).
- **Monitoring & Status Real-Time:**
  - Jumlah sesi aktif per perangkat NAS (`active_sessions`).
  - Daftar lengkap pengguna online (IP address yang didapat, MAC Address / Calling Station ID, waktu login, NAS terhubung, dan profil).
- **Admin Web Dashboard:** Panel web untuk mengelola API Key, memonitor log audit akses API, dan mengatur akun administrator.
- **Dokumentasi Interaktif (OpenAPI / Scalar):** Tersedia dokumentasi visual berbasis Scramble di `/docs/api`.

---

## Daftar Endpoint API (v1)

Seluruh endpoint API berada di bawah prefix `/api/v1` dan dilindungi oleh `HmacAuthenticate`.

| Kategori | Method | Endpoint | Deskripsi |
| :--- | :--- | :--- | :--- |
| **NAS** | `GET` | `/api/v1/nas` | Menampilkan seluruh daftar NAS (mendukung `?search=`) |
| | `POST` | `/api/v1/nas` | Mendaftarkan NAS / Router baru |
| | `GET` | `/api/v1/nas/{id}` | Melihat detail satu NAS |
| | `PUT` | `/api/v1/nas/{id}` | Memperbarui data NAS |
| | `DELETE` | `/api/v1/nas/{id}` | Menghapus NAS |
| **Groups** | `GET` | `/api/v1/groups` | Menampilkan semua profil & group berserta limitasi |
| | `POST` | `/api/v1/groups` | Membuat grup/profil baru (Normal atau Voucher) |
| | `PUT` | `/api/v1/groups/{groupname}` | Memperbarui atribut limit/group name |
| | `DELETE` | `/api/v1/groups/{groupname}` | Menghapus seluruh atribut profil grup |
| **Users** | `GET` | `/api/v1/users` | Menampilkan daftar seluruh user dan profilnya |
| | `POST` | `/api/v1/users` | Membuat user PPPoE/Hotspot baru |
| | `GET` | `/api/v1/users/{username}` | Melihat detail kredensial dan grup user |
| | `PUT` | `/api/v1/users/{username}` | Memperbarui password atau profil user |
| | `DELETE` | `/api/v1/users/{username}` | Menghapus user dari FreeRADIUS |
| **Isolir** | `POST` | `/api/v1/isolate` | Bulk isolir user + PoD jika user online |
| | `POST` | `/api/v1/isolate/restore` | Bulk restore profil user + PoD jika user online |
| **Status** | `GET` | `/api/v1/status/nas` | Status koneksi & total sesi aktif per NAS |
| | `GET` | `/api/v1/status/users-online` | Daftar user online, framed IP, MAC, & profil |

---

## Spesifikasi Autentikasi HMAC

### Format Signature
String canonical dibentuk dari 4 komponen yang digabungkan dengan karakter ampersand (`&`):
```text
stringToSign = "{METHOD}&{PATH}&{TIMESTAMP}&{PAYLOAD}"
```
- **`METHOD`**: Huruf kapital (misal: `GET`, `POST`, `PUT`, `DELETE`).
- **`PATH`**: Request URI path diawali tanda garis miring (misal: `/api/v1/users`).
- **`TIMESTAMP`**: Epoch time integer dalam detik (misal: `1726720000`).
- **`PAYLOAD`**: Konten raw request body (kosong `""` untuk method `GET` atau `DELETE` tanpa body).

Signature dihitung dengan:
```php
$signature = hash_hmac('sha256', $stringToSign, $secretKey);
```

### Contoh Kode Generator Signature (PHP)
```php
<?php

$apiKey    = 'rad_live_example12345';
$secretKey = 'your_secret_key_here';
$method    = 'POST';
$path      = '/api/v1/users';
$timestamp = time();

$body = json_encode([
    'username' => 'customer_pppoe_01',
    'password' => 'Rahasia123!',
    'group'    => '10Mbps_Regular',
]);

$stringToSign = "{$method}&{$path}&{$timestamp}&{$body}";
$signature    = hash_hmac('sha256', $stringToSign, $secretKey);

$headers = [
    "X-API-KEY: {$apiKey}",
    "X-TIMESTAMP: {$timestamp}",
    "X-SIGNATURE: {$signature}",
    "Content-Type: application/json",
];
```

### Contoh Request cURL
```bash
TIMESTAMP=$(date +%s)
METHOD="GET"
PATH_URL="/api/v1/status/nas"
PAYLOAD=""
API_KEY="rad_live_example12345"
SECRET_KEY="your_secret_key_here"

STRING_TO_SIGN="${METHOD}&${PATH_URL}&${TIMESTAMP}&${PAYLOAD}"
SIGNATURE=$(echo -n "$STRING_TO_SIGN" | openssl dgst -sha256 -hmac "$SECRET_KEY" | sed 's/^.* //')

curl -X GET "https://radius-admin.gsmnet.co.id/api/v1/status/nas" \
  -H "X-API-KEY: $API_KEY" \
  -H "X-TIMESTAMP: $TIMESTAMP" \
  -H "X-SIGNATURE: $SIGNATURE" \
  -H "Accept: application/json"
```

---

## Struktur Skema Database

Aplikasi beroperasi langsung di atas database FreeRADIUS (`raddb`):

- **Tabel FreeRADIUS Native:**
  - `nas`: Menyimpan identitas NAS (IP/host, nama pendek, tipe router, secret RADIUS, port CoA).
  - `radcheck`: Atribut otentikasi user (username, `Cleartext-Password`, dsb.).
  - `radusergroup`: Pemetaan username ke profil grup beserta prioritasnya.
  - `radgroupreply`: Atribut balasan otorisasi (`Mikrotik-Group`, `Max-All-Session`, limit kuota).
  - `radacct`: Pencatatan sesi login/logout, framed IP address, durasi, pemakaian bandwidth octet.
- **Tabel Manajemen RadiusAPI:**
  - `api_keys`: Menyimpan daftar client API key, secret, hak akses/scopes, IP whitelist, dan rate limit.
  - `api_logs`: Audit trail historis setiap request (endpoint, method, IP pengirim, dan status respons).
  - `users`: Akun administrator web dashboard.

---

## Dashboard & Dokumentasi Interaktif

- **Web Portal:** Akses `https://radius-admin.gsmnet.co.id/`
- **Dashboard Admin:** `/admin/dashboard` & `/admin/api-keys` (Pengelolaan kredensial API Key & pemantauan traffic).
- **Dokumentasi Interaktif:** `/docs/api` (Swagger/Scalar visual documentation via Scramble).

---

## Persyaratan Sistem & Instalasi

### Persyaratan
- Linux Server (Debian 12 / Ubuntu 22.04 LTS direkomendasikan)
- PHP >= 8.2 (ekstensi: `pdo_mysql`, `mbstring`, `openssl`, `curl`, `json`)
- FreeRADIUS Server 3.x
- MariaDB >= 10.11 / MySQL >= 8.0
- Web Server Apache atau Nginx

### Script Hot Reload FreeRADIUS
Pastikan file `/usr/local/bin/restart_freeradius.sh` memiliki izin eksekusi:
```bash
sudo chmod +x /usr/local/bin/restart_freeradius.sh
```
Isi script:
```bash
#!/bin/bash
systemctl reload freeradius
```
Dan pastikan user web server (`www-data`) memiliki hak akses sudoers tanpa password untuk script ini:
```text
www-data ALL=(ALL) NOPASSWD: /usr/local/bin/restart_freeradius.sh
```

---

## Lisensi

Proyek ini dilisensikan di bawah lisensi [MIT](LICENSE).
