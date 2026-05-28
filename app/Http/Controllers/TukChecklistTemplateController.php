<?php

namespace App\Http\Controllers;

use App\Models\TukChecklistTemplate;
use Illuminate\Http\Request;

class TukChecklistTemplateController extends Controller
{
    public function index(Request $request)
    {
        $query = TukChecklistTemplate::where('is_active', true)->orderBy('urutan');

        if ($request->filled('kategori')) {
            $query->where('kategori', $request->kategori);
        }

        return response()->json([
            'status' => 'success',
            'data' => $query->get(),
        ]);
    }
}
