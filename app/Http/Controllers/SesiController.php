<?php

namespace App\Http\Controllers;

use App\Models\Sesi;
use Illuminate\Http\Request;

class SesiController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // If frontend explicitly asks for all data
        if ($request->boolean('all')) {
            $sesi = Sesi::select(['id', 'sesi'])->get();

            return response()->json([
                'status' => 'success',
                'data' => $sesi,
                'meta' => [
                    'all' => true,
                    'total' => $sesi->count(),
                ],
            ]);
        }

        // Normal pagination
        $perPage = (int) $request->query('per_page', 10);
        $perPage = min(max($perPage, 1), 100);

        $sesi = Sesi::select(['id', 'sesi'])->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $sesi,
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
    public function show(Sesi $sesi)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Sesi $sesi)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Sesi $sesi)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Sesi $sesi)
    {
        //
    }
}
