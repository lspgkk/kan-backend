<?php

namespace App\Http\Controllers;

use App\Models\Negara;
use Illuminate\Http\Request;

class NegaraController extends Controller
{
    public function index(Request $request)
    {
        $query = Negara::select(['id', 'name', 'code']);

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $perPage = (int) $request->query('per_page', 10);
        $perPage = min(max($perPage, 1), 100);

        return response()->json([
            'message' => 'Negara retrieved successfully',
            'data' => $query->orderBy('name')->paginate($perPage),
        ]);
    }
}
