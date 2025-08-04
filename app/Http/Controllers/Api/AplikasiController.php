<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Aplikasi;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * @OA\Tag(
 *     name="Aplikasi Management",
 *     description="API untuk mengelola data aplikasi yang terintegrasi dengan Google Analytics"
 * )
 */
class AplikasiController extends Controller
{
    /**
     * @OA\Get(
     *     path="/aplikasi",
     *     summary="Mendapatkan daftar semua aplikasi",
     *     description="Mengambil daftar semua aplikasi yang terdaftar beserta konfigurasi analytics-nya",
     *     operationId="getAplikasiList",
     *     tags={"Aplikasi Management"},
     *     @OA\Parameter(
     *         name="active_only",
     *         in="query",
     *         description="Filter hanya aplikasi yang aktif",
     *         required=false,
     *         @OA\Schema(type="boolean", default=false)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Berhasil mendapatkan daftar aplikasi",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Daftar aplikasi berhasil diambil"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="nama_aplikasi", type="string", example="Aplikasi Lapakami"),
     *                     @OA\Property(property="key_aplikasi", type="string", example="lapakami"),
     *                     @OA\Property(property="property_id", type="string", example="123456789"),
     *                     @OA\Property(property="page_path_filter", type="string", example="/"),
     *                     @OA\Property(property="deskripsi", type="string", example="Aplikasi untuk laporan pengaduan"),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="konfigurasi_tambahan", type="object"),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $query = Aplikasi::query();
        
        if ($request->boolean('active_only')) {
            $query->active();
        }
        
        $aplikasi = $query->orderBy('nama_aplikasi')->get();
        
        return response()->json([
            'success' => true,
            'message' => 'Daftar aplikasi berhasil diambil',
            'data' => $aplikasi
        ]);
    }

    /**
     * @OA\Post(
     *     path="/aplikasi",
     *     summary="Membuat aplikasi baru",
     *     description="Menambahkan aplikasi baru ke dalam sistem",
     *     operationId="createAplikasi",
     *     tags={"Aplikasi Management"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"nama_aplikasi", "key_aplikasi", "property_id"},
     *             @OA\Property(property="nama_aplikasi", type="string", example="Aplikasi Dashboard Baru"),
     *             @OA\Property(property="key_aplikasi", type="string", example="dashboard_baru"),
     *             @OA\Property(property="property_id", type="string", example="987654321"),
     *             @OA\Property(property="page_path_filter", type="string", example="/dashboard"),
     *             @OA\Property(property="deskripsi", type="string", example="Aplikasi dashboard untuk monitoring"),
     *             @OA\Property(property="is_active", type="boolean", example=true),
     *             @OA\Property(property="konfigurasi_tambahan", type="object", example={"key": "value"})
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Aplikasi berhasil dibuat",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Aplikasi berhasil ditambahkan"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation Error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_aplikasi' => 'required|string|max:255',
            'key_aplikasi' => 'required|string|max:100|unique:aplikasi,key_aplikasi',
            'property_id' => 'required|string|max:50',
            'page_path_filter' => 'nullable|string|max:255',
            'deskripsi' => 'nullable|string',
            'is_active' => 'boolean',
            'konfigurasi_tambahan' => 'nullable|array',
        ]);

        // Set default values
        $validated['page_path_filter'] = $validated['page_path_filter'] ?? '/';
        $validated['is_active'] = $validated['is_active'] ?? true;

        $aplikasi = Aplikasi::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Aplikasi berhasil ditambahkan',
            'data' => $aplikasi
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/aplikasi/{id}",
     *     summary="Mendapatkan detail aplikasi",
     *     description="Mengambil detail aplikasi berdasarkan ID",
     *     operationId="getAplikasiDetail",
     *     tags={"Aplikasi Management"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID aplikasi",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Detail aplikasi berhasil diambil",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Aplikasi tidak ditemukan",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Aplikasi tidak ditemukan")
     *         )
     *     )
     * )
     */
    public function show(Aplikasi $aplikasi)
    {
        return response()->json([
            'success' => true,
            'message' => 'Detail aplikasi berhasil diambil',
            'data' => $aplikasi
        ]);
    }

    /**
     * @OA\Put(
     *     path="/aplikasi/{id}",
     *     summary="Update aplikasi",
     *     description="Mengupdate data aplikasi yang sudah ada",
     *     operationId="updateAplikasi",
     *     tags={"Aplikasi Management"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID aplikasi",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="nama_aplikasi", type="string"),
     *             @OA\Property(property="key_aplikasi", type="string"),
     *             @OA\Property(property="property_id", type="string"),
     *             @OA\Property(property="page_path_filter", type="string"),
     *             @OA\Property(property="deskripsi", type="string"),
     *             @OA\Property(property="is_active", type="boolean"),
     *             @OA\Property(property="konfigurasi_tambahan", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Aplikasi berhasil diupdate",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function update(Request $request, Aplikasi $aplikasi)
    {
        $validated = $request->validate([
            'nama_aplikasi' => 'sometimes|required|string|max:255',
            'key_aplikasi' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                Rule::unique('aplikasi', 'key_aplikasi')->ignore($aplikasi->id)
            ],
            'property_id' => 'sometimes|required|string|max:50',
            'page_path_filter' => 'nullable|string|max:255',
            'deskripsi' => 'nullable|string',
            'is_active' => 'boolean',
            'konfigurasi_tambahan' => 'nullable|array',
        ]);

        $aplikasi->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Aplikasi berhasil diupdate',
            'data' => $aplikasi->fresh()
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/aplikasi/{id}",
     *     summary="Hapus aplikasi",
     *     description="Menghapus aplikasi dari sistem",
     *     operationId="deleteAplikasi",
     *     tags={"Aplikasi Management"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID aplikasi",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Aplikasi berhasil dihapus",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Aplikasi berhasil dihapus")
     *         )
     *     )
     * )
     */
    public function destroy(Aplikasi $aplikasi)
    {
        $aplikasi->delete();

        return response()->json([
            'success' => true,
            'message' => 'Aplikasi berhasil dihapus'
        ]);
    }

    /**
     * @OA\Get(
     *     path="/aplikasi/key/{key}",
     *     summary="Mendapatkan aplikasi berdasarkan key",
     *     description="Mengambil data aplikasi berdasarkan key_aplikasi",
     *     operationId="getAplikasiByKey",
     *     tags={"Aplikasi Management"},
     *     @OA\Parameter(
     *         name="key",
     *         in="path",
     *         description="Key aplikasi (contoh: lapakami, dashboard)",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Aplikasi ditemukan",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Aplikasi tidak ditemukan"
     *     )
     * )
     */
    public function getByKey($key)
    {
        $aplikasi = Aplikasi::where('key_aplikasi', $key)->first();

        if (!$aplikasi) {
            return response()->json([
                'success' => false,
                'message' => 'Aplikasi dengan key tersebut tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Aplikasi berhasil ditemukan',
            'data' => $aplikasi
        ]);
    }
}