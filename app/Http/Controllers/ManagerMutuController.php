<?php

namespace App\Http\Controllers;

use App\Models\Barcode;
use App\Models\JabatanConfigA;
use App\Models\KlasifikasiKualifikasi;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class ManagerMutuController extends Controller
{
    // ──────────────────────────────────────────────
    //  DASHBOARD: List Jabatan Kerja
    // ──────────────────────────────────────────────

    public function jabker(): JsonResponse
    {
        $jabkers = \App\Models\JabatanKerja::select([
                'jabatan_kerjas.id_jabatan_kerja',
                'jabatan_kerjas.jabatan_kerja',
                'jabatan_kerjas.jenjang_id',
            ])
            ->get()
            ->map(function ($jabker) {
                $skemaCount = JabatanConfigA::where('id_jabatan', $jabker->id_jabatan_kerja)
                    ->whereNotNull('kan_no_skema')
                    ->count();

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
    //  IA04B: Get & Submit
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
        $filename = 'ttd_manajer_mutu_' . Str::uuid() . '.png';

        $qrImage = QrCode::format('png')->size(300)->margin(1)->generate($urlTtd);

        $path = "barcodes/{$filename}";
        Storage::disk('str_moon')->put($path, $qrImage);

        $urlImage = "https://moon.lspgatensi.id/files/new_gatensi/{$path}";

        // Save to JabatanConfigA
        JabatanConfigA::where('id_jabatan', $klasifikasi->jabatan_kerja)->update([
            'kan_manajer_id'  => $user->id,
            'kan_manajer_tgl' => Carbon::now()->toDateString(),
            'kan_manajer_img' => $urlImage,
            'kan_manajer_url' => $urlTtd,
        ]);

        // Also save to Barcode for verification
        Barcode::updateOrCreate(
            [
                'id_user'       => $user->id,
                'id_izin'       => $idIzin,
                'jenis_dokumen' => 'MANAGER_MUTU',
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
