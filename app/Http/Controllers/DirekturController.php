<?php

namespace App\Http\Controllers;

use App\Models\BaKomtek;
use App\Models\Jadwal;
use App\Models\SkPelaksanaanUji;
use App\Models\SPTAsesor;
use App\Models\SPTKomtek;
use Illuminate\Http\Request;

class DirekturController extends Controller
{
    public function jadwalFiles($id)
    {
        $jadwal = Jadwal::where('jadwal_id', $id)->first();

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
                'is_approved' => $sk_pelaksanaan_uji ? $sk_pelaksanaan_uji->is_approved : null,
                'approval_status' => [
                    'ba_komtek' => $jadwal ? (bool) $jadwal->ba_komtek_direktur_approved : false,
                    'sk_komtek' => $jadwal ? (bool) $jadwal->sk_komtek_direktur_approved : false,
                    'sk_hasil_asesmen' => $jadwal ? (bool) $jadwal->sk_hasil_asesmen_direktur_approved : false,
                ],
            ]
        ]);
    }

    public function approveBaKomtek($jadwal_id)
    {
        return $this->approve($jadwal_id, 'ba_komtek_direktur_approved', 'BA Komtek');
    }

    public function approveSkKomtek($jadwal_id)
    {
        return $this->approve($jadwal_id, 'sk_komtek_direktur_approved', 'SK Komtek');
    }

    public function approveSkHasilAsesmen($jadwal_id)
    {
        return $this->approve($jadwal_id, 'sk_hasil_asesmen_direktur_approved', 'SK Hasil Asesmen');
    }

    private function approve($jadwal_id, $column, $label)
    {
        if (auth()->user()->role_id !== '6') {
            return response()->json(['message' => 'Invalid Role'], 403);
        }

        $jadwal = Jadwal::where('jadwal_id', $jadwal_id)->first();

        if (!$jadwal) {
            return response()->json(['message' => 'Jadwal tidak ditemukan'], 404);
        }

        if ((int) $jadwal->ketua_lsp !== (int) auth()->id()) {
            return response()->json(['message' => 'Anda bukan ketua LSP untuk jadwal ini'], 403);
        }

        $jadwal->update([$column => true]);

        return response()->json([
            'message' => "{$label} berhasil disetujui",
        ]);
    }
}
