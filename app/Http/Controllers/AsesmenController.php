<?php

namespace App\Http\Controllers;

use App\Models\AsesiJadwal;
use App\Models\JabatanConfigA;
use App\Models\JabatanDokumen;
use App\Models\JabatanKerja;
use App\Models\Jadwal;
use App\Models\KlasifikasiKualifikasi;
use App\Models\Personal;
use App\Models\Referensi;
use App\Models\Soal;
use App\Models\User;
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

    public function getDokumenHeader($id)
    {
        $namaAsesi = Personal::where('id_izin', $id)->value('nama');
        $jadwalId = AsesiJadwal::where('id_izin', $id)->value('jadwal_id');
        $klasifikasi = KlasifikasiKualifikasi::select(['jabatan_kerja', 'jenjang'])
            ->where('id_izin', $id)
            ->first();
        $jabatanKerja = JabatanKerja::where('id_jabatan_kerja', $klasifikasi->jabatan_kerja)->value('jabatan_kerja');
        $jabatanConfig = JabatanConfigA::select(['no_skema'])->where('id_jabatan', $klasifikasi->jabatan_kerja)->first();
        $jadwal = Jadwal::select([
            'jadwal_id',
            'tuk_id',
            'asesor_id',
            'asesor2_id',
            'tanggal_uji',
            'tanggal_selesai',
            'jenis_kelas',
        ])->with([
            'tuk:id,nama',
            'asesor:id,nama,noreg',
            'asesor2:id,nama,noreg',
        ])->where('jadwal_id', $jadwalId)->first();

        $idAsesor1 = User::where('noreg', $jadwal->asesor ? $jadwal->asesor->noreg : null)->value('id');
        $idAsesor2 = User::where('noreg', $jadwal->asesor2 ? $jadwal->asesor2->noreg : null)->value('id');

        return response()->json([
            'message' => 'Success',
            'data' => [
                'jabatan_kerja' => $jabatanKerja,
                'nomor_skema' => $jabatanConfig->no_skema,
                'tuk' => $jadwal->tuk ? $jadwal->tuk->nama : null,
                'id_asesor_1' => $idAsesor1,
                'id_asesor_2' => $idAsesor2,
                'nama_asesi' => $namaAsesi,
                'asesor_1' => $jadwal->asesor ? $jadwal->asesor->nama : null,
                'asesor_2' => $jadwal->asesor2 ? $jadwal->asesor2->nama : null,
                'noreg_asesor_1' => $jadwal->asesor ? $jadwal->asesor->noreg : null,
                'noreg_asesor_2' => $jadwal->asesor2 ? $jadwal->asesor2->noreg : null,
                'tanggal_uji' => $jadwal->tanggal_uji,
                'tanggal_selesai' => $jadwal->tanggal_selesai,
                'jenis_kelas' => $jadwal->jenis_kelas,
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
