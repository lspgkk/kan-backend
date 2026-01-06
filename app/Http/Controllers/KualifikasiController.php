<?php

namespace App\Http\Controllers;

use App\Models\Kualifikasi;
use Illuminate\Http\Request;

class KualifikasiController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // If frontend explicitly asks for all data
        if ($request->boolean('all')) {
            $kualifikasis = Kualifikasi::get();

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
