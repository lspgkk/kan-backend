<?php

namespace App\Http\Controllers;

use App\Models\AsesiJadwal;
use App\Models\BuktiAsesmen;
use App\Models\Pendidikan;
use App\Models\Personal;
use App\Models\Proyek;
use App\Models\SPTAsesor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;

class BuktiController extends Controller
{
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

    public function dokumenAsesi($id)
    {
        $personal = Personal::select(['file_npwp', 'ktp'])->where('id_izin', $id)->first();
        $ijazah = Pendidikan::where('id_izin', $id)->latest('created_at')->value('scan_ijazah_legalisir');
        $referensiKerja = Proyek::where('id_izin', $id)->latest('created_at')->value('surat_referensi');
        $jadwalId = AsesiJadwal::where('id_izin', $id)->value('jadwal_id');
        $sptAsesor = SPTAsesor::where('jadwal_id', $jadwalId)->first();
        $verifikasiTuk = null;
        $kebenaranData = [
            'ktp' => $personal->ktp,
            'npwp' => $personal->file_npwp,
            'ijazah' => $ijazah,
            'referensi_kerja' => $referensiKerja,
            'spt_asesor' => $sptAsesor ? $sptAsesor->file_spt : null,
            'verifikasi_tuk' => $verifikasiTuk,
            
        ];
        return response()->json([
            'message' => 'Success',
            'data' => $kebenaranData
        ]);
    }

    public function uploadKegiatan(Request $request, $id)
    {
        $request->validate([
            'image' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048', // KB
            ],
        ]);

        $image = $request->file('image');

        $filename = (string) Str::uuid() . '.' . $image->getClientOriginalExtension();
        $path = "dok_asesmen/foto/{$filename}";

        $uploaded = Storage::disk('str_moon')->putFileAs(
            'dok_asesmen/foto',
            $image,
            $filename
        );

        if (!$uploaded) {
            return response()->json([
                'message' => 'Upload failed',
            ], 500);
        }

        $fullImageUrl = 'https://moon.lspgatensi.id/files/new_gatensi' . '/' . $path;

        BuktiAsesmen::updateOrCreate(
            ['id_izin' => $id],
            ['url_docs_asesmen' => $fullImageUrl]
        );

        return response()->json([
            'message' => 'success',
            'url' => $fullImageUrl,
        ]);
    }
}
