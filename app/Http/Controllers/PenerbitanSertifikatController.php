<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Models\AbsenKomtek;
use App\Models\AsesorBnsp;
use App\Models\BaKomtek;
use App\Models\Barcode;
use App\Models\City;
use App\Models\DataPencatatan;
use App\Models\Jadwal;
use App\Models\KlasifikasiKualifikasi;
use App\Models\Personal;
use App\Models\Province;
use App\Models\RekomAsesor;
use App\Models\SPTAsesor;
use App\Models\SPTKomtek;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Ramsey\Uuid\Uuid;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class PenerbitanSertifikatController extends Controller
{
    public function storePenerbitan(Request $request, $jadwal_id)
    {
        try {
            $request->merge(['jadwal_id' => $jadwal_id]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 422);
        }

        $year = now()->year;

        $lastRecord = DataPencatatan::selectRaw('MAX(CAST(nomor_sertifikasi AS UNSIGNED)) AS nomor')
            ->whereYear('created_at', $year)
            ->first();

        $lastNomor = (int) ($lastRecord->nomor ?? 0);

        // nomor awal minimal 3000
        $nomor_sertif_baru = $lastNomor > 0
            ? max($lastNomor + 1, 1000)
            : 1000;

        // Get bnsp authorization from db
        $authData = DB::table('b_n_s_p_auths')->select('x_authorization')->first()->x_authorization;

        $spt_asesor = SPTAsesor::where("jadwal_id", $request->input("jadwal_id"))->first();
        $jadwal = Jadwal::where("jadwal_id", $request->input("jadwal_id"))->first();
        if ($spt_asesor == null) {
            return response()->json([
                'success' => false,
                'message' => 'Belum submit TTD Asesor'
            ], 400);
        }
        $asesor_mygatensi = AsesorBnsp::where("id", $jadwal->asesor_id)->first();
        $asesor2_mygatensi = AsesorBnsp::where("id", $jadwal->asesor2_id)->first();

        /* GET BLANKO */
        $url = 'https://konstruksi.bnsp.go.id/api/v1/jadwal/blanko?jadwal_id=' . trim($request->input('jadwal_id'));
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'x-authorization' => $authData
        ])->timeout(30)->connectTimeout(30)->get($url);

        $response_jadwal = $response->json();
        if ($response_jadwal['code'] == "ERR") {
            return response()->json([
                'success' => false,
                'message' => 'Error surat tugas: ID Jadwal tidak ditemukan'
            ], 404);
        } else if ($response_jadwal['message'] == "Unauthorized") {
            Log::error("BNSP authorization expired! Generate through manual through artisan");
            return response()->json([
                'success' => false,
                'message' => 'BNSP authorization expired'
            ], 500);
        }

        $peserta_blanko = $response_jadwal['data'];

        // Check jika blanko masih null
        foreach ($peserta_blanko as $blanko) {
            $temp_check = Jadwal::where(["jadwal_id" => trim($request->input('jadwal_id')), "nik" => $blanko['nik']])->first();
            if ($temp_check->kompeten == "BK") {
                continue;
            }

            if ($blanko['nomor_blanko'] == null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Blanko belum terbit'
                ], 400);
            }
        }

        // Get peserta yang kompeten dan masukkan dalam database list pencatatan
        $peserta = Jadwal::where(["jadwal_id" => trim($request->input('jadwal_id')), "kompeten" => "K"])->get();

        DB::beginTransaction();
        $curr_index = 0;
        foreach ($peserta as $id_izin) {
            $id_izin = $id_izin->id_izin;

            // check nomor sertif is already registered to database
            $temp_check_nomor_sertif = DataPencatatan::where("id_izin", $id_izin)->select("nomor_sertifikasi", "id_izin")->first();
            if ($temp_check_nomor_sertif) {
                // change value to used nomor sertif because it's deserved better girl ðŸ’…
                $nomor_sertifikat = $temp_check_nomor_sertif->nomor_sertifikasi;
                continue;
            }

            $nomor_sertifikat = $nomor_sertif_baru;
            $nomor_sertifikat = (int)$nomor_sertifikat + $curr_index;
            $nomor_sertifikat = str_pad($nomor_sertifikat, 8, '0', STR_PAD_LEFT);
            $curr_index += 1;


            // ambil no. blanko dan simpan dalam database
            $get_peserta_pencatatan = Personal::where("id_izin", $id_izin)->first();
            $nik_peserta = $get_peserta_pencatatan->nik;
            $nomor_blanko = '';
            foreach ($peserta_blanko as $blanko) {
                if ($nik_peserta == $blanko['nik']) {
                    $nomor_blanko = $blanko['nomor_blanko'];
                }
            }


            // ambil data dari personals (nama dan nik)
            try {
                $check = DataPencatatan::where("id_izin", $id_izin)->first();

                if (!$check) {
                    $personal = Personal::where("id_izin", $id_izin)->first();

                    $propinsi = Province::where("id_propinsi_dagri", $personal->propinsi)->first();

                    $kabupaten = City::where("kode_kabupaten_dagri", $personal->kabupaten)->first();

                    $klasifikasi_kualifikasi = KlasifikasiKualifikasi::where("id_izin", $id_izin)->first();

                    // $jabatan_kerja = MasterJabatanKerja::where("id_jabatan_kerja", $klasifikasi_kualifikasi->jabatan_kerja)->first();

                    // $kbli = str($jabatan_kerja->kbli); // 74321
                    // $kbji = str($jabatan_kerja->kbji); // 2142.08

                    $jabatan_kerja = DB::connection("mygatensi")->table("myjabatankerja")->where("id_jabatan_kerja", $klasifikasi_kualifikasi->jabatan_kerja)->first();
                    $jabatan_kerja_bnsp = DB::connection("mygatensi")->table("myjabatankerjabnsp")->where("KodeLPJK", $jabatan_kerja->id_jabatan_kerja)->first();
                    $kbli = str($jabatan_kerja_bnsp->KBLI); // 74321
                    $kbji = str($jabatan_kerja_bnsp->KBJI); // 2142.08

                    $buat_data = [
                        "nomor_sertifikasi" => $nomor_sertifikat,
                        "id_izin" => $id_izin,
                        "nik" => $personal->nik,
                        "nama" => $personal->nama,
                        "propinsi" => $propinsi->nama,
                        "id_propinsi" => $propinsi->id_propinsi_dagri,
                        "id_kabupaten" => $kabupaten->kode_kabupaten_dagri,
                        "kabupaten" => $kabupaten->nama_kabupaten_dagri,
                        "id_kualifikasi" => $jabatan_kerja->lsp_kualifikasi_id,
                        "kualifikasi" => $jabatan_kerja->kualifikasi,
                        "kualifikasi_en" => $jabatan_kerja->kualifikasi_en,
                        "id_klasifikasi" => $jabatan_kerja->lsp_id_klasifikasi,
                        "id_subklasifikasi" => $jabatan_kerja->lsp_sub_klasifikasi_id,
                        "subklasifikasi" => $jabatan_kerja->subklasifikasi,
                        "subklasifikasi_en" => $jabatan_kerja->subklasifikasi_en,
                        "id_jabatan_kerja" => $jabatan_kerja->id_jabatan_kerja,
                        "jabatan_kerja" => $jabatan_kerja->jabatan_kerja,
                        "jabatan_kerja_en" => $jabatan_kerja->work_position,
                        "jenjang" => $jabatan_kerja->jenjang_id,
                        "nomor_sertifikat_lengkap" => $kbli . " " . $kbji . " " . $jabatan_kerja->jenjang_id . " " . $nomor_sertifikat . ' ' . Carbon::now()->year,
                        "nomor_registrasi_lsp" => "F 2146 " .substr($nomor_sertifikat, -5) . " " . Carbon::now()->year,
                        "nomor_registrasi_lpjk" => "-",
                        "nomor_blangko_bnsp" => $nomor_blanko,
                        "tanggal_ditetapkan" => date('Y-m-d'),
                        "tanggal_masa_berlaku" => date('Y-m-d', strtotime('+5 years -1 day')),
                        "jenis_permohonan" => "-",
                        "qr" => "-",
                        "qr_signature" => "-",
                        "link_e_sertifikat" => "-",
                        "catatan" => "-",
                        "ketua_pelaksana" => "-",
                        "ttd_ketua_pelaksana" => "-",
                    ];
                    // masukkan data
                    $result = DataPencatatan::create($buat_data);
                } else {
                    $personal = Personal::where("id_izin", $id_izin)->first();

                    $propinsi = Province::where("id_propinsi_dagri", $personal->propinsi)->first();

                    $kabupaten = City::where("kode_kabupaten_dagri", $personal->kabupaten)->first();

                    $klasifikasi_kualifikasi = KlasifikasiKualifikasi::where("id_izin", $id_izin)->first();

                    $jabatan_kerja = DB::connection("mygatensi")->table("myjabatankerja")->where("id_jabatan_kerja", $klasifikasi_kualifikasi->jabatan_kerja)->first();
                    $jabatan_kerja_bnsp = DB::connection("mygatensi")->table("myjabatankerjabnsp")->where("KodeLPJK", $jabatan_kerja->id_jabatan_kerja)->first();
                    $kbli = str($jabatan_kerja_bnsp->KBLI); // 74321
                    $kbji = str($jabatan_kerja_bnsp->KBJI); // 2142.08

                    // masukkan data
                    $data_untuk_kirim = [
                        "nomor_sertifikasi" => $nomor_sertifikat,
                        "propinsi" => $propinsi->nama,
                        "id_propinsi" => $propinsi->id_propinsi_dagri,
                        "id_kabupaten" => $kabupaten->kode_kabupaten_dagri,
                        "kabupaten" => $kabupaten->nama_kabupaten_dagri,
                        "id_kualifikasi" => $jabatan_kerja->lsp_kualifikasi_id,
                        "kualifikasi" => $jabatan_kerja->kualifikasi,
                        "kualifikasi_en" => $jabatan_kerja->kualifikasi_en,
                        "id_klasifikasi" => $jabatan_kerja->lsp_id_klasifikasi,
                        "id_subklasifikasi" => $jabatan_kerja->lsp_sub_klasifikasi_id,
                        "subklasifikasi" => $jabatan_kerja->subklasifikasi,
                        "subklasifikasi_en" => $jabatan_kerja->subklasifikasi_en,
                        "id_jabatan_kerja" => $jabatan_kerja->id_jabatan_kerja,
                        "jabatan_kerja" => $jabatan_kerja->jabatan_kerja,
                        "jabatan_kerja_en" => $jabatan_kerja->work_position,
                        "nomor_blangko_bnsp" => $nomor_blanko,
                        "nomor_sertifikat_lengkap" => $kbli . " " . $kbji . " " . $jabatan_kerja->jenjang_id . " " . $nomor_sertifikat . ' ' . Carbon::now()->year,
                        "nomor_registrasi_lsp" => "F 2146 " . substr($nomor_sertifikat, -5) . " " . Carbon::now()->year,
                    ];
                    $result = DataPencatatan::where("id_izin", $id_izin)->update($data_untuk_kirim);
                }
            } catch (\Exception $e) {
                DB::rollBack();
                dd($e);
            }
        }
        DB::commit();
        /* END OF GET BLANKO */




        /* PENUGASAN ASESOR */

        // Get asesi kompeten berdasarkan jadwal_id
        $peserta_kompeten = Jadwal::where(["jadwal_id" => $request->input('jadwal_id'), "kompeten" => "K"])->get();

        // Get asesor, tuk, link spt asesor
        $id_asesor = $asesor_mygatensi->Noreg;
        $id_asesor_2 = null;
        if(isset($asesor2_mygatensi)) {
            $id_asesor_2 = $asesor2_mygatensi->Noreg;
        }

        $jadwal_db = Jadwal::where("jadwal_id", trim($request->input("jadwal_id")))->first();
        $id_tuk = $jadwal_db->tuk_id;

        $tuk = DB::connection("mygatensi")->table("mytukbnsp")->select(['ID', "Kode", "Nama", "Alamat"])->where("ID", $id_tuk)->first();
        $kode_tuk = '';
        $nama_tuk = '';

        if ($tuk) {
            $kode_tuk = $tuk->Kode;
            $nama_tuk = $tuk->Nama;
        }

        if ($kode_tuk == '' || $nama_tuk == '') {
            return response()->json([
                'success' => false,
                'message' => 'Error penugasan asesor: Tuk tidak ditemukan!'
            ], 404);
        }


        $link_asesor = SPTAsesor::where("jadwal_id", trim($request->input("jadwal_id")))->first();
        $no_surat_tugas = $link_asesor->spt_no;
        $link_asesor = $link_asesor->link;

        // upload rekom asesor
        // Generate spt terbaru
        $check_if_exist = RekomAsesor::where("jadwal_id", trim($request->input("jadwal_id")))->first();
        if ($check_if_exist) {
            $no_rekom_asesor = $check_if_exist->no_surat;
        } else {
            $no_rekom_asesor = RekomAsesor::latest()->first();
            $no_rekom_asesor = $no_rekom_asesor->no_surat;

            $monthsInRomanNumerals = [
                1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV',
                5 => 'V', 6 => 'VI', 7 => 'VII', 8 => 'VIII',
                9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII',
            ];

            $currentMonthNumber = Carbon::now()->month;
            $currentYearNumber = Carbon::now()->year;

            $bulan = $monthsInRomanNumerals[$currentMonthNumber];

            $no_rekom_asesor = explode("/", $no_rekom_asesor);

            if ((int)$no_rekom_asesor[3] == $currentYearNumber) {
                $no_rekom_asesor[0] = str((int)$no_rekom_asesor[0] + 1);
            } else {
                $no_rekom_asesor[0] = str(0);
            }

            $no_rekom_asesor[2] = $bulan;
            $no_rekom_asesor[3] = str($currentYearNumber);
            $no_rekom_asesor = implode("/", $no_rekom_asesor);
        }


        // Mulailah generate
        $authData = DB::table('b_n_s_p_auths')->select('x_authorization')->first()->x_authorization;

        // Get data jadwal_id from bnsp
        $url = 'https://konstruksi.bnsp.go.id/api/v1/jadwal/blanko?jadwal_id=' . $request->input("jadwal_id");
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'x-authorization' => $authData
        ])->timeout(30)->connectTimeout(30)->get($url);

        $response_jadwal = $response->json();
        if ($response_jadwal['code'] == "ERR") {
            return response()->json([
                'success' => false,
                'message' => 'Error surat tugas: ID Jadwal tidak ditemukan'
            ], 404);
        }
        $count_data = count($response_jadwal['data']);

        // Get data asesor from bnsp
        // $allAsesor = resource_path('json/myasesorbnspbaru.json');
        // $allAsesor = json_decode(file_get_contents($allAsesor), true)['myasesorbnsp'];


        // $found_asesor = "";
        $current_asesor = Jadwal::where("jadwal_id", $request->input("jadwal_id"))->first();
        $found_asesor = DB::connection("mygatensi")->table("myasesorbnsp")->where("ID", $current_asesor->asesor_id)->first();

        if ($found_asesor == null) {
            return response()->json([
                'success' => false,
                'message' => 'ID Asesor tidak ditemukan'
            ], 404);
        }

        $found_tuk = DB::connection("mygatensi")->table("mytukbnsp")->select(['ID', "Kode", "Nama", "Alamat"])->where("Kode", $response_jadwal['dataJadwal']['kode_tuk'])->first();

        if (!$found_tuk) {
            return response()->json([
                'success' => false,
                'message' => 'ID TUK tidak ditemukan'
            ], 404);
        }


        // Get kota untuk surat rekom asesor
        $get_tuk_kota = $found_tuk->Kode;
        $get_tuk_db = DB::connection("mygatensi")->table("mytukbnsp")->where("Kode", $get_tuk_kota)->first();
        $get_tuk_id = $get_tuk_db->ID;

        $url = 'https://konstruksi.bnsp.go.id/api/v1/tuk/' . (string)$get_tuk_id;

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'x-authorization' => $authData,
        ])->timeout(30)->connectTimeout(30)->get($url);

        $get_tuk_response = $response->json();
        $get_tuk_response = $get_tuk_response['data']['kota']['kota'];


        // Kumpul semua skema tiap asesi
        $nama_peserta = [];
        $all_detect_skema = [];
        foreach ($response_jadwal['data'] as $asesi) {
            $peserta = Jadwal::where("jadwal_id", $request->input("jadwal_id"))->where("nik", $asesi['nik'])->first();
            $allSkema = resource_path('json/skemabnsp.json');
            $allSkema = json_decode(file_get_contents($allSkema), true)['myskemabnsp'];

            $temp_skema_nama = '';
            foreach ($allSkema as $skema) {
                if ($skema['id'] == $asesi['skema_id']) {
                    $temp_skema_nama = $skema['nama'];
                    if (!in_array($temp_skema_nama, $all_detect_skema)) {
                        array_push($all_detect_skema, $temp_skema_nama);
                    }
                }
            }

            array_push($nama_peserta, ["nama" => $asesi['nama'], "skema" => $temp_skema_nama, "kota" => $peserta->kota_id, "kompeten" => $peserta->kompeten]);
        }


        $tanggal_surat_qr = \Carbon\Carbon::parse($response_jadwal['dataJadwal']['tanggal_mulai'])->setTimezone('Asia/Jakarta')->isoFormat('dddd, D MMMM YYYY');
        $html1 = '<img src="data:image/svg+xml;base64,' . base64_encode(QrCode::margin(0)->size(70)->generate("Jadwal ID : " . trim($request->input("jadwal_id")) . "\n Nama Asesor: " . $found_asesor->Nama . "\n Tanggal: " . $tanggal_surat_qr)) . '"  width="100" height="100" />';
        $result = [
            "no_surat" => $no_rekom_asesor,
            "nama_asesor" => $found_asesor->Nama . " " . $found_asesor->Noreg,
            "tuk" => $found_tuk->Nama,
            "tuk_alamat" => $found_tuk->Alamat,
            "nama_asesor_ttd" => $found_asesor->Nama,
            "tempat" => $get_tuk_response,
            "hari_tanggal" => \Carbon\Carbon::parse($response_jadwal['dataJadwal']['tanggal_mulai'])->setTimezone('Asia/Jakarta')->isoFormat('D MMMM YYYY'),
            "hari_tanggal_split" => explode(" ", \Carbon\Carbon::parse($response_jadwal['dataJadwal']['tanggal_mulai'])->setTimezone('Asia/Jakarta')->isoFormat('dddd D MMMM YYYY')),
            "jmlh_peserta" => $count_data,
            "nama_peserta" => $nama_peserta,
            "all_detect_skema" => implode(", ", $all_detect_skema),
            "svg" => $html1,
            "tanggal_surat" => \Carbon\Carbon::parse($response_jadwal['dataJadwal']['tanggal_mulai'])->subDay()->setTimezone('Asia/Jakarta')
        ];



        // Convert to html to pdf
        $pdf_rekom = PDF::loadView('template-surat.rekom_asesor', $result);
        $pdfContent_rekom = $pdf_rekom->output();
        $filename_rekom_asesor = str(trim($request->input("jadwal_id"))) . '.pdf';
        Storage::disk("sertif_ftp")->put("/baru/rekom_asesor/" . $filename_rekom_asesor, $pdfContent_rekom);

        $full_url_file_ra = 'https://lspgatensi.id/files/baru/rekom_asesor/' . $filename_rekom_asesor;

        RekomAsesor::updateOrCreate(["jadwal_id" => trim(str($request->jadwal_id))], [
            "jadwal_id" => trim(str($request->jadwal_id)),
            'link' => $full_url_file_ra,
            'no_surat' => $no_rekom_asesor,
            'inputby_id' => auth()->id(),
        ]);

        foreach ($peserta_kompeten as $peserta) {
            $asesi = Personal::select(['validpjby_id', 'validpj_time'])->where('id_izin', $peserta->id_izin)->first();
            $admin = User::where('id', $asesi->validpjby_id)->pluck('name')->first();
            $bukti = DB::table("bukti_asesmens")->where("id_izin", trim($peserta->id_izin))->first();
            $klas_kua = KlasifikasiKualifikasi::select('tuk', 'jenjang', 'metode_uji')->where('id_izin', $peserta->id_izin)->first();
            if (!$bukti) {
                return response()->json([
                    'success' => false,
                    'message' => $peserta->id_izin . " belum mengupload bukti asesmen"
                ], 400);
            }
            $tuk = DB::connection("mygatensi_2")->table("mytuk")->where("id", $klas_kua->tuk)->pluck('nama_tuk')->first();
            $verifikasi_tuk = DB::connection('tuk')->table('verifications')->select('link', 'verificator', 'created_at')->where('tuk', $tuk)->first();
            if (!isset($verifikasi_tuk)) {
                return response()->json([
                    'success' => false,
                    'message' => "File verifikasi TUK tidak ditemukan, silakan lakukan verifikasi di https://verif-tuk.lspgatensi.id"
                ], 404);
            }
            try {
                // Hit api bnsp
                // Fill the json
                $request_api_asesor = [
                    "id_asesor" => $id_asesor,
                    "id_asesor_2" => $id_asesor_2,
                    "rekomendasi" => "K",
                    "catatan" => "",
                    "tgl_surat_tugas" => \Carbon\Carbon::parse($response_jadwal['dataJadwal']['tanggal_mulai'])->subDay()->setTimezone('Asia/Jakarta')->isoFormat('YYYY-MM-DD'),
                    "no_surat_tugas" => $no_surat_tugas,
                    "kode_tuk" => $kode_tuk,
                    "nama_tuk" => $nama_tuk,
                    "tgl_uji" => \Carbon\Carbon::parse($response_jadwal['dataJadwal']['tanggal_mulai'])->setTimezone('Asia/Jakarta')->isoFormat('YYYY-MM-DD'),
                    "tgl_selesai_uji" => \Carbon\Carbon::parse($response_jadwal['dataJadwal']['tanggal_mulai'])->setTimezone('Asia/Jakarta')->isoFormat('YYYY-MM-DD'),
                    "metode_uji" => "observasi",
                    "uji_praktek_atau_observasi_lapangan" => "1",
                    "uji_tulis" => "1",
                    "uji_lisan" => "1",
                    "wawancara" => "0",
                    "penyelenggaraan_uji" => $klas_kua->metode_uji === 'onsite' ? '4' : '1',
                    "url_surat_tugas" => $link_asesor,
                    "url_surat_rekomendasi_akhir" => $bukti->url_ak02,
                    "url_apl01" => $bukti->url_apl_01,
                    "url_apl02" => $bukti->url_apl_02,
                    "url_dokumentasi_asesmen" => $bukti->url_docs_asesmen,

                    "url_form_uji_tulis" => $bukti->new_ia_05,
                    "tgl_pelaksaaan_form_uji_tulis" => \Carbon\Carbon::parse($response_jadwal['dataJadwal']['tanggal_mulai'])->setTimezone('Asia/Jakarta')->isoFormat('YYYY-MM-DD'),
                    "url_form_uji_lisan" => "",
                    "tgl_pelaksaaan_form_uji_lisan" => \Carbon\Carbon::parse($response_jadwal['dataJadwal']['tanggal_mulai'])->setTimezone('Asia/Jakarta')->isoFormat('YYYY-MM-DD'),
                    "url_form_wawancara" => "",
                    "tgl_pelaksaaan_form_wawancara" => "",
                    "tgl_verifikasi_apl01" => \Carbon\Carbon::parse($response_jadwal['dataJadwal']['tanggal_mulai'])->setTimezone('Asia/Jakarta')->subDay()->isoFormat('YYYY-MM-DD'),    // Dikurangi 1 hari untuk apl 1
                    "tgl_verifikasi_apl02" => \Carbon\Carbon::parse($response_jadwal['dataJadwal']['tanggal_mulai'])->setTimezone('Asia/Jakarta')->isoFormat('YYYY-MM-DD'),
                    "tgl_dokumentasi" => \Carbon\Carbon::parse($response_jadwal['dataJadwal']['tanggal_mulai'])->setTimezone('Asia/Jakarta')->isoFormat('YYYY-MM-DD'),

                    "url_form_uji_praktek_atau_observasi_lapangan" => "",
                    "tgl_pelaksaaan_form_uji_praktek_atau_observasi_lapangan" => \Carbon\Carbon::parse($response_jadwal['dataJadwal']['tanggal_mulai'])->setTimezone('Asia/Jakarta')->isoFormat('YYYY-MM-DD'),

                    // item baru
                    "nama_admin_lsp" => $admin,
                    "tgl_periksa_admin_lsp" => \Carbon\Carbon::parse($asesi->validpj_time)->setTimezone('Asia/Jakarta')->isoFormat('YYYY-MM-DD'),
                    "url_surat_verifikasi_tuk" => str_replace(" ", "%20", "https://lspgatensi.id/files/tuk/" . Carbon::parse($verifikasi_tuk->created_at)->toDateString() . '/' . $nama_tuk . "/" . $verifikasi_tuk->link),
                    "tgl_kegiatan_pra_asesmen" => \Carbon\Carbon::parse($response_jadwal['dataJadwal']['tanggal_mulai'])->setTimezone('Asia/Jakarta')->subDay()->isoFormat('YYYY-MM-DD'),
                    "url_absensi_kegiatan_asesmen" => $bukti->url_absensi_asesi_asesmen,
                    "url_absensi_kegiatan_pra_asesmen" => $bukti->url_absensi_asesi_pra,
                    "url_mapa01" => $bukti->url_mapa01,
                    "url_mapa02" => $bukti->url_mapa02,
                    "url_ak01" => $bukti->url_ak01,
                    "url_ak02" => $bukti->url_ak02,
                    "url_ak03" => $bukti->url_ak03,
                    "url_ak04" => $bukti->url_ak04,
                    "url_ak05" => $bukti->url_ak05,
                    "url_ak07" => $bukti->url_ak07,
                    "url_ia01" => $bukti->new_ia_01,
                    "url_ia02" => $bukti->url_ia_02,
                    "url_ia03" => $bukti->new_ia_03,
                    "url_ia04a" => $bukti->url_ia_04a,
                    "url_ia04b" => $bukti->url_ia_04b,
                    "url_ia05" => $bukti->new_ia_05,
                    "url_ia06" => $bukti->url_ia06,
                    "url_ia07" => $bukti->url_ia07,
                    "url_ia08" => $bukti->url_ia08,
                    "url_ia09" => $bukti->url_ia09,
                    "url_ia10" => $bukti->url_ia10,

                    "nama_ttd_surat_verifikasi_tuk" => $verifikasi_tuk->verificator,
                    "tgl_ttd_surat_verifikasi_tuk" => Carbon::parse($verifikasi_tuk->created_at)->toDateString(),
                    "url_absensi_asesor_kegiatan_pra_asesmen" => $bukti->url_absensi_asesor_pra,
                    "url_absensi_asesi_kegiatan_pra_asesmen" => $bukti->url_absensi_asesi_pra,
                    "url_absensi_asesor_kegiatan_asesmen" => $bukti->url_absensi_asesor_asesmen,
                    "url_absensi_asesi_kegiatan_asesmen" => $bukti->url_absensi_asesi_asesmen,
                ];

                if($klas_kua->jenjang < 4) {
                    $request_api_asesor['url_form_uji_praktek_atau_observasi_lapangan'] = $bukti->new_ia_01;
                    $request_api_asesor['url_form_uji_lisan'] = $bukti->new_ia_03;
                } else {
                    $request_api_asesor['url_form_uji_praktek_atau_observasi_lapangan'] = $bukti->url_ia_04a;
                    $request_api_asesor['url_form_uji_lisan'] = $bukti->url_paparan;
                    // $request_api_asesor['url_form_uji_lisan'] = $bukti->url_ia_04b;
                }

                $request_api_asesor['url_apl01'] = str_replace("\\", "%5C", $request_api_asesor['url_apl01']);
                $request_api_asesor['url_apl02'] = str_replace("\\", "%5C", $request_api_asesor['url_apl02']);

                $request_api_asesor['url_apl01'] = str_replace("`", "%60", $request_api_asesor['url_apl01']);
                $request_api_asesor['url_apl02'] = str_replace("`", "%60", $request_api_asesor['url_apl02']);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => "Belum upload bukti asessmen (Foto, IA 05 dan IA 03) " . $peserta->id_izin,
                    'error' => $e->getMessage()
                ], 400);
            }

            $url = 'https://siki.pu.go.id/siki-api/v2/asesor-lsp-penugasan/' . $peserta->id_izin;

            $headers = [
                'Content-Type' => 'application/json',
                'token' => 'f3332337ac671c33262198340c2f7b579f7843775ecc425107f086956cbb2b1a9e96b0cc6f643d24'
            ];

            $response_post_bnsp = Http::withHeaders($headers)->timeout(30)->connectTimeout(30)->post($url, $request_api_asesor);
            if ($response_post_bnsp->successful()) {
                $responseData = $response_post_bnsp->json();
                if ($responseData['status'] == "success") {
                    Log::info("berhasil penugasan komtek " . str($responseData['message']));
                    usleep(500);
                    continue;
                } else if ($responseData['status'] == "errors") {
                    return response()->json([
                        'success' => false,
                        'message' => "Terjadi kesalahan post pencatatan siki, " . $responseData['message']
                    ], 400);
                }
            } else {
                // dd($request_api_asesor);
                if (data_get($response_post_bnsp->json(), 'message') === "tidak dapat post data penugasan asesor karena sudah penugasan komtek") {
                    continue;
                }
                
                $json = $response_post_bnsp->json();

                $errors = collect($json)
                    ->flatten()
                    ->filter()
                    ->implode(' | ');

                $errorBnsp = $errors ?: 'Terjadi kesalahan pada layanan BNSP';

                logger()->error('BNSP ERROR', [
                    'status' => $response_post_bnsp->status(),
                    'response' => $json,
                    'id_izin' => $peserta->id_izin,
                ]);


                // dd([
                //     'status_code' => $response_post_bnsp->status(),
                //     'headers'     => $response_post_bnsp->headers(),
                //     'body_raw'    => $response_post_bnsp->body(),
                //     'json'        => $response_post_bnsp->json(),
                // ]);

                return response()->json([
                    'success' => false,
                    'message' => $errorBnsp . '. (' . $peserta->id_izin . ') . Status error:' . $response_post_bnsp->status()
                ], $response_post_bnsp->status());
            }
        }

        /* END OF PENUGASAN ASESOR */

        /* PENUGASAN KOMTEK */
        // $nama_komtek = $request->input("nama_ketua_komtek");
        // $no_spt_komtek = $request->input("no_spt_komtek");
        // upload spt komtek
        // Get BA Komtek

        $nama_komtek = Jadwal::select(['komtek1', 'komtek2', 'komtek3'])->where("jadwal_id", trim($request->input("jadwal_id")))->first();
        $nama_komtek1 = DB::connection("mygatensi")->table("mypersonillsp")->where("Nomor", $nama_komtek->komtek1)->first()->Nama;
        $nama_komtek2 = DB::connection("mygatensi")->table("mypersonillsp")->where("Nomor", $nama_komtek->komtek2)->first()->Nama;
        $nama_komtek3 = DB::connection("mygatensi")->table("mypersonillsp")->where("Nomor", $nama_komtek->komtek3)->first()->Nama;

        // Generate spt terbaru
        $check_if_exist = SPTKomtek::where("jadwal_id", trim($request->input("jadwal_id")))->first();
        if ($check_if_exist) {
            $spt_no = $check_if_exist->no_surat;
        } else {
            $no_spt_komtek = SPTKomtek::latest()->first();
            $spt_no = $no_spt_komtek->no_surat;

            $monthsInRomanNumerals = [
                1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV',
                5 => 'V', 6 => 'VI', 7 => 'VII', 8 => 'VIII',
                9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII',
            ];

            $currentMonthNumber = Carbon::now()->month;
            $currentYearNumber = Carbon::now()->year;

            $bulan = $monthsInRomanNumerals[$currentMonthNumber];

            $spt_no = explode("/", $spt_no);

            if ((int)$spt_no[3] == $currentYearNumber) {
                $spt_no[0] = str((int)$spt_no[0] + 1);
            } else {
                $spt_no[0] = str(0);
            }

            $spt_no[2] = $bulan;
            $spt_no[3] = str($currentYearNumber);
            $spt_no = implode("/", $spt_no);
        }

        // Mulailah generate
        $authData = DB::table('b_n_s_p_auths')->select('x_authorization')->first()->x_authorization;

        // Get data jadwal_id from bnsp
        $url = 'https://konstruksi.bnsp.go.id/api/v1/jadwal/blanko?jadwal_id=' . $request->input("jadwal_id");
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'x-authorization' => $authData
        ])->timeout(30)->connectTimeout(30)->get($url);

        $response_jadwal = $response->json();
        if ($response_jadwal['code'] == "ERR") {
            return response()->json([
                'success' => false,
                'message' => 'Error surat tugas: ID Jadwal tidak ditemukan'
            ], 404);
        }
        $count_data = count($response_jadwal['data']);

        $found_tuk = DB::connection("mygatensi")->table("mytukbnsp")->select(['ID', "Kode", "Nama", "Alamat"])->where("Kode", $response_jadwal['dataJadwal']['kode_tuk'])->first();
        if (!$found_tuk) {
            return response()->json([
                'success' => false,
                'message' => 'ID TUK tidak ditemukan'
            ], 404);
        }

        // Kumpul semua skema tiap asesi
        $kumpul_skema = [];
        $nama_peserta = [];
        foreach ($response_jadwal['data'] as $asesi) {
            array_push($nama_peserta, $asesi['nama']);

            if (!in_array($asesi['skema_id'], $kumpul_skema)) {
                array_push($kumpul_skema, $asesi['skema_id']);
            }
        }

        $nama_skema = [];
        $allSkema = resource_path('json/skemabnsp.json');
        $allSkema = json_decode(file_get_contents($allSkema), true)['myskemabnsp'];
        for ($i = 0; $i < count($kumpul_skema); $i++) {
            foreach ($allSkema as $skema) {
                if ($skema['id'] == $kumpul_skema[$i]) {
                    array_push($nama_skema, $skema['nama']);
                }
            }
        }

        $barcode1 = (string) Uuid::uuid4();
        $tanggal_surat_qr = \Carbon\Carbon::parse($response_jadwal['dataJadwal']['tanggal_mulai'])->subDay()->setTimezone('Asia/Jakarta')->isoFormat('dddd, D MMMM YYYY');
        $html = '<img src="data:image/svg+xml;base64,' . base64_encode(QrCode::margin(0)->size(70)->generate("https://barcode.lspgatensi.id/$barcode1")) . '"  width="100" height="100" />';
        $result = [
            "no_surat" => $spt_no,
            "nama_komtek" => $nama_komtek1,
            "nama_komtek2" => $nama_komtek2,
            "nama_komtek3" => $nama_komtek3,
            "nama_tuk" => $response_jadwal['dataJadwal']['nama_tuk'],
            "alamat_tuk" => $found_tuk->Alamat,
            "hari_tanggal" => \Carbon\Carbon::parse($response_jadwal['dataJadwal']['tanggal_mulai'])->setTimezone('Asia/Jakarta')->isoFormat('dddd, D MMMM YYYY'),
            "jmlh_peserta" => $count_data,
            "nama_skema" => join(", ", $nama_skema),
            "nama_peserta" => $nama_peserta,
            "qr" => QrCode::margin(0)->size(70)->generate("https://barcode.lspgatensi.id/$barcode1"),
            "svg" => $html,
            "tanggal_surat" => \Carbon\Carbon::parse($response_jadwal['dataJadwal']['tanggal_mulai'])->subDay()->setTimezone('Asia/Jakarta')
        ];

        Barcode::create([
            'nama' => 'Radinal Efendy, S.T.',
            'id_izin' => '2220910001',
            'jabatan' => 'Direktur LSP',
            'url' => 'https://barcode.lspgatensi.id/' . $barcode1,
            'created_at' => \Carbon\Carbon::parse($response_jadwal['dataJadwal']['tanggal_mulai'])->subDay()->setTimezone('Asia/Jakarta')
        ]);

        // Convert to html to pdf
        $pdf = PDF::loadView('template-surat.spt_komtek', $result);
        $pdfContent_sptkomtek = $pdf->output();
        $filename_spt_komtek = str(trim($request->input("jadwal_id"))) . '.pdf';
        Storage::disk("sertif_ftp")->put("/baru/spt_komtek/" . $filename_spt_komtek, $pdfContent_sptkomtek);
        $full_url_file_sptkomtek = 'https://lspgatensi.id/files/baru/spt_komtek/' . $filename_spt_komtek;

        SPTKomtek::updateOrCreate(["jadwal_id" => trim(str($request->jadwal_id))], [
            "jadwal_id" => trim(str($request->jadwal_id)),
            'link' => $full_url_file_sptkomtek,
            'no_surat' => $spt_no,
            'inputby_id' => auth()->id(),
        ]);


        // get url ba komtek
        $url_ba_komtek = BaKomtek::where("jadwal_id", trim($request->input("jadwal_id")))->first();
        $url_ba_komtek = $url_ba_komtek->link;

        // Absen Komtek
        $barcode_komtek1 = (string) Uuid::uuid4();
        $barcode_komtek2 = (string) Uuid::uuid4();
        $barcode_komtek3 = (string) Uuid::uuid4();
        $temp_url_absen_komtek = 'https://moon.lspgatensi.id/files/surat/absen_komtek.pdf';
        $file_absen_komtek = $this->generateAbsen($temp_url_absen_komtek, $request->input("jadwal_id"), str(trim($request->input("jadwal_id"))), $barcode_komtek1, $barcode_komtek2, $barcode_komtek3, $nama_komtek1, $nama_komtek2, $nama_komtek3);
        Storage::disk("str_moon")->put("/dok_asesmen/absen_komtek/" . str(trim($request->input("jadwal_id"))) . '.pdf', $file_absen_komtek);
        $url_absen_komtek = AbsenKomtek::updateOrCreate(["jadwal_id" => trim(str($request->jadwal_id))], [
            'jadwal_id' => trim($request->input("jadwal_id")),
            'no_surat' => $spt_no,
            'link' => 'https://moon.lspgatensi.id/files/dok_asesmen/absen_komtek/' . str(trim($request->input("jadwal_id"))) . '.pdf'
        ]);
        DB::beginTransaction();
        try {
            Barcode::create([
                'nama' => $nama_komtek1,
                'id_izin' => str(trim($request->input("jadwal_id"))),
                'jabatan' => 'Komite Teknis',
                'tahap' => 'asesmen',
                'url' => 'https://barcode.lspgatensi.id/' . $barcode_komtek1
            ]);
            Barcode::create([
                'nama' => $nama_komtek2,
                'id_izin' => str(trim($request->input("jadwal_id"))),
                'jabatan' => 'Komite Teknis',
                'tahap' => 'asesmen',
                'url' => 'https://barcode.lspgatensi.id/' . $barcode_komtek2
            ]);
            Barcode::create([
                'nama' => $nama_komtek3,
                'id_izin' => str(trim($request->input("jadwal_id"))),
                'jabatan' => 'Komite Teknis',
                'tahap' => 'asesmen',
                'url' => 'https://barcode.lspgatensi.id/' . $barcode_komtek3
            ]);
            DB::commit();
        } catch (\Exception $e) {
            dd($e->getMessage());
            // return redirect()->back()->with('error', 'Error storing data.');
        }

        $full_url_file_sptkomtek = 'https://lspgatensi.id/files/baru/spt_komtek/' . $filename_spt_komtek;
        $komtekMapping = [
            '0010' => 'MET.000.003411 2025',
            '0011' => 'MET.000.003408 2025',
            '0051' => 'MET.000.003409 2025',
            '0052' => 'MET.000.006828 2021',
            '0053' => 'MET.000.004966 2021',
            '0055' => 'MET.000.003410 2025',
        ];
        $met1 = DB::connection('mygatensi')->table('myasesorbnsp')->where('Nama', 'LIKE', '%' . $nama_komtek1 . '%')->pluck('Noreg')->first();
        if (!isset($met1)) {
            $met1 = $komtekMapping[$nama_komtek->komtek1] ?? null;
        }
        $met2 = DB::connection('mygatensi')->table('myasesorbnsp')->where('Nama', 'LIKE', '%' . $nama_komtek2 . '%')->pluck('Noreg')->first();
        if (!isset($met2)) {
            $met2 = $komtekMapping[$nama_komtek->komtek2] ?? null;
        }
        $met3 = DB::connection('mygatensi')->table('myasesorbnsp')->where('Nama', 'LIKE', '%' . $nama_komtek3 . '%')->pluck('Noreg')->first();
        if (!isset($met3)) {
            $met3 = $komtekMapping[$nama_komtek->komtek3] ?? null;
        }

        $request_api_komtek = [
            "nama_komite_teknis" => $nama_komtek1,
            "jabatan_komite_teknis" => "Ketua",
            "hasil_penetapan" => "K",
            "catatan" => "",
            "tgl_surat_tugas" => \Carbon\Carbon::parse($response_jadwal['dataJadwal']['tanggal_mulai'])->subDay()->setTimezone('Asia/Jakarta')->isoFormat('YYYY-MM-DD'),
            "no_surat_tugas" => $spt_no,
            "tgl_penetapan" => \Carbon\Carbon::parse($response_jadwal['dataJadwal']['tanggal_mulai'])->setTimezone('Asia/Jakarta')->isoFormat('YYYY-MM-DD'),
            "url_surat_tugas" => $full_url_file_sptkomtek,
            "url_ba_penetapan" => $url_ba_komtek,
            "met_komtek_1" => $met1,
            "met_komtek_2" => $met2,
            "met_komtek_3" => $met3,
            "url_absensi_tim_komtek" => $url_absen_komtek->link
        ];

        foreach ($peserta_kompeten as $peserta) {
            // Hit api bnsp
            $url = 'https://siki.pu.go.id/siki-api/v1/komtek-lsp-penugasan/' . $peserta->id_izin;

            $headers = [
                'Content-Type' => 'application/json',
                'token' => 'f3332337ac671c33262198340c2f7b579f7843775ecc425107f086956cbb2b1a9e96b0cc6f643d24'
            ];

            $response_post_bnsp = Http::withHeaders($headers)->timeout(30)->connectTimeout(30)->post($url, $request_api_komtek);
            if ($response_post_bnsp->successful()) {
                $responseData = $response_post_bnsp->json();
                if ($responseData['status'] == "success") {
                    Log::info("berhasil penugasan komtek");
                    usleep(1000);
                    continue;
                } else if ($responseData['status'] == "errors") {
                    return response()->json([
                        'success' => false,
                        'message' => "Terjadi kesalahan post pencatatan siki, " . $responseData['message']
                    ], 400);
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => "Terjadi kesalahan post pencatatan siki, " . json_encode($responseData)
                    ], 400);
                }
            } else {
                if ($response_post_bnsp->json()['message'] == "tidak dapat post penugasan komite karena sudah generate qrcode") {
                    usleep(1000);
                    continue;
                }
                error_log($response_post_bnsp->json()['message']);
                return response()->json([
                    'success' => false,
                    'message' => $response_post_bnsp->json()['message']
                ], 400);
            }
        }
        /* END OF PENUGASAN KOMTEK */


        /* PENCATATAN */
        $peserta_kompeten = Jadwal::where(["jadwal_id" => $request->input('jadwal_id'), "kompeten" => "K"])->get();

        foreach ($peserta_kompeten as $peserta) {
            $id_izin = DataPencatatan::where("id_izin", $peserta->id_izin)->first();

            // Hit api bnsp
            $url = 'https://siki.pu.go.id/siki-api/v1/pencatatan-skk/' . $peserta->id_izin;

            $headers = [
                'Content-Type' => 'application/json',
                'token' => 'f3332337ac671c33262198340c2f7b579f7843775ecc425107f086956cbb2b1a9e96b0cc6f643d24'
            ];

            $request_api_asesor = array(
                "nomor_registrasi_lsp" => $id_izin->nomor_registrasi_lsp,
                "nomor_sertifikasi_lsp" => $id_izin->nomor_sertifikat_lengkap,
                "nomor_blangko_bnsp" => $id_izin->nomor_blangko_bnsp
            );

            DB::beginTransaction();
            $response_post_bnsp = Http::withHeaders($headers)->timeout(30)->connectTimeout(30)->post($url, $request_api_asesor);
            if ($response_post_bnsp->successful()) {
                $responseData = $response_post_bnsp->json();
                if ($responseData['status'] == "success") {
                    DataPencatatan::where("id_izin", $peserta->id_izin)->update([
                        'nomor_registrasi_lpjk' => $responseData['nomor_registrasi'],
                        'nomor_registrasi_lsp' => $responseData['nomor_registrasi'],
                        'qr' => $responseData['qr']
                    ]);
                    DB::commit();
                    usleep(500);
                    continue;
                } else if ($responseData['status'] == "errors") {
                    DB::rollBack();
                    return redirect('/penerbitan-sertifikat')->with("error", "Terjadi kesalahan post pencatatan siki, " . str($responseData['message']));
                } else {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => "Terjadi kesalahan post pencatatan siki, " . json_encode($responseData)
                    ], 400);
                }
            } else {
                if ($response_post_bnsp->json()['message'] == "tidak dapat post data penugasan asesor karena sudah penugasan komtek") {
                    continue;
                }
                DB::rollBack();
                error_log("($peserta->nama)" . $response_post_bnsp['message']);
                return response()->json([
                    'success' => false,
                    'message' => "($peserta->nama) " . $response_post_bnsp['message']
                ], 400);
            }
        }
        /* END OF PENCATATAN */



        /* GENERATE SERTIFIKAT */
        $peserta_kompeten = Jadwal::where(["jadwal_id" => $request->input('jadwal_id'), "kompeten" => "K"])->get();
        foreach ($peserta_kompeten as $peserta) {
            $semua_data = DataPencatatan::where("id_izin", $peserta->id_izin)->get();

            DB::beginTransaction();
            try {
                foreach ($semua_data as $dataPencatatan) {
                    if ($dataPencatatan->jenjang == 1) {
                        $jenjang_en = "One";
                    } else if ($dataPencatatan->jenjang == 2) {
                        $jenjang_en = "Two";
                    } else if ($dataPencatatan->jenjang == 3) {
                        $jenjang_en = "Three";
                    } else if ($dataPencatatan->jenjang == 4) {
                        $jenjang_en = "Four";
                    } else if ($dataPencatatan->jenjang == 5) {
                        $jenjang_en = "Five";
                    } else if ($dataPencatatan->jenjang == 6) {
                        $jenjang_en = "Six";
                    } else if ($dataPencatatan->jenjang == 7) {
                        $jenjang_en = "Seven";
                    } else if ($dataPencatatan->jenjang == 8) {
                        $jenjang_en = "Eight";
                    } else if ($dataPencatatan->jenjang == 9) {
                        $jenjang_en = "Nine";
                    }

                    $pas_foto = Personal::select("pas_foto")->where("id_izin", $dataPencatatan->id_izin)->first();

                    setlocale(LC_TIME, 'id_ID');
                    Carbon::setLocale('id');

                    $tgl_ditetapkan =  $dataPencatatan->tanggal_ditetapkan;
                    $tanggal_ditetapkan = Carbon::parse($tgl_ditetapkan)->isoFormat('D MMMM Y');

                    setlocale(LC_TIME, 'en_EN');
                    Carbon::setLocale('en');

                    $tgl_ditetapkan_en =  $dataPencatatan->tanggal_ditetapkan;
                    $tanggal_ditetapkan_en = Carbon::parse($tgl_ditetapkan_en)->isoFormat('MMMM, D Y');

                    $uid = Uuid::uuid4();

                    $result = [
                        "nama" => $dataPencatatan->nama,
                        "kualifikasi" => $dataPencatatan->kualifikasi,
                        "kualifikasi_en" => $dataPencatatan->kualifikasi_en,
                        "subklasifikasi" => $dataPencatatan->subklasifikasi,
                        "subklasifikasi_en" => $dataPencatatan->subklasifikasi_en,
                        "jabatan_kerja" => $dataPencatatan->jabatan_kerja,
                        "jabatan_kerja_en" => $dataPencatatan->jabatan_kerja_en,
                        "jenjang" => $dataPencatatan->jenjang,
                        "jenjang_en" => $jenjang_en,
                        "pas_foto" => $pas_foto->pas_foto,
                        "nomor_registrasi_lpjk" => $dataPencatatan->nomor_registrasi_lpjk,
                        "nomor_sertifikat_lengkap" => $dataPencatatan->nomor_sertifikat_lengkap,
                        "nomor_registrasi_lsp" => $dataPencatatan->nomor_registrasi_lsp,
                        "nomor_blangko_bnsp" => $dataPencatatan->nomor_blangko_bnsp,
                        "tanggal_ditetapkan" => $tanggal_ditetapkan,
                        "tanggal_ditetapkan_en" => $tanggal_ditetapkan_en,
                        "qr" => $dataPencatatan->qr,
                        "qr_ketua" => QrCode::margin(0)->size(70)->generate("https://reguler.lspgatensi.id/validasi-signature/" .  $uid)
                    ];

                    // Export to html and save to lspgatensi.id/files/reguler/sertifikat/
                    // $nama_file = (string) Uuid::uuid4();
                    $nama_file = strtolower($dataPencatatan->nomor_blangko_bnsp);
                    $html = view('certificate.sertifikat', $result)->render();
                    Storage::disk("sertif_ftp")->put("/reguler/sertifikat/" . $nama_file . ".html", $html);

                    DataPencatatan::where("nomor_blangko_bnsp", $dataPencatatan->nomor_blangko_bnsp)->update([
                        "link_e_sertifikat" => "https://lspgatensi.id/files/reguler/sertifikat/" . $nama_file . ".html",
                        "qr_signature" => $uid,
                    ]);
                }
            } catch (\Exception $e) {
                DB::rollBack();
                dd("terjadi error: " . $e->getMessage());
            }
            DB::commit();
        }
        Log::info("sertifikat berhasil tercetak");
        /* END OF GENERATE SERTIFIKAT */




        /* SUBMIT HASIL UJI 2 */
        $peserta_kompeten = Jadwal::where(["jadwal_id" => $request->input('jadwal_id'), "kompeten" => "K"])->get();

        foreach ($peserta_kompeten as $peserta) {
            $pencatatan_peserta = DataPencatatan::where("id_izin", $peserta->id_izin)->first();
            $klas_kua = KlasifikasiKualifikasi::select(['jabatan_kerja'])->where("id_izin", $peserta->id_izin)->first();
            $url = 'https://konstruksi.bnsp.go.id/api/v1/skema?kode_lpjk=' . $klas_kua->jabatan_kerja;
            $authData = DB::table('b_n_s_p_auths')->select('x_authorization')->first()->x_authorization;

            $response = rescue(function () use ($authData, $url) {
                return Http::withHeaders([
                    'Content-Type' => 'application/json',
                    'x-authorization' => $authData
                ])->timeout(30)->connectTimeout(30)->get($url);
            }, function ($e) {
                return $e->response;
            });

            $response_data = $response->json();
            if ($response_data['code'] != "ERR") {
                $id_skema_bnsp = (int)$response_data['data']['id'];
            } else {
                if ($klas_kua->jabatan_kerja == "SI101015") {
                    $id_skema_bnsp = 63566;
                } else {
                    return response()->json([
                        "success" => false,
                        "message" => 'Error skema bnsp: id skema tidak ditemukan'
                    ], 400);
                }
            }

            $request_api_hasil2 = array(
                'jadwal_id' => trim($request->input("jadwal_id")),
                'nik_peserta' => $pencatatan_peserta->nik,
                'skema_id' => $id_skema_bnsp,
                'nomor_sertifikat' => $pencatatan_peserta->nomor_sertifikat_lengkap,
                'nomor_reg' => $pencatatan_peserta->nomor_registrasi_lsp,
                'nomor_reg_lpjk' => $pencatatan_peserta->nomor_registrasi_lpjk,
                'link_sertifikat' => $pencatatan_peserta->link_e_sertifikat,
                'tgl_srtf' => $pencatatan_peserta->tanggal_ditetapkan,
                'tgl_srtf_end' => $pencatatan_peserta->tanggal_masa_berlaku
            );

            // Hit api bnsp
            $url = 'https://konstruksi.bnsp.go.id/api/v1/jadwal/peserta/sertifikat';

            $headers = [
                'Content-Type' => 'application/json',
                'x-authorization' => $authData
            ];

            $response_post_bnsp = Http::withHeaders($headers)->timeout(30)->connectTimeout(30)->post($url, $request_api_hasil2);
            if ($response_post_bnsp->successful()) {
                $responseData = $response_post_bnsp->json();
                if ($responseData['code'] == "OK") {
                    usleep(500);
                    continue;
                } else if ($responseData['message'] == "Unauthorized") {
                    return response()->json([
                        'success' => false,
                        'message' => "Terjadi kesalahan post pencatatan siki, " . $responseData['message']
                    ], 401);
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => "Terjadi kesalahan post pencatatan siki, " . $responseData['message']
                    ], 400);
                }
            } else {
                error_log($response_post_bnsp->json()['message']);
                return response()->json([
                    'success' => false,
                    'message' => $response_post_bnsp->json()['message']
                ], 400);
            }
        }
        /* END OF SUBMIT HASIL UJI 2 */


        return response()->json([
            'success' => true,
            'message' => "Berhasil menyelesaikan penerbitan sertifikat untuk jadwal: " . $request->input("jadwal_id"),
            'data' => [
                'jadwal_id' => $request->input("jadwal_id")
            ]
        ], 200);
    }

    private function generateAbsen($pdfUrl, $idJadwal, $filename, $barcode1, $barcode2, $barcode3, $komtek1, $komtek2, $komtek3)
    {
        $pdfContents = file_get_contents($pdfUrl);

        if (!$pdfContents) {
            throw new \Exception("Failed to fetch the PDF from the URL");
        }

        // Store the file in a temporary location
        $tempPdfPath = tempnam(sys_get_temp_dir(), 'pdf');
        file_put_contents($tempPdfPath, $pdfContents);

        $outputPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $filename . '.pdf';

        $pdf2 = new PdfTk($tempPdfPath);

        $jadwal = JadwalBNSP::where('jadwal_id', $idJadwal)->select(['asesor_id', 'tuk_id', 'id_izin'])->first();
        $klas_kua = KlasifikasiKualifikasi::select(['jabatan_kerja'])->where('id_izin', $jadwal->id_izin)->first();
        $klasifikasi = DB::connection('mygatensi')->table('myjabatankerja')->select(['subklasifikasi', 'kualifikasi'])->where('id_jabatan_kerja', $klas_kua->jabatan_kerja)->first();
        $tuk = DB::connection('mygatensi')->table('mytukbnsp')->select(['Nama', 'IDJenis'])->where('id', $jadwal->tuk_id)->first();
        $asesi = Personal::select(['tanggal_uji'])->where('id_izin', $jadwal->id_izin)->first();

        $result = $pdf2->fillForm([
            'komtek1' => $komtek1,
            'komtek2' => $komtek2,
            'komtek3' => $komtek3,
            'alamat1' => 'Graha Gapensi, Jl. Raya Ragunan No.4, Jakarta Selatan',
            'alamat2' => 'Graha Gapensi, Jl. Raya Ragunan No.4, Jakarta Selatan',
            'alamat3' => 'Graha Gapensi, Jl. Raya Ragunan No.4, Jakarta Selatan',
            'tuk' => $tuk->Nama,
            'tanggal' => Carbon::parse($asesi->tanggal_uji)->format('Y-m-d'),
            'subklasifikasi' => $klasifikasi->subklasifikasi,
            'kualifikasi' => $klasifikasi->kualifikasi
        ])->saveAs($outputPath);

        if ($result === false) {
            $error = $pdf2->getError();
            unlink($tempPdfPath);
            throw new \Exception("PDF save error: $error");
        }
        unlink($tempPdfPath);

        $qrData1 = "https://barcode.lspgatensi.id/" . $barcode1;
        $qrData2 = "https://barcode.lspgatensi.id/" . $barcode2;
        $qrData3 = "https://barcode.lspgatensi.id/" . $barcode3;
        $encodedQrData1 = base64_encode($qrData1);
        $encodedQrData2 = base64_encode($qrData2);
        $encodedQrData3 = base64_encode($qrData3);

        $outputPdfPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $filename . '_final.pdf';
        $command = escapeshellcmd("python3 " . app_path() . "/Http/Controllers/python/absen.py " . escapeshellarg($outputPath) . " " . escapeshellarg($outputPdfPath) . " " . escapeshellarg($encodedQrData1) . " " . escapeshellarg($encodedQrData2) . " " . escapeshellarg($encodedQrData3));
        $pythonResult = shell_exec($command);

        if (!file_exists($outputPdfPath)) {
            throw new \Exception("$pythonResult");
        }

        $finishedContents = file_get_contents($outputPdfPath);

        $finishedPdfPath = tempnam(sys_get_temp_dir(), 'pdf');
        file_put_contents($finishedPdfPath, $finishedContents);

        $finalPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $filename . '.pdf';

        $pdf = new PdfTk($finishedPdfPath);

        $result = $pdf->flatten()->saveAs($finalPath);

        // Read the final file content
        $fileContents = file_get_contents($finalPath);

        // Clean up temporary files
        foreach ([$outputPath, $outputPdfPath, $finishedPdfPath] as $tmpFile) {
            if (file_exists($tmpFile)) {
                unlink($tmpFile);
            }
        }

        // unlink($outputPath);
        // unlink($outputPdfPath);
        // unlink($finishedPdfPath);

        // unlink($finalPath);

        return $fileContents;
    }
}
