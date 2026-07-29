<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class StatusController extends Controller
{
    // Status NAS dan jumlah koneksi aktif per NAS
    public function nasStatus() {
        $nasList = DB::table('nas')
            ->leftJoin('radacct', function($join) {
                $join->on('nas.nasname', '=', 'radacct.nasipaddress')
                     ->whereNull('radacct.acctstoptime');
            })
            ->select('nas.id', 'nas.nasname', 'nas.shortname', DB::raw('COUNT(radacct.radacctid) as active_sessions'))
            ->groupBy('nas.id', 'nas.nasname', 'nas.shortname')
            ->get();

        return response()->json($nasList);
    }

    // Daftar user yang sedang online beserta profilnya
    public function onlineUsers() {
        $onlineUsers = DB::table('radacct')
            ->leftJoin('radusergroup', 'radacct.username', '=', 'radusergroup.username')
            ->whereNull('radacct.acctstoptime')
            ->select(
                'radacct.username',
                'radacct.nasipaddress',
                'radacct.framedipaddress',
                'radacct.acctstarttime',
                'radacct.callingstationid', // MAC Address / Phone number
                DB::raw('COALESCE(radusergroup.groupname, "default") as profile') // Mengambil groupname sebagai profile
            )
            ->get();

        return response()->json([
            'total_online' => $onlineUsers->count(),
            'users' => $onlineUsers
        ]);
    }
}