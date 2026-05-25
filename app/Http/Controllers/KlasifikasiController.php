<?php

namespace App\Http\Controllers;

use App\Models\Klasifikasi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class KlasifikasiController extends Controller
{
    public function index(Request $request)
    {
        $query = Klasifikasi::select(['id', 'id_klasifikasi', 'klasifikasi', 'klasifikasi_en']);

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('id_klasifikasi', 'like', "{$search}%")
                  ->orWhere('klasifikasi', 'like', "{$search}%");
            });
        }

        if ($request->boolean('all')) {
            $klasifikasis = $query->orderBy('id_klasifikasi')->get();

            return response()->json([
                'status' => 'success',
                'data' => $klasifikasis,
                'meta' => [
                    'all' => true,
                    'total' => $klasifikasis->count(),
                ],
            ]);
        }

        $perPage = (int) $request->query('per_page', 10);
        $perPage = min(max($perPage, 1), 100);

        $klasifikasis = $query->orderBy('id_klasifikasi')->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $klasifikasis,
        ]);
    }

    public function sync(Request $request)
    {
        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'token' => env('SIKI_TOKEN'),
            ])->timeout(60)->get('https://siki.pu.go.id/siki-api/v2/klasifikasi');

            if (!$response->successful()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Gagal mengambil data dari SIKI API',
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
                $idKlasifikasi = $item['id_klasifikasi'] ?? '';

                if (Klasifikasi::where('id_klasifikasi', $idKlasifikasi)->exists()) {
                    $skipped++;
                    continue;
                }

                try {
                    Klasifikasi::create([
                        'id_klasifikasi' => $idKlasifikasi,
                        'klasifikasi' => $item['klasifikasi'] ?? '',
                        'klasifikasi_en' => $item['klasifikasi_en'] ?? null,
                    ]);
                    $added++;
                } catch (\Exception $e) {
                    $errors[] = [
                        'id_klasifikasi' => $idKlasifikasi,
                        'message' => $e->getMessage(),
                    ];
                }
            }

            return response()->json([
                'status' => 'success',
                'message' => "Sinkronisasi selesai. {$added} klasifikasi baru ditambahkan, {$skipped} sudah ada.",
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
}
