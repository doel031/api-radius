<?php

use Dedoc\Scramble\Http\Middleware\RestrictedDocsAccess;

return [
    /*
     * Which routes to document. String or array form; use Scramble::routes() for custom selection.
     */
    'api_path' => 'api',

    /*
     * Your API domain. By default, app domain is used.
     */
    'api_domain' => null,

    /*
     * The path where your OpenAPI specification will be exported.
     */
    'export_path' => 'api.json',

    /*
     * Cache configuration for the generated OpenAPI document.
     */
    'cache' => [
        'key' => 'scramble.openapi',
        'store' => 'file',
    ],

    'info' => [
        /*
         * API version.
         */
        'version' => '1.0.0',

        /*
         * Description rendered on the home page of the API documentation (`/docs/api`).
         */
        'description' => '
## Selamat Datang di RadiusAPI Documentation

**RadiusAPI** adalah RESTful API Engine berbasis Laravel 11 untuk manajemen terpusat server **FreeRADIUS 3.x** pada infrastruktur jaringan ISP (MikroTik / Cisco), Hotspot, dan PPPoE.

Engine ini mengelola tabel native FreeRADIUS (`nas`, `radcheck`, `radgroupreply`, `radusergroup`, `radacct`) secara atomik, aman, dan *real-time*.

---

### 🔐 Panduan Autentikasi HMAC-SHA256

Seluruh endpoint pada prefix `/api/v1/*` diproteksi oleh autentikasi **HMAC (Hash-based Message Authentication Code)** menggunakan SHA-256.

Setiap request wajib menyertakan **3 Header Utama**:

| Header | Tipe | Keterangan |
| :--- | :--- | :--- |
| `X-API-KEY` | String | Identifier Client Key (ID atau Nama Key di sistem) |
| `X-TIMESTAMP` | Integer | Unix Epoch Timestamp dalam detik (contoh: `1726720000`). Toleransi waktu maksimal **±300 detik (5 menit)** untuk proteksi Replay Attack. |
| `X-SIGNATURE` | String | Signature HMAC-SHA256 dari string canonical request |

#### 1. Rumus Pembentukan Canonical String
Canonical string dibentuk dengan menggabungkan 4 komponen menggunakan pemisah tanda ampersand (`&`):
```text
StringToSign = METHOD + "&" + PATH + "&" + TIMESTAMP + "&" + PAYLOAD
```
- **`METHOD`**: Huruf kapital HTTP method (misal: `GET`, `POST`, `PUT`, `DELETE`).
- **`PATH`**: Request URI path lengkap diawali garis miring (misal: `/api/v1/users` atau `/api/v1/status/nas`).
- **`TIMESTAMP`**: Nilai integer epoch time yang sama persis dengan yang dikirim di header `X-TIMESTAMP`.
- **`PAYLOAD`**: Konten raw request body JSON. Untuk request tanpa body (seperti `GET` atau `DELETE`), gunakan string kosong `""`.

#### 2. Perhitungan Signature
```text
Signature = hash_hmac("sha256", StringToSign, SECRET_KEY)
```

#### 3. Contoh Implementasi PHP
```php
$apiKey    = "1"; // ID atau Nama API Key
$secretKey = "rad_live_example_secret_key";
$method    = "POST";
$path      = "/api/v1/users";
$timestamp = time();

$payload = json_encode([
    "username" => "customer_01",
    "password" => "Rahasia123!",
    "group"    => "100M"
]);

$stringToSign = "{$method}&{$path}&{$timestamp}&{$payload}";
$signature    = hash_hmac("sha256", $stringToSign, $secretKey);

$headers = [
    "X-API-KEY: {$apiKey}",
    "X-TIMESTAMP: {$timestamp}",
    "X-SIGNATURE: {$signature}",
    "Content-Type: application/json",
    "Accept: application/json"
];
```

#### 4. Contoh Request cURL
```bash
TIMESTAMP=$(date +%s)
METHOD="GET"
PATH_URL="/api/v1/status/nas"
PAYLOAD=""
API_KEY="1"
SECRET_KEY="rad_live_example_secret_key"

STRING_TO_SIGN="${METHOD}&${PATH_URL}&${TIMESTAMP}&${PAYLOAD}"
SIGNATURE=$(echo -n "$STRING_TO_SIGN" | openssl dgst -sha256 -hmac "$SECRET_KEY" | sed "s/^.* //")

curl -X GET "https://radius-admin.gsmnet.co.id/api/v1/status/nas" \\
  -H "X-API-KEY: $API_KEY" \\
  -H "X-TIMESTAMP: $TIMESTAMP" \\
  -H "X-SIGNATURE: $SIGNATURE" \\
  -H "Accept: application/json"
```

---

### ⚡ Fitur Utama & Alur Kerja Otomatis

1. **Auto-Reload FreeRADIUS (Zero-Downtime):**
   Setiap kali ada penambahan, pembaruan, atau penghapusan router NAS melalui `/api/v1/nas`, sistem secara otomatis mengeksekusi reload service FreeRADIUS di latar belakang tanpa memutus koneksi pelanggan yang sedang online.

2. **Real-time Disconnect (Packet of Disconnect / PoD):**
   Saat melakukan eksekusi isolir (`POST /api/v1/isolate`) atau pemulihan profil (`POST /api/v1/isolate/restore`), sistem otomatis memeriksa sesi online pengguna di tabel `radacct`. Jika user sedang aktif, perintah `radclient` langsung dikirimkan ke IP NAS router untuk memutus koneksi seketika sehingga profil baru langsung aktif saat koneksi ulang.

3. **Manajemen Profil Normal vs Voucher:**
   - **Normal:** Menggunakan parameter `devicegroup` yang otomatis dipetakan ke atribut `Mikrotik-Group`.
   - **Voucher / Kuota:** Mendukung limit durasi (`time` ➔ `Max-All-Session`), batas upload (`upload` ➔ `Mikrotik-Xmit-Limit`), dan batas download (`download` ➔ `Mikrotik-Recv-Limit`).

---

### 📊 Kode Status Respons HTTP

- `200 OK` / `201 Created`: Permintaan berhasil diproses.
- `401 Unauthorized`: Header autentikasi HMAC tidak lengkap, signature tidak cocok, timestamp kadaluarsa, atau API Key dinonaktifkan (revoked).
- `403 Forbidden`: IP pengirim tidak terdaftar dalam IP Whitelist kunci akses.
- `404 Not Found`: Data (NAS, User, atau Group) tidak ditemukan.
- `422 Unprocessable Content`: Validasi input request tidak sesuai spesifikasi.
- `500 Internal Server Error`: Terjadi kegagalan pemrosesan pada database atau sistem server.
        ',
    ],

    'ui' => [
        'title' => 'RadiusAPI - Dokumentasi REST API FreeRADIUS Engine',
    ],

    'renderer' => 'elements',

    'renderers' => [
        'elements' => [
            'view' => 'scramble::docs',
            'theme' => 'light',
            'hideTryIt' => false,
            'hideSchemas' => false,
            'logo' => '',
            'tryItCredentialsPolicy' => 'include',
            'layout' => 'responsive',
            'router' => 'hash',
        ],
        'scalar' => [
            'view' => 'scramble::scalar',
            'cdn' => 'https://cdn.jsdelivr.net/npm/@scalar/api-reference',
            'theme' => 'laravel',
            'proxyUrl' => 'https://proxy.scalar.com',
            'darkMode' => false,
            'showDeveloperTools' => 'never',
            'agent' => ['disabled' => true],
            'credentials' => 'include',
        ],
    ],

    'servers' => [
        'Production Server' => env('APP_URL', 'https://radius-admin.gsmnet.co.id') . '/api',
    ],

    'enum_cases_description_strategy' => 'description',
    'enum_cases_names_strategy' => false,
    'flatten_deep_query_parameters' => true,

    'middleware' => [
        'web',
        RestrictedDocsAccess::class,
    ],

    'extensions' => [],
    'security_strategy' => null,
];
