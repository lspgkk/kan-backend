<?php

namespace App\Http\Controllers;

use App\Models\JabatanKerja;
use App\Models\Kualifikasi;
use Illuminate\Http\Request;

class JabatanKerjaController extends Controller
{
    public function index(Request $request)
    {
        $query = JabatanKerja::select(['id_jabatan_kerja', 'jabatan_kerja']);

        // Search functionality
        if ($request->has('search')) {
            $searchTerm = $request->query('search');
            $query->where(function ($q) use ($searchTerm) {
                $q->where('jabatan_kerja', 'like', "%{$searchTerm}%")
                    ->orWhere('id_jabatan_kerja', 'like', "%{$searchTerm}%");
            });
        }

        // If frontend explicitly asks for all data
        if ($request->boolean('all')) {
            $jabatanKerjas = $query->get();

            return response()->json([
                'status' => 'success',
                'data' => $jabatanKerjas,
                'meta' => [
                    'all' => true,
                    'total' => $jabatanKerjas->count(),
                ],
            ]);
        }

        // Normal pagination
        $perPage = (int) $request->query('per_page', 10);
        $perPage = min(max($perPage, 1), 100);

        $jabatanKerjas = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $jabatanKerjas,
        ]);
    }

    public function kualifikasi(Request $request)
    {
        // If frontend explicitly asks for all data
        if ($request->boolean('all')) {
            $kualifikasis = Kualifikasi::all();

            return response()->json([
                'status' => 'success',
                'data' => $kualifikasis,
                'meta' => [
                    'all' => true,
                    'total' => $kualifikasis->count(),
                ],
            ]);
        }

        // Normal pagination
        $perPage = (int) $request->query('per_page', 10);
        $perPage = min(max($perPage, 1), 100);

        $kualifikasis = Kualifikasi::paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $kualifikasis,
        ]);
    }
}
