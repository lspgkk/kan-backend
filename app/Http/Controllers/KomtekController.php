<?php

namespace App\Http\Controllers;

use App\Models\AbsenKomtek;
use App\Models\AsesiJadwal;
use App\Models\BaKomtek;
use App\Models\Barcode;
use App\Models\JabatanKerja;
use App\Models\Jadwal;
use App\Models\KlasifikasiKualifikasi;
use App\Models\KomtekRecommendation;
use App\Models\Personal;
use App\Models\SkKomtek;
use App\Models\SPTKomtek;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\Storage;

class KomtekController extends Controller
{
    public function index(Request $request)
    {
        // If frontend explicitly asks for all data
        if ($request->boolean('all')) {
            $komteks = User::where('role_id', 4)->select(['id', 'name', 'noreg', 'email'])->get();

            return response()->json([
                'status' => 'success',
                'data' => $komteks,
                'meta' => [
                    'all' => true,
                    'total' => $komteks->count(),
                ],
            ]);
        }

        // Normal pagination
        $perPage = (int) $request->query('per_page', 10);
        $perPage = min(max($perPage, 1), 100);

        $query = User::where('role_id', 4)->select(['id', 'name', 'noreg', 'email']);

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "{$search}%")
                  ->orWhere('noreg', 'like', "{$search}%")
                  ->orWhere('email', 'like', "{$search}%");
            });
        }

        $komteks = $query->orderBy('name')->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $komteks,
        ]);
    }

    public function getRekomendasi($id)
    {
        $jadwal_id = AsesiJadwal::where('id_izin', $id)->value('jadwal_id');
        $jadwal = Jadwal::where('jadwal_id', $jadwal_id)->firstOrFail();

        $komtekIds = array_filter([
            $jadwal->komtek1,
            $jadwal->komtek2,
            $jadwal->komtek3,
        ]);

        $recommendations = KomtekRecommendation::where('id_izin', $id)
            ->whereIn('id_komtek', $komtekIds)
            ->get()
            ->keyBy('id_komtek');

        return response()->json([
            'komtek1' => [
                'id' => $jadwal->komtek1,
                'rekomendasi' => $recommendations[$jadwal->komtek1]->rekomendasi ?? null,
            ],
            'komtek2' => [
                'id' => $jadwal->komtek2,
                'rekomendasi' => $recommendations[$jadwal->komtek2]->rekomendasi ?? null,
            ],
            'komtek3' => [
                'id' => $jadwal->komtek3,
                'rekomendasi' => $recommendations[$jadwal->komtek3]->rekomendasi ?? null,
            ],
        ]);
    }

    public function komtekFiles($id)
    {
        $user = auth()->user();
        $ba = BaKomtek::where('jadwal_id', $id)->first();
        $spt_komtek = SPTKomtek::where('jadwal_id', $id)->value('link');
        $absen_komtek = AbsenKomtek::where('jadwal_id', $id)->value('link');

        // Determine which komtek position the logged-in user holds
        $jadwal = Jadwal::where('jadwal_id', $id)->first();
        $komtekPosition = null;
        if ($jadwal) {
            if ((int) $jadwal->komtek1 === (int) $user->id) {
                $komtekPosition = 1;
            } elseif ((int) $jadwal->komtek2 === (int) $user->id) {
                $komtekPosition = 2;
            } elseif ((int) $jadwal->komtek3 === (int) $user->id) {
                $komtekPosition = 3;
            }
        }

        $ttdSigned = $komtekPosition && $ba
            ? (bool) $ba->{"has_ttd_komtek{$komtekPosition}"}
            : false;

        return response()->json([
            'ba_komtek' => $ba?->link,
            'ba_komtek_no_surat' => $ba?->no_surat,
            'ba_komtek_ttd_progress' => [
                'komtek1' => (bool) ($ba->has_ttd_komtek1 ?? false),
                'komtek2' => (bool) ($ba->has_ttd_komtek2 ?? false),
                'komtek3' => (bool) ($ba->has_ttd_komtek3 ?? false),
            ],
            'is_started' => $ba ? !is_null($ba->started_at) : false,
            'started_at' => $ba?->started_at?->isoFormat('D MMMM Y'),
            'my_position' => $komtekPosition,
            'my_ttd_signed' => $ttdSigned,
            'spt_komtek' => $spt_komtek,
            'sk_komtek' => env('APP_URL') . '/files/sk_komtek.pdf',
            'pleno_komtek' => $absen_komtek
        ]);
    }

    /**
     * Start BA Komtek (only komtek 1 / ketua can start).
     * Marks the start time of the BA Komtek meeting.
     */
    public function startBaKomtek($id)
    {
        $user = auth()->user();

        $jadwal = Jadwal::where('jadwal_id', $id)->firstOrFail();

        // Only komtek 1 (ketua) can start
        if ((int) $jadwal->komtek1 !== (int) $user->id) {
            return response()->json([
                'message' => 'Hanya Ketua Komite Teknis yang dapat memulai BA Komtek',
            ], 403);
        }

        $ba = BaKomtek::updateOrCreate(
            ['jadwal_id' => $id],
            [
                'started_at' => now(),
                'inputby_id' => $user->id,
            ]
        );

        return response()->json([
            'message' => 'BA Komtek berhasil dimulai',
            'data' => [
                'is_started' => true,
                'started_at' => $ba->started_at->isoFormat('D MMMM Y'),
            ],
        ]);
    }

    public function rekomendasi(Request $request, $id)
    {
        $validated = $request->validate([
            'kompeten' => 'required|boolean',
        ]);

        AsesiJadwal::where('id_izin', $id)->firstOrFail();

        KomtekRecommendation::updateOrCreate(
            [
                'id_izin'   => $id,
                'id_komtek' => auth()->id(),
            ],
            [
                'rekomendasi' => $validated['kompeten'] ? 'K' : 'BK',
            ]
        );

        return response()->json([
            'message' => 'Rekomendasi berhasil disimpan',
        ]);
    }
}
