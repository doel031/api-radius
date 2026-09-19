<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\RadCheck;
use App\Models\Radusergroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class UserController extends Controller
{
    /**
     * Helper Function: Mencari timestamp paling baru dari radcheck & radusergroup
     */
    private function getLatestTimestamp($radcheck, $radusergroup)
    {
        $dates = collect();

        if ($radcheck) {
            if ($radcheck->created_at) $dates->push(Carbon::parse($radcheck->created_at));
            if ($radcheck->updated_at) $dates->push(Carbon::parse($radcheck->updated_at));
        }

        if ($radusergroup) {
            if ($radusergroup->created_at) $dates->push(Carbon::parse($radusergroup->created_at));
            if ($radusergroup->updated_at) $dates->push(Carbon::parse($radusergroup->updated_at));
        }

        // Ambil tanggal paling akhir (max), ubah format ke String sesuai timezone di .env
        $latest = $dates->max();

        return $latest ? $latest->timezone(config('app.timezone'))->toDateTimeString() : null;
    }

    /**
     * Daftar User RADIUS
     *
     * Mengambil daftar seluruh pengguna PPPoE/Hotspot yang terdaftar di FreeRADIUS beserta password dan profil grupnya.
     */
    public function index(): JsonResponse
    {
        $users = RadCheck::where('attribute', 'Cleartext-Password')
            ->get()
            ->map(function ($check) {
                $group = Radusergroup::where('username', $check->username)->first();
                return [
                    'username' => $check->username,
                    'password' => $check->value,
                    'group'    => $group ? $group->groupname : null,
                    'last_updated' => $this->getLatestTimestamp($check, $group),
                ];
            });

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar user berhasil diambil',
            'total'  => $users->count(),
            'data'   => $users
        ], 200);
    }

    /**
     * Tambah User Baru
     *
     * Mendaftarkan pengguna PPPoE/Hotspot baru ke tabel `radcheck` (Cleartext-Password) dan `radusergroup` secara atomik.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'username' => 'required|string|max:64|unique:radcheck,username',
            'password' => 'required|string|max:253',
            'group'    => 'required|string|max:64',
        ]);
        
        $now = Carbon::now()->toDateTimeString();

        DB::transaction(function () use ($validated) {
            // Simpan password ke RadCheck
            RadCheck::create([
                'username'  => $validated['username'],
                'attribute' => 'Cleartext-Password',
                'op'        => ':=',
                'value'     => $validated['password'],
            ]);

            // Simpan group ke radusergroup
            Radusergroup::create([
                'username'  => $validated['username'],
                'groupname' => $validated['group'],
                'priority'  => 1,
            ]);
        });

        return response()->json([
            'status'  => 'success',
            'message' => 'User FreeRADIUS berhasil dibuat',
            'data'    => [
                'username'     => $validated['username'],
                'password'     => $validated['password'],
                'group'        => $validated['group'],
                'last_updated' => $now,
            ]
        ], 201);
    }

    /**
     * Detail User
     *
     * Melihat informasi detail kredensial dan grup profil dari satu user berdasarkan username.
     */
    public function show($username): JsonResponse
    {
        $check = RadCheck::where('username', $username)
            ->where('attribute', 'Cleartext-Password')
            ->first();

        if (!$check) {
            return response()->json(['status' => 'error', 'message' => 'User tidak ditemukan'], 404);
        }

        $group = Radusergroup::where('username', $username)->first();

        return response()->json([
            'status' => 'success',
            'data'   => [
                'username' => $check->username,
                'password' => $check->value,
                'group'    => $group ? $group->groupname : null,
                'last_updated' => $this->getLatestTimestamp($check, $group),
            ]
        ], 200);
    }

    /**
     * Update Password atau Profil User
     *
     * Memperbarui password atau grup profil pengguna. Semua field bersifat opsional (kirim field yang ingin diubah).
     */
    public function update(Request $request, $username): JsonResponse
    {
        $check = RadCheck::where('username', $username)
            ->where('attribute', 'Cleartext-Password')
            ->first();

        if (!$check) {
            return response()->json(['status' => 'error', 'message' => 'User tidak ditemukan'], 404);
        }

        $validated = $request->validate([
            'password' => 'nullable|string|max:253',
            'group'    => 'nullable|string|max:64',
        ]);

        DB::transaction(function () use ($validated, $check, $username) {
            if (isset($validated['password']) && $validated['password'] !== '') {
                $check->update(['value' => $validated['password']]);
            }

            if (isset($validated['group']) && $validated['group'] !== '') {
                Radusergroup::updateOrCreate(
                    ['username' => $username],
                    ['groupname' => $validated['group'], 'priority' => 1]
                );
            }
        });
        
        // Ambil data terbaru setelah di-update
        $check->refresh();
        $group = Radusergroup::where('username', $username)->first();

        return response()->json([
            'status'  => 'success',
            'message' => 'User FreeRADIUS berhasil diperbarui',
            'data'    => [
                'username'     => $check->username,
                'password'     => $check->value,
                'group'        => $group ? $group->groupname : null,
                'last_updated' => $this->getLatestTimestamp($check, $group),
            ]
        ], 200);
    }

    /**
     * Hapus User
     *
     * Menghapus user secara permanen dari tabel `radcheck` dan `radusergroup`.
     */
    public function destroy($username): JsonResponse
    {
        $check = RadCheck::where('username', $username)->first();

        if (!$check) {
            return response()->json(['status' => 'error', 'message' => 'User tidak ditemukan'], 404);
        }

        DB::transaction(function () use ($username) {
            RadCheck::where('username', $username)->delete();
            Radusergroup::where('username', $username)->delete();
        });

        return response()->json([
            'status'  => 'success',
            'message' => 'User FreeRADIUS berhasil dihapus'
        ], 200);
    }
}