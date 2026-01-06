<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Personal;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

ini_set('max_execution_time', 3800);

class HitStatusController extends Controller
{
    public function hitVerifikasi(Request $request, $id_izin)
    {
        // Get the personal record to determine type
        $personal = Personal::where('id_izin', $id_izin)->first();
        if (!$personal) {
            return response()->json([
                'success' => false,
                'message' => 'Data asesi tidak ditemukan.'
            ], 404);
        }

        // Determine endpoint based on type
        $type = $personal->type; // expects 'reguler', 'balai', or 'fg'
        $endpointMap = [
            'reguler' => '/',
            'balai'   => '-balai/',
            'fg'      => '-fg/',
        ];

        $suffix = $endpointMap[$type] ?? '/';
        $apiEndpoint = 'https://siki.pu.go.id/siki-api/v1/permohonan-skk' . $suffix . $id_izin;

        $apiData = [
            'kd_status' => '20',
            'keterangan' => 'Verifikasi Data'
        ];

        DB::beginTransaction();
        try {
            $httpClient = new \GuzzleHttp\Client();
            $httpClient->request('POST', $apiEndpoint, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'token' => env('SIKI_API'),
                ],
                'json' => $apiData,
            ]);

            // Update verification status in database
            $personal->update([
                'status' => '20',
                'verifby_id' => Auth::user()->id,
                'verif_time' => now(),
            ]);

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Verifikasi berhasil'
            ]);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            DB::rollBack();
            $statusCode = $e->getResponse()?->getStatusCode() ?? 500;
            $responseBody = $e->getResponse()?->getBody()->getContents() ?? $e->getMessage();

            error_log("API Request Failed with status code: $statusCode, response body: $responseBody");
            return response()->json([
                'success' => false,
                'message' => "Tidak dapat memverifikasi",
                'details' => $responseBody
            ], $statusCode);
        } catch (\Exception $e) {
            DB::rollBack();
            error_log('API Request Failed with an exception: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    public function hitLengkap(Request $request, $id_izin)
    {
        // Get the personal record to determine type
        $personal = Personal::where('id_izin', $id_izin)->first();

        if (!$personal) {
            return response()->json([
                'success' => false,
                'message' => 'Data asesi tidak ditemukan.'
            ], 404);
        }

        // Determine endpoint based on type
        $type = $personal->type; // reguler | balai | fg
        $endpointMap = [
            'reguler' => '/',
            'balai'   => '-balai/',
            'fg'      => '-fg/',
        ];

        $suffix = $endpointMap[$type] ?? '/';

        if ($personal->validby_id == Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi tidak dapat dilakukan oleh verifikator!'
            ], 403);
        }

        $api_url = 'https://siki.pu.go.id/siki-api/v1/permohonan-skk' . $suffix . $id_izin;

        $headers = [
            'Content-Type' => 'application/json',
            'token' => env('SIKI_API'),
        ];

        $payload = [
            'kd_status'  => '10',
            'keterangan' => 'Dokumen Permohonan Dinyatakan Lengkap',
        ];

        $response = Http::withHeaders($headers)->post($api_url, $payload);

        if (!$response->successful()) {
            $message = $response->json()['message'] ?? 'Terjadi kesalahan pada SIKI';

            if (in_array($message, [
                'tidak dapat mengirim kode status 10, kode status saat ini 32',
                'tidak dapat mengirim kode status 10, kode status saat ini 30',
            ])) {
                return response()->json([
                    'success' => false,
                    'message' => 'ID Izin sudah divalidasi!'
                ], 409);
            }

            return response()->json([
                'success' => false,
                'message' => "Terjadi masalah pada ID Izin ($id_izin)",
                'detail'  => $message
            ], 500);
        }

        $responseData = $response->json();

        if (($responseData['status'] ?? null) !== 'success') {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat validasi',
                'detail'  => $responseData['message'] ?? null
            ], 422);
        }

        // Update local data
        Personal::where('id_izin', $id_izin)->update([
            'status'        => '10',
            'validpjby_id'  => Auth::id(),
            'validpj_time'  => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Sukses Validasi!',
            'data' => [
                'id_izin' => $id_izin,
                'status'  => '10',
            ]
        ]);
    }

    public function hitTidakLengkap(Request $request, $id_izin)
    {
        // Get personal data
        $personal = Personal::where('id_izin', trim($id_izin))->first();

        if (!$personal) {
            return response()->json([
                'success' => false,
                'message' => 'Data asesi tidak ditemukan.'
            ], 404);
        }

        // Validation: tanggal uji must exist
        if (!$personal->tanggal_uji) {
            return response()->json([
                'success' => false,
                'message' => 'Tanggal Uji belum diisi!'
            ], 422);
        }

        // Validation: cannot validate by same user
        if ($personal->validby_id == Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi 2 tidak dapat dilakukan oleh orang yang sama!'
            ], 403);
        }

        // Determine endpoint based on type
        $type = $personal->type; // reguler | balai | fg
        $endpointMap = [
            'reguler' => '/',
            'balai'   => '-balai/',
            'fg'      => '-fg/',
        ];

        $suffix = $endpointMap[$type] ?? '/';

        $api_url = 'https://siki.pu.go.id/siki-api/v1/permohonan-skk' . $suffix . $id_izin;

        $headers = [
            'Content-Type' => 'application/json',
            'token'        => env('SIKI_API'),
        ];

        $payload = [
            'kd_status'  => '11',
            'keterangan' => 'Dokumen Tidak Lengkap',
        ];

        $response = Http::withHeaders($headers)->post($api_url, $payload);

        // HTTP failure
        if (!$response->successful()) {
            $message = $response->json()['message'] ?? 'Terjadi kesalahan pada SIKI';

            if (in_array($message, [
                'tidak dapat mengirim kode status 11, kode status saat ini 32',
                'tidak dapat mengirim kode status 11, kode status saat ini 30',
            ])) {
                return response()->json([
                    'success' => false,
                    'message' => 'ID Izin sudah divalidasi!'
                ], 409);
            }

            return response()->json([
                'success' => false,
                'message' => "Terjadi masalah pada ID Izin ($id_izin)",
                'detail'  => $message
            ], 500);
        }

        $responseData = $response->json();

        // SIKI logical failure
        if (($responseData['status'] ?? null) !== 'success') {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat validasi',
                'detail'  => $responseData['message'] ?? null
            ], 422);
        }

        // Update local database
        $personal->update([
            'status'      => '11',
            'verifby_id'  => null,
            'verif_time'  => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Sukses kirim status dokumen tidak lengkap!',
            'data' => [
                'id_izin' => $id_izin,
                'status'  => '11',
            ]
        ]);
    }
    
    public function hitTolak(Request $request)
    {
        $id_izin = $request->id_izin;

        $api_url = "https://siki.pu.go.id/siki-api/v1/permohonan-skk/" . trim($id_izin);

        $headers = [
            'Content-Type' => 'application/json',
            'token' => env('SIKI_API')
        ];

        $status11 = [
            "kd_status" => "90",
            "keterangan" => "Permohonan Ditolak"
        ];

        $response_post_bnsp = Http::withHeaders($headers)->timeout(60)->connectTimeout(60)->post($api_url, $status11);
        if ($response_post_bnsp->successful()) {
            $responseData = $response_post_bnsp->json();
            if ($responseData['status'] == "success") {
                Log::info('sukses!');
            } else if ($responseData['status'] == "errors") {
                return back()->with("error", "Terjadi kesalahan saat validasi, " . str($responseData['message']));
            } else {
                return back()->with("error", "Terjadi kesalahan pada SIKI");
            }
        } else {
            if ($response_post_bnsp->json()['message'] == 'tidak dapat mengirim kode status 90, kode status saat ini 90') {
                return back()->with("error", "ID Izin sudah ditolak!");
            } else if ($response_post_bnsp->json()['message'] == 'tidak dapat mengirim kode status 90, kode status saat ini 30') {
                return back()->with("error", "Status sudah divalidasi!");
            } else {
                return back()->with("error", "Terjadi masalah pada ($id_izin), info: " . $response_post_bnsp->json()['message'])->withInput();
            }
        }

        Personal::where('id_izin', $id_izin)->update([
            "status" => "90"
        ]);

        DB::connection("mygatensi_2")->table("mystatuspermohonan")->where('ID_Ijin', $id_izin)->update([
            'Status' => 'Permohonan Ditolak'
        ]);

        return back()->with("success", "Sukses tolak id izin!");
    }

    public function hitCabut(Request $request)
    {
        $all_id_izin = $request->input("id_izin");

        if ($all_id_izin == "" || $all_id_izin == " ") {
            return back()->with("error", "Input ID Izin tidak boleh kosong!");
        }

        $all_id_izin = preg_split('/\r\n|\r|\n/', $all_id_izin);

        $error_id_izin = [];
        $success_id_izin = 0;

        $headers = [
            'Content-Type' => 'application/json',
            'token' => env('SIKI_API')
        ];

        $status70 = [
            "kd_status" => "11",
            "keterangan" => "Dokumen Tidak Lengkap"
        ];

        // $status91 = [
        //     "kd_status" => "91",
        //     "keterangan" => "Pencabutan"
        // ];

        foreach ($all_id_izin as $id_izin) {
            error_log($id_izin);
            $is_balai = DB::connection('balai')->table('personals')->where('id_izin', $id_izin)->exists();
            $is_fg = DB::connection('fg')->table('personals')->where('id_izin', $id_izin)->exists();
            if ($is_balai) {
                $api_url = "https://siki.pu.go.id/siki-api/v1/permohonan-skk-balai/" . trim($id_izin);
            } else if ($is_fg) {
                $api_url = "https://siki.pu.go.id/siki-api/v1/permohonan-skk-fg/" . trim($id_izin);
            } else {
                $api_url = "https://siki.pu.go.id/siki-api/v1/permohonan-skk/" . trim($id_izin);
            }
            $response_post_bnsp = Http::withHeaders($headers)->timeout(60)->connectTimeout(60)->post($api_url, $status70);
            $data = $response_post_bnsp->json();
            if ($response_post_bnsp->successful()) {
                $responseData = $response_post_bnsp->json();
                if ($responseData['status'] == "success") {
                    Log::info('sukses!');
                } else if ($responseData['status'] == "errors") {
                    array_push($error_id_izin,  ["id_izin" => $id_izin, "msg" => $data['message']]);
                } else {
                    array_push($error_id_izin,  ["id_izin" => $id_izin, "msg" => $data['message']]);
                }
            } else {
                if ($response_post_bnsp->json()['message'] == 'tidak dapat mengirim kode status 70, kode status saat ini 70') {
                    array_push($error_id_izin,  ["id_izin" => $id_izin, "msg" => $data['message']]);
                } else if ($response_post_bnsp->json()['message'] == 'tidak dapat mengirim kode status 70, kode status saat ini 91') {
                    array_push($error_id_izin,  ["id_izin" => $id_izin, "msg" => $data['message']]);
                } else {
                    array_push($error_id_izin,  ["id_izin" => $id_izin, "msg" => $data['message']]);
                }
            }

            sleep(10);

            // $response_post_bnsp = Http::withHeaders($headers)->timeout(60)->connectTimeout(60)->post($api_url, $status91);
            // $data = $response_post_bnsp->json();
            // if ($response_post_bnsp->successful()) {
            //     $responseData = $response_post_bnsp->json();
            //     if ($responseData['status'] == "success") {
            //         Log::info('sukses!');
            //     } else if ($responseData['status'] == "errors") {
            //         array_push($error_id_izin,  ["id_izin" => $id_izin, "msg" => $data['message']]);
            //     } else {
            //         array_push($error_id_izin,  ["id_izin" => $id_izin, "msg" => $data['message']]);
            //     }
            // } else {
            //     if ($response_post_bnsp->json()['message'] == 'tidak dapat mengirim kode status 91, kode status saat ini 91') {
            //         array_push($error_id_izin,  ["id_izin" => $id_izin, "msg" => $data['message']]);
            //     } else {
            //         array_push($error_id_izin,  ["id_izin" => $id_izin, "msg" => $data['message']]);
            //     }
            // }

            DB::beginTransaction();
            try {
                Personal::where('id_izin', $id_izin)->update([
                    "status" => "11"
                ]);

                DB::connection("mygatensi_2")->table("mystatuspermohonan")->where('ID_Ijin', $id_izin)->update([
                    'Status' => 'Dokumen Tidak Lengkap'
                ]);
                DB::commit();
                $success_id_izin++;
            } catch (\Exception $e) {
                // Log or handle the exception
                dd($e);
                array_push($error_id_izin,  ["id_izin" => $id_izin, "msg" => $e->getMessage()]);
                // return redirect()->back()->with('error', 'Error storing data.');
            }
        }
        return redirect()->back()->with('success', 'Total ' . (string)$success_id_izin . ' ID Izin berhasil dicabut!')->with(['error_id_izin' => $error_id_izin]);
    }
}