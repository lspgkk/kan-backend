<?php

namespace App\Http\Controllers;

use App\Models\AsesiJadwal;
use App\Models\AsesorBnsp;
use App\Models\JabatanKerja;
use App\Models\Jadwal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Database\QueryException;
use App\Models\KlasifikasiKualifikasi;
use App\Models\MyTuk;
use App\Models\MyTukBnsp;
use App\Models\Pendidikan;
use App\Models\Personal;
use App\Models\User;
use Illuminate\Support\Facades\Http;

class JadwalController extends Controller
{
    public function index(Request $request)
    {
        $query = Jadwal::select(['id', 'jadwal_id', 'nama_kegiatan', 'tanggal_uji', 'status', 'jenis_kelas'])->whereIn('status', ['sinkronisasi-bnsp', 'draft']);

        // Search filter (by role name, you can add more fields if needed)
        if ($search = trim($request->input('search'))) {
            $query->where('nama_kegiatan', 'like', "%{$search}%");
        }

        // Get per_page from request, default to 20
        $perPage = min((int) $request->input('per_page', 20), 100);

        // Paginate the result
        $jadwals = $query->orderBy('tanggal_uji', 'desc')->paginate($perPage);

        return response()->json([
            'message' => 'Jadwal retrieved successfully',
            'data' => $jadwals
        ]);
    }

    public function blankos(Request $request)
    {
        $query = Jadwal::select(['id', 'jadwal_id', 'nama_kegiatan', 'tanggal_uji', 'status', 'jenis_kelas'])->whereIn('status', ['buat-blanko']);

        // Search filter (by role name, you can add more fields if needed)
        if ($search = trim($request->input('search'))) {
            $query->where('nama_kegiatan', 'like', "%{$search}%");
        }

        // Get per_page from request, default to 20
        $perPage = min((int) $request->input('per_page', 20), 100);

        // Paginate the result
        $jadwals = $query->orderBy('tanggal_uji', 'desc')->paginate($perPage);

        return response()->json([
            'message' => 'Jadwal retrieved successfully',
            'data' => $jadwals
        ]);
    }

    public function sertifikasi(Request $request)
    {
        $query = Jadwal::select(['id', 'jadwal_id', 'nama_kegiatan', 'tanggal_uji', 'status', 'jenis_kelas'])->whereIn('status', ['get-blanko', 'jadwal-selesai']);

        // Search filter (by role name, you can add more fields if needed)
        if ($search = trim($request->input('search'))) {
            $query->where('nama_kegiatan', 'like', "%{$search}%");
        }

        // Get per_page from request, default to 20
        $perPage = min((int) $request->input('per_page', 20), 100);

        // Paginate the result
        $jadwals = $query->orderBy('tanggal_uji', 'desc')->paginate($perPage);

        return response()->json([
            'message' => 'Jadwal retrieved successfully',
            'data' => $jadwals
        ]);
    }

    public function show($id)
    {
        $jadwal = Jadwal::where('id', $id)->first();

        return response()->json([
            'message' => 'Jadwal retrieved successfully',
            'data' => $jadwal
        ]);
    }
    
    public function store(Request $request)
    {
        // 1️⃣ Validation (API-style)
        try {
            $validated = $request->validate([
                "nama_jadwal" => ['required'],
                "tuk" => ['required'],
                "subklasifikasi" => ['required'],
                "kualifikasi" => ['required'],
                "asesor1" => ['required'],
                "asesor2" => ['nullable'],
                "tanggal_mulai" => ['required'],
                "tanggal_selesai" => ['required'],
                "sesi" => ['required'],
                "jenis_kelas" => ['required'],
                "jenis_anggaran" => ['required'],
                "komtek1" => ['required'],
                "komtek2" => ['required'],
                "komtek3" => ['required'],
                "ketua_lsp" => ['required'],
                "manajer_sertifikasi" => ['required'],
                "admin_tuk" => ['required'],
                "all_id_izin" => ['required', 'array', 'min:1'],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $e->errors(),
            ], 422);
        }

        // 2️⃣ Resolve Skema IDs
        $all_skema_ids = [];

        $jabatanKerjaList = KlasifikasiKualifikasi::whereIn('id_izin', $validated['all_id_izin'])
            ->pluck('jabatan_kerja')
            ->unique()
            ->toArray();

        $authData = DB::table('b_n_s_p_auths')->value('x_authorization');

        foreach ($jabatanKerjaList as $jabatanKerja) {
            $url = 'https://konstruksi.bnsp.go.id/api/v1/skema?kode_lpjk=' . $jabatanKerja;

            try {
                $response = Http::withHeaders([
                    'Content-Type'    => 'application/json',
                    'x-authorization' => $authData,
                ])->get($url);

                $responseData = $response->json();

                if (($responseData['code'] ?? null) !== 'ERR') {
                    $all_skema_ids[] = $responseData['data']['id'];
                    continue;
                }

                // Hardcoded fallback
                if ($jabatanKerja === 'SI101015') {
                    $all_skema_ids[] = 63566;
                } elseif ($jabatanKerja === 'SI013041') {
                    $all_skema_ids[] = 63538;
                } else {
                    // return response()->json([
                    //     'success' => false,
                    //     'message' => 'Skema BNSP tidak ditemukan',
                    //     'jabatan_kerja' => $jabatanKerja,
                    // ], 404);
                }
            } catch (\Throwable $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal mengambil data skema dari BNSP',
                    'error'   => $e->getMessage(),
                ], 500);
            }
        }

        // 3️⃣ Resolve TUK ID
        $tukName = MyTuk::where('id', $validated['tuk'])->value('nama_tuk');
        $id_tuk = MyTukBnsp::where('nama', $tukName)->value('id');

        if (!$id_tuk) {
            return response()->json([
                'success' => false,
                'message' => 'TUK tidak ditemukan',
            ], 404);
        }

        // 4️⃣ Prepare payload for BNSP API
        $createData = [
            "tuk_id"            => $id_tuk,
            "jenis_jadwal"      => 6, // Reguler, pilih 6
            "jenis_anggaran"    => $validated['jenis_anggaran'],
            "nama_jadwal"       => $validated['nama_jadwal'],
            "klasifikasi_id"    => $validated['kualifikasi'],
            "subklasifikasi_id" => $validated['subklasifikasi'],
            "tanggal_mulai"     => $validated['tanggal_mulai'],
            "tanggal_selesai"   => $validated['tanggal_selesai'],
            "keterangan"        => "",
            "jabker_id"         => $all_skema_ids,
        ];

        // 5️⃣ Call BNSP Create Jadwal API
        try {
            $client = new \GuzzleHttp\Client();

            $response = $client->post('https://konstruksi.bnsp.go.id/api/v1/jadwal', [
                'headers' => [
                    'Authorization'   => $request->header('Authorization'),
                    'x-authorization' => $authData,
                    'Content-Type'    => 'application/json',
                    'Accept'          => 'application/json',
                ],
                'json' => $createData,
            ]);

            $responseData = json_decode($response->getBody()->getContents(), true);

            return response()->json([
                'success' => true,
                'message' => 'Jadwal berhasil dibuat',
                'data'    => $responseData,
            ], 201);

        } catch (RequestException $e) {
            $responseBody = $e->hasResponse()
                ? $e->getResponse()->getBody()->getContents()
                : null;

            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat jadwal di BNSP',
                'error'   => $responseBody,
            ], 500);
        }

        $error_id = [];

        DB::beginTransaction();

        foreach ($all_id_izin as $id_izin) {
            $personalsData = Personal::where('id_izin', $id_izin)->select('nik', 'nama', 'tempat_lahir', 'tanggal_lahir', 'jenis_kelamin', 'alamat', 'kabupaten', 'propinsi', 'telepon', 'email')->first();
            $jadwal_id = (int)$request->input('jadwal_id');
            $asesor_id = (int)$request->input('asesor_id');
            $asesor2_id = (int)$request->input('asesor2_id');
            $nik = $personalsData->nik;
            $nama = $personalsData->nama;
            $tempat_lahir = $personalsData->tempat_lahir;
            $tanggal_lahir = $personalsData->tanggal_lahir;
            $jenis_kelamin = ($personalsData->jenis_kelamin == 'Pria') ? 2 : 1;
            $alamat = $personalsData->alamat ?? null;
            $kota_id = $personalsData->kabupaten;
            $provinsi_id = $personalsData->propinsi;
            $telepon = $personalsData->telepon;
            $email = $personalsData->email;

            $klasifikasi = KlasifikasiKualifikasi::where('id_izin', $id_izin)->select(['jabatan_kerja', 'tuk'])->first();
            $jabker = JabatanKerja::where('id_jabatan_kerja', $klasifikasi->jabatan_kerja)->orderBy('InputDate', 'desc')->pluck('jabatan_kerja')->first();
            $normalizedJabker = strtoupper(trim($jabker));
            $skema_id = DB::connection('mygatensi')->table('myskemabnsp')->whereRaw("UPPER(TRIM(REPLACE(nama, '`', ''))) = ?", [$normalizedJabker])->orderBy('id', 'desc')->pluck('id')->first();
            if (!$skema_id) {
                $similarSkema = DB::connection('mygatensi')
                    ->table('myskemabnsp')
                    ->whereRaw("UPPER(nama) LIKE ?", ["%{$normalizedJabker}%"])
                    ->limit(5)
                    ->pluck('nama');

                return response()->json([
                    'id_izin' => $id_izin,
                    'error' => 'Skema tidak ditemukan untuk jabatan: ' . $jabker,
                    'jabatan_dicari' => $jabker,
                    'saran_skema' => $similarSkema->isEmpty()
                        ? 'Tidak ada skema yang mirip. Silakan cek list skema yang tersedia.'
                        : $similarSkema,
                    'total_skema_tersedia' => DB::connection('mygatensi')
                        ->table('myskemabnsp')
                        ->count()
                ], 404);
            }

            $tuk = MyTuk::select(['nama_tuk'])->where('id', $klasifikasi->tuk)->first();
            $tuk_id = MyTukBnsp::select('ID')->where('Nama', $tuk->nama_tuk)->first();

            // Retrieve values from the "pendidikans" table
            $pendidikansData = Pendidikan::where('id_izin', $id_izin)->select('program_studi', 'no_ijazah', 'tahun_lulus', 'propinsi', 'jenjang', 'kabupaten', 'nama_sekolah_perguruan_tinggi', 'scan_surat_keterangan', 'scan_ijazah_legalisir')->first();
            try {
                $prodi = $pendidikansData->program_studi;
            } catch (\Exception $e) {
                dd($id_izin);
            }
            $no_ijasah = $pendidikansData->no_ijazah;
            $tahun_lulus = $pendidikansData->tahun_lulus;
            $kota_sekolah = $pendidikansData->kabupaten;
            $prov_sekolah = $pendidikansData->propinsi;
            $nama_sekolah = $pendidikansData->nama_sekolah_perguruan_tinggi;
            $fileFotoData = DB::table('personals')->where('id_izin', $id_izin)->select('pas_foto')->first();
            $file_foto = $fileFotoData->pas_foto;
            $fileKtpData = DB::table('personals')->where('id_izin', $id_izin)->select('ktp')->first();
            $file_ktp = $fileKtpData->ktp;
            if ($pendidikansData->scan_surat_keterangan) {
                $file_ijazah = $pendidikansData->scan_surat_keterangan;
            } else {
                $file_ijazah = $pendidikansData->scan_ijazah_legalisir;
            }
            $jenjang_id = $pendidikansData->jenjang;


            if ($jenjang_id == 99) {
                // this came from unannounced bnsp update kekw
                $jenjang_id = 3; // not 20 why?
                $no_ijasah = "1";
                $nama_sekolah = "Non Pendidikan";
                $prodi = "UMUM";
                $tahun_lulus = 0;
            } else if ($pendidikansData->jenjang == 8) {
                $jenjang_id = 7;
            }

            $apiData = [
                "jadwal_id" => $jadwal_id,
                "tuk_id" => $tuk_id->ID,
                "asesor_id" => $asesor_id,
                "asesor2_id" => $asesor2_id,
                "is_fg" => 0,
                "nik" => $nik,
                "nib" => "",
                "nama" => $nama,
                "tempat_lahir" => $tempat_lahir,
                "tanggal_lahir" => $tanggal_lahir,
                "jenis_kelamin" => $jenis_kelamin,
                "alamat" => $alamat ?? null,
                "kota_id" => $kota_id,
                "provinsi_id" => $provinsi_id,
                "negara_id" => 1,
                "telepon" => $telepon,
                "email" => $email,
                "jenis_mohon" => 1,
                "skema_id" => $skema_id,
                "keterangan" => "-",
                "jenjang_id" => (int)$jenjang_id,
                "prodi" => $prodi,
                "no_ijasah" => $no_ijasah,
                "tanggal_ijazah" => '0000-00-00',
                "tahun_lulus" => $tahun_lulus,
                "kota_sekolah" => (int)$kota_sekolah,
                "prov_sekolah" => (int)$prov_sekolah,
                "negara_sekolah" => 1,
                "nama_sekolah" => $nama_sekolah,
                "pekerjaan" => 89,
                "instansi_pekerjaan" => "",
                "jabatan_pekerjaan" => $jabker,
                "file_foto" => $file_foto,
                "file_ktp" => $file_ktp,
                "file_nib" => "-",
                "file_ijazah" => $file_ijazah
            ];

            // if(App::environment() == "production") {
            $httpClient = new \GuzzleHttp\Client();
            $authData = DB::table('b_n_s_p_auths')->select('x_authorization')->first()->x_authorization;
            $apiEndpoint = 'https://konstruksi.bnsp.go.id/api/v1/jadwal/peserta';

            try {
                $response = $httpClient->request('POST', $apiEndpoint, [
                    'headers' => [
                        'Authorization' => $request->header('Authorization'),
                        'x-authorization' => $authData,
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ],
                    'json' => $apiData,
                ]);

                $responseBody = $response->getBody()->getContents();
                $responseData = json_decode($responseBody, true);
            } catch (RequestException $e) {
                if ($e->hasResponse()) {
                    $response = $e->getResponse();
                    $statusCode = $response->getStatusCode();
                    $responseBody = $response->getBody()->getContents();
                    $responseData = json_decode($responseBody, true);

                    if ($responseData['message'] == 'Data Peserta tidak boleh duplikat') {
                        $jadwalBnsp = Jadwal::where('id_izin', $id_izin)->first();

                        if ($jadwalBnsp) {
                            $jadwalBnsp->id_izin = $id_izin;
                            $jadwalBnsp->jadwal_id = $apiData['jadwal_id'];
                            $jadwalBnsp->tuk_id = $apiData['tuk_id'];
                            $jadwalBnsp->asesor_id = $apiData['asesor_id'];
                            $jadwalBnsp->asesor2_id = $asesor2_id;
                            $jadwalBnsp->is_fg = $apiData['is_fg'];
                            $jadwalBnsp->nik = $apiData['nik'];
                            $jadwalBnsp->nib = $apiData['nib'];
                            $jadwalBnsp->nama = $apiData['nama'];
                            $jadwalBnsp->tempat_lahir = $apiData['tempat_lahir'];
                            $jadwalBnsp->tanggal_lahir = $apiData['tanggal_lahir'];
                            $jadwalBnsp->jenis_kelamin = $apiData['jenis_kelamin'];
                            $jadwalBnsp->alamat = $apiData['alamat'] ?? null;
                            $jadwalBnsp->kota_id = $apiData['kota_id'];
                            $jadwalBnsp->provinsi_id = $apiData['provinsi_id'];
                            $jadwalBnsp->negara_id = $apiData['negara_id'];
                            $jadwalBnsp->telepon = $apiData['telepon'];
                            $jadwalBnsp->email = $apiData['email'];
                            $jadwalBnsp->jenis_mohon = $apiData['jenis_mohon'];
                            $jadwalBnsp->skema_id = $apiData['skema_id'] ?? null;
                            $jadwalBnsp->keterangan = $apiData['keterangan'];
                            $jadwalBnsp->jenjang_id = $apiData['jenjang_id'];
                            $jadwalBnsp->prodi = $apiData['prodi'];
                            $jadwalBnsp->no_ijasah = $apiData['no_ijasah'];
                            // $jadwalBnsp->tanggal_ijazah = $apiData['tanggal_ijazah'];
                            $jadwalBnsp->tahun_lulus = $apiData['tahun_lulus'];
                            $jadwalBnsp->kota_sekolah = $apiData['kota_sekolah'];
                            $jadwalBnsp->prov_sekolah = $apiData['prov_sekolah'];
                            $jadwalBnsp->negara_sekolah = $apiData['negara_sekolah'];
                            $jadwalBnsp->nama_sekolah = $apiData['nama_sekolah'];
                            $jadwalBnsp->pekerjaan = $apiData['pekerjaan'];
                            $jadwalBnsp->instansi_pekerjaan = $apiData['instansi_pekerjaan'];
                            $jadwalBnsp->jabatan_pekerjaan = $apiData['jabatan_pekerjaan'];
                            $jadwalBnsp->kompeten = "BK";
                            $jadwalBnsp->file_foto = $apiData['file_foto'];
                            $jadwalBnsp->file_ktp = $apiData['file_ktp'];
                            $jadwalBnsp->file_nib = $apiData['file_nib'];
                            $jadwalBnsp->file_ijazah = $apiData['file_ijazah'];
                            $jadwalBnsp->komtek1 = $request->input("komtek1");
                            $jadwalBnsp->komtek2 = $request->input("komtek2");
                            $jadwalBnsp->komtek3 = $request->input("komtek3");
                            $jadwalBnsp->ketua_lsp = "Radinal Efendy, ST";
                            $jadwalBnsp->mgr_sertif = "Akhmadi Hafid";
                            $jadwalBnsp->save();
                        } else {
                            Jadwal::create([
                                "id_izin" => $id_izin,
                                "jadwal_id" => $apiData['jadwal_id'],
                                "tuk_id" => $apiData['tuk_id'],
                                "asesor_id" => $apiData['asesor_id'],
                                "asesor2_id" => $asesor2_id,
                                "is_fg" => $apiData['is_fg'],
                                "nik" => $apiData['nik'] ?? $nik,
                                "nib" => $apiData['nib'],
                                "nama" => $apiData['nama'],
                                "tempat_lahir" => $apiData['tempat_lahir'],
                                "tanggal_lahir" => $apiData['tanggal_lahir'],
                                "jenis_kelamin" => $apiData['jenis_kelamin'],
                                "alamat" => $apiData['alamat'] ?? null,
                                "kota_id" => $apiData['kota_id'],
                                "provinsi_id" => $apiData['provinsi_id'],
                                "negara_id" => $apiData['negara_id'],
                                "telepon" => $apiData['telepon'],
                                "email" => $apiData['email'],
                                "jenis_mohon" => $apiData['jenis_mohon'],
                                "skema_id" => $apiData['skema_id'] ?? null,
                                "keterangan" => $apiData['keterangan'],
                                "jenjang_id" => $apiData['jenjang_id'],
                                "prodi" => $apiData['prodi'],
                                "no_ijasah" => $apiData['no_ijasah'],
                                "kompeten" => "BK",
                                //"tanggal_ijazah" => $apiData['tanggal_ijazah'],
                                "tahun_lulus" => $apiData['tahun_lulus'],
                                "kota_sekolah" => $apiData['kota_sekolah'],
                                "prov_sekolah" => $apiData['prov_sekolah'],
                                "negara_sekolah" => $apiData['negara_sekolah'],
                                "nama_sekolah" => $apiData['nama_sekolah'],
                                "pekerjaan" => $apiData['pekerjaan'],
                                "instansi_pekerjaan" => $apiData['instansi_pekerjaan'],
                                "jabatan_pekerjaan" => $apiData['jabatan_pekerjaan'],
                                "file_foto" => $apiData['file_foto'],
                                "file_ktp" => $apiData['file_ktp'],
                                "file_nib" => $apiData['file_nib'],
                                "file_ijazah" => $apiData['file_ijazah'],
                                "komtek1" => $request->input("komtek1"),
                                "komtek2" => $request->input("komtek2"),
                                "komtek3" => $request->input("komtek3"),
                                "ketua_lsp" => "Radinal Efendy, ST",
                                "mgr_sertif" => "Akhmadi Hafid",
                            ]);
                        }
                        dd($responseData, $apiData);
                        array_push($error_id, ['id_izin' => $id_izin, 'msg' => $responseData['message']]);
                        continue;
                    }
                    // dd($responseData, $apiData);
                    array_push($error_id, ['id_izin' => $id_izin, 'msg' => $responseData['message']]);
                }
            }
            // }

            try {
                $jadwalBnsp = Jadwal::where('id_izin', $id_izin)->first();

                if ($jadwalBnsp) {
                    $jadwalBnsp->id_izin = $id_izin;
                    $jadwalBnsp->jadwal_id = $apiData['jadwal_id'];
                    $jadwalBnsp->tuk_id = $apiData['tuk_id'];
                    $jadwalBnsp->asesor_id = $apiData['asesor_id'];
                    $jadwalBnsp->asesor2_id = $asesor2_id;
                    $jadwalBnsp->is_fg = $apiData['is_fg'];
                    $jadwalBnsp->nik = $apiData['nik'];
                    $jadwalBnsp->nib = $apiData['nib'];
                    $jadwalBnsp->nama = $apiData['nama'];
                    $jadwalBnsp->tempat_lahir = $apiData['tempat_lahir'];
                    $jadwalBnsp->tanggal_lahir = $apiData['tanggal_lahir'];
                    $jadwalBnsp->jenis_kelamin = $apiData['jenis_kelamin'];
                    $jadwalBnsp->alamat = $apiData['alamat'];
                    $jadwalBnsp->kota_id = $apiData['kota_id'];
                    $jadwalBnsp->provinsi_id = $apiData['provinsi_id'];
                    $jadwalBnsp->negara_id = $apiData['negara_id'];
                    $jadwalBnsp->telepon = $apiData['telepon'];
                    $jadwalBnsp->email = $apiData['email'];
                    $jadwalBnsp->jenis_mohon = $apiData['jenis_mohon'];
                    $jadwalBnsp->skema_id = $apiData['skema_id'];
                    $jadwalBnsp->keterangan = $apiData['keterangan'];
                    $jadwalBnsp->jenjang_id = $apiData['jenjang_id'];
                    $jadwalBnsp->prodi = $apiData['prodi'];
                    $jadwalBnsp->no_ijasah = $apiData['no_ijasah'];
                    // $jadwalBnsp->tanggal_ijazah = $apiData['tanggal_ijazah'];
                    $jadwalBnsp->tahun_lulus = $apiData['tahun_lulus'];
                    $jadwalBnsp->kota_sekolah = $apiData['kota_sekolah'];
                    $jadwalBnsp->prov_sekolah = $apiData['prov_sekolah'];
                    $jadwalBnsp->negara_sekolah = $apiData['negara_sekolah'];
                    $jadwalBnsp->nama_sekolah = $apiData['nama_sekolah'];
                    $jadwalBnsp->pekerjaan = $apiData['pekerjaan'];
                    $jadwalBnsp->instansi_pekerjaan = $apiData['instansi_pekerjaan'];
                    $jadwalBnsp->jabatan_pekerjaan = $apiData['jabatan_pekerjaan'];
                    $jadwalBnsp->kompeten = "BK";
                    $jadwalBnsp->file_foto = $apiData['file_foto'];
                    $jadwalBnsp->file_ktp = $apiData['file_ktp'];
                    $jadwalBnsp->file_nib = $apiData['file_nib'];
                    $jadwalBnsp->file_ijazah = $apiData['file_ijazah'];
                    $jadwalBnsp->komtek1 = $request->input("komtek1");
                    $jadwalBnsp->komtek2 = $request->input("komtek2");
                    $jadwalBnsp->komtek3 = $request->input("komtek3");
                    $jadwalBnsp->ketua_lsp = "Radinal Efendy, ST";
                    $jadwalBnsp->mgr_sertif = "Akhmadi Hafid";
                    $jadwalBnsp->save();
                } else {
                    Jadwal::create([
                        "id_izin" => $id_izin,
                        "jadwal_id" => $apiData['jadwal_id'],
                        "tuk_id" => $apiData['tuk_id'],
                        "asesor_id" => $apiData['asesor_id'],
                        "asesor2_id" => $asesor2_id,
                        "is_fg" => $apiData['is_fg'],
                        "nik" => $apiData['nik'],
                        "nib" => $apiData['nib'],
                        "nama" => $apiData['nama'],
                        "tempat_lahir" => $apiData['tempat_lahir'],
                        "tanggal_lahir" => $apiData['tanggal_lahir'],
                        "jenis_kelamin" => $apiData['jenis_kelamin'],
                        "alamat" => $apiData['alamat'],
                        "kota_id" => $apiData['kota_id'],
                        "provinsi_id" => $apiData['provinsi_id'],
                        "negara_id" => $apiData['negara_id'],
                        "telepon" => $apiData['telepon'],
                        "email" => $apiData['email'],
                        "jenis_mohon" => $apiData['jenis_mohon'],
                        "skema_id" => $apiData['skema_id'],
                        "keterangan" => $apiData['keterangan'],
                        "jenjang_id" => $apiData['jenjang_id'],
                        "prodi" => $apiData['prodi'],
                        "no_ijasah" => $apiData['no_ijasah'],
                        "kompeten" => "BK",
                        //"tanggal_ijazah" => $apiData['tanggal_ijazah'],
                        "tahun_lulus" => $apiData['tahun_lulus'],
                        "kota_sekolah" => $apiData['kota_sekolah'],
                        "prov_sekolah" => $apiData['prov_sekolah'],
                        "negara_sekolah" => $apiData['negara_sekolah'],
                        "nama_sekolah" => $apiData['nama_sekolah'],
                        "pekerjaan" => $apiData['pekerjaan'],
                        "instansi_pekerjaan" => $apiData['instansi_pekerjaan'],
                        "jabatan_pekerjaan" => $apiData['jabatan_pekerjaan'],
                        "file_foto" => $apiData['file_foto'],
                        "file_ktp" => $apiData['file_ktp'],
                        "file_nib" => $apiData['file_nib'],
                        "file_ijazah" => $apiData['file_ijazah'],
                        "komtek1" => $request->input("komtek1"),
                        "komtek2" => $request->input("komtek2"),
                        "komtek3" => $request->input("komtek3"),
                        "ketua_lsp" => "Radinal Efendy, ST",
                        "mgr_sertif" => "Akhmadi Hafid",
                    ]);
                }
            } catch (QueryException $e) {
                continue;
                // DB::rollBack();
                $errorMessage = 'Error putting data into the database: ' . $e->getMessage();
                return response()->json(['error' => $errorMessage], 500);
            }
        }
        DB::commit();
        if (count($error_id) == 0) {
            return redirect('persiapan-uji')->with('success', 'Berhasil input asesi' . $id_izin . 'ke jadwal BNSP');
        } else {
            return redirect()->back()->with('error', json_encode($error_id));
        }
    }

    private function baseJadwalQuery()
    {
        return Jadwal::select([
            'jadwal_id',
            'nama_kegiatan',
            'tuk_id',
            'asesor_id',
            'asesor2_id',
            'skema_id',
            'jenjang_id',
            'tanggal_uji',
            'tanggal_selesai',
            'is_started',
            'is_started_praasesmen',
            'jenis_kelas',
            'tahap'
        ])->with([
            'tuk:id,nama,alamat',
            'asesor:id,nama,noreg',
            'asesor2:id,nama,noreg',
            'skema:id,nama'
        ]);
    }

    private function checkRole(string $roleId)
    {
        if (auth()->user()->role_id !== $roleId) {
            return response()->json([
                'message' => 'Invalid Role',
                'data' => null
            ], 403);
        }

        return null;
    }

    private function perPage(Request $request, int $default = 10): int
    {
        return (int) $request->get('per_page', $default);
    }

    public function getKegiatanAsesi()
    {
        if ($resp = $this->checkRole('8')) {
            return $resp;
        }

        $jadwalId = AsesiJadwal::where('account', auth()->user()->email)
            ->value('jadwal_id');

        $jadwals = $this->baseJadwalQuery()
            ->where('jadwal_id', $jadwalId)
            ->where('tahap', '!=', 0)
            ->first();

        return response()->json([
            'message' => 'Kegiatan Asesi retrieved successfully.',
            'data' => $jadwals
        ]);
    }

    public function getKegiatanAsesor()
    {
        if ($resp = $this->checkRole('5')) {
            return $resp;
        }

        $noreg = User::where('id', auth()->id())->value('noreg');
        $asesorId = AsesorBnsp::where('noreg', $noreg)->value('id');

        $jadwals = $this->baseJadwalQuery()
            ->where(function ($q) use ($asesorId) {
                $q->where('asesor_id', $asesorId)
                ->orWhere('asesor2_id', $asesorId);
            })
            ->where('tahap', '!=', 0)
            ->whereDate('tanggal_uji', today())
            ->first();

        return response()->json([
            'message' => 'Kegiatan Asesor retrieved successfully.',
            'data' => $jadwals
        ]);
    }

    public function getKegiatanKomtek(Request $request)
    {
        if ($resp = $this->checkRole('4')) {
            return $resp;
        }

        $userId = auth()->id();
        $ttd = filter_var($request->query('ttd'), FILTER_VALIDATE_BOOLEAN);

        $jadwals = $this->baseJadwalQuery()
        ->join('ba_komteks', 'ba_komteks.jadwal_id', '=', 'jadwals.jadwal_id')
        ->where(function ($q) use ($userId) {
            $q->where('komtek1', $userId)
            ->orWhere('komtek2', $userId)
            ->orWhere('komtek3', $userId);
        })
        ->when($ttd !== null, function ($q) use ($ttd, $userId) {
            $q->where(function ($sq) use ($ttd, $userId) {
                $sq->where(function ($s) use ($ttd, $userId) {
                    $s->where('komtek1', $userId)
                    ->where('ba_komteks.has_ttd_komtek1', $ttd);
                })
                ->orWhere(function ($s) use ($ttd, $userId) {
                    $s->where('komtek2', $userId)
                    ->where('ba_komteks.has_ttd_komtek2', $ttd);
                })
                ->orWhere(function ($s) use ($ttd, $userId) {
                    $s->where('komtek3', $userId)
                    ->where('ba_komteks.has_ttd_komtek3', $ttd);
                });
            });
        })
        ->select('jadwals.*') // <-- specify jadwals table explicitly
        ->paginate($this->perPage($request));

        return response()->json([
            'message' => 'Kegiatan Komtek retrieved successfully.',
            'data' => $jadwals
        ]);
    }

    public function getKegiatanDirektur(Request $request)
    {
        if ($resp = $this->checkRole('6')) {
            return $resp;
        }

        $ttd = filter_var($request->query('ttd'), FILTER_VALIDATE_BOOLEAN);

        $jadwals = $this->baseJadwalQuery()
            ->where('ketua_lsp', auth()->id())
            ->when($ttd, function ($q) {
                $q->where('has_spt_asesor', true)
                ->where('has_spt_komtek', true);
            }, function ($q) {
                $q->where(function ($sq) {
                    $sq->where('has_spt_asesor', false)
                    ->orWhere('has_spt_komtek', false);
                });
            })
            ->paginate($this->perPage($request));

        return response()->json([
            'message' => 'Kegiatan Direktur retrieved successfully.',
            'data' => $jadwals
        ]);
    }

    public function getKegiatanAdminTuk(Request $request)
    {
        if ($resp = $this->checkRole('3')) {
            return $resp;
        }

        $jadwals = $this->baseJadwalQuery()
            ->where('admin_id', auth()->id())
            ->when($request->filled('tanggal_uji'), function ($query) use ($request) {
                $query->whereDate('tanggal_uji', $request->tanggal_uji);
            })
            ->paginate($this->perPage($request));

        return response()->json([
            'message' => 'Kegiatan Direktur retrieved successfully.',
            'data' => $jadwals
        ]);
    }

    public function startAsesmen($id)
    {
        $jadwal = Jadwal::where('jadwal_id', $id)
            ->where('tahap', '=', 1)
            ->lockForUpdate()
            ->first();

        if (! $jadwal) {
            return response()->json([
                'message' => 'Jadwal tidak ditemukan atau sudah dimulai.'
            ], 409);
        }

        $jadwal->update([
            'is_started' => true,
            'started_at' => now(),
            'tahap' => 2
        ]);

        return response()->json([
            'message' => 'Jadwal berhasil dimulai.'
        ]);
    }

    public function startPraAsesmen($id)
    {
        $jadwal = Jadwal::where('jadwal_id', $id)
            ->where('tahap', '=', 0)
            ->lockForUpdate()
            ->first();

        if (! $jadwal) {
            return response()->json([
                'message' => 'Jadwal tidak ditemukan atau sudah dimulai.'
            ], 409);
        }

        $jadwal->update([
            'is_started_praasesmen' => true,
            'tahap' => 1
        ]);

        return response()->json([
            'message' => 'Pra Asesmen berhasil dimulai.'
        ]);
    }

    public function listAsesi($id)
    {
        $listAsesi = AsesiJadwal::query()
        ->join('personals', 'personals.id_izin', '=', 'asesi_jadwals.id_izin')
        ->where('asesi_jadwals.jadwal_id', $id)
        ->select([
            'asesi_jadwals.jadwal_id',
            'asesi_jadwals.id_izin',
            'personals.nama',
            'asesi_jadwals.is_started',
            'asesi_jadwals.started_at',
            'asesi_jadwals.kompeten',
        ])
        ->get();

        return response()->json([
            'message' => 'Success',
            'list_asesi' => $listAsesi
        ]);
    }

    public function listAsesiCreateJadwal(Request $request)
    {
        $validated = $request->validate([
            'tuk_id' => ['required'],
            'subklasifikasi_id' => ['required'],
        ]);

        $listAsesi = Personal::where('status', 31)->whereIn('id_izin', function ($q) use ($validated) {
            $q->select('id_izin')
            ->from('klasifikasi_kualifikasis')
            ->where('tuk', $validated['tuk_id'])
            ->where('subklasifikasi', $validated['subklasifikasi_id']);
        })->select([
            'id_izin',
            'nama',
        ])->get();

        return response()->json([
            'success' => true,
            'message' => 'Success',
            'list_asesi' => $listAsesi,
        ]);
    }
}
