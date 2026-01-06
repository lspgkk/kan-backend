<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MyTuk;
use App\Models\MyTukBnsp;

class TukController extends Controller
{
    public function allTukSiki(Request $request)
    {
        // If frontend explicitly asks for all data
        if ($request->boolean('all')) {
            $tuks = MyTuk::where('id_lsp', 16)->get();

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

        $tuks = MyTuk::where('id_lsp', 16)->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $tuks,
        ]);
    }

    public function allTukBnsp(Request $request)
    {
        // If frontend explicitly asks for all data
        if ($request->boolean('all')) {
            $tuks = MyTukBnsp::all();

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

        $tuks = MyTukBnsp::paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $tuks,
        ]);
    }
}
