<?php

namespace App\Http\Controllers;

use App\Models\JenisKelas;
use Illuminate\Http\Request;

class JenisKelasController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = JenisKelas::select(['id', 'nama_kelas']);

        // Search functionality
        if ($request->has('search')) {
            $searchTerm = $request->query('search');
            $query->where(function ($q) use ($searchTerm) {
                $q->where('nama_kelas', 'like', "%{$searchTerm}%")
                    ->orWhere('id', 'like', "%{$searchTerm}%");
            });
        }

        // If frontend explicitly asks for all data
        if ($request->boolean('all')) {
            $jenis_kelas = $query->get();

            return response()->json([
                'status' => 'success',
                'data' => $jenis_kelas,
                'meta' => [
                    'all' => true,
                    'total' => $jenis_kelas->count(),
                ],
            ]);
        }

        // Normal pagination
        $perPage = (int) $request->query('per_page', 10);
        $perPage = min(max($perPage, 1), 100);

        $jenis_kelas = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $jenis_kelas,
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
    public function show(JenisKelas $jenisKelas)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(JenisKelas $jenisKelas)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, JenisKelas $jenisKelas)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(JenisKelas $jenisKelas)
    {
        //
    }
}
