<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Nas;
use App\Http\Requests\StoreNasRequest;
use App\Http\Requests\UpdateNasRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NasController extends Controller
{
    /**
     * Display a listing of the NAS records with search & pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Nas::query();

        // Pencarian opsional berdasarkan nasname atau shortname
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where('nasname', 'like', "%{$search}%")
                  ->orWhere('shortname', 'like', "%{$search}%");
        }

        //$nasList = $query->paginate($request->input('per_page', 15));
        $nasList = $query->latest()->get();

        return response()->json([
            'status'  => 'success',
            'message' => 'Data NAS berhasil diambil',
            'total'   => $nasList->count(),
            'data'    => $nasList
        ], 200);
    }

    /**
     * Store a newly created NAS in storage.
     */
    public function store(StoreNasRequest $request): JsonResponse
    {
        $nas = Nas::create($request->validated());

        return response()->json([
            'status'  => 'success',
            'message' => 'NAS berhasil ditambahkan',
            'radius_status' => $nas->radiusStatus ?? 'konfigurasi sudah siap',
            'data'    => $nas
        ], 201);
    }

    /**
     * Display the specified NAS.
     */
    public function show($id): JsonResponse
    {
        $nas = Nas::find($id);

        if (!$nas) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Data NAS tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Detail NAS ditemukan',
            'data'    => $nas
        ], 200);
    }

    /**
     * Update the specified NAS in storage.
     */
    public function update(UpdateNasRequest $request, $id): JsonResponse
    {
        $nas = Nas::find($id);

        if (!$nas) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Data NAS tidak ditemukan'
            ], 404);
        }

        $nas->update($request->validated());

        return response()->json([
            'status'  => 'success',
            'message' => 'Data NAS berhasil diperbarui',
            'radius_status' => $nas->radiusStatus ?? 'konfigurasi sudah siap',
            'data'    => $nas
        ], 200);
    }

    /**
     * Remove the specified NAS from storage.
     */
    public function destroy($id): JsonResponse
    {
        $nas = Nas::find($id);

        if (!$nas) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Data NAS tidak ditemukan'
            ], 404);
        }

        $nas->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'NAS berhasil dihapus',
            'radius_status' => $nas->radiusStatus ?? 'konfigurasi sudah siap',
        ], 200);
    }
}