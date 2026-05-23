<?php

namespace App\Http\Controllers;

use App\Models\AsesiJadwal;
use App\Models\Pendidikan;
use App\Models\Personal;
use App\Models\Proyek;
use Illuminate\Http\Request;

class KebenaranDataController extends Controller
{
    public function index($id_izin = null)
    {
        if (!$id_izin) {
            $user = auth()->user();
            $id_izin = AsesiJadwal::where('account', $user->email)->latest('created_at')->value('id_izin');
        }

        $personal = Personal::select(['nama', 'nik', 'tempat_lahir', 'tanggal_lahir', 'jenis_kelamin', 'alamat', 'telepon', 'email', 'file_npwp', 'ktp', 'pas_foto'])->where('id_izin', $id_izin)->first();
        $pendidikan = Pendidikan::select(['program_studi', 'scan_ijazah_legalisir'])->latest('created_at')->where('id_izin', $id_izin)->first();

        if (!$personal) {
            return response()->json([
                'success' => false,
                'message' => 'Data asesi tidak ditemukan. Pastikan sudah diverifikasi.',
            ], 404);
        }

        $referensi = Proyek::where('id_izin', $id_izin)->latest('created_at')->value('surat_referensi');
        return response()->json([
            'success' => true,
            'data' => [
                'nama' => $personal->nama,
                'nik' => $personal->nik,
                'tempat_lahir' => $personal->tempat_lahir,
                'tanggal_lahir' => $personal->tanggal_lahir,
                'jenis_kelamin' => $personal->jenis_kelamin,
                'alamat' => $personal->alamat,
                'telepon' => $personal->telepon,
                'email' => $personal->email,
                'pendidikan' => $pendidikan?->program_studi,
                'npwp' => $personal->file_npwp,
                'ktp' => $personal->ktp,
                'pas_foto' => $personal->pas_foto,
                'referensi_kerja' => $referensi,
                'ijazah' => $pendidikan?->scan_ijazah_legalisir,
            ]
        ]);
    }
}
