<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\ApiKey;
use App\Models\ApiLog;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HmacAuthenticate
{
    public function handle(Request $request, Closure $next): Response
    {
        $clientId        = $request->header('X-API-KEY'); // Identifier Client / ID Key
        $timestamp       = $request->header('X-TIMESTAMP');
        $clientSignature = $request->header('X-SIGNATURE');

        // 1. Cek keberadaan Header wajib
        if (!$clientId || !$timestamp || !$clientSignature) {
            return $this->responseAndLog($request, 'Header autentikasi HMAC tidak lengkap', 401, $clientId);
        }

        // 2. Proteksi Replay Attack (Toleransi 5 menit)
        if (abs(time() - (int) $timestamp) > 300) {
            return $this->responseAndLog($request, 'Request kadaluarsa / timestamp tidak valid', 401, $clientId);
        }

        // 3. Cari API Key dari Database
        $clientKey = ApiKey::where('id', $clientId)
            ->orWhere('name', $clientId)
            ->where('is_active', true)
            ->first();

        if (!$clientKey) {
            return $this->responseAndLog($request, 'API Key / Client ID tidak valid atau tidak aktif', 401, $clientId);
        }

        // 4. Cek Masa Kadaluarsa
        if ($clientKey->expires_at && $clientKey->expires_at->isPast()) {
            return $this->responseAndLog($request, 'API Key sudah kadaluarsa', 401, $clientId);
        }

        // 5. Cek IP Whitelist (Jika diisi di database)
        if (!empty($clientKey->ip_whitelist)) {
            $allowedIps = array_map('trim', explode(',', $clientKey->ip_whitelist));
            if (!in_array($request->ip(), $allowedIps)) {
                return $this->responseAndLog($request, 'IP Address (' . $request->ip() . ') tidak diizinkan', 403, $clientId);
            }
        }

        // 6. Rekonstruksi Payload & Signature (Gunakan kolom $clientKey->key sebagai Secret)
        $method  = strtoupper($request->getMethod());
        $path    = '/' . ltrim($request->path(), '/');
        $payload = $request->getContent();

        $stringToSign = "{$method}&{$path}&{$timestamp}&{$payload}";
        $expectedSignature = hash_hmac('sha256', $stringToSign, $clientKey->key);

        // 7. Verifikasi Signature
        if (!hash_equals($expectedSignature, $clientSignature)) {
            return $this->responseAndLog($request, 'Signature HMAC tidak valid', 401, $clientId);
        }

        // 8. Lanjutkan Request dan Ambil Response
        $response = $next($request);

        // 9. Update last_used_at & simpan log sukses
        $clientKey->update(['last_used_at' => now()]);
        
        ApiLog::create([
            'api_key'         => $clientId,
            'endpoint'        => $path,
            'method'          => $method,
            'ip_address'      => $request->ip(),
            'response_status' => $response->getStatusCode(),
        ]);

        return $response;
    }

    /**
     * Helper response error sekaligus logging ke database.
     */
    private function responseAndLog(Request $request, string $message, int $status, ?string $apiKey): Response
    {
        ApiLog::create([
            'api_key'         => $apiKey,
            'endpoint'        => '/' . ltrim($request->path(), '/'),
            'method'          => strtoupper($request->getMethod()),
            'ip_address'      => $request->ip(),
            'response_status' => $status,
        ]);

        return response()->json([
            'status'  => 'error',
            'message' => $message
        ], $status);
    }
}