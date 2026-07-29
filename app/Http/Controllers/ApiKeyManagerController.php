<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ApiKeyManagerController extends Controller
{
    public function index()
    {
        $apiKeys = DB::table('api_keys')->orderBy('id', 'desc')->get();
        
        // Decode JSON scopes untuk setiap key
        foreach ($apiKeys as $key) {
            $key->scopes = json_decode($key->scopes ?? '[]');
        }

        $totalKeys = $apiKeys->count();
        $activeKeys = $apiKeys->where('is_active', true)->count();
        $totalRequests = DB::table('api_logs')->count();

        $recentLogs = DB::table('api_logs')->orderBy('id', 'desc')->limit(30)->get();

        return view('admin.api_keys.index', compact('apiKeys', 'totalKeys', 'activeKeys', 'totalRequests', 'recentLogs'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'scopes' => 'nullable|array',
            'ip_whitelist' => 'nullable|string',
            'rate_limit' => 'required|integer|min:1',
            'expires_at' => 'nullable|date',
        ]);

        $plainKey = 'rad_live_' . Str::random(36);

        DB::table('api_keys')->insert([
            'name' => $request->name,
            'key' => $plainKey,
            'scopes' => json_encode($request->scopes ?? []),
            'ip_whitelist' => $request->ip_whitelist,
            'rate_limit' => $request->rate_limit,
            'is_active' => true,
            'expires_at' => $request->expires_at,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->back()->with('new_key', $plainKey)->with('success', 'API Key berhasil dibuat!');
    }

    public function regenerate($id)
    {
        $newKey = 'rad_live_' . Str::random(36);

        DB::table('api_keys')->where('id', $id)->update([
            'key' => $newKey,
            'updated_at' => now(),
        ]);

        return redirect()->back()->with('new_key', $newKey)->with('success', 'API Key berhasil di-rotate/regenerate!');
    }

    public function toggle($id)
    {
        $key = DB::table('api_keys')->where('id', $id)->first();
        if ($key) {
            DB::table('api_keys')->where('id', $id)->update(['is_active' => !$key->is_active]);
        }
        return redirect()->back()->with('success', 'Status API Key berhasil diperbarui!');
    }

    public function destroy($id)
    {
        DB::table('api_keys')->where('id', $id)->delete();
        return redirect()->back()->with('success', 'API Key telah dihapus!');
    }
}