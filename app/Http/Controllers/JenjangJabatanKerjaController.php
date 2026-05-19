<?php

namespace App\Http\Controllers;

use App\Models\JenjangJabatanKerja;
use Illuminate\Http\Request;

class JenjangJabatanKerjaController extends Controller
{
    public function index(Request $request)
    {
        $query = JenjangJabatanKerja::with(['jabatanKerja', 'jenjang']);

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhereHas('jabatanKerja', function ($u) use ($search) {
                      $u->where('jabatan_kerja', 'like', "%{$search}%");
                  })
                  ->orWhereHas('jenjang', function ($u) use ($search) {
                      $u->where('nama', 'like', "%{$search}%");
                  });
            });
        }

        $perPage = (int) $request->query('per_page', 10);
        $perPage = min(max($perPage, 1), 100);

        return response()->json([
            'message' => 'Jenjang Jabatan Kerja retrieved successfully',
            'data' => $query->orderBy('name')->paginate($perPage),
        ]);
    }
}
