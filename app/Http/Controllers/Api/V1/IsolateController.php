<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Nas;
use App\Models\RadAcct;
use App\Models\Radusergroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class IsolateController extends Controller
{
    /**
     * Endpoint Restore Bulk Users dengan Payload Dynamic Key
     * Format Key: original_group_1, username_1, original_group_2, username_2, dst.
     */
    public function restore(Request $request)
    {
        $payload = $request->all();

        // 1. Extract dan pasangkan original_group dengan list username secara dinamis
        $groupedData = [];

        foreach ($payload as $key => $value) {
            if (str_starts_with($key, 'original_group_')) {
                // Ambil index/suffix angka di akhir key (misal: "1" dari "original_group_1")
                $index = str_replace('original_group_', '', $key);
                $usernameKey = 'username_' . $index;

                if (isset($payload[$usernameKey]) && is_array($payload[$usernameKey])) {
                    $groupedData[] = [
                        'group' => $value,
                        'users' => $payload[$usernameKey],
                    ];
                }
            }
        }

        if (empty($groupedData)) {
            return response()->json([
                'success' => false,
                'message' => 'Format payload restore tidak valid. Membutuhkan pasangan original_group_N dan username_N.',
            ], 422);
        }

        $restoredUsers = [];

        // 2. Transaksi Database untuk Update Group
        DB::beginTransaction();
        try {
            foreach ($groupedData as $item) {
                $groupName = $item['group'];
                $users     = $item['users'];

                foreach ($users as $username) {
                    Radusergroup::updateOrCreate(
                        ['username' => $username],
                        ['groupname' => $groupName, 'priority' => 1]
                    );

                    $restoredUsers[] = $username;
                }
            }

            DB::commit();

            // 3. Cek status online di radacct & disconnect jika user sedang aktif
            $disconnectedCount = 0;
            foreach ($restoredUsers as $username) {
                if ($this->disconnectIfOnline($username)) {
                    $disconnectedCount++;
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Proses restore profil berhasil dilakukan.',
                'data'    => [
                    'restored_users_count' => count($restoredUsers),
                    'restored_users'       => $restoredUsers,
                    'disconnected_users'   => $disconnectedCount,
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to execute bulk restore: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal memulihkan user: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Endpoint Isolate Users (Array of Users)
     */
    public function isolate(Request $request)
    {
        $request->validate([
            'username'      => 'required|array|min:1',
            'username.*'    => 'string',
            'isolate_group' => 'nullable|string', // Default: 'ISOLATE'
        ]);

        $usernames    = $request->username;
        $isolateGroup = $request->isolate_group ?? 'ISOLATE';

        $isolatedUsers = [];

        DB::beginTransaction();
        try {
            foreach ($usernames as $username) {
                Radusergroup::updateOrCreate(
                    ['username' => $username],
                    ['groupname' => $isolateGroup, 'priority' => 1]
                );

                $isolatedUsers[] = $username;
            }

            DB::commit();

            $disconnectedCount = 0;
            foreach ($isolatedUsers as $username) {
                if ($this->disconnectIfOnline($username)) {
                    $disconnectedCount++;
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Proses isolasi berhasil dilakukan.',
                'data'    => [
                    'isolated_users'     => $isolatedUsers,
                    'disconnected_users' => $disconnectedCount,
                    'isolate_group'      => $isolateGroup,
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to execute bulk isolate: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal melakukan isolasi user: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Helper untuk mengecek status online di radacct & kirim PoD jika user aktif
     */
    private function disconnectIfOnline(string $username): bool
    {
        // Ambil sesi aktif (acctstoptime IS NULL)
        $activeSession = RadAcct::where('username', $username)
            ->whereNull('acctstoptime')
            ->orderBy('acctstarttime', 'desc')
            ->first();

        if (!$activeSession) {
            return false; // User sedang offline
        }

        $nasIp = $activeSession->nasipaddress;

        // Ambil data NAS dari tabel 'nas' FreeRADIUS
        $nas = Nas::where('nasname', $nasIp)->first();

        // Parameter fallback jika record NAS tidak ditemukan atau port bernilai null
        $secret = $nas && $nas->secret ? $nas->secret : 'testing123';
        $port   = $nas && $nas->ports  ? (int) $nas->ports   : 1700;

        return $this->sendPoD($username, $nasIp, $port, $secret, $activeSession->acctsessionid);
    }

    /**
     * Helper eksekusi radclient untuk Packet of Disconnect (PoD) dengan Dynamic Port
     */
    private function sendPoD(string $username, string $nasIp, int $port, string $secret, ?string $sessionId = null): bool
    {
        try {
            $attributes = "User-Name={$username}";
            if ($sessionId) {
                $attributes .= ",Acct-Session-Id={$sessionId}";
            }

            // Command radclient menggunakan port dinamis ($port) dari DB
            $command = sprintf(
                'echo %s | radclient -x %s:%d disconnect %s 2>&1',
                escapeshellarg($attributes),
                escapeshellarg($nasIp),
                $port,
                escapeshellarg($secret)
            );

            exec($command, $output, $returnVar);

            if ($returnVar === 0) {
                Log::info("PoD sukses dikirim ke NAS {$nasIp}:{$port} untuk user {$username}");
                return true;
            }

            Log::warning("PoD gagal dikirim ke NAS {$nasIp}:{$port} untuk user {$username}. Output: " . implode(" ", $output));
            return false;
        } catch (\Exception $e) {
            Log::error("Error executing PoD: " . $e->getMessage());
            return false;
        }
    }
}