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

        $questions = Soal::select(['id', 'no', 'jenis', 'soal', 'soal1', 'soal2', 'is_komentar'])
            ->where('id_dokumen', $dokumen->id)->where('tipe', 8)
            ->orderBy('no', 'asc')->get();

        $rekomendasi = Soal::select(['id', 'no', 'jenis', 'soal', 'is_komentar'])
            ->where('id_dokumen', $dokumen->id)->where('tipe', 3)
            ->first();

        return response()->json([
            'message' => 'Success',
            'data' => [
                'dokumen' => $dokumen,
                'soal' => $questions,
                'rekomendasi' => $rekomendasi
            ]
        ]);
    }

    public function getIa05($id)
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
            ->where('kode', 'FR.IA.05')
            ->where('is_active', true)
            ->first();

        if (!$dokumen) {
            return response()->json([
                'message' => 'Success',
                'data' => []
            ]);
        }

        $questions = Soal::with('unit:id,kode')->select(['id', 'no', 'id_unitkompetensi', 'jenis', 'soal', 'jawab_a', 'jawab_b', 'jawab_c', 'jawab_d', 'file_a', 'file_b', 'file_c', 'file_d', 'jawaban'])
            ->where('id_dokumen', $dokumen->id)->where('tipe', 1)
            ->orderBy('no', 'asc')->get();

        return response()->json([
            'message' => 'Success',
            'data' => [
                'dokumen' => $dokumen,
                'soal' => $questions
            ]
        ]);
    }

    public function getAk02($id)
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
            ->where('kode', 'FR.AK.02')
            ->where('is_active', true)
            ->first();

        if (!$dokumen) {
            return response()->json([
                'message' => 'Success',
                'data' => []
            ]);
        }
    }

    public function getAk03($id)
    {
        
    }
}
