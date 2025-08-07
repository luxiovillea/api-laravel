<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Opd;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * @OA\Tag(
 *     name="OPD Management",
 *     description="API untuk mengelola data Organisasi Perangkat Daerah (OPD)"
 * )
 */
class OpdController extends Controller
{
    /**
     * @OA\Get(
     *     path="/opd",
     *     summary="Mendapatkan daftar semua OPD",
     *     description="Mengambil daftar semua Organisasi Perangkat Daerah yang terdaftar",
     *     operationId="getOpdList",
     *     tags={"OPD Management"},
     *     @OA\Response(
     *         response=200,
     *         description="Berhasil mendapatkan daftar OPD",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="kode_opd", type="string", example="DISKOMINFO"),
     *                 @OA\Property(property="nama", type="string", example="Dinas Komunikasi dan Informatika"),
     *                 @OA\Property(property="akronim", type="string", example="DISKOMINFO"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server Error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="error", type="string", example="Internal server error")
     *         )
     *     )
     * )
     */
    public function index()
    {
        try {
            $opds = Opd::all();
            return response()->json($opds);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/opd",
     *     summary="Membuat OPD baru",
     *     description="Menambahkan Organisasi Perangkat Daerah baru ke dalam sistem",
     *     operationId="createOpd",
     *     tags={"OPD Management"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"kode_opd", "nama", "akronim"},
     *             @OA\Property(property="kode_opd", type="string", maxLength=50, example="DISKOMINFO", description="Kode unik OPD"),
     *             @OA\Property(property="nama", type="string", maxLength=255, example="Dinas Komunikasi dan Informatika", description="Nama lengkap OPD"),
     *             @OA\Property(property="akronim", type="string", maxLength=20, example="DISKOMINFO", description="Singkatan nama OPD")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="OPD berhasil dibuat",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="kode_opd", type="string", example="DISKOMINFO"),
     *             @OA\Property(property="nama", type="string", example="Dinas Komunikasi dan Informatika"),
     *             @OA\Property(property="akronim", type="string", example="DISKOMINFO"),
     *             @OA\Property(property="created_at", type="string", format="date-time"),
     *             @OA\Property(property="updated_at", type="string", format="date-time")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation Error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="errors", type="object",
     *                 @OA\Property(property="kode_opd", type="array", @OA\Items(type="string", example="The kode opd field is required.")),
     *                 @OA\Property(property="nama", type="array", @OA\Items(type="string", example="The nama field is required.")),
     *                 @OA\Property(property="akronim", type="array", @OA\Items(type="string", example="The akronim field is required."))
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server Error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="error", type="string", example="Internal server error")
     *         )
     *     )
     * )
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'kode_opd' => 'required|string|max:50|unique:opds,kode_opd',
            'nama' => 'required|string|max:255',
            'akronim' => 'required|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $opd = Opd::create($validator->validated());
            return response()->json($opd, 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/opd/{id}",
     *     summary="Mendapatkan detail OPD",
     *     description="Mengambil detail Organisasi Perangkat Daerah berdasarkan ID",
     *     operationId="getOpdDetail",
     *     tags={"OPD Management"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID OPD",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Detail OPD berhasil diambil",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="kode_opd", type="string", example="DISKOMINFO"),
     *             @OA\Property(property="nama", type="string", example="Dinas Komunikasi dan Informatika"),
     *             @OA\Property(property="akronim", type="string", example="DISKOMINFO"),
     *             @OA\Property(property="created_at", type="string", format="date-time"),
     *             @OA\Property(property="updated_at", type="string", format="date-time")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="OPD tidak ditemukan",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="error", type="string", example="OPD not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server Error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="error", type="string", example="Internal server error")
     *         )
     *     )
     * )
     */
    public function show($id)
    {
        try {
            $opd = Opd::findOrFail($id);
            return response()->json($opd);
        } catch (\Exception $e) {
            return response()->json(['error' => 'OPD not found'], 404);
        }
    }

    /**
     * @OA\Put(
     *     path="/opd/{id}",
     *     summary="Update OPD",
     *     description="Mengupdate data Organisasi Perangkat Daerah yang sudah ada",
     *     operationId="updateOpd",
     *     tags={"OPD Management"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID OPD",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Data OPD yang akan diupdate (semua field opsional)",
     *         @OA\JsonContent(
     *             @OA\Property(property="kode_opd", type="string", maxLength=50, example="DISKOMINFO_NEW", description="Kode unik OPD"),
     *             @OA\Property(property="nama", type="string", maxLength=255, example="Dinas Komunikasi dan Informatika Kota", description="Nama lengkap OPD"),
     *             @OA\Property(property="akronim", type="string", maxLength=20, example="DISKOMINFOKOTA", description="Singkatan nama OPD")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="OPD berhasil diupdate",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="kode_opd", type="string", example="DISKOMINFO_NEW"),
     *             @OA\Property(property="nama", type="string", example="Dinas Komunikasi dan Informatika Kota"),
     *             @OA\Property(property="akronim", type="string", example="DISKOMINFOKOTA"),
     *             @OA\Property(property="created_at", type="string", format="date-time"),
     *             @OA\Property(property="updated_at", type="string", format="date-time")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation Error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="OPD tidak ditemukan",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="error", type="string", example="OPD not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server Error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="error", type="string", example="Internal server error")
     *         )
     *     )
     * )
     */
    public function update(Request $request, $id)
    {
        try {
            $opd = Opd::findOrFail($id);
            
            $validator = Validator::make($request->all(), [
                'kode_opd' => [
                    'sometimes',
                    'required',
                    'string',
                    'max:50',
                    Rule::unique('opds')->ignore($opd->id)
                ],
                'nama' => 'sometimes|required|string|max:255',
                'akronim' => 'sometimes|required|string|max:20',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $opd->update($validator->validated());
            return response()->json($opd);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/opd/{id}",
     *     summary="Hapus OPD",
     *     description="Menghapus Organisasi Perangkat Daerah dari sistem",
     *     operationId="deleteOpd",
     *     tags={"OPD Management"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID OPD yang akan dihapus",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="OPD berhasil dihapus",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="OPD deleted successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="OPD tidak ditemukan",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="error", type="string", example="OPD not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server Error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="error", type="string", example="Cannot delete OPD that has associated applications")
     *         )
     *     )
     * )
     */
    public function destroy($id)
    {
        try {
            $opd = Opd::findOrFail($id);
            $opd->delete();
            return response()->json(['message' => 'OPD deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/opd/{id}/aplikasi",
     *     summary="Mendapatkan aplikasi berdasarkan OPD",
     *     description="Mengambil semua aplikasi yang terkait dengan OPD tertentu",
     *     operationId="getAplikasiByOpd",
     *     tags={"OPD Management"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID OPD",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Daftar aplikasi berhasil diambil",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Daftar aplikasi untuk OPD berhasil diambil"),
     *             @OA\Property(
     *                 property="data", 
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="nama_aplikasi", type="string"),
     *                     @OA\Property(property="key_aplikasi", type="string"),
     *                     @OA\Property(property="property_id", type="string"),
     *                     @OA\Property(property="is_active", type="boolean")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="OPD tidak ditemukan",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="error", type="string", example="OPD not found")
     *         )
     *     )
     * )
     */
    public function aplikasis($id)
    {
        try {
            $opd = Opd::findOrFail($id);
            $aplikasis = $opd->aplikasis;
            
            return response()->json([
                'success' => true,
                'message' => 'Daftar aplikasi untuk OPD berhasil diambil',
                'data' => $aplikasis
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'OPD not found'], 404);
        }
    }
}