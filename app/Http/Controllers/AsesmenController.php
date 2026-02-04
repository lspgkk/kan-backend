<?php

namespace App\Http\Controllers;

use App\Models\JabatanDokumen;
use App\Models\KlasifikasiKualifikasi;
use App\Models\Referensi;
use App\Models\Soal;
use Illuminate\Http\Request;

class AsesmenController extends Controller
{
    public function getIa04a($id)
    {
        $klasifikasi = KlasifikasiKualifikasi::select('jabatan_kerja')
            ->where('id_izin', $id)
            ->first();

        if (!$klasifikasi) {
            return response()->json([
                'message' => 'Data not found',
                'data' => null
            ], 404);
        }
        
        $referensis = Referensi::select(['id', 'nama'])
            ->where('kategori', 'IA04')
            ->get();

        $dokumen = JabatanDokumen::with([
                'kelompoks.units.unit'
            ])
            ->where('id_jabatan', $klasifikasi->jabatan_kerja)
            ->where('kode', 'FR.IA.04')
            ->where('is_active', true)
            ->first();

        if (!$dokumen) {
            return response()->json([
                'message' => 'Success',
                'data' => []
            ]);
        }

        $questions = Soal::select(['id', 'urut', 'jenis', 'soal', 'jawab_a as jawaban', 'is_komentar'])->where('id_dokumen', $dokumen->id)->orderBy('urut', 'asc')->get();

        $kelompokKerja = [
            'id' => $dokumen->id,
            'kode' => $dokumen->kode,
            'nama_dokumen' => $dokumen->nama_dokumen,
            'kelompok_kerja' => $dokumen->kelompoks->map(function ($kelompok) {
                return [
                    'id' => $kelompok->id,
                    'nama' => $kelompok->nama,
                    'urut' => $kelompok->urut,
                    'units' => $kelompok->units->map(function ($unit) {
                        return [
                            'id_unit'   => $unit->id_unit,
                            'nama_unit' => $unit->unit?->nama,
                            'kode_unit' => $unit->unit?->kode,
                        ];
                    })->values()
                ];
            })->values()
        ];

        return response()->json([
            'message' => 'Success',
            'data' => [
                'kelompok_kerja' => $kelompokKerja,
                'referensi_form' => $referensis,
                'soal' => $questions
            ]
        ]);
    }

    public function uploadTugas(Request $request, $id)
    {
        
    }

    public function getIa04b($id)
    {
        $klasifikasi = KlasifikasiKualifikasi::select('jabatan_kerja')
            ->where('id_izin', $id)
            ->first();

        if (!$klasifikasi) {
            return response()->json([
                'message' => 'Data not found',
                'data' => null
            ], 404);
        }

        $dokumen = JabatanDokumen::select(['id', 'nama_dokumen'])
            ->where('id_jabatan', $klasifikasi->jabatan_kerja)
            ->where('kode', 'FR.IA.04.B')
            ->where('is_active', true)
            ->first();

        if (!$dokumen) {
            return response()->json([
                'message' => 'Success',
                'data' => []
            ]);
        }

        $questions = Soal::select(['id', 'urut', 'jenis', 'tipe', 'soal', 'soal1', 'soal2', 'is_komentar'])
            ->where('id_dokumen', $dokumen->id)
            ->orderBy('urut', 'asc')->get()->groupBy('tipe');

        return response()->json([
            'message' => 'Success',
            'data' => [
                'dokumen' => $dokumen,
                'soal' => $questions
            ]
        ]);
    }

    public function getIa05($id)
    {
        
    }

    public function getAk02($id)
    {
        
    }

    public function getAk03($id)
    {
        
    }
}
