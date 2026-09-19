<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class StatusController extends Controller
{
    /**
     * Status NAS & Sesi Aktif
     *
     * Mengambil daftar seluruh perangkat NAS / Router yang terdaftar beserta jumlah sesi pengguna yang sedang aktif terkoneksi saat ini.
     */
    public function nasStatus(): JsonResponse
    {
        $nasList = DB::table('nas')
            ->leftJoin('radacct', function ($join) {
                $join->on('nas.nasname', '=', 'radacct.nasipaddress')
                     ->whereNull('radacct.acctstoptime');
            })
            ->select('nas.id', 'nas.nasname', 'nas.shortname', DB::raw('COUNT(radacct.radacctid) as active_sessions'))
            ->groupBy('nas.id', 'nas.nasname', 'nas.shortname')
            ->get();

        return response()->json($nasList);
    }

    /**
     * Daftar User Online
     *
     * Mengambil daftar seluruh pengguna PPPoE/Hotspot yang sedang online (sesi aktif pada tabel radacct) beserta alamat IP, MAC Address, router NAS, waktu login, dan profil grupnya.
     */
    public function onlineUsers(): JsonResponse
    {
        $onlineUsers = DB::table('radacct')
            ->leftJoin('radusergroup', 'radacct.username', '=', 'radusergroup.username')
            ->whereNull('radacct.acctstoptime')
            ->select(
                'radacct.username',
                'radacct.nasipaddress',
                'radacct.framedipaddress',
                'radacct.acctstarttime',
                'radacct.callingstationid',
                DB::raw('COALESCE(radusergroup.groupname, "default") as profile')
            )
            ->get();

        return response()->json([
            'total_online' => $onlineUsers->count(),
            'users'        => $onlineUsers,
        ]);
    }
}