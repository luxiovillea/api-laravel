<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Opd;
use Illuminate\Http\Request;

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
     *     description="Mengambil daftar semua Organisasi Perangkat Daerah (OPD)",
     *     operationId="getOpdList",
     *     tags={"OPD Management"},
     *     @OA\Response(
     *         response=200,
     *         description="Berhasil mendapatkan daftar OPD",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Daftar OPD berhasil diambil"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="kode_opd", type="string", example="2622240000"),
     *                     @OA\Property(property="nama", type="string", example="Badan Kepegawaian dan Pengembangan Sumber Daya Manusia"),
     *                     @OA\Property(property="akronim", type="string", example="BKPSDM"),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function index()
    {
        $opds = Opd::orderBy('nama')->get();
        
        return response()->json([
            'success' => true,
            'message' => 'Daftar OPD berhasil diambil',
            'data' => $opds
        ]);
    }

    /**
     * @OA\Post(
     *     path="/opd",
     *     summary="Membuat OPD baru",
     *     description="Menambahkan OPD baru ke dalam sistem",
     *     operationId="createOpd",
     *     tags={"OPD Management"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"kode_opd", "nama", "akronim"},
     *             @OA\Property(property="kode_opd", type="string", example="2622240001"),
     *             @OA\Property(property="nama", type="string", example="Dinas Pendidikan"),
     *             @OA\Property(property="akronim", type="string", example="DISDIK")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="OPD berhasil dibuat",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="OPD berhasil ditambahkan"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation Error"
     *     )
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'kode_opd' => 'required|string|max:20|unique:opds,kode_opd',
            'nama' => 'required|string|max:255',
            'akronim' => 'required|string|max:20',
        ]);

        $opd = Opd::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'OPD berhasil ditambahkan',
            'data' => $opd
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/opd/{id}",
     *     summary="Mendapatkan detail OPD",
     *     description="Mengambil detail OPD berdasarkan ID",
     *     operationId="getOpdDetail",
     *     tags={"OPD Management"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID OPD",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Detail OPD berhasil diambil",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="OPD tidak ditemukan"
     *     )
     * )
     */
    public function show(Opd $opd)
    {
        return response()->json([
            'success' => true,
            'message' => 'Detail OPD berhasil diambil',
            'data' => $opd
        ]);
    }

    /**
     * @OA\Put(
     *     path="/opd/{id}",
     *     summary="Update OPD",
     *     description="Mengupdate data OPD yang sudah ada",
     *     operationId="updateOpd",
     *     tags={"OPD Management"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID OPD",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="kode_opd", type="string"),
     *             @OA\Property(property="nama", type="string"),
     *             @OA\Property(property="akronim", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="OPD berhasil diupdate"
     *     )
     * )
     */
    public function update(Request $request, Opd $opd)
    {
        $validated = $request->validate([
            'kode_opd' => 'sometimes|required|string|max:20|unique:opds,kode_opd,' . $opd->id,
            'nama' => 'sometimes|required|string|max:255',
            'akronim' => 'sometimes|required|string|max:20',
        ]);

        $opd->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'OPD berhasil diupdate',
            'data' => $opd->fresh()
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/opd/{id}",
     *     summary="Hapus OPD",
     *     description="Menghapus OPD dari sistem",
     *     operationId="deleteOpd",
     *     tags={"OPD Management"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID OPD",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="OPD berhasil dihapus"
     *     )
     * )
     */
    public function destroy(Opd $opd)
    {
        $opd->delete();

        return response()->json([
            'success' => true,
            'message' => 'OPD berhasil dihapus'
        ]);
    }

    /**
     * @OA\Get(
     *     path="/opd/kode/{kode}",
     *     summary="Mendapatkan OPD berdasarkan kode",
     *     description="Mengambil data OPD berdasarkan kode_opd",
     *     operationId="getOpdByKode",
     *     tags={"OPD Management"},
     *     @OA\Parameter(
     *         name="kode",
     *         in="path",
     *         description="Kode OPD",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="OPD ditemukan"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="OPD tidak ditemukan"
     *     )
     * )
     */
    public function getByKode($kode)
    {
        $opd = Opd::where('kode_opd', $kode)->first();

        if (!$opd) {
            return response()->json([
                'success' => false,
                'message' => 'OPD dengan kode tersebut tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'OPD berhasil ditemukan',
            'data' => $opd
        ]);
    }

    /**
     * @OA\Get(
     *     path="/opd/{id}/aplikasi",
     *     summary="Mendapatkan aplikasi milik OPD",
     *     description="Mengambil daftar semua aplikasi yang dimiliki oleh OPD tertentu",
     *     operationId="getOpdAplikasis",
     *     tags={"OPD Management"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID OPD",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="active_only",
     *         in="query",
     *         description="Filter hanya aplikasi yang aktif",
     *         required=false,
     *         @OA\Schema(type="boolean", default=false)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Berhasil mendapatkan daftar aplikasi OPD",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Daftar aplikasi OPD berhasil diambil"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="opd",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="kode_opd", type="string"),
     *                     @OA\Property(property="nama", type="string"),
     *                     @OA\Property(property="akronim", type="string")
     *                 ),
     *                 @OA\Property(
     *                     property="aplikasis",
     *                     type="array",
     *                     @OA\Items(type="object")
     *                 ),
     *                 @OA\Property(property="total_aplikasi", type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="OPD tidak ditemukan"
     *     )
     * )
     */
    public function aplikasis(Request $request, $id)
    {
        $opd = Opd::find($id);

        if (!$opd) {
            return response()->json([
                'success' => false,
                'message' => 'OPD tidak ditemukan'
            ], 404);
        }

        $query = $opd->aplikasis();
        
        if ($request->boolean('active_only')) {
            $query->where('is_active', true);
        }
        
        $aplikasis = $query->orderBy('nama_aplikasi')->get();

        return response()->json([
            'success' => true,
            'message' => 'Daftar aplikasi OPD berhasil diambil',
            'data' => [
                'opd' => $opd,
                'aplikasis' => $aplikasis,
                'total_aplikasi' => $aplikasis->count()
            ]
        ]);
    }

    /**
     * @OA\Get(
     *     path="/opd/kode/{kode}/aplikasi",
     *     summary="Mendapatkan aplikasi milik OPD berdasarkan kode",
     *     description="Mengambil daftar semua aplikasi yang dimiliki oleh OPD tertentu berdasarkan kode OPD",
     *     operationId="getOpdAplikasisByKode",
     *     tags={"OPD Management"},
     *     @OA\Parameter(
     *         name="kode",
     *         in="path",
     *         description="Kode OPD",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="active_only",
     *         in="query",
     *         description="Filter hanya aplikasi yang aktif",
     *         required=false,
     *         @OA\Schema(type="boolean", default=false)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Berhasil mendapatkan daftar aplikasi OPD",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Daftar aplikasi OPD berhasil diambil"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="opd",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="kode_opd", type="string"),
     *                     @OA\Property(property="nama", type="string"),
     *                     @OA\Property(property="akronim", type="string")
     *                 ),
     *                 @OA\Property(
     *                     property="aplikasis",
     *                     type="array",
     *                     @OA\Items(type="object")
     *                 ),
     *                 @OA\Property(property="total_aplikasi", type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="OPD tidak ditemukan"
     *     )
     * )
     */
    public function aplikasisByKode(Request $request, $kode)
    {
        $opd = Opd::where('kode_opd', $kode)->first();

        if (!$opd) {
            return response()->json([
                'success' => false,
                'message' => 'OPD dengan kode tersebut tidak ditemukan'
            ], 404);
        }

        $query = $opd->aplikasis();
        
        if ($request->boolean('active_only')) {
            $query->where('is_active', true);
        }
        
        $aplikasis = $query->orderBy('nama_aplikasi')->get();

        return response()->json([
            'success' => true,
            'message' => 'Daftar aplikasi OPD berhasil diambil',
            'data' => [
                'opd' => $opd,
                'aplikasis' => $aplikasis,
                'total_aplikasi' => $aplikasis->count()
            ]
        ]);
    }

}