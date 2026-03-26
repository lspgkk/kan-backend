<?php

namespace App\Http\Controllers;

use App\Models\AsesorBnsp;
use Illuminate\Http\Request;

class AsesorController extends Controller
{
    public function index(Request $request)
    {
        $query = AsesorBnsp::select(['id', 'noreg', 'nama', 'nik']);

        // Search functionality
        if ($request->has('search')) {
            $searchTerm = $request->query('search');
            $query->where(function ($q) use ($searchTerm) {
                $q->where('nama', 'like', "%{$searchTerm}%")
                    ->orWhere('noreg', 'like', "%{$searchTerm}%")
                    ->orWhere('nik', 'like', "%{$searchTerm}%");
            });
        }

        // If frontend explicitly asks for all data
        if ($request->boolean('all')) {
            $asesors = $query->get();

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

        $asesors = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $asesors,
        ]);
    }
}
