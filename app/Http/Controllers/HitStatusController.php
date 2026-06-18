<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\JabatanKerja;
use App\Models\KlasifikasiKualifikasi;
use App\Models\Personal;
use App\Models\User;
use App\Services\QontakService;
use App\Services\SikiService;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class HitStatusController extends Controller
{
    public function __construct()
    {
        set_time_limit(3800);
    }


    public function hitVerifikasi(Request $request, $id_izin)
    {
        // Validate payload
        $validated = $request->validate([
            'rencana_tanggal_uji' => 'required|date',
            'jenis_kelas_id'      => 'nullable|integer|exists:jenis_kelas,id',
        ]);

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

        // Generate credentials for the asesi if not already set
        if (!$personal->wa_uuid) {
            $account = strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8));
            $passwordPlain = strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8));
            $personal->wa_uuid = (string) Str::uuid();
            $personal->account = $account;
            $personal->password_plain = $passwordPlain;
        }

        DB::beginTransaction();
        try {
            $siki = app(SikiService::class);
            if ($siki->isActive()) {
                $httpClient = new \GuzzleHttp\Client();
                $httpClient->request('POST', $apiEndpoint, [
                    'headers' => $siki->getHeaders(),
                    'json' => $apiData,
                ]);
            } else {
                Log::info('SIKI API call skipped (is_active=false)', [
                    'id_izin' => $id_izin,
                    'kd_status' => '20',
                    'action' => 'hitVerifikasi',
                ]);
            }

            // Update verification status and wa fields in database
            $personal->update([
                'status' => '20',
                'verifby_id' => Auth::user()->id,
                'verif_time' => now(),
                'rencana_tanggal_uji' => \Carbon\Carbon::parse($validated['rencana_tanggal_uji'])->format('Y-m-d'),
                'jenis_kelas_id' => $validated['jenis_kelas_id'] ?? null,
                'wa_uuid' => $personal->wa_uuid,
                'account' => $personal->account,
                'password_plain' => $personal->password_plain,
            ]);

            // Create User for asesi if not already exists
            if (!User::where('email', $personal->account)->exists()) {
                User::create([
                    'name' => $personal->nama,
                    'email' => $personal->account,
                    'phone' => $personal->telepon ?? null,
                    'password' => bcrypt($personal->password_plain),
                    'address' => $personal->alamat ?? null,
                    'role_id' => 8,
                ]);
            }

            DB::commit();

            // Send WhatsApp notification to asesi
            try {
                $this->sendVerifikasiWhatsApp($personal->fresh(), $id_izin);
            } catch (\Throwable $waError) {
                Log::warning('WhatsApp notification failed for ' . $id_izin . ': ' . $waError->getMessage());
                // Don't fail the request — WhatsApp is secondary
            }

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
        // Validate catatan (optional)
        $validated = $request->validate([
            'catatan' => 'nullable|string|max:1000',
        ]);

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

        if ($personal->verifby_id == Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi tidak dapat dilakukan oleh verifikator!'
            ], 403);
        }

        $api_url = 'https://siki.pu.go.id/siki-api/v1/permohonan-skk' . $suffix . $id_izin;

        $payload = [
            'kd_status'  => '10',
            'keterangan' => 'Dokumen Permohonan Dinyatakan Lengkap',
        ];

        $siki = app(SikiService::class);
        if ($siki->isActive()) {
            $response = Http::withHeaders($siki->getHeaders())->post($api_url, $payload);

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
        } else {
            Log::info('SIKI API call skipped (is_active=false)', [
                'id_izin' => $id_izin,
                'kd_status' => '10',
                'action' => 'hitLengkap',
            ]);
        }

        // Update local data
        Personal::where('id_izin', $id_izin)->update([
            'status'        => '10',
            'validby_id'  => Auth::id(),
            'valid_time'  => now(),
            'catatan'     => $validated['catatan'] ?? null,
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

    public function hitValidasiPJ(Request $request, $id_izin)
    {
        $personal = Personal::where('id_izin', $id_izin)->first();

        if (!$personal) {
            return response()->json([
                'success' => false,
                'message' => 'Data asesi tidak ditemukan.',
            ], 404);
        }

        if ($personal->type !== 'balai') {
            return response()->json([
                'success' => false,
                'message' => 'Validasi PJ hanya tersedia untuk permohonan balai.',
            ], 422);
        }

        $type = $personal->type;
        $endpointMap = [
            'reguler' => '/',
            'balai'   => '-balai/',
            'fg'      => '-fg/',
        ];

        $suffix = $endpointMap[$type] ?? '/';
        $api_url = 'https://siki.pu.go.id/siki-api/v1/permohonan-skk' . $suffix . $id_izin;

        $payload = [
            'kd_status'  => '30',
            'keterangan' => 'Validasi PJ',
        ];

        $siki = app(SikiService::class);
        if ($siki->isActive()) {
            $response = Http::withHeaders($siki->getHeaders())->post($api_url, $payload);

            if (!$response->successful()) {
                $message = $response->json()['message'] ?? 'Terjadi kesalahan pada SIKI';

                if (in_array($message, [
                    'tidak dapat mengirim kode status 30, kode status saat ini 32',
                    'tidak dapat mengirim kode status 30, kode status saat ini 30',
                    'tidak dapat mengirim kode status 30, kode status saat ini 31',
                ])) {
                    return response()->json([
                        'success' => false,
                        'message' => 'ID Izin sudah divalidasi PJ!',
                    ], 409);
                }

                return response()->json([
                    'success' => false,
                    'message' => "Terjadi masalah pada ID Izin ($id_izin)",
                    'detail'  => $message,
                ], 500);
            }

            $responseData = $response->json();

            if (($responseData['status'] ?? null) !== 'success') {
                return response()->json([
                    'success' => false,
                    'message' => 'Terjadi kesalahan saat validasi PJ',
                    'detail'  => $responseData['message'] ?? null,
                ], 422);
            }
        } else {
            Log::info('SIKI API call skipped (is_active=false)', [
                'id_izin' => $id_izin,
                'kd_status' => '30',
                'action' => 'hitValidasiPJ',
            ]);
        }

        Personal::where('id_izin', $id_izin)->update([
            'status'        => '30',
            'valid2by_id'  => Auth::id(),
            'valid2_time'  => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Sukses Validasi PJ!',
            'data' => [
                'id_izin' => $id_izin,
                'status'  => '30',
            ],
        ]);
    }

    public function hitTidakLengkap(Request $request, $id_izin)
    {
        // Validate catatan (optional)
        $validated = $request->validate([
            'catatan' => 'required|string|max:1000',
        ]);

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

        $payload = [
            'kd_status'  => '11',
            'keterangan' => 'Dokumen Tidak Lengkap',
        ];

        $siki = app(SikiService::class);
        if ($siki->isActive()) {
            $response = Http::withHeaders($siki->getHeaders())->post($api_url, $payload);

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
        } else {
            Log::info('SIKI API call skipped (is_active=false)', [
                'id_izin' => $id_izin,
                'kd_status' => '11',
                'action' => 'hitTidakLengkap',
            ]);
        }

        // Update local database
        $personal->update([
            'status'      => '11',
            'verifby_id'  => null,
            'verif_time'  => null,
            'catatan'     => $validated['catatan'] ?? null,
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
    
    public function hitTolak(Request $request, $id_izin)
    {
        // Validate catatan (optional)
        $validated = $request->validate([
            'catatan' => 'required|string|max:1000',
        ]);

        // Get the personal record to determine type
        $personal = Personal::where('id_izin', trim($id_izin))->first();

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
        $api_url = 'https://siki.pu.go.id/siki-api/v1/permohonan-skk' . $suffix . $id_izin;

        $payload = [
            'kd_status'  => '90',
            'keterangan' => 'Permohonan Ditolak',
        ];

        $siki = app(SikiService::class);
        if ($siki->isActive()) {
            $response = Http::withHeaders($siki->getHeaders())->post($api_url, $payload);

            if (!$response->successful()) {
                $message = $response->json()['message'] ?? 'Terjadi kesalahan pada SIKI';

                if (in_array($message, [
                    'tidak dapat mengirim kode status 90, kode status saat ini 90',
                ])) {
                    return response()->json([
                        'success' => false,
                        'message' => 'ID Izin sudah ditolak!'
                    ], 409);
                }

                if (in_array($message, [
                    'tidak dapat mengirim kode status 90, kode status saat ini 30',
                ])) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Status sudah divalidasi!'
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
                    'message' => 'Terjadi kesalahan saat tolak permohonan',
                    'detail'  => $responseData['message'] ?? null
                ], 422);
            }
        } else {
            Log::info('SIKI API call skipped (is_active=false)', [
                'id_izin' => $id_izin,
                'kd_status' => '90',
                'action' => 'hitTolak',
            ]);
        }

        // Update local data
        Personal::where('id_izin', $id_izin)->update([
            'status'  => '90',
            'catatan' => $validated['catatan'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Sukses Tolak!',
            'data' => [
                'id_izin' => $id_izin,
                'status'  => '90',
            ]
        ]);
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

        $status70 = [
            "kd_status" => "11",
            "keterangan" => "Dokumen Tidak Lengkap"
        ];

        $siki = app(SikiService::class);
        $sikiActive = $siki->isActive();
        if (!$sikiActive) {
            Log::info('SIKI API calls skipped (is_active=false)', ['action' => 'hitCabut']);
        }

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
            if ($sikiActive) {
                $response_post_bnsp = Http::withHeaders($siki->getHeaders())->timeout(60)->connectTimeout(60)->post($api_url, $status70);
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
            }

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
                Log::error('Error processing pencabutan for ' . $id_izin . ': ' . $e->getMessage());
                $error_id_izin[] = ['id_izin' => $id_izin, 'msg' => $e->getMessage()];
            }
        }
        return redirect()->back()->with('success', 'Total ' . (string)$success_id_izin . ' ID Izin berhasil dicabut!')->with(['error_id_izin' => $error_id_izin]);
    }

    /**
     * Send WhatsApp notification to asesi after verification.
     *
     * Uses Personal + KlasifikasiKualifikasi data — no Jadwal/AsesiJadwal dependency.
     */
    private function sendVerifikasiWhatsApp(Personal $personal, string $id_izin): void
    {
        $telepon = $personal->telepon;
        if (!$telepon) {
            Log::info('Skipping WhatsApp — no phone number for ' . $id_izin);
            return;
        }

        // Format phone number: remove non-digits, ensure 62 prefix
        $telepon = preg_replace('/[^0-9]/', '', $telepon);
        if (str_starts_with($telepon, '0')) {
            $telepon = '62' . substr($telepon, 1);
        } elseif (!str_starts_with($telepon, '62')) {
            $telepon = '62' . $telepon;
        }

        $uuid = $personal->wa_uuid;
        if (!$uuid) {
            Log::info('Skipping WhatsApp — no wa_uuid on personal for ' . $id_izin);
            return;
        }

        // Tanggal pelaksanaan uji (from Personal)
        $tanggalUji = $personal->rencana_tanggal_uji
            ? \Carbon\Carbon::parse($personal->rencana_tanggal_uji)->translatedFormat('d F Y')
            : '-';

        // TUK from KlasifikasiKualifikasi → MyTuk
        $klasifikasi = KlasifikasiKualifikasi::where('id_izin', $id_izin)->first();
        $tukId = $klasifikasi?->tuk;
        $tukModel = is_numeric($tukId) ? \App\Models\MyTuk::find($tukId) : null;
        $tukText = $tukModel
            ? trim($tukModel->nama_tuk . ' - ' . $tukModel->alamat)
            : '-';

        // Skema from KlasifikasiKualifikasi → JabatanKerja
        $skemaNama = '-';
        if ($klasifikasi?->jabatan_kerja) {
            $skemaNama = JabatanKerja::where('id_jabatan_kerja', $klasifikasi->jabatan_kerja)
                ->value('jabatan_kerja') ?? '-';
        }

        // Frontend magic link
        $frontendUrl = config('app.frontend_url', 'https://devgatensi.site');
        $link = rtrim($frontendUrl, '/') . '/persiapan-asesmen/' . $uuid;

        $qontak = app(QontakService::class);
        $result = $qontak->sendMessage($telepon, $personal->nama, [
            'nama_asesi' => $personal->nama,
            'tanggal_uji' => $tanggalUji,
            'tuk' => $tukText,
            'skema' => $skemaNama,
            'link' => $link,
        ]);

        if ($result) {
            Log::info('WhatsApp sent successfully to ' . $telepon . ' for ' . $id_izin);
        } else {
            Log::warning('WhatsApp send returned no result for ' . $id_izin);
        }
    }
}