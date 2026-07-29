<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApiKeyMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $keyHeader = $request->header('X-API-KEY');
        $apiKey = DB::table('api_keys')->where('key', $keyHeader)->where('is_active', true)->first();

        // Cek jika key tidak valid
        if (!$apiKey) {
            // Catat log gagal
            $this->logRequest($request, $keyHeader, 401);
            return response()->json(['message' => 'Unauthorized: Invalid or Inactive API Key'], 401);
        }

        // Update last used
        DB::table('api_keys')->where('id', $apiKey->id)->update(['last_used_at' => now()]);

        // Lanjutkan request
        $response = $next($request);

        // Catat log sukses
        $this->logRequest($request, $keyHeader, $response->getStatusCode());

        return $response;
    }

    private function logRequest(Request $request, $key, $status) {
        DB::table('api_logs')->insert([
            'api_key' => $key ?? 'NO_KEY',
            'endpoint' => $request->path(),
            'method' => $request->method(),
            'ip_address' => $request->ip(),
            'response_status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}