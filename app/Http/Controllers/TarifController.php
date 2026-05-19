<?php

namespace App\Http\Controllers;

use App\Models\Tarif;
use Illuminate\Http\Request;

class TarifController extends Controller
{
    public function index(Request $request)
    {
        $query = Tarif::select(['id', 'name', 'jenis', 'nominal']);

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('jenis', 'like', "%{$search}%");
            });
        }

        $perPage = (int) $request->query('per_page', 10);
        $perPage = min(max($perPage, 1), 100);

        return response()->json([
            'message' => 'Tarif retrieved successfully',
            'data' => $query->orderBy('name')->paginate($perPage),
        ]);
    }
}
