<?php

namespace App\Http\Controllers;

use App\Models\Diskon;
use Illuminate\Http\Request;

class DiskonController extends Controller
{
    public function index(Request $request)
    {
        $query = Diskon::select(['id', 'name', 'persentase', 'deskripsi']);

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('deskripsi', 'like', "%{$search}%");
            });
        }

        $perPage = (int) $request->query('per_page', 10);
        $perPage = min(max($perPage, 1), 100);

        return response()->json([
            'message' => 'Diskon retrieved successfully',
            'data' => $query->orderBy('name')->paginate($perPage),
        ]);
    }
}
