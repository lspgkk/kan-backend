<?php

namespace App\Http\Controllers;

use App\Models\AsesorBnsp;
use App\Models\Barcode;
use App\Models\JabatanConfigA;
use App\Models\Jadwal;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class BarcodeController extends Controller
{
    public function __construct()
    {
        set_time_limit(3800);
    }


    public function generateBarcodePenyusun()
    {
        $list_jabker = JabatanConfigA::where('penyusun_img', 'NOT LIKE', '%https://barcode.lspgatensi.id%')->get();

        foreach ($list_jabker as $jabker) {
            // DB::transaction(function () use ($jabker) {

                $uuidPenyusun  = (string) Str::uuid();
                $uuidValidator = (string) Str::uuid();

                $urlTtdPenyusun  = "https://barcode.lspgatensi.id/penyusun-validator/{$uuidPenyusun}";
                $urlTtdValidator = "https://barcode.lspgatensi.id/penyusun-validator/{$uuidValidator}";

                $filenamePenyusun  = "{$uuidPenyusun}.png";
                $filenameValidator = "{$uuidValidator}.png";

                $qrPenyusun = QrCode::format('png')->size(300)->margin(1)->generate($urlTtdPenyusun);
                $qrValidator = QrCode::format('png')->size(300)->margin(1)->generate($urlTtdValidator);

                $pathPenyusun  = "barcodes/{$filenamePenyusun}";
                $pathValidator = "barcodes/{$filenameValidator}";

                Storage::disk('str_moon')->put($pathPenyusun, $qrPenyusun);
                Storage::disk('str_moon')->put($pathValidator, $qrValidator);

                JabatanConfigA::where('id_jabatan', $jabker->id_jabatan)->update([
                    'penyusun_img'  => "https://moon.lspgatensi.id/files/new_gatensi/{$pathPenyusun}",
                    'penyusun_url'  => $urlTtdPenyusun,
                    'validator_img' => "https://moon.lspgatensi.id/files/new_gatensi/{$pathValidator}",
                    'validator_url' => $urlTtdValidator,
                ]);
            // });
        }

        return response()->json([
            'message' => 'success'
        ]);
    }

    public function generateBarcodeManajer()
    {
        $manajer = User::where('role_id', 7)->orderBy('id')->first();
        if (!$manajer) {
            return response()->json(['message' => 'User dengan role_id 7 tidak ditemukan'], 404);
        }

        $list_jabker = JabatanConfigA::all()->filter(function ($jabker) use ($manajer) {
            // Re-generate jika Barcode record tidak ada atau url_ttd-nya tidak valid
            $barcodeExists = Barcode::where('id_user', $manajer->id)
                ->where('id_izin', 'PENYUSUN_' . $jabker->id_jabatan)
                ->where('jenis_dokumen', 'MANAJER')
                ->where('url_ttd', 'LIKE', '%https://barcode.lspgatensi.id%')
                ->exists();
            return !$barcodeExists;
        });

        foreach ($list_jabker as $jabker) {
            $uuid = (string) Str::uuid();
            $urlTtd = "https://barcode.lspgatensi.id/ttd/{$uuid}";
            $filename = "{$uuid}.png";

            $qrImage = QrCode::format('png')->size(300)->margin(1)->generate($urlTtd);

            $path = "barcodes/{$filename}";
            Storage::disk('str_moon')->put($path, $qrImage);
            $urlImage = "https://moon.lspgatensi.id/files/new_gatensi/{$path}";

            // Parse penyusun_tgl to match created_at format (Y-m-d H:i:s)
            $createdAt = null;
            if ($jabker->penyusun_tgl) {
                $createdAt = \Carbon\Carbon::parse($jabker->penyusun_tgl)->startOfDay()->format('Y-m-d H:i:s');
            }

            // Save to Barcode model
            Barcode::updateOrCreate(
                [
                    'id_user'       => $manajer->id,
                    'id_izin'       => 'PENYUSUN_' . $jabker->id_jabatan,
                    'jenis_dokumen' => 'MANAJER',
                ],
                [
                    'url_image'  => $urlImage,
                    'url_ttd'    => $urlTtd,
                    'id_role'    => 7,
                    'created_at' => $createdAt,
                ]
            );

            // Update JabatanConfigA
            JabatanConfigA::where('id_jabatan', $jabker->id_jabatan)->update([
                'manajer_id'  => $manajer->id,
                'manajer_img' => $urlImage,
                'manajer_url' => $urlTtd,
            ]);
        }

        return response()->json([
            'message' => 'success'
        ]);
    }

    public function getTtdApl01($id)
    {
        $barcodes = Barcode::where('id_izin', $id)
            ->where('jenis_dokumen', 'APL01')
            ->get()
            ->keyBy('id_user'); // 🔑 important

        if ($barcodes->isEmpty()) {
            return response()->json([
                'message' => 'Barcode belum tersedia',
                'data' => null
            ], 404);
        }

        // Get jadwal
        $jadwal = Jadwal::select(['asesor_id', 'asesor2_id'])
            ->where('jadwal_id', $barcodes->first()->id_jadwal)
            ->first();

        // Resolve asesor → user
        $asesors = AsesorBnsp::select(['id', 'noreg'])
            ->whereIn('id', [$jadwal->asesor_id, $jadwal->asesor2_id])
            ->get()
            ->keyBy('id');

        $asesorUsers = User::whereIn('noreg', $asesors->pluck('noreg'))
            ->get()
            ->keyBy('noreg');

        // 4️⃣ Map results
        $asesor1User = $asesorUsers[$asesors[$jadwal->asesor_id]->noreg] ?? null;
        $asesor2User = $asesorUsers[$asesors[$jadwal->asesor2_id]->noreg] ?? null;

        return response()->json([
            'message' => 'Success',
            'data' => [
                'url_barcode_asesi' =>
                    $barcodes->firstWhere('id_role', 8)?->url_image,

                'url_barcode_asesor1' =>
                    $asesor1User
                        ? ($barcodes[$asesor1User->id]->url_image ?? null)
                        : null,

                'url_barcode_asesor2' =>
                    $asesor2User
                        ? ($barcodes[$asesor2User->id]->url_image ?? null)
                        : null,
            ]
        ]);
    }
    
    private function generateTtdBarcode(
        Request $request,
        string $docType,
        string $filePrefix,
        string $izinId
    ) {
        $request->validate([
            'id_jadwal' => 'required'
        ]);

        // 1️⃣ Generate UUID & URL TTD
        $uuid = (string) Uuid::uuid4();
        $urlTtd = "https://barcode.lspgatensi.id/ttd/{$uuid}";

        // 2️⃣ Generate filename
        $filename = "{$filePrefix}_" . Str::uuid() . '.png';

        // 3️⃣ Generate QR image
        $qrImage = QrCode::format('png')
            ->size(300)
            ->margin(1)
            ->generate($urlTtd);

        // 4️⃣ FTP path
        $path = "barcodes/{$filename}";

        // 5️⃣ Upload to FTP
        Storage::disk('str_moon')->put($path, $qrImage);

        // 6️⃣ Public image URL
        $urlImage = "https://moon.lspgatensi.id/files/new_gatensi/{$path}";

        // 7️⃣ Save / update DB
        Barcode::updateOrCreate(
            [
                'id_user'       => auth()->user()->id,
                'id_izin'       => $izinId,
                'jenis_dokumen' => $docType,
            ],
            [
                'id_jadwal' => $request->id_jadwal,
                'url_image' => $urlImage,
                'url_ttd'   => $urlTtd,
                'id_role'   => auth()->user()->role_id,
            ]
        );

        return [
            'url_image' => $urlImage,
            'url_ttd'   => $urlTtd,
        ];
    }

    public function ttdAk05(Request $request, $id)
    {
        $data = $this->generateTtdBarcode(
            $request,
            'AK05',
            'ttd_ak05',
            $id
        );

        return response()->json([
            'message' => 'Success',
            'data'    => $data,
        ]);
    }

    public function getBarcodeByUrlTtd(Request $request)
    {
        $request->validate([
            'url_ttd' => 'required|string'
        ]);

        // Extract UUID from full URL if needed
        $urlTtd = $request->url_ttd;

        // If full URL is provided, extract just the UUID
        if (filter_var($urlTtd, FILTER_VALIDATE_URL)) {
            $path = parse_url($urlTtd, PHP_URL_PATH);
            $uuid = basename($path);
        } else {
            // Assume it's just the UUID
            $uuid = $urlTtd;
        }

        // Build the full URL to match what's stored in database
        $fullUrl = "https://barcode.lspgatensi.id/ttd/{$uuid}";

        // Query barcode with user and role relationships
        $barcode = Barcode::with(['user:id,name', 'role:id,name'])
            ->where('url_ttd', $fullUrl)
            ->first();

        if (!$barcode) {
            return response()->json([
                'message' => 'Barcode not found',
                'data' => null
            ], 404);
        }

        return response()->json([
            'message' => 'Success',
            'data' => [
                'user_name' => $barcode->user?->name,
                'role_name' => $barcode->role?->name,
                'created_at' => $barcode->created_at,
                'url_ttd' => $barcode->url_ttd,
                'url_image' => $barcode->url_image,
            ]
        ]);
    }

    public function getPenyusunValidatorByUrl(Request $request)
    {
        $request->validate([
            'url' => 'required|string'
        ]);

        // Extract UUID from full URL if needed
        $url = $request->url;

        // If full URL is provided, extract just the UUID
        if (filter_var($url, FILTER_VALIDATE_URL)) {
            $path = parse_url($url, PHP_URL_PATH);
            $uuid = basename($path);
        } else {
            // Assume it's just the UUID
            $uuid = $url;
        }

        // Build the full URL to match what's stored in database
        $fullUrl = "https://barcode.lspgatensi.id/penyusun-validator/{$uuid}";

        // 1) Try BNSP fields first, then KAN fields
        $jabatanConfig = JabatanConfigA::where('penyusun_url', $fullUrl)
            ->orWhere('validator_url', $fullUrl)
            ->orWhere('kan_penyusun_url', $fullUrl)
            ->orWhere('kan_validator_url', $fullUrl)
            ->first();

        if (!$jabatanConfig) {
            return response()->json([
                'message' => 'Barcode not found',
                'data' => null
            ], 404);
        }

        // Determine if it's penyusun or validator based on URL match
        // Check BNSP URLs first, then KAN URLs
        $isPenyusun = $jabatanConfig->penyusun_url === $fullUrl
            || $jabatanConfig->kan_penyusun_url === $fullUrl;

        // Resolve user ID: prefer kan_* field, fallback to BNSP field
        $userId = $isPenyusun
            ? ($jabatanConfig->kan_penyusun_id ?? $jabatanConfig->penyusun_id)
            : ($jabatanConfig->kan_validator_id ?? $jabatanConfig->validator_id);

        $jabatan = $isPenyusun ? 'Penyusun' : 'Validator';

        // Resolve tanggal: prefer kan_*_tgl, fallback to BNSP *_tgl
        $tanggal = $isPenyusun
            ? ($jabatanConfig->kan_penyusun_tgl ?? $jabatanConfig->penyusun_tgl)
            : ($jabatanConfig->kan_validator_tgl ?? $jabatanConfig->validator_tgl);

        // Get user information
        $user = User::find($userId);

        if (!$user) {
            return response()->json([
                'message' => 'User not found',
                'data' => null
            ], 404);
        }

        return response()->json([
            'message' => 'Success',
            'data' => [
                'user_name' => $user->name,
                'jabatan' => $jabatan,
                'tanggal' => $tanggal,
                'url' => $fullUrl,
            ]
        ]);
    }

    public function getBarcode($id)
    {
        $fullUrl = "https://barcode.lspgatensi.id/{$id}";

        $barcode = Barcode::where('url', $fullUrl)->first();

        if (!$barcode) {
            return response()->json([
                'message' => 'Barcode not found',
            ], 404);
        }

        return response()->json([
            'nama'     => $barcode->nama,
            'id_izin'  => $barcode->id_izin,
            'noreg'    => $barcode->id_izin,
            'jabatan'  => $barcode->jabatan,
            'tanggal'  => $barcode->created_at
                ? \Carbon\Carbon::parse($barcode->created_at)->locale('id')->isoFormat('dddd, D MMMM YYYY')
                : null,
            'waktu'    => $barcode->created_at
                ? \Carbon\Carbon::parse($barcode->created_at)->setTimezone('Asia/Jakarta')->format('H:i')
                : null,
            'lsp'      => 'gkk',
        ]);
    }
}
