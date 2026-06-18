<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\MyTuk;
use App\Models\MyTukBnsp;
use App\Models\TukDiscount;

class TukController extends Controller
{
    public function allTukSiki(Request $request)
    {
        $query = MyTuk::leftJoin('tuk_discounts', 'tuk_discounts.id_tuk_lpjk', '=', 'mytuk.id')
            ->select(['mytuk.id', 'mytuk.kode_tuk', 'mytuk.nama_tuk', 'mytuk.alamat', 'mytuk.latitude', 'mytuk.longitude', 'tuk_discounts.kategori'])
            ->where('id_lsp', 16);

        // Search functionality
        if ($request->has('search')) {
            $searchTerm = $request->query('search');
            $query->where(function ($q) use ($searchTerm) {
                $q->where('mytuk.kode_tuk', 'like', "%{$searchTerm}%")
                    ->orWhere('mytuk.nama_tuk', 'like', "%{$searchTerm}%")
                    ->orWhere('mytuk.alamat', 'like', "%{$searchTerm}%");
            });
        }

        // If frontend explicitly asks for all data
        if ($request->boolean('all')) {
            $tuks = $query->get();

            return response()->json([
                'status' => 'success',
                'data' => $tuks,
                'meta' => [
                    'all' => true,
                    'total' => $tuks->count(),
                ],
            ]);
        }

        // Normal pagination
        $perPage = (int) $request->query('per_page', 10);
        $perPage = min(max($perPage, 1), 100);

        $tuks = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $tuks,
        ]);
    }

    public function syncTukSiki(Request $request)
    {
        $request->validate([
            'id_lsp' => 'nullable|string',
        ]);

        $idLsp = $request->input('id_lsp', '04');

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'token' => env('SIKI_TOKEN'),
            ])->timeout(60)->get('https://siki.pu.go.id/siki-api/v2/tuk');

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

            $tuks = $apiData['data'];
            $added = 0;
            $skipped = 0;
            $errors = [];

            $now = now();

            foreach ($tuks as $tuk) {
                $sikiId = (string) $tuk['id'];

                if (MyTuk::find($sikiId)) {
                    $skipped++;
                    continue;
                }

                try {
                    MyTuk::create([
                        'id' => $sikiId,
                        'id_lsp' => $tuk['id_lsp'] ?? $idLsp,
                        'kode_tuk' => $tuk['kode_tuk'] ?? '',
                        'jenis_tuk' => $tuk['jenis_tuk'] ?? '',
                        'nama_tuk' => $tuk['nama_tuk'] ?? '',
                        'alamat' => $tuk['alamat'] ?? '',
                        'created_at' => $now,
                        'created_by' => $request->user()?->name ?? 'system',
                    ]);
                    $added++;
                } catch (\Exception $e) {
                    $errors[] = [
                        'id' => $sikiId,
                        'kode_tuk' => $tuk['kode_tuk'] ?? '',
                        'message' => $e->getMessage(),
                    ];
                }
            }

            return response()->json([
                'status' => 'success',
                'message' => "Sinkronisasi selesai. {$added} TUK baru ditambahkan, {$skipped} sudah ada.",
                'data' => [
                    'total_from_api' => count($tuks),
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

    public function showTukSiki(string $id)
    {
        $tuk = MyTuk::leftJoin('tuk_discounts', 'tuk_discounts.id_tuk_lpjk', '=', 'mytuk.id')
            ->select(['mytuk.id', 'mytuk.kode_tuk', 'mytuk.nama_tuk', 'mytuk.alamat', 'mytuk.latitude', 'mytuk.longitude', 'tuk_discounts.kategori'])
            ->where('mytuk.id', $id)
            ->first();

        if (!$tuk) {
            return response()->json([
                'status' => 'error',
                'message' => 'TUK tidak ditemukan',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $tuk,
        ]);
    }

    public function updateKategori(Request $request, string $id)
    {
        $request->validate([
            'kategori' => 'required|string',
        ]);

        $tuk = MyTuk::find($id);

        if (!$tuk) {
            return response()->json([
                'status' => 'error',
                'message' => 'TUK tidak ditemukan',
            ], 404);
        }

        TukDiscount::updateOrCreate(
            ['id_tuk_lpjk' => $id],
            ['kategori' => $request->input('kategori'), 'nama_tuk' => $tuk->nama_tuk]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Kategori berhasil diupdate',
        ]);
    }

    public function allTukBnsp(Request $request)
    {
        $query = MyTukBnsp::select(['id', 'kode', 'nama']);

        // Search functionality
        if ($request->has('search')) {
            $searchTerm = $request->query('search');
            $query->where(function ($q) use ($searchTerm) {
                $q->where('kode', 'like', "%{$searchTerm}%")
                    ->orWhere('nama', 'like', "%{$searchTerm}%");
            });
        }

        // If frontend explicitly asks for all data
        if ($request->boolean('all')) {
            $tuks = $query->get();

            return response()->json([
                'status' => 'success',
                'data' => $tuks,
                'meta' => [
                    'all' => true,
                    'total' => $tuks->count(),
                ],
            ]);
        }

        // Normal pagination
        $perPage = (int) $request->query('per_page', 10);
        $perPage = min(max($perPage, 1), 100);

        $tuks = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $tuks,
        ]);
    }

    /**
     * Simple list of all TUKs for dropdown selections.
     * Returns only id and nama_tuk.
     * For adm_tuk users (role_id = 3), filters to only their assigned TUK.
     */
    public function listTuk(Request $request)
    {
        $query = MyTuk::select('id', 'nama_tuk')
            ->where('id_lsp', 16);

        // Role-based filtering (adm_tuk only sees their own TUK)
        $user = $request->user();
        if ($user && (int) $user->role_id === 3 && $user->tuk_id) {
            $query->where('id', $user->tuk_id);
        }

        $tuks = $query->orderBy('nama_tuk')->get();

        return response()->json([
            'status' => 'success',
            'data' => $tuks,
        ]);
    }
}
