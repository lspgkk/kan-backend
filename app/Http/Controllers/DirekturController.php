<?php

namespace App\Http\Controllers;

use App\Models\BaKomtek;
use App\Models\SkKomtek;
use App\Models\SkPelaksanaanUji;
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
        $sk_pelaksanaan_uji = SkPelaksanaanUji::select(['link', 'is_approved'])->where('jadwal_id', $id)->first();

        return response()->json([
            'message' => 'Success',
            'data' => [
                'sk_pelaksanaan_uji' => $sk_pelaksanaan_uji,
                'spt_asesor' => $spt_asesor,
                'spt_komtek' => $spt_komtek,
                'sk_komtek' => env('APP_URL') . '/files/sk_komtek.pdf',
                'ba_komtek' => $ba_komtek,
                'is_approved' => $sk_pelaksanaan_uji ? $sk_pelaksanaan_uji->is_approved : null
            ]
        ]);
    }
}
