<?php

namespace App\Http\Controllers;

use App\Models\Pendidikan;
use App\Models\Personal;
use App\Models\Proyek;
use Illuminate\Http\Request;

class BuktiController extends Controller
{
    public function absensiAsesmen(Request $request)
    {
        
    }

    public function absensiPraAsesmen(Request $request)
    {

    }

    public function getKebenaranData($id)
    {
        $personal = Personal::select(['nama', 'nik', 'tempat_lahir', 'tanggal_lahir', 'alamat', 'telepon', 'email', 'file_npwp', 'ktp', 'pas_foto'])->where('id_izin', $id)->first();
        $ijazah = Pendidikan::where('id_izin', $id)->value('scan_ijazah_legalisir');
        $referensiKerja = Proyek::where('id_izin', $id)->value('surat_referensi');
        $kebenaranData = [
            'personal' => $personal,
            'ijazah' => $ijazah,
            'referensi_kerja' => $referensiKerja
        ];
        return response()->json($kebenaranData);
    }
}
