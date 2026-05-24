<?php

namespace App\Http\Controllers;

use App\Models\AsesiJadwal;
use App\Models\BaKomtek;
use App\Models\Jadwal;
use App\Models\KlasifikasiKualifikasi;
use App\Models\Personal;
use App\Models\SkPelaksanaanUji;
use App\Models\SuratBlanko;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Ramsey\Uuid\Uuid;
use App\Traits\ResolvesKomtekDecision;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class BlankoController extends Controller
{
    use ResolvesKomtekDecision;

    public function submitHasilUji(Request $request, $jadwal_id)
    {
        // Get all asesi from AsesiJadwal for this jadwal_id
        $allAsesiJadwal = AsesiJadwal::where('jadwal_id', $jadwal_id)->get();

        if ($allAsesiJadwal->isEmpty()) {
            return response()->json(['message' => 'Tidak ada asesi untuk jadwal ini'], 422);
        }

        // Check uploaded asesmen for all asesi
        $is_error_uploaded_assesmen = false;
        foreach ($allAsesiJadwal as $asesiJadwal) {
            $id_izin = $asesiJadwal->id_izin;

            // Check if exist in bukti assesment
            $asesmen_exist = DB::table("bukti_asesmens")->where("id_izin", $id_izin)->first();
            if (!$asesmen_exist) {
                $is_error_uploaded_assesmen = true;
                return response()->json(['message' => $id_izin . " belum mengupload bukti assesmen!"], 422);
            }

            if ($asesmen_exist->new_ia_05 == null and $asesmen_exist->new_ia_03) {
                return response()->json(['message' => $id_izin . " belum mengupload IA 05 dan IA 03 baru!"], 422);
            }
        }

        // Get competent (K) and not competent (BK) asesies by komtek majority decision
        $asesiKompeten = $allAsesiJadwal->filter(fn($a) => $this->resolveKomtekDecision($a->id_izin) === 'K');
        $asesiBelumKompeten = $allAsesiJadwal->filter(fn($a) => $this->resolveKomtekDecision($a->id_izin) === 'BK');

        // Process each competent asesi
        foreach ($asesiKompeten as $asesiJadwal) {
            if (!$is_error_uploaded_assesmen) {
                $personal = Personal::where("id_izin", $asesiJadwal->id_izin)->first();
                $klas_kua = KlasifikasiKualifikasi::select(['jabatan_kerja'])->where("id_izin", $asesiJadwal->id_izin)->first();

                $apl_foto = DB::table("bukti_asesmens")->where("id_izin", $asesiJadwal->id_izin)->first();

                $url = 'https://konstruksi.bnsp.go.id/api/v1/skema?kode_lpjk=' . $klas_kua->jabatan_kerja;
                $authData = DB::table('b_n_s_p_auths')->select('x_authorization')->first()->x_authorization;

                $response = rescue(function () use ($authData, $url) {
                    return Http::withHeaders([
                        'Content-Type' => 'application/json',
                        'x-authorization' => $authData
                    ])->get($url);
                }, function ($e) {
                    return $e->getMessage();
                });

                $response_data = is_string($response) || !$response ? null : $response->json();
                if ($response_data && ($response_data['code'] ?? '') != "ERR") {
                    $id_skema_bnsp = (int) ($response_data['data']['id'] ?? 0);
                    if ($klas_kua->jabatan_kerja == "SI013041") {
                        $id_skema_bnsp = 63538;
                    }
                } else {
                    if ($klas_kua->jabatan_kerja == "SI101015") {
                        $id_skema_bnsp = 63566;
                    } else if ($klas_kua->jabatan_kerja == "SI013041") {
                        $id_skema_bnsp = 63538;
                    } else {
                        return response()->json(['message' => 'Error skema bnsp: id skema tidak ditemukan'], 422);
                    }
                }

                $request_data = array(
                    'jadwal_id' => $jadwal_id,
                    'nik_peserta' => $personal->nik,
                    'kompeten' => 1,

                    'skema_id' => $id_skema_bnsp,
                    'metode_uji' => "observasi",
                    "uji_praktek_atau_observasi_lapangan" => 1,
                    "uji_tulis" => 1,
                    "uji_lisan" => 1,
                    "wawancara" => 0,
                    "penyelenggaraan_uji" => 1,
                    "apl_01" => $apl_foto->url_apl_01,
                    "apl_02" => $apl_foto->url_apl_02,
                    "url_dokumentasi_asesmen" => $apl_foto->url_docs_asesmen
                );

                usleep(500);

                try {
                    // Check if jadwal_id exist
                    $url = 'https://konstruksi.bnsp.go.id/api/v1/jadwal/blanko?jadwal_id=' . $jadwal_id;
                    $response = rescue(function () use ($authData, $url) {
                        return Http::withHeaders([
                            'Content-Type' => 'application/json',
                            'x-authorization' => $authData
                        ])->get($url);
                    }, function ($e) {
                        return $e->getMessage();
                    });

                    usleep(2000);

                    $response_jadwal = is_string($response) || !$response ? null : $response->json();
                    if (!$response_jadwal || ($response_jadwal['code'] ?? '') == "ERR") {
                        return response()->json(['message' => 'Error surat tugas: ID Jadwal tidak ditemukan'], 422);
                    } else if (($response_jadwal['message'] ?? '') == "Unauthorized") {
                        Log::error("BNSP authorization expired! Generate through manual through artisan");
                        abort(500);
                    }

                    // Tahan kalau belum uji
                    $tgl_uji = Carbon::parse($response_jadwal['dataJadwal']['tanggal_mulai'] ?? 'now');
                    $today = Carbon::now();
                    if ($today->lt($tgl_uji)) {
                        return response()->json(['message' => "Tidak boleh melakukan pengajuan, belum uji di tanggal " . str($response_jadwal['dataJadwal']['tanggal_mulai'] ?? '')], 422);
                    }

                    // Hit api
                    $url = 'https://konstruksi.bnsp.go.id/api/v1/jadwal/peserta/hasil-uji';

                    $headers = [
                        'Content-Type' => 'application/json',
                        'x-authorization' => $authData
                    ];

                    DB::beginTransaction();
                    $response_post_bnsp = rescue(function () use ($headers, $url, $request_data) {
                        return Http::withHeaders($headers)->post($url, $request_data);
                    }, function ($e) {
                        return $e->getMessage();
                    });

                    usleep(2000);

                    if (is_string($response_post_bnsp) || !$response_post_bnsp) {
                        DB::rollBack();
                        return response()->json(['message' => "1. Error BNSP: gagal konek ke BNSP  " . $asesiJadwal->id_izin], 422);
                    }

                    if ($response_post_bnsp->successful()) {
                        $responseData = $response_post_bnsp->json();
                        if (($responseData['code'] ?? '') == "OK") {
                            // Update kompeten in AsesiJadwal
                            AsesiJadwal::where("id_izin", $asesiJadwal->id_izin)->update([
                                "kompeten" => "K"
                            ]);

                            DB::commit();
                        } else if (($responseData['message'] ?? '') == "Unauthorized") {
                            Log::error("BNSP authorization expired! Generate through manual through artisan");
                            DB::rollBack();
                            abort(500);
                        } else {
                            DB::rollBack();
                            return response()->json(['message' => "Error BNSP: " . ($responseData['message'] ?? 'tidak diketahui') . " " . $asesiJadwal->id_izin], 422);
                        }
                    } else {
                        DB::rollBack();
                        return response()->json(['message' => "1. Error BNSP: " . ($response_post_bnsp->json()['message'] ?? 'tidak diketahui') . "  " . $asesiJadwal->id_izin], 422);
                    }
                    usleep(1000);
                } catch (\Exception $e) {
                    DB::rollBack();
                    return response()->json(['message' => "Terjadi kesalahan saat hasil uji 1 - K (" . $e->getMessage() . ")"], 422);
                }
            }
        }

        // Sekarang Tidak Kompeten - process from AsesiJadwal
        foreach ($asesiBelumKompeten as $asesiJadwal) {
            $personal = Personal::where("id_izin", $asesiJadwal->id_izin)->first();
            $klas_kua = KlasifikasiKualifikasi::select(['jabatan_kerja'])->where("id_izin", $asesiJadwal->id_izin)->first();
            $apl_foto = DB::table("bukti_asesmens")->where("id_izin", $asesiJadwal->id_izin)->first();
            $url = 'https://konstruksi.bnsp.go.id/api/v1/skema?kode_lpjk=' . $klas_kua->jabatan_kerja;
            $authData = DB::table('b_n_s_p_auths')->select('x_authorization')->first()->x_authorization;

            $response = rescue(function () use ($authData, $url) {
                return Http::withHeaders([
                    'Content-Type' => 'application/json',
                    'x-authorization' => $authData
                ])->get($url);
            }, function ($e) {
                return $e->getMessage();
            });

            $response_data = is_string($response) || !$response ? null : $response->json();
            if ($response_data && ($response_data['code'] ?? '') != "ERR") {
                $id_skema_bnsp = (int) ($response_data['data']['id'] ?? 0);
                if ($klas_kua->jabatan_kerja == "SI013041") {
                    $id_skema_bnsp = 63538;
                }
            } else {
                if ($klas_kua->jabatan_kerja == "SI101015") {
                    $id_skema_bnsp = 63566;
                } else if ($klas_kua->jabatan_kerja == "SI013041") {
                    $id_skema_bnsp = 63538;
                } else {
                    return response()->json(['message' => 'Error skema bnsp: id skema tidak ditemukan'], 422);
                }
            }

            $request_data = array(
                'jadwal_id' => $jadwal_id,
                'nik_peserta' => $personal->nik,
                'kompeten' => 2,

                'skema_id' => $id_skema_bnsp,
                'metode_uji' => "observasi",
                "uji_praktek_atau_observasi_lapangan" => 1,
                "uji_tulis" => 1,
                "uji_lisan" => 1,
                "wawancara" => 0,
                "penyelenggaraan_uji" => 1,
                "apl_01" => "",
                "apl_02" => "",
                "url_dokumentasi_asesmen" => ""
            );

            usleep(500);

            try {
                // THIS IS THE PART OF BNSP //
                // Check if jadwal_id exist
                $url = 'https://konstruksi.bnsp.go.id/api/v1/jadwal/blanko?jadwal_id=' . $jadwal_id;
                $response = rescue(function () use ($authData, $url) {
                    return Http::withHeaders([
                        'Content-Type' => 'application/json',
                        'x-authorization' => $authData
                    ])->get($url);
                }, function ($e) {
                    return $e->getMessage();
                });

                usleep(500);

                $response_jadwal = is_string($response) || !$response ? null : $response->json();
                if (!$response_jadwal || ($response_jadwal['code'] ?? '') == "ERR") {
                    return response()->json(['message' => 'Error surat tugas: ID Jadwal tidak ditemukan'], 422);
                } else if (($response_jadwal['message'] ?? '') == "Unauthorized") {
                    Log::error("BNSP authorization expired! Generate through manual through artisan");
                    abort(500);
                }

                // Hit api
                $url = 'https://konstruksi.bnsp.go.id/api/v1/jadwal/peserta/hasil-uji';

                $headers = [
                    'Content-Type' => 'application/json',
                    'x-authorization' => $authData
                ];

                $response_post_bnsp = rescue(function () use ($headers, $url, $request_data) {
                    return Http::withHeaders($headers)->post($url, $request_data);
                }, function ($e) {
                    return $e->getMessage();
                });

                usleep(500);

                if (is_string($response_post_bnsp) || !$response_post_bnsp) {
                    DB::rollBack();
                    return response()->json(['message' => "2. Error BNSP: gagal konek ke BNSP  " . $asesiJadwal->id_izin], 422);
                }

                if ($response_post_bnsp->successful()) {
                    $responseData = $response_post_bnsp->json();
                    if (($responseData['code'] ?? '') == "OK") {
                        continue;
                    } else if (($responseData['message'] ?? '') == "Unauthorized") {
                        Log::error("BNSP authorization expired! Generate through manual through artisan");
                        abort(500);
                    }
                } else {
                    DB::rollBack();
                    return response()->json(['message' => "2. Error BNSP: " . ($response_post_bnsp->json()['message'] ?? 'tidak diketahui') . " " . $asesiJadwal->id_izin], 422);
                }
                // END OF PART OF BNSP //
            } catch (\Exception $e) {
                DB::rollBack();
                return response()->json(['message' => "Terjadi kesalahan saat hasil uji 2 - BK (" . $e->getMessage() . ")"], 422);
            }
        }

        /* END OF HASIL UJI 1 */






        /* END OF HASIL UJI 1 */



        /* CREATE BLANKO */
        // Recalculate counts for the blanko generation
        $belum_kompeten = $asesiBelumKompeten->count();
        $kompeten = $asesiKompeten->count();

        // Get existing BA Komtek from jadwal flow
        $ba_komtek = BaKomtek::where("jadwal_id", $jadwal_id)->first();
        if (!$ba_komtek || !$ba_komtek->no_surat) {
            return response()->json(['message' => 'BA Komtek belum tersedia'], 422);
        }

        // Get auth data
        $authData = DB::table('b_n_s_p_auths')->select('x_authorization')->first()->x_authorization;

        // Get data jadwal_id from bnsp
        $url = 'https://konstruksi.bnsp.go.id/api/v1/jadwal/blanko?jadwal_id=' . $jadwal_id;
        $response = Http::retry(2, 100)->withHeaders([
            'Content-Type' => 'application/json',
            'x-authorization' => $authData
        ])->get($url);

        $response_jadwal = $response->json();
        if (!$response_jadwal || ($response_jadwal['code'] ?? '') == "ERR") {
            return response()->json(['message' => 'Error surat tugas: ID Jadwal tidak ditemukan'], 422);
        }

        $tanggal_surat_bnsp = \Carbon\Carbon::parse($response_jadwal['dataJadwal']['tanggal_mulai'])->setTimezone('Asia/Jakarta');



        // Permohonan Blanko
        $check_blanko = SuratBlanko::where("jadwal_id", $jadwal_id)->first();
        if ($check_blanko && $check_blanko->no_surat) {
            $no_surat = $check_blanko->no_surat;
        } else {
            $defaultSurat = '1/PB/LSP - Gatensi/I/2025';

            $no_surat = SuratBlanko::whereYear('created_at', Carbon::now()->year)->latest("id")->first();
            $no_surat = $no_surat->no_surat ?? $defaultSurat;

            $no_surat_balai = rescue(
                fn() => DB::connection("balai")->table("surat_blankos")->select(["no_surat"])->whereYear('created_at', Carbon::now()->year)->latest("id")->first(),
                null
            );
            $no_surat_balai = $no_surat_balai->no_surat ?? $defaultSurat;

            $no_surat = explode("/", $no_surat);
            $no_surat_balai = explode("/", $no_surat_balai);

            if (count($no_surat) < 5) $no_surat = explode("/", $defaultSurat);
            if (count($no_surat_balai) < 5) $no_surat_balai = explode("/", $defaultSurat);

            if ((int) $no_surat_balai[0] > (int) $no_surat[0]) {
                $no_surat = $no_surat_balai;
            }

            $monthsInRomanNumerals = [
                1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV',
                5 => 'V', 6 => 'VI', 7 => 'VII', 8 => 'VIII',
                9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII',
            ];

            $currentMonthNumber = Carbon::now()->month;
            $currentYearNumber = Carbon::now()->year;

            $bulan = $monthsInRomanNumerals[$currentMonthNumber];

            if ((int) $no_surat[4] == $currentYearNumber) {
                $no_surat[0] = str((int) $no_surat[0] + 1);
            } else {
                $no_surat[0] = str((int) 1);
            }

            $no_surat[3] = $bulan;
            $no_surat[4] = str($currentYearNumber);
            $no_surat = implode("/", $no_surat);
        }
        $barcodeKetua = (string) Uuid::uuid4();
        $html1 = '<img src="data:image/svg+xml;base64,' . base64_encode(QrCode::margin(0)->size(70)->generate("https://barcode.lspgatensi.id/$barcodeKetua")) . '"  width="100" height="100" />';
        $result = [
            "no_surat" => $no_surat,
            "kompeten" => $kompeten,
            "belum_kompeten" => $belum_kompeten,
            "svg" => $html1,
            "tanggal_surat" => \Carbon\Carbon::parse($response_jadwal['dataJadwal']['tanggal_mulai'])->setTimezone('Asia/Jakarta')
        ];

        $pdf_sk = PDF::loadView('template-surat.permohonan_blanko', $result);
        $pdfContent_sk = $pdf_sk->output();
        Storage::disk("str_moon")->put("surat_blanko/" . trim($jadwal_id) . ".pdf", $pdfContent_sk);

        $full_url_file_blanko = 'https://moon.lspgatensi.id/files/new_gatensi/surat_blanko/' . trim($jadwal_id) . ".pdf";


        // Get existing SK Pelaksanaan Uji from jadwal flow
        $sk_pelaksanaan = SkPelaksanaanUji::where("jadwal_id", $jadwal_id)->first();
        if (!$sk_pelaksanaan || !$sk_pelaksanaan->no_surat) {
            return response()->json(['message' => 'SK Pelaksanaan Uji belum tersedia'], 422);
        }
        try {
            // Hit api
            $url = 'https://konstruksi.bnsp.go.id/api/v1/jadwal/blanko';

            $headers = [
                'Content-Type' => 'application/json',
                'x-authorization' => $authData
            ];

            $request_create_blanko = [
                [
                    "jadwal_id" => $jadwal_id,
                    "form_id" => 1,
                    "nama" => "Nomor Permohonan",
                    "nomor" => $no_surat,
                    "tanggal" => $tanggal_surat_bnsp->copy()->addDay()->isoFormat('YYYY-MM-DD'),
                    "file_dokumen" => $full_url_file_blanko
                ],
                [
                    "jadwal_id" => $jadwal_id,
                    "form_id" => 2,
                    "nama" => "Berita Acara Pleno Komite Teknis",
                    "nomor" => $ba_komtek->no_surat,
                    "tanggal" => $tanggal_surat_bnsp->copy()->addDay()->isoFormat('YYYY-MM-DD'),
                    "file_dokumen" => $ba_komtek->link
                ],
                [
                    "jadwal_id" => $jadwal_id,
                    "form_id" => 3,
                    "nama" => "SK Hasil Sertifikasi Kompetensi",
                    "nomor" => $sk_pelaksanaan->no_surat,
                    "tanggal" => $tanggal_surat_bnsp->copy()->addDay()->isoFormat('YYYY-MM-DD'),
                    "file_dokumen" => $sk_pelaksanaan->link
                ]
            ];

            DB::beginTransaction();
            $response_post_bnsp = Http::retry(2, 100)->withHeaders($headers)->post($url, $request_create_blanko);
            if ($response_post_bnsp->successful()) {
                $responseData = $response_post_bnsp->json();
                if (($responseData['code'] ?? '') == "OK") {
                    SuratBlanko::updateOrCreate(
                        ['jadwal_id' => $jadwal_id],
                        [
                            'link' => $full_url_file_blanko,
                            'no_surat' => $no_surat,
                            'inputby_id' => Auth::user()->id
                        ]
                    );
                    DB::commit();

                    Jadwal::where('id', $jadwal_id)->update(['status' => 'get-blanko']);
                } else if (($responseData['message'] ?? '') == "Unauthorized") {
                    Log::error("BNSP authorization expired! Generate through manual through artisan");
                    DB::rollBack();
                    abort(500);
                } else {
                    dd($responseData);
                }
            } else {
                DB::rollBack();
                return response()->json(['message' => "3. Error BNSP: " . ($response_post_bnsp->json()['message'] ?? 'tidak diketahui')], 422);
            }
        } catch (\Exception $e) {
            return response()->json(['message' => "Terjadi kesalahan saat create blanko (" . $e->getMessage() . ")"], 422);
        }
        /* END OF CREATE BLANKO */


        /* CONFIRM JADWAL */
        try {
            // Hit api
            $url = 'https://konstruksi.bnsp.go.id/api/v1/jadwal/confirm';

            $headers = [
                'Content-Type' => 'application/json',
                'x-authorization' => $authData
            ];

            $request_confirm = ['jadwal_id' => $jadwal_id];

            $response_post_bnsp = Http::retry(2, 100)->withHeaders($headers)->post($url, $request_confirm);
            if ($response_post_bnsp->successful()) {
                $responseData = $response_post_bnsp->json();
                if (($responseData['code'] ?? '') == "OK") {
                    return response()->json(['message' => 'Berhasil submit hasil uji 1/create blanko/confirm jadwal ke BNSP!']);
                } else if (($responseData['message'] ?? '') == "Unauthorized") {
                    Log::error("BNSP authorization expired! Generate through manual through artisan");
                    abort(500);
                } else {
                    Log::info(str($responseData));
                    return response()->json(['message' => 'Terjadi kesalahan post bnsp, ' . str($responseData)], 422);
                }
            } else {
                return response()->json(['message' => "4. Error BNSP: " . ($response_post_bnsp->json()['message'] ?? 'tidak diketahui')], 422);
            }
        } catch (\Exception $e) {
            return response()->json(['message' => "Terjadi kesalahan saat confirm jadwal (" . $e->getMessage() . ")"], 422);
        }
        /* END OF CONFIRM JADWAL */
    }
}
