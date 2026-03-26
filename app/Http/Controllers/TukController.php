<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MyTuk;
use App\Models\MyTukBnsp;

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
}
