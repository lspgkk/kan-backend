<?php

namespace App\Http\Controllers\core;

use App\Http\Controllers\Controller;
use App\Models\AsesiJadwal;
use App\Models\Barcode;
use App\Models\DataPencatatan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Jadwal;
use App\Models\KlasifikasiKualifikasi;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Personal;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Ramsey\Uuid\Uuid;
use mikehaertl\pdftk\Pdf;
use App\Traits\BantuanTrait;
use App\Traits\ResolvesKomtekDecision;
use App\Services\SikiService;

class CetakJadwalController extends Controller
{
    use BantuanTrait, ResolvesKomtekDecision;

    private function suratPernyataan($pdfUrl, $idIzin, $filename, $jabker, $nama, $noreg)
    {
        // Download the PDF from the URL
        $pdfContents = file_get_contents($pdfUrl);
        
        if (!$pdfContents) {
            throw new \Exception("Failed to fetch the PDF from the URL");
        }

        // Store the file in a temporary location
        $tempPdfPath = tempnam(sys_get_temp_dir(), 'pdf');
        file_put_contents($tempPdfPath, $pdfContents);

        $outputPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $filename . '.pdf';

        $pdf2 = new Pdf($tempPdfPath);

        $check_if_exist = DB::table('bukti_asesmens')->select('no_pernyataan')->where("id_izin", $idIzin)->orderBy('created_at', 'desc')->first();
        if (isset($check_if_exist->no_pernyataan)) {
            $no_surat = $check_if_exist->no_pernyataan;
        } else {
            // Check which number the largest from reguler and balai
            $no_surat = DB::table('bukti_asesmens')->select(['no_pernyataan'])->whereYear('created_at', Carbon::now()->year)->latest("id")->first();
            $no_surat_balai = DB::connection("balai")->table("bukti_asesmens")->select(["no_pernyataan"])->whereYear('created_at', Carbon::now()->year)->latest("id")->first();
            $no_surat = $no_surat->no_pernyataan  ?? '1/LSP GKK B.020/I/2025';
            $no_surat_balai = $no_surat_balai->no_pernyataan ?? '1/LSP GKK B.020/I/2025';

            $no_surat = explode("/", $no_surat);
            $no_surat_balai = explode("/", $no_surat_balai);

            if ((int)$no_surat_balai[0] > (int)$no_surat[0]) {
                $no_surat = $no_surat_balai;
            }

            $currentMonthNumber = Carbon::now()->month;
            $currentYearNumber = Carbon::now()->year;

            $bulan = $this->bulanRomawi($currentMonthNumber);

            if ((int)$no_surat[3] == $currentYearNumber) {
                $no_surat[0] = str((int)$no_surat[0] + 1);
            } else {
                $no_surat[0] = str((int)1);
            }

            $no_surat[2] = $bulan;
            $no_surat[3] = str($currentYearNumber);
            $no_surat = implode("/", $no_surat);
        }

        $maxSkema1Length = 46;
        $skemaWords = explode(' ', $jabker);
        $skema1 = '';
        $skema2 = '';
        $skema1Length = 0;

        foreach ($skemaWords as $index => $word) {
            if (($skema1Length + strlen($word) + 1) <= $maxSkema1Length || count($skemaWords) == 1) {
                $skema1 .= ($skema1 === '' ? '' : ' ') . $word;
                $skema1Length += strlen($word) + 1;
            } else {
                // Ensure skema2 is assigned properly
                $skema2 = implode(' ', array_slice($skemaWords, $index));
                break;
            }
        }

        $maxJabker1Length = 37;
        $jabkerWords = explode(' ', $jabker);
        $jabker1 = '';
        $jabker2 = '';
        $jabker1Length = 0;
        foreach ($jabkerWords as $word) {
            if (($jabker1Length + strlen($word) + 1) <= $maxJabker1Length) {
                $jabker1 .= ($jabker1 === '' ? '' : ' ') . $word;
                $jabker1Length += strlen($word) + 1;
            } else {
                $jabker2 .= ($jabker2 === '' ? '' : ' ') . $word;
            }
        }

        $result = $pdf2->fillForm([
            'nama' => $nama,
            'nomor' => $no_surat,
            'skema' => $jabker,
            'skema1' => $skema1,
            'skema2' => $skema2,
            'jabker1' => $jabker1,
            'jabker2' => $jabker2,
            'noreg' => $noreg,
            'tanggal' => Carbon::now()->translatedFormat('l, d F Y') . ',',
        ])->saveAs($outputPath);

        if ($result === false) {
            $error = $pdf2->getError();
            unlink($tempPdfPath);
            throw new \Exception("PDF save error: $error");
        }
        unlink($tempPdfPath);
        $qrData1 = "https://barcode.lspgatensi.id/" . $filename;
        $qrData2 = "";
        $encodedQrData1 = base64_encode($qrData1);
        $encodedQrData2 = base64_encode($qrData2);

        $outputPdfPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $filename . '_final.pdf';
        $command = escapeshellcmd("python3 " . app_path(). "/Http/Controllers/python/muk.py " .
            escapeshellarg($outputPath) . " " . escapeshellarg($outputPdfPath) . " " . escapeshellarg($encodedQrData1) . " " . escapeshellarg($encodedQrData2));
        $pythonResult = shell_exec($command);

        if (!file_exists($outputPdfPath)) {
            throw new \Exception("Jabker $jabker $pythonResult");
        }

        $finishedContents = file_get_contents($outputPdfPath);
        
        $finishedPdfPath = tempnam(sys_get_temp_dir(), 'pdf');
        file_put_contents($finishedPdfPath, $finishedContents);
        
        $finalPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $filename . '.pdf';

        $pdf = new Pdf($finishedPdfPath);

        $result = $pdf->flatten()->saveAs($finalPath);

        // Read the final file content
        $fileContents = file_get_contents($finalPath);
        
        DB::table('bukti_asesmens')->select(['no_pernyataan'])->whereYear('created_at', Carbon::now()->year)->latest("id")->update([
            'no_pernyataan' => $no_surat
        ]);

        // Clean up temporary files
        unlink($outputPath);
        unlink($outputPdfPath);
        unlink($finishedPdfPath);
        // unlink($finalPath);

        return $fileContents;
    }

    public function storeCetakJadwal($jadwal_id)
    {
        // Auth from bnsp
        $authData = DB::connection('balai')->table('b_n_s_p_auths')->select('x_authorization')->first()->x_authorization;

        // Get competent asesies by komtek majority decision
        $allAsesi = AsesiJadwal::where('jadwal_id', $jadwal_id)->get();
        $asesiKompeten = $allAsesi->filter(fn($a) => $this->resolveKomtekDecision($a->id_izin) === 'K');

        if ($asesiKompeten->isEmpty()) {
            return response()->json([
                'message' => 'Tidak ada asesi kompeten untuk jadwal ini'
            ], 400);
        }

        /* IZIN FINAL */
        foreach ($asesiKompeten as $asesiJadwal) {
            $pencatatan_peserta = DataPencatatan::select(['nama', 'nomor_registrasi_lpjk', 'link_e_sertifikat'])->where("id_izin", $asesiJadwal->id_izin)->first();
            if (!$pencatatan_peserta) {
                return response()->json([
                    'message' => "ID Izin " . $asesiJadwal->id_izin . " belum diterbitkan"
                ], 400);
            }
            $surat_pernyataan_url_name = (string) Uuid::uuid4();
            $temp_url_pernyataan = 'https://moon.lspgatensi.id/files/surat/surat_pernyataan.pdf';
            $id_jabker = KlasifikasiKualifikasi::where('id_izin', $asesiJadwal->id_izin)->pluck('jabatan_kerja')->first();
            $jabker = DB::connection('mygatensi')->table('myjabatankerja')->where('id_jabatan_kerja', $id_jabker)->orderBy('InputDate', 'desc')->pluck('jabatan_kerja')->first();
            $file_pernyataan = $this->suratPernyataan($temp_url_pernyataan, $asesiJadwal->id_izin, $surat_pernyataan_url_name, $jabker, $pencatatan_peserta->nama, $pencatatan_peserta->nomor_registrasi_lpjk);
            Storage::disk("str_moon")->put("/dok_asesmen/pernyataan/" . $surat_pernyataan_url_name . '.pdf', $file_pernyataan);
            $full_url_pernyataan_name = "https://moon.lspgatensi.id/files/dok_asesmen/pernyataan/" . $surat_pernyataan_url_name . '.pdf';

            try {
                $personal = Personal::where("id_izin", $asesiJadwal->id_izin)->first();
                Barcode::create([
                    'nama' => $personal ? $personal->nama : '',
                    'id_izin' => $asesiJadwal->id_izin,
                    'jabatan' => 'asesi',
                    'url' => 'https://barcode.lspgatensi.id/' . $surat_pernyataan_url_name
                ]);
                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                return response()->json([
                    'message' => "Gagal create barcode: " . $e->getMessage()
                ], 500);
            }

            $bukti = DB::table('bukti_asesmens')->select('url_pernyataan')->where('id_izin', $asesiJadwal->id_izin);
            $update = $bukti->update([
                "url_pernyataan" => $full_url_pernyataan_name,
            ]);
            if (!$update) {
                return response()->json([
                    'message' => "Gagal upload surat pernyataan"
                ], 500);
            }

            // hit api
            $siki = app(SikiService::class);
            $url = $siki->getBaseUrl() . '/izin-final-skk/' . $asesiJadwal->id_izin;

            $request_izin_final = array(
                'file_lampiran' => array(
                    'link_e_sertifikat' => $pencatatan_peserta->link_e_sertifikat,
                    'url_surat_pernyataan_pemegang_sertifikat' => $full_url_pernyataan_name
                )
            );

            if (!$siki->isActive()) {
                Log::info('SIKI API call skipped (is_active=false)', [
                    'id_izin' => $asesiJadwal->id_izin,
                    'action' => 'izinFinalSkk',
                ]);
            } else {
                $response_post_bnsp = rescue(function () use ($siki, $url, $request_izin_final) {
                    return Http::retry(8, 1000)->withHeaders($siki->getHeaders())->post($url, $request_izin_final);
            }, function ($e) {
                return $e->response;
            });

            if ($response_post_bnsp->successful()) {
                $responseData = $response_post_bnsp->json();
                if ($responseData['status'] == "success") {
                    Log::info("berhasil izin final");
                    continue;
                } else if ($responseData['status'] == "errors") {
                    $errorMessage = $responseData['message'] ?? 'Unknown error';
                    Log::error("SIKI izin final error: " . $errorMessage);
                    return response()->json([
                        'message' => "Terjadi kesalahan saat izin final ke siki",
                        'error' => $errorMessage
                    ], 500);
                } else {
                    Log::error("SIKI izin final unexpected response: " . str($responseData));
                    return response()->json([
                        'message' => "Terjadi kesalahan saat izin final ke siki",
                        'response' => str($responseData)
                    ], 500);
                }
            } else {
                $errorMessage = $response_post_bnsp->json()['message'] ?? '';
                if ($errorMessage == "tidak dapat mengirim izin final ,kode status saat ini 50") {
                    continue;
                }
                return response()->json([
                    'message' => "Server SIKI sedang bermasalah!",
                    'error' => $errorMessage
                ], 500);
            }
            } /* END of isActive else */
        }
        /* END OF IZIN FINAL */




        /* CETAK JADWAL */
        $url = 'https://konstruksi.bnsp.go.id/api/v1/jadwal/cetak';
        $request_cetak = [
            "jadwal_id" => $jadwal_id
        ];
        $headers = [
            'Content-Type' => 'application/json',
            'x-authorization' => $authData
        ];

        $response_post_bnsp = rescue(function () use ($headers, $url, $request_cetak) {
            return Http::retry(8, 1000)->withHeaders($headers)->post($url, $request_cetak, $headers);
        }, function ($e) {
            return $e->response;
        });

        if ($response_post_bnsp->successful()) {
            $responseData = $response_post_bnsp->json();
            if ($responseData['code'] == "OK") {
                Log::info($responseData['message']);
            } else if ($responseData['message'] == "Unauthorized") {
                Log::error("BNSP authorization expired! Generate through manual through artisan");
                return response()->json([
                    'message' => 'BNSP authorization expired'
                ], 500);
            } else {
                Log::error("BNSP cetak jadwal error: " . str($responseData));
                return response()->json([
                    'message' => "Terjadi kesalahan post bnsp",
                    'response' => str($responseData)
                ], 500);
            }
        } else {
            Log::error("BNSP cetak jadwal failed: " . str($response_post_bnsp->json()));
            return response()->json([
                'message' => "Server BNSP sedang bermasalah!"
            ], 500);
        }
        /* END OF CETAK JADWAL */

        /* STATUS 90 */
        // Get non-competent asesies by komtek majority decision
        $asesiBelumKompeten = $allAsesi->filter(fn($a) => $this->resolveKomtekDecision($a->id_izin) === 'BK');

        $siki = app(SikiService::class);

        foreach ($asesiBelumKompeten as $asesiJadwal) {
            $url = $siki->getBaseUrl() . '/permohonan-skk/' . $asesiJadwal->id_izin;
            $status90 = [
                "kd_status" => "90",
                "keterangan" => "Tolak Permohonan"
            ];

            DB::beginTransaction();

            if (!$siki->isActive()) {
                Log::info('SIKI API call skipped (is_active=false)', [
                    'id_izin' => $asesiJadwal->id_izin,
                    'action' => 'status90',
                ]);
                Personal::where("id_izin", trim($asesiJadwal->id_izin))->update([
                    'status' => "90"
                ]);
                DB::commit();
                continue;
            }

            $response_post_bnsp = rescue(function () use ($siki, $url, $status90) {
                return Http::retry(8, 1000)->withHeaders($siki->getHeaders())->post($url, $status90);
            }, function ($e) {
                return $e->response;
            });

            if ($response_post_bnsp->successful()) {
                $responseData = $response_post_bnsp->json();
                if ($responseData['status'] == "success") {
                    Log::info("berhasil mengirim bk ke " . $asesiJadwal->id_izin);
                    Personal::where("id_izin", trim($asesiJadwal->id_izin))->update([
                        'status' => "90"
                    ]);
                    DB::commit();
                    continue;
                } else if ($responseData['status'] == "errors") {
                    DB::rollBack();
                    $errorMessage = $responseData['message'] ?? 'Unknown error';
                    Log::error("SIKI status 90 error: " . $errorMessage);
                    return response()->json([
                        'message' => "Terjadi kesalahan tolak permohonan siki",
                        'error' => $errorMessage
                    ], 500);
                }
            } else {
                DB::rollBack();
                $errorMessage = $response_post_bnsp->json()['message'] ?? 'Unknown error';
                error_log(str($errorMessage));
                return response()->json([
                    'message' => "Tidak dapat mengirim tolak permohonan",
                    'error' => $errorMessage
                ], 500);
            }
        }
        // END OF LPJK

        return response()->json([
            'message' => "Berhasil izin final dan cetak jadwal untuk " . $jadwal_id,
            'data' => [
                'jadwal_id' => $jadwal_id,
                'jumlah_kompeten' => $asesiKompeten->count(),
                'jumlah_belum_kompeten' => $asesiBelumKompeten->count()
            ]
        ], 200);
    }
}
