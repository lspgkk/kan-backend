<?php

namespace App\Http\Controllers;

use App\Models\JenisKegiatan;
use Illuminate\Http\Request;

class JenisKegiatanController extends Controller
{
    public function index(Request $request)
    {
        $query = JenisKegiatan::select(['id', 'name', 'kode', 'deskripsi']);

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('kode', 'like', "%{$search}%");
            });
        }

        $perPage = (int) $request->query('per_page', 10);
        $perPage = min(max($perPage, 1), 100);

        return response()->json([
            'message' => 'Jenis Kegiatan retrieved successfully',
            'data' => $query->orderBy('kode')->paginate($perPage),
        ]);
    }
}
