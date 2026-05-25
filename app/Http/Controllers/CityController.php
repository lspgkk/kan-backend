<?php

namespace App\Http\Controllers;

use App\Models\City;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class CityController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = City::select(['kode_kabupaten_bps as kode', 'nama_kabupaten_bps as nama']);

        // Search functionality
        if ($request->has('search')) {
            $searchTerm = $request->query('search');
            $query->where(function ($q) use ($searchTerm) {
                $q->where('nama_kabupaten_bps', 'like', "%{$searchTerm}%")
                    ->orWhere('kode_kabupaten_bps', 'like', "%{$searchTerm}%");
            });
        }

        // If frontend explicitly asks for all data
        if ($request->boolean('all')) {
            $city = $query->get();

            return response()->json([
                'status' => 'success',
                'data' => $city,
                'meta' => [
                    'all' => true,
                    'total' => $city->count(),
                ],
            ]);
        }

        // Normal pagination
        $perPage = (int) $request->query('per_page', 10);
        $perPage = min(max($perPage, 1), 100);

        $city = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $city,
        ]);
    }

    public function sync(Request $request)
    {
        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'token' => env('SIKI_TOKEN'),
            ])->timeout(60)->get('https://siki.pu.go.id/siki-api/v1/kabupaten');

            if (!$response->successful()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Gagal mengambil data kabupaten dari SIKI API',
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
                $kode = $item['kode_kabupaten_bps'] ?? '';

                if (City::where('kode_kabupaten_bps', $kode)->exists()) {
                    $skipped++;
                    continue;
                }

                try {
                    City::create([
                        'id_propinsi_bps' => $item['id_propinsi_bps'] ?? '',
                        'nama_kabupaten_bps' => $item['nama_kabupaten_bps'] ?? '',
                        'kode_kabupaten_bps' => $kode,
                        'id_propinsi_dagri' => $item['id_propinsi_dagri'] ?? '',
                        'nama_kabupaten_dagri' => $item['nama_kabupaten_dagri'] ?? '',
                        'kode_kabupaten_dagri' => $item['kode_kabupaten_dagri'] ?? '',
                        'cek' => $item['cek'] ?? '',
                        'created_by' => $request->user()?->name ?? 'system',
                    ]);
                    $added++;
                } catch (\Exception $e) {
                    $errors[] = [
                        'kode_kabupaten_bps' => $kode,
                        'message' => $e->getMessage(),
                    ];
                }
            }

            return response()->json([
                'status' => 'success',
                'message' => "Sinkronisasi selesai. {$added} kabupaten baru ditambahkan, {$skipped} sudah ada.",
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
    public function show(City $city)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(City $city)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, City $city)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(City $city)
    {
        //
    }
}
