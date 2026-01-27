<?php

namespace App\Http\Controllers;

use App\Models\UrutanDokumen;
use Illuminate\Http\Request;

class UjiController extends Controller
{
    public function urutanDokumen()
    {
        $urutanDokumen = UrutanDokumen::all();
        
        return response()->json([
            'message' => 'Success',
            'data' => $urutanDokumen
        ]);
    }
}
