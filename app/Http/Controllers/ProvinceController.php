<?php

namespace App\Http\Controllers;

use App\Models\Province;
use Illuminate\Http\Request;

class ProvinceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Province::select(['id_provinsi', 'id_provinsi_dagri', 'nama']);

        // Search functionality
        if ($request->has('search')) {
            $searchTerm = $request->query('search');
            $query->where(function ($q) use ($searchTerm) {
                $q->where('nama', 'like', "%{$searchTerm}%")
                    ->orWhere('id_provinsi', 'like', "%{$searchTerm}%")
                    ->orWhere('id_provinsi_dagri', 'like', "%{$searchTerm}%");
            });
        }

        // If frontend explicitly asks for all data
        if ($request->boolean('all')) {
            $province = $query->get();

            return response()->json([
                'status' => 'success',
                'data' => $province,
                'meta' => [
                    'all' => true,
                    'total' => $province->count(),
                ],
            ]);
        }

        // Normal pagination
        $perPage = (int) $request->query('per_page', 10);
        $perPage = min(max($perPage, 1), 100);

        $province = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $province,
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
    public function show(Province $province)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Province $province)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Province $province)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Province $province)
    {
        //
    }
}
