<?php

namespace App\Http\Controllers;

use App\Models\Province;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

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

    public function sync(Request $request)
    {
        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'token' => env('SIKI_TOKEN'),
            ])->timeout(60)->get('https://siki.pu.go.id/siki-api/v1/provinsi');

            if (!$response->successful()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Gagal mengambil data provinsi dari SIKI API',
                ], 502);
            }

            $apiData = $response->json();

            if (($apiData['status'] ?? '') !== 'success' || !isset($apiData['data'])) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Respon SIKI API tidak valid',
                ], 502);
            }

            $items = $apiData['data'];
            $added = 0;
            $skipped = 0;
            $errors = [];

            foreach ($items as $item) {
                $idProvinsi = $item['ID_Propinsi'] ?? '';

                if (Province::where('id_provinsi', $idProvinsi)->exists()) {
                    $skipped++;
                    continue;
                }

                try {
                    Province::create([
                        'id_provinsi' => $idProvinsi,
                        'id_provinsi_dagri' => $item['id_propinsi_dagri'] ?? '',
                        'nama' => $item['Nama'] ?? '',
                    ]);
                    $added++;
                } catch (\Exception $e) {
                    $errors[] = [
                        'id_provinsi' => $idProvinsi,
                        'message' => $e->getMessage(),
                    ];
                }
            }

            return response()->json([
                'status' => 'success',
                'message' => "Sinkronisasi selesai. {$added} provinsi baru ditambahkan, {$skipped} sudah ada.",
                'data' => [
                    'total_from_api' => count($items),
                    'added' => $added,
                    'skipped' => $skipped,
                    'errors' => $errors,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal sinkronisasi: ' . $e->getMessage(),
            ], 500);
        }
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
