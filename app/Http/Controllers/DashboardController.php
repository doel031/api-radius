<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DashboardController extends Controller
{
    public function index()
    {
        $apiKeys = DB::table('api_keys')->orderBy('id', 'desc')->get();
        $logs = DB::table('api_logs')->orderBy('id', 'desc')->limit(50)->get();
        $users = DB::table('users')->orderBy('id', 'desc')->get();

        return view('dashboard', compact('apiKeys', 'logs', 'users'));
    }

    // --- API KEY MANAGEMENT ---
    public function storeApiKey(Request $request)
    {
        $request->validate(['name' => 'required|string|max:255']);
        
        DB::table('api_keys')->insert([
            'name' => $request->name,
            'key' => 'rad_' . Str::random(40), // Contoh format key
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->back()->with('success', 'API Key berhasil dibuat!');
    }

    public function toggleApiKey($id)
    {
        $key = DB::table('api_keys')->where('id', $id)->first();
        if ($key) {
            DB::table('api_keys')->where('id', $id)->update(['is_active' => !$key->is_active]);
        }
        return redirect()->back()->with('success', 'Status API Key berhasil diperbarui!');
    }

    public function deleteApiKey($id)
    {
        DB::table('api_keys')->where('id', $id)->delete();
        return redirect()->back()->with('success', 'API Key berhasil dihapus!');
    }

    // --- USER MANAGEMENT ---
    public function storeUser(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6',
        ]);

        DB::table('users')->insert([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->back()->with('success', 'User Admin berhasil ditambahkan!');
    }

    public function deleteUser($id)
    {
        // Cegah hapus diri sendiri jika perlu
        DB::table('users')->where('id', $id)->delete();
        return redirect()->back()->with('success', 'User berhasil dihapus!');
    }
}