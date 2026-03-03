<?php

namespace App\Http\Controllers;

use App\Models\BaKomtek;
use App\Models\SPTAsesor;
use App\Models\SPTKomtek;
use Illuminate\Http\Request;

class DirekturController extends Controller
{
    public function jadwalFiles($id)
    {
        $ba_komtek = BaKomtek::where('jadwal_id', $id)->value('link');
        $spt_komtek = SPTKomtek::where('jadwal_id', $id)->value('link');
        $spt_asesor = SPTAsesor::where('jadwal_id', $id)->value('link');

        return response()->json([
            'message' => 'Success',
            'data' => [
                'sk_pelaksanaan_uji' => null,
                'spt_asesor' => $spt_asesor,
                'spt_komtek' => $spt_komtek,
                'sk_komtek' => null,
                'ba_komtek' => $ba_komtek,
            ]
        ]);
    }
}
