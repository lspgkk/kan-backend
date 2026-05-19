<?php

namespace App\Http\Controllers;

use App\Models\Klasifikasi;
use Illuminate\Http\Request;

class KlasifikasiController extends Controller
{
    public function index(Request $request)
    {
        if ($request->boolean('all')) {
            $klasifikasis = Klasifikasi::select(['id', 'kode', 'name', 'deskripsi'])->get();

            return response()->json([
                'status' => 'success',
                'data' => $klasifikasis,
                'meta' => [
                    'all' => true,
                    'total' => $klasifikasis->count(),
                ],
            ]);
        }

        $perPage = (int) $request->query('per_page', 10);
        $perPage = min(max($perPage, 1), 100);

        $query = Klasifikasi::select(['id', 'kode', 'name', 'deskripsi']);

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "{$search}%")
                  ->orWhere('kode', 'like', "{$search}%")
                  ->orWhere('deskripsi', 'like', "{$search}%");
            });
        }

        $klasifikasis = $query->orderBy('kode')->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $klasifikasis,
        ]);
    }
}
