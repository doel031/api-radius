<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\RadCheck;
use App\Models\Radusergroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
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
     * Get list semua user beserta group-nya
     */
    public function index()
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
     * Create / Tambah User Baru
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|max:64|unique:radcheck,username',
            'password' => 'required|string|max:253',
            'group'    => 'required|string|max:64',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }
        
        $now = Carbon::now()->toDateTimeString();

        DB::transaction(function () use ($request) {
            // Simpan password ke RadCheck
            RadCheck::create([
                'username'  => $request->username,
                'attribute' => 'Cleartext-Password',
                'op'        => ':=',
                'value'     => $request->password,
            ]);

            // Simpan group ke radusergroup
            Radusergroup::create([
                'username'  => $request->username,
                'groupname' => $request->group,
                'priority'  => 1,
            ]);
        });

        return response()->json([
            'status'  => 'success',
            'message' => 'User FreeRADIUS berhasil dibuat',
            'data'    => [
                'username'     => $request->username,
                'password'     => $request->password,
                'group'        => $request->group,
                'last_updated' => $now,
            ]
        ], 201);
    }

    /**
     * Detail User berdasarkan username
     */
    public function show($username)
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
     * Update Password atau Group
     */
    public function update(Request $request, $username)
    {
        $check = RadCheck::where('username', $username)
            ->where('attribute', 'Cleartext-Password')
            ->first();

        if (!$check) {
            return response()->json(['status' => 'error', 'message' => 'User tidak ditemukan'], 404);
        }

        $validator = Validator::make($request->all(), [
            'password' => 'nullable|string|max:253',
            'group'    => 'nullable|string|max:64',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        DB::transaction(function () use ($request, $check, $username) {
            if ($request->has('password')) {
                $check->update(['value' => $request->password]);
            }

            if ($request->has('group')) {
                Radusergroup::updateOrCreate(
                    ['username' => $username],
                    ['groupname' => $request->group, 'priority' => 1]
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
     */
    public function destroy($username)
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