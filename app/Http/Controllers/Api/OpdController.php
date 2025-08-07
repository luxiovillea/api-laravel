<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Opd;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class OpdController extends Controller
{
    public function index()
    {
        try {
            $opds = Opd::all();
            return response()->json($opds);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

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

    public function show($id)
    {
        try {
            $opd = Opd::findOrFail($id);
            return response()->json($opd);
        } catch (\Exception $e) {
            return response()->json(['error' => 'OPD not found'], 404);
        }
    }

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
}