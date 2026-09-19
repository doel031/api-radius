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
     * Daftar Profil & Group
     *
     * Menampilkan daftar seluruh grup/profil FreeRADIUS beserta nilai parameter limitasi (time, upload, download) dan pemetaan grup MikroTik (`devicegroup`).
     */
    public function index()
    {
        $allowedAttributes = array_values($this->attributeMap);

        $records = RadGroupReply::whereIn('attribute', $allowedAttributes)->get();

        $grouped = $records->groupBy('groupname')->map(function ($items, $groupName) {
            $attributes = $items->pluck('value', 'attribute')->toArray();

            // Cek apakah ada atribut limit/session yang bernilai > 0
            $hasTimeLimit     = isset($attributes['Max-All-Session']) && (float) $attributes['Max-All-Session'] > 0;
            $hasUploadLimit   = isset($attributes['Mikrotik-Xmit-Limit']) && (float) $attributes['Mikrotik-Xmit-Limit'] > 0;
            $hasDownloadLimit = isset($attributes['Mikrotik-Recv-Limit']) && (float) $attributes['Mikrotik-Recv-Limit'] > 0;

            $hasVoucherAttr = $hasTimeLimit || $hasUploadLimit || $hasDownloadLimit;
            $hasNormalAttr  = array_key_exists('Mikrotik-Group', $attributes);

            // Jika punya atribut limit/session > 0 MAKA Tipe Voucher, jika hanya Group MAKA Normal
            $type = $hasVoucherAttr ? 'voucher' : ($hasNormalAttr ? 'normal' : 'unknown');

            // Mengambil nilai created_at dan updated_at terbaru di antara baris-baris dalam group ini
            $latestCreate = $items->max('created_at');
            $latestUpdate = $items->max('updated_at');

            return [
                'groupname'   => $groupName,
                'type'        => $type,
                'devicegroup' => $attributes['Mikrotik-Group'] ?? null,
                'time'        => $hasTimeLimit ? (int) $attributes['Max-All-Session'] : 0,
                'upload'      => $hasUploadLimit ? (int) $attributes['Mikrotik-Xmit-Limit'] : 0,
                'download'    => $hasDownloadLimit ? (int) $attributes['Mikrotik-Recv-Limit'] : 0,
                // Hanya tampilkan updated_at di JSON response
                'last_updated' => $latestUpdate ? \Carbon\Carbon::parse($latestUpdate)->toDateTimeString() : \Carbon\Carbon::parse($latestCreate)->toDateTimeString(),
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
     * Tambah Profil / Group Baru
     *
     * Membuat profil paket baru pada tabel `radgroupreply`. Parameter `devicegroup` wajib diisi untuk pemetaan `Mikrotik-Group`. Parameter `time` (detik), `upload` (bytes), dan `download` (bytes) bersifat opsional untuk membuat paket bertipe voucher/kuota (> 0). Jika dikosongkan atau bernilai 0, paket otomatis bertipe normal (unlimited) tanpa menyimpan atribut batas ke database.
     */
    public function store(Request $request)
    {
        // 1. Validasi Input
        $validated = $request->validate([
            'groupname'   => 'required|string|max:64',
            'devicegroup' => 'required|string|max:64',
            'time'        => 'nullable|numeric|min:0',
            'upload'      => 'nullable|numeric|min:0',
            'download'    => 'nullable|numeric|min:0',
        ]);

        // 2. Cek apakah groupname sudah terdaftar di database
        $groupExists = RadGroupReply::where('groupname', $validated['groupname'])->exists();
        if ($groupExists) {
            return response()->json([
                'status'  => 'error',
                'message' => "Gagal membuat group. Nama group '{$validated['groupname']}' sudah terdaftar di database."
            ], 422);
        }

        // Hanya anggap sebagai limit voucher jika nilainya > 0
        $hasTime     = !empty($request->input('time')) && (float) $request->input('time') > 0;
        $hasUpload   = !empty($request->input('upload')) && (float) $request->input('upload') > 0;
        $hasDownload = !empty($request->input('download')) && (float) $request->input('download') > 0;

        $isVoucher = $hasTime || $hasUpload || $hasDownload;
        $now = now();

        // 3. Simpan ke Database
        DB::beginTransaction();
        try {
            $recordsToInsert = [];

            // 1. Simpan 'Group' (Mikrotik-Group) -> Selalu Wajib untuk Normal & Voucher
            $recordsToInsert[] = [
                'groupname'  => $validated['groupname'],
                'attribute'  => 'Mikrotik-Group',
                'op'         => ':=',
                'value'      => $validated['devicegroup'],
                'created_at' => $now,
            ];

            // 2. Jika tipe Voucher (ada limit > 0), simpan atribut-atribut batas tersebut
            if ($isVoucher) {
                if ($hasTime) {
                    $recordsToInsert[] = [
                        'groupname'  => $validated['groupname'],
                        'attribute'  => 'Max-All-Session',
                        'op'         => ':=',
                        'value'      => (string) ((int) $validated['time']),
                        'created_at' => $now,
                    ];
                }

                if ($hasUpload) {
                    $recordsToInsert[] = [
                        'groupname'  => $validated['groupname'],
                        'attribute'  => 'Mikrotik-Xmit-Limit',
                        'op'         => ':=',
                        'value'      => (string) ((int) $validated['upload']),
                        'created_at' => $now,
                    ];
                }

                if ($hasDownload) {
                    $recordsToInsert[] = [
                        'groupname'  => $validated['groupname'],
                        'attribute'  => 'Mikrotik-Recv-Limit',
                        'op'         => ':=',
                        'value'      => (string) ((int) $validated['download']),
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
                    'groupname'   => $validated['groupname'],
                    'devicegroup' => $validated['devicegroup'],
                    'time'        => $hasTime ? (int) $validated['time'] : 0,
                    'upload'      => $hasUpload ? (int) $validated['upload'] : 0,
                    'download'    => $hasDownload ? (int) $validated['download'] : 0,
                    'created_at'  => $now->toDateTimeString(),
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Update Atribut Profil Group
     *
     * Memperbarui satu atau beberapa atribut (devicegroup, time, upload, download) pada profil group yang sudah ada. Mengirim nilai 0, null, atau kosong pada time, upload, atau download akan menghapus atribut batas tersebut sehingga paket kembali normal/unlimited.
     */
    public function update(Request $request, $groupname)
    {
        // Validasi input (semua opsional/nullable agar fleksibel saat update)
        $validated = $request->validate([
            'devicegroup' => 'nullable|string|max:64',
            'time'        => 'nullable|numeric|min:0',
            'upload'      => 'nullable|numeric|min:0',
            'download'    => 'nullable|numeric|min:0',
        ]);

        // Cek apakah ada minimal 1 data yang dikirim untuk diupdate
        $hasAnyInput = $request->hasAny(['devicegroup', 'time', 'upload', 'download']);

        if (!$hasAnyInput) {
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

            // 1. Update devicegroup (Mikrotik-Group) jika dikirim
            if ($request->has('devicegroup') && !is_null($request->input('devicegroup')) && $request->input('devicegroup') !== '') {
                RadGroupReply::updateOrCreate(
                    [
                        'groupname' => $groupname,
                        'attribute' => 'Mikrotik-Group',
                    ],
                    [
                        'op'         => ':=',
                        'value'      => (string) $request->input('devicegroup'),
                        'updated_at' => $now,
                    ]
                );
            }

            // 2. Handle limit atribut: time, upload, download
            // Jika nilai > 0, simpan/update ke database
            // Jika nilai 0, null, atau kosong, HAPUS baris dari radgroupreply agar kembali normal/unlimited
            $limitFields = [
                'time'     => 'Max-All-Session',
                'upload'   => 'Mikrotik-Xmit-Limit',
                'download' => 'Mikrotik-Recv-Limit',
            ];

            foreach ($limitFields as $field => $radiusAttr) {
                if ($request->has($field)) {
                    $val = $request->input($field);
                    if (!is_null($val) && $val !== '' && (float) $val > 0) {
                        RadGroupReply::updateOrCreate(
                            [
                                'groupname' => $groupname,
                                'attribute' => $radiusAttr,
                            ],
                            [
                                'op'         => ':=',
                                'value'      => (string) ((int) $val),
                                'updated_at' => $now,
                            ]
                        );
                    } else {
                        // Hapus atribut jika nilainya 0, null, atau kosong
                        RadGroupReply::where('groupname', $groupname)
                            ->where('attribute', $radiusAttr)
                            ->delete();
                    }
                }
            }

            DB::commit();

            // Ambil data terbaru seluruh atribut group ini untuk display
            $updatedRecords = RadGroupReply::where('groupname', $groupname)
                ->whereIn('attribute', array_values($this->attributeMap))
                ->get();

            $attributes = $updatedRecords->pluck('value', 'attribute')->toArray();

            $hasTimeLimit     = isset($attributes['Max-All-Session']) && (float) $attributes['Max-All-Session'] > 0;
            $hasUploadLimit   = isset($attributes['Mikrotik-Xmit-Limit']) && (float) $attributes['Mikrotik-Xmit-Limit'] > 0;
            $hasDownloadLimit = isset($attributes['Mikrotik-Recv-Limit']) && (float) $attributes['Mikrotik-Recv-Limit'] > 0;

            $hasVoucherAttr = $hasTimeLimit || $hasUploadLimit || $hasDownloadLimit;

            return response()->json([
                'status'  => 'success',
                'type'    => $hasVoucherAttr ? 'voucher' : 'normal',
                'message' => "Group {$groupname} berhasil diperbarui",
                'data'    => [
                    'groupname'   => $groupname,
                    'devicegroup' => $attributes['Mikrotik-Group'] ?? null,
                    'time'        => $hasTimeLimit ? (int) $attributes['Max-All-Session'] : 0,
                    'upload'      => $hasUploadLimit ? (int) $attributes['Mikrotik-Xmit-Limit'] : 0,
                    'download'    => $hasDownloadLimit ? (int) $attributes['Mikrotik-Recv-Limit'] : 0,
                    'updated_at'  => $now->toDateTimeString(),
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Hapus Profil Group
     *
     * Menghapus seluruh atribut dan konfigurasi profil group dari tabel `radgroupreply`.
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