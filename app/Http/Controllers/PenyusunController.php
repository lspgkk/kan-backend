<?php

namespace App\Http\Controllers;

use App\Models\AsesiJadwal;
use App\Models\Barcode;
use App\Models\JabatanConfigA;
use App\Models\Jadwal;
use App\Models\KanPraktisiJabatan;
use App\Models\KlasifikasiKualifikasi;
use App\Models\Personal;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class PenyusunController extends Controller
{
    use KanRoleDashboardTrait;

    // ──────────────────────────────────────────────
    //  SKEMA PESERTA: Participants per Skema
    // ──────────────────────────────────────────────

    public function skemaPeserta($idSkema): JsonResponse
    {
        $config = JabatanConfigA::with('jabatan:id_jabatan_kerja,jabatan_kerja')
            ->where('id_jabatan', $idSkema)->firstOrFail();

        $jabatanNama = $config->jabatan?->jabatan_kerja;

        // Get all participants for this jabatan
        $peserta = KlasifikasiKualifikasi::select([
                'klasifikasi_kualifikasis.id_izin',
                'klasifikasi_kualifikasis.jabatan_kerja',
                'personals.nama',
            ])
            ->join('personals', 'personals.id_izin', '=', 'klasifikasi_kualifikasis.id_izin')
            ->where('klasifikasi_kualifikasis.jabatan_kerja', $config->id_jabatan)
            ->get();

        $izinIds = $peserta->pluck('id_izin');

        // Batch load KanPraktisiJabatan ids per user for this jabatan
        $kanIds = KanPraktisiJabatan::select(['id', 'id_user'])
            ->where('id_jabatan_kerja', $config->id_jabatan)
            ->get()
            ->keyBy('id_user');

        // Batch load Barcode TTD status per id_izin per document type
        $barcodes = Barcode::select(['id_izin', 'jenis_dokumen', 'url_image'])
            ->whereIn('id_izin', $izinIds)
            ->whereIn('jenis_dokumen', ['IA04B', 'IA05', 'IA06'])
            ->get()
            ->groupBy('id_izin');

        // Map User.noreg -> id_izin for matching KanPraktisiJabatan
        $usersByNoreg = \App\Models\User::whereIn('noreg', $izinIds)
            ->pluck('id', 'noreg');

        $result = $peserta->map(function ($p) use ($barcodes, $jabatanNama, $kanIds, $usersByNoreg) {
            $userId = $usersByNoreg[$p->id_izin] ?? null;
            $kanId  = $userId ? ($kanIds[$userId]->id ?? null) : null;

            $docs = $barcodes[$p->id_izin] ?? collect();

            $ia04b = $docs->firstWhere('jenis_dokumen', 'IA04B');
            $ia05  = $docs->firstWhere('jenis_dokumen', 'IA05');
            $ia06  = $docs->firstWhere('jenis_dokumen', 'IA06');

            $ia04bStatus = $ia04b && $ia04b->url_image ? 'Terisi' : 'Belum';
            $ia05Status  = $ia05  && $ia05->url_image  ? 'Terisi' : 'Belum';
            $ia06Status  = $ia06  && $ia06->url_image  ? 'Terisi' : 'Belum';

            return [
                'id'             => $kanId,
                'id_izin'        => $p->id_izin,
                'nama'           => $p->nama,
                'jabker'         => $jabatanNama,
                'ia04b_status'   => $ia04bStatus,
                'ia05_status'    => $ia05Status,
                'ia06_status'    => $ia06Status,
                'selesai'        => $ia06Status === 'Terisi',
            ];
        });

        return response()->json([
            'message' => 'OK',
            'data'    => [
                'skema'    => [
                    'id'            => $config->id_jabatan,
                    'jabatan_kerja' => $jabatanNama,
                    'kan_no_skema'  => $config->kan_no_skema,
                ],
                'peserta'  => $result,
            ],
        ]);
    }

    // ──────────────────────────────────────────────
    //  IA04B: Get & Submit (delegates to KanAsesmenController)
    // ──────────────────────────────────────────────

    public function getIa04b($idIzin): JsonResponse
    {
        $kan = app(KanAsesmenController::class);
        return $kan->getKanIa04b($idIzin);
    }

    public function submitIa04b(Request $request, $idIzin): JsonResponse
    {
        $kan = app(KanAsesmenController::class);
        return $kan->submitKanIa04b($request, $idIzin);
    }

    // ──────────────────────────────────────────────
    //  IA05: Get & Submit
    // ──────────────────────────────────────────────

    public function getIa05($idIzin): JsonResponse
    {
        $kan = app(KanAsesmenController::class);
        return $kan->getKanIa05($idIzin);
    }

    public function submitIa05(Request $request, $idIzin): JsonResponse
    {
        $kan = app(KanAsesmenController::class);
        return $kan->submitKanIa05($request, $idIzin);
    }

    // ──────────────────────────────────────────────
    //  IA06: Get & Submit
    // ──────────────────────────────────────────────

    public function getIa06($idIzin): JsonResponse
    {
        $kan = app(KanAsesmenController::class);
        return $kan->getKanIa06($idIzin);
    }

    public function submitIa06(Request $request, $idIzin): JsonResponse
    {
        $kan = app(KanAsesmenController::class);
        return $kan->submitKanIa06($request, $idIzin);
    }

    // ──────────────────────────────────────────────
    //  MUK: Hasil MUK Participant
    // ──────────────────────────────────────────────

    public function getMuk($idIzin): JsonResponse
    {
        $personal = Personal::select(['id_izin', 'nama'])->where('id_izin', $idIzin)->first();
        if (!$personal) {
            return response()->json(['message' => 'Data not found'], 404);
        }

        $asesiJadwal = AsesiJadwal::where('id_izin', $idIzin)->first();
        $jadwal = $asesiJadwal
            ? Jadwal::select(['jadwal_id', 'tanggal_uji', 'tanggal_selesai'])
                ->where('jadwal_id', $asesiJadwal->jadwal_id)
                ->first()
            : null;

        $klasifikasi = KlasifikasiKualifikasi::select(['jabatan_kerja', 'jenjang'])
            ->where('id_izin', $idIzin)->first();

        // Get MUK TTD status (penyusun, validator, manajer)
        $config = $klasifikasi
            ? JabatanConfigA::select([
                'kan_penyusun_id', 'kan_penyusun_tgl', 'kan_penyusun_img',
                'kan_validator_id', 'kan_validator_tgl', 'kan_validator_img',
                'kan_manajer_id', 'kan_manajer_tgl', 'kan_manajer_img',
                'kan_no_skema',
            ])
            ->where('id_jabatan', $klasifikasi->jabatan_kerja)
            ->first()
            : null;

        return response()->json([
            'message' => 'OK',
            'data'    => [
                'id_izin'        => $idIzin,
                'nama'           => $personal->nama,
                'tanggal_uji'    => $jadwal?->tanggal_uji,
                'tanggal_selesai'=> $jadwal?->tanggal_selesai,
                'kan_no_skema'   => $config?->kan_no_skema,
                'ttd'            => [
                    'penyusun'   => [
                        'signed'  => !empty($config?->kan_penyusun_img),
                        'tanggal' => $config?->kan_penyusun_tgl,
                    ],
                    'validator'  => [
                        'signed'  => !empty($config?->kan_validator_img),
                        'tanggal' => $config?->kan_validator_tgl,
                    ],
                    'manajer'    => [
                        'signed'  => !empty($config?->kan_manajer_img),
                        'tanggal' => $config?->kan_manajer_tgl,
                    ],
                ],
            ],
        ]);
    }

    // ──────────────────────────────────────────────
    //  TTD: Generate QR Code & Save to JabatanConfigA
    // ──────────────────────────────────────────────

    public function ttd(Request $request, $idIzin): JsonResponse
    {
        $user = auth()->user();

        $klasifikasi = KlasifikasiKualifikasi::where('id_izin', $idIzin)->first();
        if (!$klasifikasi) {
            return response()->json(['message' => 'Data not found'], 404);
        }

        $uuid = (string) Uuid::uuid4();
        $urlTtd = "https://barcode.lspgatensi.id/ttd/{$uuid}";
        $filename = 'ttd_penyusun_' . Str::uuid() . '.png';

        $qrImage = QrCode::format('png')->size(300)->margin(1)->generate($urlTtd);

        $path = "barcodes/{$filename}";
        Storage::disk('str_moon')->put($path, $qrImage);

        $urlImage = "https://moon.lspgatensi.id/files/new_gatensi/{$path}";

        // Save to JabatanConfigA
        JabatanConfigA::where('id_jabatan', $klasifikasi->jabatan_kerja)->update([
            'kan_penyusun_id'  => $user->id,
            'kan_penyusun_tgl' => Carbon::now()->toDateString(),
            'kan_penyusun_img' => $urlImage,
            'kan_penyusun_url' => $urlTtd,
        ]);

        // Also save to Barcode for verification
        Barcode::updateOrCreate(
            [
                'id_user'       => $user->id,
                'id_izin'       => $idIzin,
                'jenis_dokumen' => 'PENYUSUN',
            ],
            [
                'url_image' => $urlImage,
                'url_ttd'   => $urlTtd,
                'id_role'   => $user->role_id,
            ]
        );

        return response()->json([
            'message'  => 'TTD saved',
            'data'     => [
                'url_image' => $urlImage,
                'url_ttd'   => $urlTtd,
                'tanggal'   => Carbon::now()->toDateString(),
            ],
        ]);
    }
}
