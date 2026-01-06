<?php

namespace App\Http\Controllers;

use App\Models\Asosiasi;
use Illuminate\Http\Request;

class AsosiasiController extends Controller
{
    public function index(Request $request)
    {
        // If frontend explicitly asks for all data
        if ($request->boolean('all')) {
            $asosiasis = Asosiasi::all();

            return response()->json([
                'status' => 'success',
                'data' => $asosiasis,
                'meta' => [
                    'all' => true,
                    'total' => $asosiasis->count(),
                ],
            ]);
        }

        // Normal pagination
        $perPage = (int) $request->query('per_page', 10);
        $perPage = min(max($perPage, 1), 100);

        $asosiasis = Asosiasi::paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $asosiasis,
        ]);
    }
}
