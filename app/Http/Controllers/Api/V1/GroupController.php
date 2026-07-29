<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\RadGroupReply;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class GroupController extends Controller
{
    /**
     * Map Alias Key ke Atribut Asli FreeRADIUS
     */
    private $attributeMap = [
        'time'     => 'Max-All-Session',
        'upload'   => 'Mikrotik-Xmit-Limit',
        'download' => 'Mikrotik-Recv-Limit',
        'devicegroup'    => 'Mikrotik-Group',
    ];

    /**
     * READ: Menampilkan daftar group beserta alias nama field
     */
    public function index()
    {
        $allowedAttributes = array_values($this->attributeMap);

        $records = RadGroupReply::whereIn('attribute', $allowedAttributes)->get();

        $grouped = $records->groupBy('groupname')->map(function ($items, $groupName) {
            $attributes = $items->pluck('value', 'attribute')->toArray();

            $hasVoucherAttr = array_key_exists('Max-All-Session', $attributes) ||
                              array_key_exists('Mikrotik-Xmit-Limit', $attributes) ||
                              array_key_exists('Mikrotik-Recv-Limit', $attributes);

            $hasNormalAttr = array_key_exists('Mikrotik-Group', $attributes);

            // Jika punya atribut limit/session MAKA Tipe Voucher, jika hanya Group MAKA Normal
            $type = $hasVoucherAttr ? 'voucher' : ($hasNormalAttr ? 'normal' : 'unknown');

            // Mengambil nilai created_at dan updated_at terbaru di antara baris-baris dalam group ini
            $latestCreate = $items->max('created_at');
            $latestUpdate = $items->max('updated_at');

            return [
                'groupname' => $groupName,
                'type'      => $type,
                'devicegroup'     => $attributes['Mikrotik-Group'] ?? null,
                'time'      => $attributes['Max-All-Session'] ?? 0,
                'upload'    => $attributes['Mikrotik-Xmit-Limit'] ?? 0,
                'download'  => $attributes['Mikrotik-Recv-Limit'] ?? 0,
                // Hanya tampilkan updated_at di JSON response
                'last_updated'  => $latestUpdate ? \Carbon\Carbon::parse($latestUpdate)->toDateTimeString() : \Carbon\Carbon::parse($latestCreate)->toDateTimeString(),
            ];
        })->values();

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar group berhasil diambil',
            'total'  => $grouped->count(),
            'data'   => $grouped
        ]);
    }

    /**
     * CREATE: Membuat group baru menggunakan field alias (devicegroup, Time, Upload, Download)
     */
    public function store(Request $request)
    {
        // 1. Validasi Input
        $validated = $request->validate([
            'groupname'   => 'required|string|max:64',
            'devicegroup' => 'required|string|max:64',
            'time'        => 'nullable|numeric',
            'upload'      => 'nullable|numeric',
            'download'    => 'nullable|numeric',
        ]);

        // 2. Cek apakah groupname sudah terdaftar di database
        $groupExists = RadGroupReply::where('groupname', $validated['groupname'])->exists();
        if ($groupExists) {
            return response()->json([
                'status'  => 'error',
                'message' => "Gagal membuat group. Nama group '{$validated['groupname']}' sudah terdaftar di database."
            ], 422);
        }

        $hasTime     = !is_null($request->input('time')) && $request->input('time') !== '';
        $hasUpload   = !is_null($request->input('upload')) && $request->input('upload') !== '';
        $hasDownload = !is_null($request->input('download')) && $request->input('download') !== '';

        $isVoucher = $hasTime || $hasUpload || $hasDownload;
        $now = now();

        // 3. Simpan ke Database
        DB::beginTransaction();
        try {
            $recordsToInsert = [];

            // 1. Simpan 'Group' (Mikrotik-Group) -> Selalu Wajib untuk Normal & Voucher
            $recordsToInsert[] = [
                'groupname' => $validated['groupname'],
                'attribute' => 'Mikrotik-Group',
                'op'        => ':=',
                'value'     => $validated['devicegroup'],
                'created_at' => $now, // Disimpan ke DB
            ];

            // 2. Jika tipe Voucher, simpan atribut-atribut opsional yang dikirim
            if ($isVoucher) {
                if ($hasTime) {
                    $recordsToInsert[] = [
                        'groupname' => $validated['groupname'],
                        'attribute' => 'Max-All-Session',
                        'op'        => ':=',
                        'value'     => (string) $validated['time'],
                        'created_at' => $now,
                    ];
                }

                if ($hasUpload) {
                    $recordsToInsert[] = [
                        'groupname' => $validated['groupname'],
                        'attribute' => 'Mikrotik-Xmit-Limit',
                        'op'        => ':=',
                        'value'     => (string) $validated['upload'],
                        'created_at' => $now,
                    ];
                }

                if ($hasDownload) {
                    $recordsToInsert[] = [
                        'groupname' => $validated['groupname'],
                        'attribute' => 'Mikrotik-Recv-Limit',
                        'op'        => ':=',
                        'value'     => (string) $validated['download'],
                        'created_at' => $now,
                    ];
                }
            }

            // Simpan ke database radgroupreply
            RadGroupReply::insert($recordsToInsert);
            DB::commit();

            // Susun response balasan persis dengan format key input
            return response()->json([
                'status'  => 'success',
                'type'    => $isVoucher ? 'voucher' : 'normal',
                'message' => 'Group berhasil dibuat',
                'data'    => [
                    'groupname' => $validated['groupname'],
                    'devicegroup'     => $validated['devicegroup'],
                    'time'      => $validated['time'] ?? 0,
                    'upload'    => $validated['upload'] ?? 0,
                    'download'  => $validated['download'] ?? 0,
                    'created_at'  => $now->toDateTimeString(),
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * UPDATE: Memperbarui satu atau beberapa atribut sekaligus berdasarkan groupname
     */
    public function update(Request $request, $groupname)
    {
        // Validasi input (semua opsional/nullable agar fleksibel saat update)
        $validated = $request->validate([
            'devicegroup' => 'nullable|string|max:64',
            'time'        => 'nullable|numeric',
            'upload'      => 'nullable|numeric',
            'download'    => 'nullable|numeric',
        ]);

        // Cek apakah ada minimal 1 data yang dikirim untuk diupdate
        $inputData = array_filter([
            'devicegroup' => $request->input('devicegroup'),
            'time'        => $request->input('time'),
            'upload'      => $request->input('upload'),
            'download'    => $request->input('download'),
        ], function ($value) {
            return !is_null($value) && $value !== '';
        });

        if (empty($inputData)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Tidak ada atribut yang dikirim untuk diperbarui.'
            ], 422);
        }

        // Cek apakah groupname ada di database
        $exists = RadGroupReply::where('groupname', $groupname)->exists();
        if (!$exists) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Group tidak ditemukan.'
            ], 404);
        }

        DB::beginTransaction();
        try {
            $now = now();

            // Loop dan update/create atribut yang dikirim
            foreach ($inputData as $aliasKey => $val) {
                $realAttribute = $this->attributeMap[$aliasKey];

                RadGroupReply::updateOrCreate(
                    [
                        'groupname' => $groupname,
                        'attribute' => $realAttribute,
                    ],
                    [
                        'op'        => ':=',
                        'value'     => (string) $val,
                        'updated_at' => $now,
                    ]
                );
            }

            DB::commit();

            // Ambil data terbaru seluruh atribut group ini untuk display
            $updatedRecords = RadGroupReply::where('groupname', $groupname)
                ->whereIn('attribute', array_values($this->attributeMap))
                ->get();

            $attributes = $updatedRecords->pluck('value', 'attribute')->toArray();

            $hasVoucherAttr = array_key_exists('Max-All-Session', $attributes) ||
                              array_key_exists('Mikrotik-Xmit-Limit', $attributes) ||
                              array_key_exists('Mikrotik-Recv-Limit', $attributes);

            return response()->json([
                'status'  => 'success',
                'type'    => $hasVoucherAttr ? 'voucher' : 'normal',
                'message' => "Group {$groupname} berhasil diperbarui",
                'data'    => [
                    'groupname'   => $groupname,
                    'devicegroup' => $attributes['Mikrotik-Group'] ?? null,
                    'time'        => $attributes['Max-All-Session'] ?? 0,
                    'upload'      => $attributes['Mikrotik-Xmit-Limit'] ?? 0,
                    'download'    => $attributes['Mikrotik-Recv-Limit'] ?? 0,
                    'updated_at'  => $now->toDateTimeString(),
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * DELETE: Menghapus seluruh atribut dalam satu group berdasarkan groupname
     */
    public function destroy($groupname)
    {
        $deleted = RadGroupReply::where('groupname', $groupname)->delete();

        if ($deleted === 0) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Group tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'status'  => 'success',
            'message' => "Group {$groupname} berhasil dihapus"
        ]);
    }
}