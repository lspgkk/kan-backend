<?php

namespace App\Http\Controllers;

use App\Models\Jenjang;
use Illuminate\Http\Request;

class JenjangController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Jenjang::select(['id_siki', 'id_bnsp', 'nama']);

        // Search functionality
        if ($request->has('search')) {
            $searchTerm = $request->query('search');
            $query->where(function ($q) use ($searchTerm) {
                $q->where('nama', 'like', "%{$searchTerm}%")
                    ->orWhere('id_siki', 'like', "%{$searchTerm}%")
                    ->orWhere('id_bnsp', 'like', "%{$searchTerm}%");
            });
        }

        // If frontend explicitly asks for all data
        if ($request->boolean('all')) {
            $jenjang = $query->get();

            return response()->json([
                'status' => 'success',
                'data' => $jenjang,
                'meta' => [
                    'all' => true,
                    'total' => $jenjang->count(),
                ],
            ]);
        }

        // Normal pagination
        $perPage = (int) $request->query('per_page', 10);
        $perPage = min(max($perPage, 1), 100);

        $jenjang = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $jenjang,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Jenjang $jenjang)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Jenjang $jenjang)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Jenjang $jenjang)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Jenjang $jenjang)
    {
        //
    }
}
