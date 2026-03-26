<?php

namespace App\Http\Controllers;

use App\Models\Subklasifikasi;
use Illuminate\Http\Request;

class SubklasifikasiController extends Controller
{
    public function index(Request $request)
    {
        $query = Subklasifikasi::select(['kode_subklasifikasi', 'deskripsi_subklasifikasi as nama'])->where('id_klasifikasi', 'SI');

        // Search functionality
        if ($request->has('search')) {
            $searchTerm = $request->query('search');
            $query->where(function ($q) use ($searchTerm) {
                $q->where('kode_subklasifikasi', 'like', "%{$searchTerm}%")
                    ->orWhere('deskripsi_subklasifikasi', 'like', "%{$searchTerm}%");
            });
        }

        // If frontend explicitly asks for all data
        if ($request->boolean('all')) {
            $subklasifikasis = $query->get();

            return response()->json([
                'status' => 'success',
                'data' => $subklasifikasis,
                'meta' => [
                    'all' => true,
                    'total' => $subklasifikasis->count(),
                ],
            ]);
        }

        // Normal pagination
        $perPage = (int) $request->query('per_page', 10);
        $perPage = min(max($perPage, 1), 100);

        $subklasifikasis = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $subklasifikasis,
        ]);
    }
}
