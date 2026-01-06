<?php

namespace App\Http\Controllers;

use App\Models\AsesorBnsp;
use Illuminate\Http\Request;

class AsesorController extends Controller
{
    public function index(Request $request)
    {
        // If frontend explicitly asks for all data
        if ($request->boolean('all')) {
            $asesors = AsesorBnsp::select(['id', 'nama'])->get();

            return response()->json([
                'status' => 'success',
                'data' => $asesors,
                'meta' => [
                    'all' => true,
                    'total' => $asesors->count(),
                ],
            ]);
        }

        // Normal pagination
        $perPage = (int) $request->query('per_page', 10);
        $perPage = min(max($perPage, 1), 100);

        $asesors = AsesorBnsp::select(['id', 'nama'])->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $asesors,
        ]);
    }
}
