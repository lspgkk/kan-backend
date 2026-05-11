<?php

namespace App\Http\Controllers;

use App\Models\AsesiJadwal;
use App\Models\BaKomtek;
use App\Models\Barcode;
use App\Models\Jadwal;
use App\Models\KlasifikasiKualifikasi;
use App\Models\Personal;
use App\Models\SKKetua;
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
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class BlankoController extends Controller
{
    public function submitHasilUji(Request $request, $jadwal_id)
    {
        // Get all asesi from AsesiJadwal for this jadwal_id
        $allAsesiJadwal = AsesiJadwal::where('jadwal_id', $jadwal_id)->get();

        if ($allAsesiJadwal->isEmpty()) {
            return back()->with('error', 'Tidak ada asesi untuk jadwal ini');
        }

        // Check uploaded asesmen for all asesi
        $is_error_uploaded_assesmen = false;
        foreach ($allAsesiJadwal as $asesiJadwal) {
            $id_izin = $asesiJadwal->id_izin;

            // Check if exist in bukti assesment
            $asesmen_exist = DB::table("bukti_asesmens")->where("id_izin", $id_izin)->first();
            if (!$asesmen_exist) {
                $is_error_uploaded_assesmen = true;
                return back()->with('error', $id_izin . " belum mengupload bukti assesmen!");
            }

            if ($asesmen_exist->new_ia_05 == null and $asesmen_exist->new_ia_03) {
                return back()->with('error', $id_izin . " belum mengupload IA 05 dan IA 03 baru!");
            }
        }

        // Get competent (K) and not competent (BK) asesies from AsesiJadwal
        $asesiKompeten = $allAsesiJadwal->where('kompeten', 'K');
        $asesiBelumKompeten = $allAsesiJadwal->where('kompeten', 'BK');

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

                $response_data = $response->json();
                if ($response_data['code'] != "ERR") {
                    $id_skema_bnsp = (int)$response_data['data']['id'];
                    if ($klas_kua->jabatan_kerja == "SI013041") {
                        $id_skema_bnsp = 63538;
                    }
                } else {
                    if ($klas_kua->jabatan_kerja == "SI101015") {
                        $id_skema_bnsp = 63566;
                    } else if ($klas_kua->jabatan_kerja == "SI013041") {
                        $id_skema_bnsp = 63538;
                    } else {
                        return back()->with('error', 'Error skema bnsp: id skema tidak ditemukan');
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

                    $response_jadwal = $response->json();
                    if ($response_jadwal['code'] == "ERR") {
                        return back()->with('error', 'Error surat tugas: ID Jadwal tidak ditemukan');
                    } else if ($response_jadwal['message'] == "Unauthorized") {
                        Log::error("BNSP authorization expired! Generate through manual through artisan");
                        abort(500);
                    }

                    // Tahan kalau belum uji
                    $tgl_uji = Carbon::parse($response_jadwal['dataJadwal']['tanggal_mulai']);
                    $today = Carbon::now();
                    if ($today->lt($tgl_uji)) {
                        return back()->with('error', "Tidak boleh melakukan pengajuan, belum uji di tanggal " . str($response_jadwal['dataJadwal']['tanggal_mulai']));
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

                    if ($response_post_bnsp->successful()) {
                        $responseData = $response_post_bnsp->json();
                        if ($responseData['code'] == "OK") {
                            // Update kompeten in AsesiJadwal
                            AsesiJadwal::where("id_izin", $asesiJadwal->id_izin)->update([
                                "kompeten" => "K"
                            ]);

                            DB::commit();
                        } else if ($responseData['message'] == "Unauthorized") {
                            Log::error("BNSP authorization expired! Generate through manual through artisan");
                            DB::rollBack();
                            abort(500);
                        } else {
                            return back()->with("error", "Error BNSP: " . $responseData['message'] . " " . $asesiJadwal->id_izin)->withInput();
                        }
                    } else {
                        return back()->with("error", "1. Error BNSP: " . $response_post_bnsp->json()['message'] . "  " . $asesiJadwal->id_izin)->withInput();
                    }
                    usleep(1000);
                } catch (\Exception $e) {
                    DB::rollBack();
                    return back()->with("error", "Terjadi kesalahan saat hasil uji 1 - K (" . $e->getMessage() . ")")->withInput();
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

            $response_data = $response->json();
            if ($response_data['code'] != "ERR") {
                $id_skema_bnsp = (int)$response_data['data']['id'];
                if ($klas_kua->jabatan_kerja == "SI013041") {
                    $id_skema_bnsp = 63538;
                }
            } else {
                if ($klas_kua->jabatan_kerja == "SI101015") {
                    $id_skema_bnsp = 63566;
                } else if ($klas_kua->jabatan_kerja == "SI013041") {
                    $id_skema_bnsp = 63538;
                } else {
                    return back()->with('error', 'Error skema bnsp: id skema tidak ditemukan');
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

                $response_jadwal = $response->json();
                if ($response_jadwal['code'] == "ERR") {
                    return back()->with('error', 'Error surat tugas: ID Jadwal tidak ditemukan');
                } else if ($response_jadwal['message'] == "Unauthorized") {
                    Log::error("BNSP authorization expired! Generate through manual through artisan");
                    abort(500);
                }

                // Hit api
                $url = 'https://komstruksi.bnsp.go.id/api/v1/jadwal/peserta/hasil-uji';

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

                if ($response_post_bnsp->successful()) {
                    $responseData = $response_post_bnsp->json();
                    if ($responseData['code'] == "OK") {
                        continue;
                    } else if ($responseData['message'] == "Unauthorized") {
                        Log::error("BNSP authorization expired! Generate through manual through artisan");
                        abort(500);
                    }
                } else {
                    DB::rollBack();
                    return back()->with("error", "2. Error BNSP: " . $response_post_bnsp->json()['message'] . " " . $asesiJadwal->id_izin)->withInput();
                }
                // END OF PART OF BNSP //
            } catch (\Exception $e) {
                DB::rollBack();
                dd($e);
                return back()->with("error", "Terjadi kesalahan saat hasil uji 1 - BK (" . $e->getMessage() . ")")->withInput();
            }
        }

        /* END OF HASIL UJI 1 */






        /* END OF HASIL UJI 1 */



        /* CREATE BLANKO */
        // Recalculate counts for the blanko generation
        $belum_kompeten = $asesiBelumKompeten->count();
        $kompeten = $asesiKompeten->count();

        // Upload file BA Komtek & SK Ketua
        $satu_orang = Jadwal::where("jadwal_id", trim($request->input("jadwal_id")))->first();
        $komtek1 = DB::connection("mygatensi")->table("mypersonillsp")->where("Nomor", $satu_orang->komtek1)->first()->Nama;
        $komtek2 = DB::connection("mygatensi")->table("mypersonillsp")->where("Nomor", $satu_orang->komtek2)->first()->Nama;
        $komtek3 = DB::connection("mygatensi")->table("mypersonillsp")->where("Nomor", $satu_orang->komtek3)->first()->Nama;

        // Generate spt terbaru
        $check_if_exist = BaKomtek::where("jadwal_id", trim($request->jadwal_id))->first();
        if ($check_if_exist) {
            $no_ba_komtek = $check_if_exist->no_surat;
        } else {
            $no_ba_komtek = BaKomtek::latest("id")->first();
            $no_ba_komtek = $no_ba_komtek->no_surat;

            $monthsInRomanNumerals = [
                1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV',
                5 => 'V', 6 => 'VI', 7 => 'VII', 8 => 'VIII',
                9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII',
            ];

            $currentMonthNumber = Carbon::now()->month;
            $currentYearNumber = Carbon::now()->year;

            $bulan = $monthsInRomanNumerals[$currentMonthNumber];

            $no_ba_komtek = explode("/", $no_ba_komtek);

            if ((int)$no_ba_komtek[3] == $currentYearNumber) {
                $no_ba_komtek[0] = str((int)$no_ba_komtek[0] + 1);
            } else {
                $no_ba_komtek[0] = str(0);
            }

            $no_ba_komtek[2] = $bulan;
            $no_ba_komtek[3] = str($currentYearNumber);
            $no_ba_komtek = implode("/", $no_ba_komtek);
        }


        // Mulailah generate
        $authData = DB::table('b_n_s_p_auths')->select('x_authorization')->first()->x_authorization;

        // Get data jadwal_id from bnsp
        $url = 'https://konstruksi.bnsp.go.id/api/v1/jadwal/blanko?jadwal_id=' . $jadwal_id;
        $response = Http::retry(2, 100)->withHeaders([
            'Content-Type' => 'application/json',
            'x-authorization' => $authData
        ])->get($url);

        $response_jadwal = $response->json();
        if ($response_jadwal['code'] == "ERR") {
            return back()->with('error', 'Error surat tugas: ID Jadwal tidak ditemukan');
        }
        $count_data = count($response_jadwal['data']);

        // Get data asesor from bnsp
        // $allAsesor = resource_path('json/myasesorbnspbaru.json');
        // // $allAsesor = json_decode(file_get_contents($allAsesor), true)['myasesorbnsp'];
        $allAsesor = DB::connection("mygatensi")->table('myasesorbnsp')->get();


        $found_asesor = "";
        $current_asesor = Jadwal::where("jadwal_id", $jadwal_id)->first();
        foreach ($allAsesor as $asesor) {
            if ($asesor->ID == $current_asesor->asesor_id) {
                $found_asesor = $asesor;
            }
        }

        if ($found_asesor == "") {
            return back()->with('error', 'ID Asesor tidak ditemukan');
        }

        // $allTuk = resource_path('json/mytukbnsp.json');
        // $allTuk = json_decode(file_get_contents($allTuk), true)['mytukbnsp'];
        $allTuk = DB::connection("mygatensi")->table("mytukbnsp")->get();

        $found_tuk = "";
        foreach ($allTuk as $tuk) {
            if ($response_jadwal['dataJadwal']['kode_tuk'] == $tuk->Kode) {
                $found_tuk = $tuk;
            }
        }
        if ($found_tuk == "") {
            return back()->with('error', 'ID TUK tidak ditemukan');
        }

        // Kumpul semua skema tiap asesi
        $nama_peserta = [];

        foreach ($response_jadwal['data'] as $asesi) {
            // Get id_izin from Personal using NIK
            $personal = Personal::where("nik", $asesi['nik'])->first();

            // Get kompeten status from AsesiJadwal
            $asesiJadwal = null;
            if ($personal) {
                $asesiJadwal = AsesiJadwal::where("jadwal_id", $jadwal_id)
                    ->where("id_izin", $personal->id_izin)
                    ->first();
            }

            $skema = DB::connection('mygatensi')
                ->table('myskemabnsp')
                ->select('id', 'nama')
                ->where('id', $asesi['skema_id'])
                ->first();

            $temp_skema_nama = $skema->nama ?? '';

            $nama_peserta[] = [
                "nama" => $asesi['nama'],
                "skema" => $temp_skema_nama,
                "kompeten" => $asesiJadwal->kompeten ?? null
            ];
        }

        $tanggal_surat_qr = \Carbon\Carbon::parse($response_jadwal['dataJadwal']['tanggal_mulai'])->addDay()->setTimezone('Asia/Jakarta')->isoFormat('dddd, D MMMM YYYY');
        $barcode1 = (string) Uuid::uuid4();
        $barcode2 = (string) Uuid::uuid4();
        $barcode3 = (string) Uuid::uuid4();
        $komtekMapping = [
            '0010' => 'MET.000.003411 2025',
            '0011' => 'MET.000.003408 2025',
            '0051' => 'MET.000.003409 2025',
            '0052' => 'MET.000.006828 2021',
            '0053' => 'MET.000.004966 2021',
            '0055' => 'MET.000.003410 2025',
        ];
        $met1 = DB::connection('mygatensi')->table('myasesorbnsp')->where('Nama', 'LIKE', '%' . $komtek1 . '%')->pluck('Noreg')->first();
        if (!isset($met1)) {
            $met1 = $komtekMapping[$satu_orang->komtek1] ?? null;
        }

        $met2 = DB::connection('mygatensi')->table('myasesorbnsp')->where('Nama', 'LIKE', '%' . $komtek2 . '%')->pluck('Noreg')->first();
        if (!isset($met2)) {
            $met2 = $komtekMapping[$satu_orang->komtek2] ?? null;
        }

        $met3 = DB::connection('mygatensi')->table('myasesorbnsp')->where('Nama', 'LIKE', '%' . $komtek3 . '%')->pluck('Noreg')->first();
        if (!isset($met3)) {
            $met3 = $komtekMapping[$satu_orang->komtek3] ?? null;
        }
        $html1 = '<img src="data:image/svg+xml;base64,' . base64_encode(QrCode::margin(0)->size(70)->generate("https://barcode.lspgatensi.id/" . $barcode1)) . '"  width="100" height="100" />';
        $html2 = '<img src="data:image/svg+xml;base64,' . base64_encode(QrCode::margin(0)->size(70)->generate("https://barcode.lspgatensi.id/" . $barcode2)) . '"  width="100" height="100" />';
        $html3 = '<img src="data:image/svg+xml;base64,' . base64_encode(QrCode::margin(0)->size(70)->generate("https://barcode.lspgatensi.id/" . $barcode3)) . '"  width="100" height="100" />';
        Barcode::create([
            'nama' => $komtek1,
            'id_izin' => $met1,
            'jabatan' => 'Komite Teknis',
            'url' => 'https://barcode.lspgatensi.id/' . $barcode1,
            'created_at' => \Carbon\Carbon::parse($response_jadwal['dataJadwal']['tanggal_mulai'])->addDay()->setTimezone('Asia/Jakarta')
        ]);
        Barcode::create([
            'nama' => $komtek2,
            'id_izin' => $met2,
            'jabatan' => 'Komite Teknis',
            'url' => 'https://barcode.lspgatensi.id/' . $barcode2,
            'created_at' => \Carbon\Carbon::parse($response_jadwal['dataJadwal']['tanggal_mulai'])->addDay()->setTimezone('Asia/Jakarta')
        ]);
        Barcode::create([
            'nama' => $komtek3,
            'id_izin' => $met3,
            'jabatan' => 'Komite Teknis',
            'url' => 'https://barcode.lspgatensi.id/' . $barcode3,
            'created_at' => \Carbon\Carbon::parse($response_jadwal['dataJadwal']['tanggal_mulai'])->addDay()->setTimezone('Asia/Jakarta')
        ]);
        $result = [
            "nomor" => $no_ba_komtek,
            "tanggal" => \Carbon\Carbon::parse($response_jadwal['dataJadwal']['tanggal_mulai'])->subDay()->setTimezone('Asia/Jakarta'),
            "nama_komtek1" => $komtek1,
            "nama_komtek2" => $komtek2,
            "nama_komtek3" => $komtek3,
            "nama_peserta" => $nama_peserta,
            "qr" => $html1,
        ];
        $pdf = PDF::loadView('template-surat.ba_komteks', $result);
        $pdfContent_BA = $pdf->output();
        Storage::disk("str_moon")->put("/ba_komtek/" . trim($jadwal_id) . ".pdf", $pdfContent_BA);
        $full_url_file_ba = 'https://lspgatensi.id/files/ba_komtek/' . trim($jadwal_id) . ".pdf";



        // Permohonan Blanko
        $check_blanko = SuratBlanko::where("jadwal_id", $jadwal_id)->first();
        if ($check_blanko) {
            $no_surat = $check_blanko->no_surat;
        } else {
            // Check which number the largest from reguler and balai
            $no_surat = SuratBlanko::whereYear('created_at', Carbon::now()->year)->latest("id")->first();
            $no_surat_balai = DB::connection("balai")->table("surat_blankos")->select(["no_surat"])->whereYear('created_at', Carbon::now()->year)->latest("id")->first();
            $no_surat = $no_surat->no_surat  ?? '1/PB/LSP - Gatensi/I/2025';
            $no_surat_balai = $no_surat_balai->no_surat ?? '1/PB/LSP - Gatensi/I/2025';

            $no_surat = explode("/", $no_surat);
            $no_surat_balai = explode("/", $no_surat_balai);

            if ((int)$no_surat_balai[0] > (int)$no_surat[0]) {
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

            if ((int)$no_surat[4] == $currentYearNumber) {
                $no_surat[0] = str((int)$no_surat[0] + 1);
            } else {
                $no_surat[0] = str((int)1);
            }

            $no_surat[3] = $bulan;
            $no_surat[4] = str($currentYearNumber);
            $no_surat = implode("/", $no_surat);
        }
        $tanggal_surat_qr = \Carbon\Carbon::parse($response_jadwal['dataJadwal']['tanggal_mulai'])->addDay()->setTimezone('Asia/Jakarta')->isoFormat('dddd, D MMMM YYYY');
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
        Storage::disk("str_moon")->put("/surat_blanko/" . trim($jadwal_id) . ".pdf", $pdfContent_sk);

        $full_url_file_blanko = 'https://lspgatensi.id/files/surat_blanko/' . trim($jadwal_id) . ".pdf";


        // SK Ketua
        $check_sk = SKKetua::where("jadwal_id", $jadwal_id)->first();
        if ($check_sk) {
            $no_sk_lsp = $check_sk->no_surat;
        } else {
            // Check which number the largest from reguler and balai
            $no_sk_lsp = SKKetua::latest("id")->first();
            $no_sk_lsp_reg = DB::connection("balai")->table("s_k_ketuas")->select(["no_surat"])->latest("id")->first();
            $no_sk_lsp = $no_sk_lsp->no_surat;
            $no_sk_lsp_reg = $no_sk_lsp_reg->no_surat ?? '1/LSP GKK B.003/I/2026';

            $no_sk_lsp = explode("/", $no_sk_lsp);
            $no_sk_lsp_reg = explode("/", $no_sk_lsp_reg);

            if ((int)$no_sk_lsp_reg[0] > (int)$no_sk_lsp[0]) {
                $no_sk_lsp = $no_sk_lsp_reg;
            }

            $monthsInRomanNumerals = [
                1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV',
                5 => 'V', 6 => 'VI', 7 => 'VII', 8 => 'VIII',
                9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII',
            ];

            $currentMonthNumber = Carbon::now()->month;
            $currentYearNumber = Carbon::now()->year;

            $bulan = $monthsInRomanNumerals[$currentMonthNumber];

            // if ((int)$no_sk_lsp[3] == $currentYearNumber) {
            //     $no_sk_lsp[0] = str((int)$no_sk_lsp[0] + 1);
            // } else {
            //     $no_sk_lsp[0] = str((int)1);
            // }

            $no_sk_lsp[0] = (string) ((int)$no_sk_lsp[0] + 1);

            $no_sk_lsp[2] = $bulan;
            $no_sk_lsp[3] = str($currentYearNumber);
            $no_sk_lsp = implode("/", $no_sk_lsp);
        }

        $tanggal_surat_qr = \Carbon\Carbon::parse($response_jadwal['dataJadwal']['tanggal_mulai'])->addDay()->setTimezone('Asia/Jakarta')->isoFormat('dddd, D MMMM YYYY');
        $html1 = '<img src="data:image/svg+xml;base64,' . base64_encode(QrCode::margin(0)->size(70)->generate("https://barcode.lspgatensi.id/$barcodeKetua")) . '"  width="100" height="100" />';
        $result = [
            "no_surat" => $no_sk_lsp,
            "jmlh_peserta" => $count_data,
            "nama_peserta" => $nama_peserta,
            "svg" => $html1,
            "tanggal_surat" => \Carbon\Carbon::parse($response_jadwal['dataJadwal']['tanggal_mulai'])->setTimezone('Asia/Jakarta')
        ];

        $pdf_sk = PDF::loadView('template-surat.sk_ketua', $result);
        $pdfContent_sk = $pdf_sk->output();
        Storage::disk("str_moon")->put("/sk_ketua/" . trim($jadwal_id) . ".pdf", $pdfContent_sk);

        $full_url_file_sk = 'https://lspgatensi.id/files/sk_ketua/' . trim($jadwal_id) . ".pdf";
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
                    "tanggal" => \Carbon\Carbon::parse($response_jadwal['dataJadwal']['tanggal_mulai'])->setTimezone('Asia/Jakarta')->addDay()->isoFormat('YYYY-MM-DD'),
                    "file_dokumen" => $full_url_file_blanko
                ],
                [
                    "jadwal_id" => $jadwal_id,
                    "form_id" => 2,
                    "nama" => "Berita Acara Pleno Komite Teknis",
                    "nomor" => $no_ba_komtek,
                    "tanggal" => \Carbon\Carbon::parse($response_jadwal['dataJadwal']['tanggal_mulai'])->setTimezone('Asia/Jakarta')->addDay()->isoFormat('YYYY-MM-DD'),
                    "file_dokumen" => $full_url_file_ba
                ],
                [
                    "jadwal_id" => $jadwal_id,
                    "form_id" => 3,
                    "nama" => "SK Hasil Sertifikasi Kompetensi",
                    "nomor" => $no_sk_lsp,
                    "tanggal" => \Carbon\Carbon::parse($response_jadwal['dataJadwal']['tanggal_mulai'])->setTimezone('Asia/Jakarta')->addDay()->isoFormat('YYYY-MM-DD'),
                    "file_dokumen" => $full_url_file_sk
                ]
            ];

            DB::beginTransaction();
            $response_post_bnsp = Http::retry(2, 100)->withHeaders($headers)->post($url, $request_create_blanko);
            if ($response_post_bnsp->successful()) {
                $responseData = $response_post_bnsp->json();
                if ($responseData['code'] == "OK") {
                    SuratBlanko::updateOrCreate([
                        'jadwal_id' => $jadwal_id,
                        'link' => $full_url_file_blanko,
                        'no_surat' => $no_surat,
                        'inputby_id' => Auth::user()->id
                    ]);
                    // $check_ba = DB::table("ba_komteks")->where("jadwal_id", $jadwal_id);
                    BaKomtek::updateOrCreate([
                        'jadwal_id' => $jadwal_id,
                        'link' => $full_url_file_ba,
                        'no_surat' => $no_ba_komtek,
                        'inputby_id' => Auth::user()->id
                    ]);

                    SKKetua::updateOrCreate([
                        'jadwal_id' => $jadwal_id,
                        'link' => $full_url_file_sk,
                        'no_surat' => $no_sk_lsp,
                        'inputby_id' => Auth::user()->id
                    ]);

                    Barcode::create([
                        'nama' => 'Radinal Efendy, S.T.',
                        'id_izin' => '2220910001',
                        'jabatan' => 'Direktur LSP',
                        'url' => 'https://barcode.lspgatensi.id/' . $barcodeKetua,
                        'created_at' => \Carbon\Carbon::parse($response_jadwal['dataJadwal']['tanggal_mulai'])->addDay()->setTimezone('Asia/Jakarta')
                    ]);
                    DB::commit();
                } else if ($responseData['message'] == "Unauthorized") {
                    Log::error("BNSP authorization expired! Generate through manual through artisan");
                    DB::rollBack();
                    abort(500);
                } else {
                    dd($responseData);
                }
            } else {
                DB::rollBack();
                return back()->with("error", "3. Error BNSP: " . $response_post_bnsp->json()['message'])->withInput();
            }
        } catch (\Exception $e) {
            return back()->with("error", "Terjadi kesalahan saat create blanko (" . $e->getMessage() . ")")->withInput();
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
                if ($responseData['code'] == "OK") {
                    return redirect('/hasil-uji')->with("success", "Berhasil submit hasil uji 1/create blanko/confirm jadwal ke BNSP!");
                } else if ($responseData['message'] == "Unauthorized") {
                    Log::error("BNSP authorization expired! Generate through manual through artisan");
                    abort(500);
                } else {
                    Log::info(str($responseData));
                    return redirect('/hasil-uji')->with("error", "Terjadi kesalahan post bnsp, " . str($responseData));
                }
            } else {
                return back()->with("error", "4. Error BNSP: " . $response_post_bnsp->json()['message'])->withInput();
            }
        } catch (\Exception $e) {
            return back()->with("error", "Terjadi kesalahan saat confirm jadwal (" . $e->getMessage() . ")")->withInput();
        }
        /* END OF CONFIRM JADWAL */
    }
}
