<?php

namespace App\Http\Controllers;

use App\Models\JabatanKerja;
use App\Models\Kualifikasi;
use App\Models\Subklasifikasi;
use Illuminate\Http\Request;

class JabatanKerjaController extends Controller
{
    public function index(Request $request)
    {
        // If frontend explicitly asks for all data
        if ($request->boolean('all')) {
            $jabatanKerjas = JabatanKerja::all();

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

        $jabatanKerjas = JabatanKerja::paginate($perPage);

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

    public function subklasifikasi(Request $request)
    {
        // If frontend explicitly asks for all data
        if ($request->boolean('all')) {
            $subklasifikasis = Subklasifikasi::get();

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

        $subklasifikasis = Subklasifikasi::paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $subklasifikasis,
        ]);
    }
}
