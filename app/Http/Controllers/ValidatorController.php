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

class ValidatorController extends Controller
{
    use KanRoleDashboardTrait;

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
        $filename = 'ttd_validator_' . Str::uuid() . '.png';

        $qrImage = QrCode::format('png')->size(300)->margin(1)->generate($urlTtd);

        $path = "barcodes/{$filename}";
        Storage::disk('str_moon')->put($path, $qrImage);

        $urlImage = "https://moon.lspgatensi.id/files/new_gatensi/{$path}";

        // Save to JabatanConfigA
        JabatanConfigA::where('id_jabatan', $klasifikasi->jabatan_kerja)->update([
            'kan_validator_id'  => $user->id,
            'kan_validator_tgl' => Carbon::now()->toDateString(),
            'kan_validator_img' => $urlImage,
            'kan_validator_url' => $urlTtd,
        ]);

        // Also save to Barcode for verification
        Barcode::updateOrCreate(
            [
                'id_user'       => $user->id,
                'id_izin'       => $idIzin,
                'jenis_dokumen' => 'VALIDATOR',
            ],
            [
                'url_image'  => $urlImage,
                'url_ttd'    => $urlTtd,
                'id_role'    => $user->role_id,
                'created_at' => Carbon::now()->toDateString(),
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
