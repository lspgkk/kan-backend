<?php

namespace App\Http\Controllers;

use App\Models\AsesiJadwal;
use App\Models\Barcode;
use App\Models\JabatanConfigA;
use App\Models\JabatanKerja;
use App\Models\Jadwal;
use App\Models\KlasifikasiKualifikasi;
use App\Models\Personal;
use App\Models\Skema;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class PenyusunController extends Controller
{
    // ──────────────────────────────────────────────
    //  DASHBOARD: List Jabatan Kerja
    // ──────────────────────────────────────────────

    public function jabker(): JsonResponse
    {
        $jabkers = JabatanKerja::select([
                'jabatan_kerjas.id_jabatan_kerja',
                'jabatan_kerjas.jabatan_kerja',
                'jabatan_kerjas.jenjang_id',
            ])
            ->get()
            ->map(function ($jabker) {
                // Count skema (jabatan_config_a entries) for this jabatan
                $skemaCount = JabatanConfigA::where('id_jabatan', $jabker->id_jabatan_kerja)
                    ->whereNotNull('kan_no_skema')
                    ->count();

                // Determine status: check if there are active asesi for this jabatan
                $hasActive = KlasifikasiKualifikasi::where('jabatan_kerja', $jabker->id_jabatan_kerja)
                    ->whereHas('asesiJadwal')
                    ->exists();

                return [
                    'id_jabatan_kerja' => $jabker->id_jabatan_kerja,
                    'jabatan_kerja'    => $jabker->jabatan_kerja,
                    'jumlah_skema'     => $skemaCount,
                    'status'           => $hasActive ? 'aktif' : '-',
                ];
            });

        return response()->json(['message' => 'OK', 'data' => $jabkers]);
    }

    // ──────────────────────────────────────────────
    //  SKEMA: List Skema
    // ──────────────────────────────────────────────

    public function skema(): JsonResponse
    {
        $skemas = JabatanConfigA::with([
                'jabatan:id_jabatan_kerja,jabatan_kerja',
            ])
            ->whereNotNull('kan_no_skema')
            ->select(['id_jabatan', 'kan_no_skema', 'kan_penyusun_id', 'kan_validator_id'])
            ->get()
            ->map(function ($config) {
                return [
                    'id'              => $config->id_jabatan,
                    'id_jabatan'      => $config->id_jabatan,
                    'jabatan_kerja'   => $config->jabatan?->jabatan_kerja,
                    'kan_no_skema'    => $config->kan_no_skema,
                    'penyusun_id'     => $config->kan_penyusun_id,
                    'validator_id'    => $config->kan_validator_id,
                ];
            });

        return response()->json(['message' => 'OK', 'data' => $skemas]);
    }

    // ──────────────────────────────────────────────
    //  SKEMA PESERTA: Participants per Skema
    // ──────────────────────────────────────────────

    public function skemaPeserta($idSkema): JsonResponse
    {
        $config = JabatanConfigA::with('jabatan:id_jabatan_kerja,jabatan_kerja')
            ->where('id_jabatan', $idSkema)->firstOrFail();

        // Get all id_izin for this jabatan
        $peserta = KlasifikasiKualifikasi::select([
                'klasifikasi_kualifikasis.id_izin',
                'klasifikasi_kualifikasis.jabatan_kerja',
                'personals.nama',
            ])
            ->join('personals', 'personals.id_izin', '=', 'klasifikasi_kualifikasis.id_izin')
            ->where('klasifikasi_kualifikasis.jabatan_kerja', $config->id_jabatan)
            ->get()
            ->map(function ($p) {
                // Check progress: find asesi_jadwals entry
                $asesiJadwal = AsesiJadwal::where('id_izin', $p->id_izin)->first();

                $status = 'belum';
                if ($asesiJadwal) {
                    $status = $asesiJadwal->is_started ? 'proses' : 'terdaftar';
                }

                return [
                    'id_izin'  => $p->id_izin,
                    'nama'     => $p->nama,
                    'status'   => $status,
                ];
            });

        return response()->json([
            'message' => 'OK',
            'data'    => [
                'skema'    => [
                    'id'            => $config->id_jabatan,
                    'jabatan_kerja' => $config->jabatan?->jabatan_kerja,
                    'kan_no_skema'  => $config->kan_no_skema,
                ],
                'peserta'  => $peserta,
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
