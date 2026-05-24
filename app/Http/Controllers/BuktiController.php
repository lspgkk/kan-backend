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
    public function indexDokumenAsesi(Request $request)
    {
        $perPage = min((int) $request->input('per_page', 10), 100);
        $search  = trim($request->input('search', ''));

        // Paginated base query (lightweight — no bukti_asesmens join)
        $query = AsesiJadwal::select([
            'asesi_jadwals.id_izin',
            'asesi_jadwals.jadwal_id',
            'personals.nama',
        ])->join('personals', 'personals.id_izin', '=', 'asesi_jadwals.id_izin');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('asesi_jadwals.id_izin', 'like', "%{$search}%")
                  ->orWhere('personals.nama', 'like', "%{$search}%");
            });
        }

        $data = $query->orderByDesc('asesi_jadwals.id_izin')->paginate($perPage);

        // Batch-load dokumen for this page only
        $idIzins = collect($data->items())->pluck('id_izin');
        $dokumenRows = BuktiAsesmen::whereIn('id_izin', $idIzins)->get()->keyBy('id_izin');

        // Map dokumen into each row
        $data->getCollection()->transform(function ($item) use ($dokumenRows) {
            $dok = $dokumenRows->get($item->id_izin);
            if ($dok) {
                $item->bukti_id_izin = $dok->id_izin;
                $item->url_apl_01 = $dok->url_apl_01;
                $item->url_apl_02 = $dok->url_apl_02;
                $item->url_mapa01 = $dok->url_mapa01;
                $item->url_mapa02 = $dok->url_mapa02;
                $item->url_ak01 = $dok->url_ak01;
                $item->url_ak02 = $dok->url_ak02;
                $item->url_ak03 = $dok->url_ak03;
                $item->url_ak04 = $dok->url_ak04;
                $item->url_ak05 = $dok->url_ak05;
                $item->url_ak06 = $dok->url_ak06;
                $item->url_ak07 = $dok->url_ak07;
                $item->url_ia_01 = $dok->url_ia_01;
                $item->url_ia_02 = $dok->url_ia_02;
                $item->url_ia_03 = $dok->url_ia_03;
                $item->url_ia_04a = $dok->url_ia_04a;
                $item->url_ia_04b = $dok->url_ia_04b;
                $item->url_ia_05 = $dok->url_ia_05;
                $item->url_ia_08 = $dok->url_ia_08;
                $item->url_ia_09 = $dok->url_ia_09;
                $item->url_ia_10 = $dok->url_ia_10;
                $item->url_paparan = $dok->url_paparan;
                $item->url_docs_asesmen = $dok->url_docs_asesmen;
            } else {
                $item->bukti_id_izin = null;
                $item->url_apl_01 = null;
                $item->url_apl_02 = null;
                $item->url_mapa01 = null;
                $item->url_mapa02 = null;
                $item->url_ak01 = null;
                $item->url_ak02 = null;
                $item->url_ak03 = null;
                $item->url_ak04 = null;
                $item->url_ak05 = null;
                $item->url_ak06 = null;
                $item->url_ak07 = null;
                $item->url_ia_01 = null;
                $item->url_ia_02 = null;
                $item->url_ia_03 = null;
                $item->url_ia_04a = null;
                $item->url_ia_04b = null;
                $item->url_ia_05 = null;
                $item->url_ia_08 = null;
                $item->url_ia_09 = null;
                $item->url_ia_10 = null;
                $item->url_paparan = null;
                $item->url_docs_asesmen = null;
            }
            return $item;
        });

        return response()->json([
            'message' => 'Dokumen Asesi retrieved successfully.',
            'data'    => $data,
        ]);
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

        // Increase execution time for file uploads
        set_time_limit(300); // 5 minutes

        try {
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
                    'message' => 'Upload failed - Server tidak merespon',
                ], 503);
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
        } catch (\Exception $e) {
            \Log::error('Upload error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Upload gagal: ' . $e->getMessage(),
            ], 503);
        }
    }

    public function uploadBuktiKegiatan(Request $request, $id)
    {
        $request->validate([
            'foto_bersama' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',

            'ttd_asesi_asesmen'  => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:2048',
            'ttd_asesi_pra'      => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:2048',
            'ttd_asesor_asesmen' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:2048',
            'ttd_asesor_pra'     => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:2048',
        ]);

        $baseUrl = 'https://moon.lspgatensi.id/files/new_gatensi';
        $data = [];

        // Increase execution time for file uploads
        set_time_limit(300); // 5 minutes

        /* =========================
        * OPTIONAL FOTO BERSAMA
        * ========================= */
        if ($request->hasFile('foto_bersama')) {
            try {
                $image = $request->file('foto_bersama');
                $imageName = Str::uuid() . '.' . $image->getClientOriginalExtension();
                $imagePath = "dok_asesmen/foto/{$imageName}";

                $uploaded = Storage::disk('str_moon')->putFileAs(
                    'dok_asesmen/foto',
                    $image,
                    $imageName
                );

                if (!$uploaded) {
                    return response()->json(['message' => 'Upload foto gagal - Server tidak merespon'], 503);
                }

                $data['url_foto_bersama'] = "{$baseUrl}/{$imagePath}";
            } catch (\Exception $e) {
                \Log::error('Upload foto error: ' . $e->getMessage());
                return response()->json(['message' => 'Upload foto gagal: ' . $e->getMessage()], 503);
            }
        }

        /* =========================
        * OPTIONAL FILE UPLOADS
        * ========================= */
        $fileFields = [
            'ttd_asesi_asesmen'  => 'url_ttd_asesi_asesmen',
            'ttd_asesi_pra'      => 'url_ttd_asesi_pra',
            'ttd_asesor_asesmen' => 'url_ttd_asesor_asesmen',
            'ttd_asesor_pra'     => 'url_ttd_asesor_pra',
        ];

        foreach ($fileFields as $input => $column) {
            if ($request->hasFile($input)) {
                try {
                    $file = $request->file($input);
                    $fileName = Str::uuid() . '.' . $file->getClientOriginalExtension();
                    $filePath = "dok_asesmen/ttd/{$fileName}";

                    $uploaded = Storage::disk('str_moon')->putFileAs(
                        'dok_asesmen/ttd',
                        $file,
                        $fileName
                    );

                    if (!$uploaded) {
                        return response()->json(['message' => "Upload {$input} gagal - Server tidak merespon"], 503);
                    }

                    $data[$column] = "{$baseUrl}/{$filePath}";
                } catch (\Exception $e) {
                    \Log::error("Upload {$input} error: " . $e->getMessage());
                    return response()->json(['message' => "Upload {$input} gagal: " . $e->getMessage()], 503);
                }
            }
        }

        /* =========================
        * SAVE (ONLY IF SOMETHING EXISTS)
        * ========================= */
        if (!empty($data)) {
            try {
                BuktiKegiatan::updateOrCreate(
                    ['id_jadwal' => $id],
                    $data
                );
            } catch (\Exception $e) {
                \Log::error('Database error: ' . $e->getMessage());
                return response()->json(['message' => 'Gagal menyimpan data: ' . $e->getMessage()], 500);
            }
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