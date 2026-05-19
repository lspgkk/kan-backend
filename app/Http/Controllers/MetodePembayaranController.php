<?php

namespace App\Http\Controllers;

use App\Models\MetodePembayaran;
use Illuminate\Http\Request;

class MetodePembayaranController extends Controller
{
    public function index(Request $request)
    {
        $query = MetodePembayaran::select(['id', 'name', 'kode', 'tipe']);

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('kode', 'like', "%{$search}%")
                  ->orWhere('tipe', 'like', "%{$search}%");
            });
        }

        $perPage = (int) $request->query('per_page', 10);
        $perPage = min(max($perPage, 1), 100);

        return response()->json([
            'message' => 'Metode Pembayaran retrieved successfully',
            'data' => $query->orderBy('kode')->paginate($perPage),
        ]);
    }
}
