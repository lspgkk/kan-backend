<?php

namespace App\Http\Controllers;

use App\Models\Subklasifikasi;
use Illuminate\Http\Request;

class SubklasifikasiController extends Controller
{
    public function index(Request $request)
    {
        // If frontend explicitly asks for all data
        if ($request->boolean('all')) {
            $subklasifikasis = Subklasifikasi::where('id_klasifikasi', 'SI')->get();

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

        $subklasifikasis = Subklasifikasi::where('id_klasifikasi', 'SI')->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $subklasifikasis,
        ]);
    }
}
