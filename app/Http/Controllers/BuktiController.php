<?php

namespace App\Http\Controllers;

use App\Models\AsesiJadwal;
use App\Models\BuktiAsesmen;
use App\Models\BuktiKegiatan;
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

    public function uploadBuktiKegiatan(Request $request, $id)
    {
        $request->validate([
            'foto_bersama' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',

            'ttd_asesi_asesmen'  => 'nullable|image|mimes:jpg,jpeg,png,webp,pdf|max:2048',
            'ttd_asesi_pra'      => 'nullable|file|mimes:jpg,jpeg,png,webppdf|max:2048',
            'ttd_asesor_asesmen' => 'nullable|file|mimes:jpg,jpeg,png,webppdf|max:2048',
            'ttd_asesor_pra'     => 'nullable|file|mimes:jpg,jpeg,png,webppdf|max:2048',
        ]);

        $baseUrl = 'https://moon.lspgatensi.id/files/new_gatensi';
        $data = [];

        /* =========================
        * OPTIONAL FOTO BERSAMA
        * ========================= */
        if ($request->hasFile('foto_bersama')) {
            $image = $request->file('foto_bersama');
            $imageName = Str::uuid() . '.' . $image->getClientOriginalExtension();
            $imagePath = "dok_asesmen/foto/{$imageName}";

            $uploaded = Storage::disk('str_moon')->putFileAs(
                'dok_asesmen/foto',
                $image,
                $imageName
            );

            if (!$uploaded) {
                return response()->json(['message' => 'Upload foto gagal'], 500);
            }

            $data['url_foto_bersama'] = "{$baseUrl}/{$imagePath}";
        }

        /* =========================
        * OPTIONAL PDF UPLOADS
        * ========================= */
        $pdfFields = [
            'ttd_asesi_asesmen'  => 'url_ttd_asesi_asesmen',
            'ttd_asesi_pra'      => 'url_ttd_asesi_pra',
            'ttd_asesor_asesmen' => 'url_ttd_asesor_asesmen',
            'ttd_asesor_pra'     => 'url_ttd_asesor_pra',
        ];

        foreach ($pdfFields as $input => $column) {
            if ($request->hasFile($input)) {
                $pdf = $request->file($input);
                $pdfName = Str::uuid() . '.pdf';
                $pdfPath = "dok_asesmen/ttd/{$pdfName}";

                Storage::disk('str_moon')->putFileAs(
                    'dok_asesmen/ttd',
                    $pdf,
                    $pdfName
                );

                $data[$column] = "{$baseUrl}/{$pdfPath}";
            }
        }

        /* =========================
        * SAVE (ONLY IF SOMETHING EXISTS)
        * ========================= */
        if (!empty($data)) {
            BuktiKegiatan::updateOrCreate(
                ['id_jadwal' => $id],
                $data
            );
        }

        return response()->json([
            'message' => 'success',
            'data'    => $data,
        ]);
    }

    public function getBuktiKegiatan($id)
    {
        $data = BuktiKegiatan::where('id_jadwal', $id)->first();

        if (!$data) {
            return response()->json([
                'message' => 'Data not found',
                'data'    => null,
            ], 404);
        }

        return response()->json([
            'message' => 'success',
            'data' => [
                'url_foto_bersama'        => $data->url_foto_bersama,
                'url_ttd_asesi_asesmen'   => $data->url_ttd_asesi_asesmen,
                'url_ttd_asesi_pra'       => $data->url_ttd_asesi_pra,
                'url_ttd_asesor_asesmen'  => $data->url_ttd_asesor_asesmen,
                'url_ttd_asesor_pra'      => $data->url_ttd_asesor_pra,
            ],
        ]);
    }
}