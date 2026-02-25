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

ini_set('max_execution_time', 3800);


class BarcodeController extends Controller
{
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
}
