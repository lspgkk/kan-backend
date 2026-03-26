<?php

namespace App\Http\Controllers;

use App\Models\JenisAnggaran;
use Illuminate\Http\Request;

class JenisAnggaranController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // If frontend explicitly asks for all data
        if ($request->boolean('all')) {
            $jenis_anggaran = JenisAnggaran::select(['id', 'nama_anggaran', 'status'])->get();

            return response()->json([
                'status' => 'success',
                'data' => $jenis_anggaran,
                'meta' => [
                    'all' => true,
                    'total' => $jenis_anggaran->count(),
                ],
            ]);
        }

        // Normal pagination
        $perPage = (int) $request->query('per_page', 10);
        $perPage = min(max($perPage, 1), 100);

        $jenis_anggaran = JenisAnggaran::select(['id', 'nama_anggaran', 'status'])->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $jenis_anggaran,
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
    public function show(JenisAnggaran $jenisAnggaran)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(JenisAnggaran $jenisAnggaran)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, JenisAnggaran $jenisAnggaran)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(JenisAnggaran $jenisAnggaran)
    {
        //
    }
}
