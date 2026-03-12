<?php

namespace App\Http\Controllers;

use App\Models\AbsenKomtek;
use App\Models\AsesiJadwal;
use App\Models\AsesorBnsp;
use App\Models\Barcode;
use App\Models\JabatanKerja;
use App\Models\Jadwal;
use App\Models\KlasifikasiKualifikasi;
use App\Models\KomtekRecommendation;
use App\Models\MyTuk;
use App\Models\MyTukBnsp;
use App\Models\Pendidikan;
use App\Models\Personal;
use App\Models\SPTAsesor;
use App\Models\SPTKomtek;
use App\Models\SkKomtek;
use App\Models\SkPelaksanaanUji;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

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
        /** ===============================
         *  1️⃣ VALIDATION
         *  =============================== */
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

        /** ===============================
         *  2️⃣ RESOLVE SKEMA IDS
         *  =============================== */
        $authData = DB::table('b_n_s_p_auths')->value('x_authorization');
        $allSkemaIds = [];

        $jabatanKerjaList = KlasifikasiKualifikasi::whereIn('id_izin', $validated['all_id_izin'])
            ->pluck('jabatan_kerja')
            ->unique()
            ->toArray();

        foreach ($jabatanKerjaList as $jabatanKerja) {
            try {
                $response = Http::withHeaders([
                    'Content-Type'    => 'application/json',
                    'x-authorization' => $authData,
                ])->get("https://konstruksi.bnsp.go.id/api/v1/skema?kode_lpjk={$jabatanKerja}");

                $data = $response->json();

                if (($data['code'] ?? null) !== 'ERR') {
                    $allSkemaIds[] = $data['data']['id'];
                    continue;
                }

                // fallback
                if ($jabatanKerja === 'SI101015') $allSkemaIds[] = 63566;
                if ($jabatanKerja === 'SI013041') $allSkemaIds[] = 63538;

            } catch (\Throwable $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal mengambil skema dari BNSP',
                    'error'   => $e->getMessage(),
                ], 500);
            }
        }

        /** ===============================
         *  3️⃣ RESOLVE TUK
         *  =============================== */
        $tukName = MyTuk::where('id', $validated['tuk'])->value('nama_tuk');
        $idTuk = MyTukBnsp::where('nama', $tukName)->value('id');

        if (!$idTuk) {
            return response()->json([
                'success' => false,
                'message' => 'TUK tidak ditemukan di BNSP',
            ], 404);
        }

        /** ===============================
         *  4️⃣ CREATE JADWAL BNSP
         *  =============================== */
        try {
            $client = new \GuzzleHttp\Client();

            $response = $client->post('https://konstruksi.bnsp.go.id/api/v1/jadwal', [
                'headers' => [
                    'Authorization'   => $request->header('Authorization'),
                    'x-authorization' => $authData,
                    'Content-Type'    => 'application/json',
                    'Accept'          => 'application/json',
                ],
                'json' => [
                    "tuk_id"            => $idTuk,
                    "jenis_jadwal"      => 6,
                    "jenis_anggaran"    => $validated['jenis_anggaran'],
                    "nama_jadwal"       => $validated['nama_jadwal'],
                    "klasifikasi_id"    => $validated['kualifikasi'],
                    "subklasifikasi_id" => $validated['subklasifikasi'],
                    "tanggal_mulai"     => $validated['tanggal_mulai'],
                    "tanggal_selesai"   => $validated['tanggal_selesai'],
                    "keterangan"        => "",
                    "jabker_id"         => $allSkemaIds,
                ],
            ]);

            $responseData = json_decode($response->getBody()->getContents(), true);
            $jadwalId = (int) ($responseData['data']['jadwal_id'] ?? 0);

            if ($jadwalId <= 0) {
                throw new \Exception('Jadwal ID tidak valid');
            }

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat jadwal di BNSP',
                'error'   => $e->getMessage(),
            ], 500);
        }

        /** ===============================
         *  5️⃣ GENERATE & SEND SPT ASESOR
         *  =============================== */
        try {
            $this->generateAndSendSptAsesor($jadwalId);
        } catch (\Throwable $e) {
            Log::error('SPT Asesor gagal', [
                'jadwal_id' => $jadwalId,
                'error' => $e->getMessage()
            ]);
        }

        /** ===============================
         *  6️⃣ ADD PESERTA (COLLECT ERRORS)
         *  =============================== */
        $errors = [];

        foreach ($validated['all_id_izin'] as $id_izin) {
            try {
                $personalsData = Personal::where('id_izin', $id_izin)->select('nik', 'nama', 'tempat_lahir', 'tanggal_lahir', 'jenis_kelamin', 'alamat', 'kabupaten', 'propinsi', 'telepon', 'email')->first();
                $jadwal_id = $jadwalId;
                $asesor_id  = (int) $validated['asesor1'];
                $asesor2_id = $validated['asesor2'] ?? null;
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
                    throw new \Exception("Data pendidikan tidak ditemukan untuk id_izin {$id_izin}");
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
                                $jadwalBnsp->ketua_lsp = $validated['ketua_lsp'];
                                $jadwalBnsp->mgr_sertif = $validated['manajer_sertifikasi'];
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
                                    "ketua_lsp" => $validated['ketua_lsp'],
                                    "mgr_sertif" => $validated['manajer_sertifikasi'],
                                ]);
                            }
                            array_push($errors, ['id_izin' => $id_izin, 'msg' => $responseData['message']]);
                            continue;
                        }
                        // dd($responseData, $apiData);
                        array_push($errors, ['id_izin' => $id_izin, 'msg' => $responseData['message']]);
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
                        $jadwalBnsp->ketua_lsp = $validated['ketua_lsp'];
                        $jadwalBnsp->mgr_sertif = $validated['manajer_sertifikasi'];
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
                            "ketua_lsp" => $validated['ketua_lsp'],
                            "mgr_sertif" => $validated['manajer_sertifikasi'],
                        ]);
                    }
                } catch (QueryException $e) {
                    continue;
                    // DB::rollBack();
                    $errorMessage = 'Error putting data into the database: ' . $e->getMessage();
                    return response()->json(['error' => $errorMessage], 500);
                }
            } catch (\Throwable $e) {
                $errors[] = [
                    'id_izin' => $id_izin,
                    'error'   => $e->getMessage(),
                ];
            }
        }

        /** ===============================
         *  ✅ FINAL RESPONSE (JSON ONLY)
         *  =============================== */
        return response()->json([
            'success' => true,
            'message' => 'Jadwal berhasil dibuat',
            'jadwal_id' => $jadwalId,
            'spt_sent' => true,
            'participant_errors' => $errors,
        ], 201);
    }

    public function updateJadwal(Request $request, $jadwal_id)
    {
        $validated = $request->validate([
            'tanggal_uji' => ['required', 'date'],
        ]);

        $jadwal = Jadwal::where('jadwal_id', $jadwal_id)->first();

        if (!$jadwal) {
            return response()->json([
                'success' => false,
                'message' => 'Jadwal not found',
            ], 404);
        }

        $jadwal->update([
            'tanggal_uji' => $validated['tanggal_uji'],
            'tahap' => 0,
            'started_at' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Jadwal successfully updated',
            'data' => $jadwal,
        ]);
    }

    private function generateAndSendSptAsesor(
        int $jadwalId,
        bool $withTtd = true
    ): array
    {
        $authData = DB::table('b_n_s_p_auths')->value('x_authorization');

        /** ===============================
         *  1. GET JADWAL DATA FROM BNSP
         *  =============================== */
        $url = "https://konstruksi.bnsp.go.id/api/v1/jadwal/blanko?jadwal_id={$jadwalId}";

        $response = Http::retry(8, 1000)->withHeaders([
            'Content-Type'    => 'application/json',
            'x-authorization' => $authData,
        ])->get($url);

        $responseJadwal = $response->json();

        if (($responseJadwal['code'] ?? null) === 'ERR') {
            throw new \Exception('Error surat tugas: ' . $responseJadwal['message']);
        }

        if (($responseJadwal['message'] ?? null) === 'Unauthorized') {
            throw new \Exception('Unauthorized BNSP');
        }

        /** ===============================
         *  2. GENERATE SPT NUMBER
         *  =============================== */
        $existing = SPTAsesor::where('jadwal_id', $jadwalId)->first();

        if ($existing) {
            $noSpt = $existing->spt_no;
        } else {
            $last = SPTAsesor::latest('id')->first();
            $noSpt = $last->spt_no;

            $romawi = [
                1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV',
                5 => 'V', 6 => 'VI', 7 => 'VII', 8 => 'VIII',
                9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII',
            ];

            $parts = explode('/', $noSpt);
            $year  = now()->year;

            $parts[0] = ((int) $parts[3] === $year) ? ((int) $parts[0] + 1) : 1;
            $parts[2] = $romawi[now()->month];
            $parts[3] = (string) $year;

            $noSpt = implode('/', $parts);
        }

        /** ===============================
         *  3. RESOLVE ASESOR & TUK
         *  =============================== */
        $jadwal = Jadwal::where('jadwal_id', $jadwalId)->firstOrFail();

        $asesor1 = DB::connection('mygatensi')
            ->table('myasesorbnsp')
            ->where('ID', $jadwal->asesor_id)
            ->first();

        if (!$asesor1) {
            throw new \Exception('Asesor 1 tidak ditemukan');
        }

        $asesor2 = null;
        if ($jadwal->asesor2_id) {
            $asesor2 = DB::connection('mygatensi')
                ->table('myasesorbnsp')
                ->where('ID', $jadwal->asesor2_id)
                ->first();
        }

        $tuk = DB::connection('mygatensi')
            ->table('mytukbnsp')
            ->where('Kode', $responseJadwal['dataJadwal']['kode_tuk'])
            ->first();

        if (!$tuk) {
            throw new \Exception('TUK tidak ditemukan');
        }

        /** ===============================
         *  4. COLLECT SKEMA & PESERTA
         *  =============================== */
        $peserta = [];
        $skemaIds = [];

        foreach ($responseJadwal['data'] as $asesi) {
            $peserta[] = $asesi['nama'];
            $skemaIds[] = $asesi['skema_id'];
        }

        $skemaIds = array_unique($skemaIds);

        $allSkema = json_decode(
            file_get_contents(resource_path('json/skemabnsp.json')),
            true
        )['myskemabnsp'];

        $namaSkema = collect($allSkema)
            ->whereIn('id', $skemaIds)
            ->pluck('nama')
            ->toArray();

        /** ===============================
         *  5. GENERATE QR & PDF
         *  =============================== */
        $barcodeUuid = null;
        $qrSvg = null;
        $fileUrl = null;

        if ($withTtd) {
            $barcodeUuid = (string) \Ramsey\Uuid\Uuid::uuid4();
            $qrUrl = "https://barcode.lspgatensi.id/{$barcodeUuid}";
            $qrSvg = QrCode::margin(0)->size(70)->generate($qrUrl);
        }

        $viewData = [
            'no_surat'     => $noSpt,
            'nama_asesor'  => $asesor1->Nama,
            'nama_met'     => $asesor1->Noreg,
            'nama_asesor2' => $asesor2->Nama ?? '',
            'nama_met2'    => $asesor2->Noreg ?? '',
            'nama_tuk'     => $responseJadwal['dataJadwal']['nama_tuk'],
            'alamat_tuk'   => $tuk->Alamat,
            'hari_tanggal' => Carbon::parse(
                $responseJadwal['dataJadwal']['tanggal_mulai']
            )->isoFormat('dddd, D MMMM YYYY'),
            'jmlh_peserta' => count($peserta),
            'nama_skema'   => implode(', ', $namaSkema),
            'nama_peserta' => $peserta,
            'qr'           => $qrSvg, // null if no ttd
        ];

        $pdf = PDF::loadView('template-surat.spt_asesor', $viewData);
        $pdfContent = $pdf->output();

        /** ===============================
         *  6. UPLOAD TO FTP
         *  =============================== */
        $path = "spt_asesor/{$jadwalId}.pdf";
        Storage::disk('str_moon')->put($path, $pdfContent);

        $fileUrl = "https://moon.lspgatensi.id/files/new_gatensi/{$path}";

        /** ===============================
         *  7. SEND TO BNSP
         *  =============================== */
        DB::beginTransaction();
        if ($withTtd) {
            $post = Http::retry(8, 1000)->withHeaders([
                'Content-Type'    => 'application/json',
                'x-authorization' => $authData,
            ])->post(
                'https://konstruksi.bnsp.go.id/api/v1/jadwal/asesor/surat-tugas',
                [
                    'jadwal_id'        => $jadwalId,
                    'asesor_id'        => $asesor1->IDAsesor,
                    'url_surat_tugas'  => $fileUrl,
                ]
            );

            $postData = $post->json();

            if (($postData['code'] ?? null) !== 'OK') {
                DB::rollBack();
                throw new \Exception('Gagal kirim SPT ke BNSP');
            }
        }

        SPTAsesor::updateOrCreate(
            ['jadwal_id' => $jadwalId],
            [
                'spt_no'    => $noSpt,
                'asesor_id' => $asesor1->IDAsesor,
                'link'      => $fileUrl,
                'send'      => $withTtd ? 1 : 0,
            ]
        );

        DB::commit();

        return [
            'spt_no' => $noSpt,
            'url'    => $fileUrl,
        ];
    }

    public function ttdSptAsesor(int $jadwalId)
    {
        try {
            $result = $this->generateAndSendSptAsesor($jadwalId, true);

            return response()->json([
                'message' => 'SPT Asesor generated and sent successfully.',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    private function generateSptKomtek(
        int $jadwalId,
        bool $withTtd = true
    ): array
    {
        /** ===============================
         *  1. RESOLVE KOMTEK NAMES (SAFE)
         *  =============================== */
        $jadwal = Jadwal::select(['komtek1', 'komtek2', 'komtek3'])
            ->where('jadwal_id', $jadwalId)
            ->firstOrFail();

        $getNamaKomtek = function ($nomor) {
            if (!$nomor) return null;

            return User::where('id', $nomor)->value('name');
        };

        $nama_komtek1 = $getNamaKomtek($jadwal->komtek1);
        $nama_komtek2 = $getNamaKomtek($jadwal->komtek2);
        $nama_komtek3 = $getNamaKomtek($jadwal->komtek3);

        /** ===============================
         *  2. GENERATE / REUSE SPT NUMBER
         *  =============================== */
        $existing = SPTKomtek::where('jadwal_id', $jadwalId)->first();

        if ($existing) {
            $spt_no = $existing->no_surat;
        } else {
            $last = SPTKomtek::latest('id')->first();
            $base = $last?->no_surat ?? '0/LSP-GATENSI/SPT-KOMTEK/I/2024';

            $romawi = [
                1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV',
                5 => 'V', 6 => 'VI', 7 => 'VII', 8 => 'VIII',
                9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII',
            ];

            $parts = explode('/', $base);
            $year  = now()->year;

            $parts[0] = ((int)($parts[3] ?? 0) === $year)
                ? ((int)$parts[0] + 1)
                : 1;

            $parts[2] = $romawi[now()->month];
            $parts[3] = (string)$year;

            $spt_no = implode('/', $parts);
        }

        /** ===============================
         *  3. GET JADWAL DATA FROM BNSP
         *  =============================== */
        $authData = DB::table('b_n_s_p_auths')->value('x_authorization');

        $response = Http::retry(5, 1000)->withHeaders([
            'Content-Type'    => 'application/json',
            'x-authorization' => $authData,
        ])->get("https://konstruksi.bnsp.go.id/api/v1/jadwal/blanko?jadwal_id={$jadwalId}");

        $responseJadwal = $response->json();

        if (($responseJadwal['code'] ?? null) === 'ERR') {
            throw new \Exception('Jadwal tidak ditemukan di BNSP');
        }

        /** ===============================
         *  4. TUK, PESERTA, SKEMA
         *  =============================== */
        $foundTuk = MyTukBnsp::where('kode', $responseJadwal['dataJadwal']['kode_tuk'])
            ->firstOrFail();

        $peserta = [];
        $skemaIds = [];

        foreach ($responseJadwal['data'] as $asesi) {
            $peserta[] = $asesi['nama'];
            $skemaIds[] = $asesi['skema_id'];
        }

        $skemaIds = array_unique($skemaIds);

        $allSkema = json_decode(
            file_get_contents(resource_path('json/skemabnsp.json')),
            true
        )['myskemabnsp'];

        $namaSkema = collect($allSkema)
            ->whereIn('id', $skemaIds)
            ->pluck('nama')
            ->toArray();

        /** ===============================
         *  5. QR (ONLY IF TTD)
         *  =============================== */
        $qrSvg = null;
        $barcodeUuid = null;
        $fileUrl = null;

        if ($withTtd) {
            $barcodeUuid = (string) \Ramsey\Uuid\Uuid::uuid4();
            $qrSvg = QrCode::margin(0)->size(70)
                ->generate("https://barcode.lspgatensi.id/{$barcodeUuid}");

            // Convert SVG to PNG image
            $qrImage = $this->convertSvgToPng($qrSvg);

            // Save to FTP server
            $path = "barcodes/{$barcodeUuid}.png";
            Storage::disk('str_moon')->put($path, $qrImage);

            // Public image URL
            $urlImage = "https://moon.lspgatensi.id/files/new_gatensi/{$path}";

            // Save / update DB
            Barcode::updateOrCreate(
                [
                    'id_user'       => auth()->id(),
                    'id_izin'       => '2220910001',
                    'jenis_dokumen' => 'SPT Komtek',
                ],
                [
                    'id_jadwal' => $jadwalId,
                    'url_image' => $urlImage,
                    'url_ttd'   => "https://barcode.lspgatensi.id/{$barcodeUuid}",
                    'id_role'   => auth()->user()->role_id ?? null,
                ]
            );
        }

        /** ===============================
         *  6. GENERATE PDF
         *  =============================== */
        $viewData = [
            'no_surat'     => $spt_no,
            'nama_komtek'  => $nama_komtek1,
            'nama_komtek2' => $nama_komtek2,
            'nama_komtek3' => $nama_komtek3,
            'nama_tuk'     => $responseJadwal['dataJadwal']['nama_tuk'],
            'alamat_tuk'   => $foundTuk->alamat,
            'hari_tanggal' => Carbon::parse(
                $responseJadwal['dataJadwal']['tanggal_mulai']
            )->isoFormat('dddd, D MMMM YYYY'),
            'jmlh_peserta' => count($peserta),
            'nama_skema'   => implode(', ', $namaSkema),
            'nama_peserta' => $peserta,
            'qr'           => $qrSvg,
        ];

        $pdf = PDF::loadView('template-surat.spt_komtek', $viewData);
        $pdfContent = $pdf->output();

        $path = "spt_komtek/{$jadwalId}.pdf";
        Storage::disk('str_moon')->put($path, $pdfContent);
        $fileUrl = "https://moon.lspgatensi.id/files/new_gatensi/{$path}";

        /** ===============================
         *  7. SAVE DB (ALWAYS)
         *  =============================== */
        SPTKomtek::updateOrCreate(
            ['jadwal_id' => $jadwalId],
            [
                'no_surat' => $spt_no,
                'link'     => $fileUrl,
                'send'     => $withTtd ? 1 : 0,
                'inputby_id' => auth()->id(),
            ]
        );

        return [
            'spt_no' => $spt_no,
            'url'    => $fileUrl,
            'ttd'    => $withTtd,
        ];
    }

    public function ttdSptKomtek(int $jadwalId)
    {
        try {
            $result = $this->generateSptKomtek($jadwalId, true);

            return response()->json([
                'message' => 'SPT Komtek generated successfully.',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    private function generateSkPelaksanaanUji(
        int $jadwalId,
        string $nomorSk,
        string $tanggalPlenoKomtek,
        string $nomorPlenoKomtek,
        string $tanggalDirekturLsp,
        bool $withTtd = true
    ): array
    {
        /** ===============================
         *  1. CHECK EXISTING SK PELAKSANAAN UJI
         *  =============================== */
        $existing = SkPelaksanaanUji::where('jadwal_id', $jadwalId)->first();

        // Use existing no_surat if available, otherwise use the provided one
        if ($existing) {
            $nomorSk = $existing->no_surat;
        }

        /** ===============================
         *  2. GET JADWAL DATA FROM DATABASE
         *  =============================== */
        $jadwal = Jadwal::where('jadwal_id', $jadwalId)->firstOrFail();
        $tanggalUji = Carbon::parse($jadwal->tanggal_uji);

        /** ===============================
         *  3. GET ASESI & SKEMA DATA FROM DATABASE
         *  =============================== */
        // Get all necessary data in optimized queries
        $asesiJadwals = AsesiJadwal::where('jadwal_id', $jadwalId)
            ->with(['personal'])
            ->get();

        $idIzinList = $asesiJadwals->pluck('id_izin')->toArray();

        // Get all KlasifikasiKualifikasi for these id_izin
        $klasifikasiMap = KlasifikasiKualifikasi::whereIn('id_izin', $idIzinList)
            ->get()
            ->keyBy('id_izin');

        // Get all id_jabatan_kerja from klasifikasi
        $jabatanKerjaIds = $klasifikasiMap->pluck('jabatan_kerja')
            ->filter()
            ->unique()
            ->toArray();

        // Get all JabatanKerja for these ids
        $jabatanKerjaMap = JabatanKerja::whereIn('id_jabatan_kerja', $jabatanKerjaIds)
            ->get()
            ->keyBy('id_jabatan_kerja');

        $asesiData = [];

        foreach ($asesiJadwals as $asesiJadwal) {
            $namaAsesi = $asesiJadwal->personal->nama ?? 'Tidak diketahui';

            // Get KlasifikasiKualifikasi from map
            $klasifikasi = $klasifikasiMap->get($asesiJadwal->id_izin);

            if ($klasifikasi && $klasifikasi->jabatan_kerja) {
                // Get JabatanKerja from map
                $jabatanKerja = $jabatanKerjaMap->get($klasifikasi->jabatan_kerja);
                $skemaNama = $jabatanKerja->jabatan_kerja ?? 'Tidak diketahui';
            } else {
                $skemaNama = 'Tidak diketahui';
            }

            $asesiData[] = [
                'id_izin' => $asesiJadwal->id_izin,
                'nama' => $namaAsesi,
                'skema' => $skemaNama,
            ];
        }

        /** ===============================
         *  4. PREPARE HASIL SERTIFIKASI DATA
         *  =============================== */
        // Get all KomtekRecommendation for these id_izin in one query
        $rekomendasiMap = KomtekRecommendation::whereIn('id_izin', $idIzinList)
            ->pluck('rekomendasi', 'id_izin');

        $hasilSertifikasi = [];
        foreach ($asesiData as $index => $asesi) {
            // Get recommendation from map using the asesi's id_izin
            $hasil = $rekomendasiMap->get($asesi['id_izin'], 'K');

            $hasilSertifikasi[] = [
                'no' => $index + 1,
                'nama' => $asesi['nama'],
                'skema' => $asesi['skema'],
                'hasil' => $hasil,
            ];
        }

        /** ===============================
         *  5. GENERATE QR CODE (only if withTtd)
         *  =============================== */
        $qrBase64 = null;

        if ($withTtd) {
            $barcodeUuid = (string) \Ramsey\Uuid\Uuid::uuid4();
            $qrSvg = QrCode::margin(1)->size(120)
                ->generate("https://barcode.lspgatensi.id/{$barcodeUuid}");

            // Convert SVG to PNG image
            $qrImage = $this->convertSvgToPng($qrSvg);

            // Convert PNG to base64 for PDF embedding
            $qrBase64 = base64_encode($qrImage);

            // Save to FTP server
            $path = "new_gatensi/barcodes/{$barcodeUuid}.png";
            Storage::disk('str_moon')->put($path, $qrImage);

            // Public image URL
            $urlImage = "https://moon.lspgatensi.id/files/new_gatensi/{$path}";

            // Save / update DB
            Barcode::updateOrCreate(
                [
                    'id_user'       => auth()->id(),
                    'id_izin'       => '2220910001',
                    'jenis_dokumen' => 'SK Pelaksanaan Uji',
                ],
                [
                    'id_jadwal' => $jadwalId,
                    'url_image' => $urlImage,
                    'url_ttd'   => "https://barcode.lspgatensi.id/{$barcodeUuid}",
                    'id_role'   => auth()->user()->role_id ?? null,
                ]
            );
        }

        /** ===============================
         *  6. GENERATE PDF
         *  =============================== */
        $viewData = [
            'nomor' => $nomorSk,
            'tanggal_pleno_komtek' => Carbon::parse($tanggalPlenoKomtek)->isoFormat('D MMMM YYYY'),
            'nomor_pleno_komtek' => $nomorPlenoKomtek,
            'tanggal_uji_sertifikasi' => $tanggalUji->isoFormat('D MMMM YYYY'),
            'tanggal_direktur_lsp' => Carbon::parse($tanggalDirekturLsp)->isoFormat('D MMMM YYYY'),
            'hasil_sertifikasi' => $hasilSertifikasi,
            'qr' => $qrBase64,
        ];

        $pdf = Pdf::loadView('template-surat.sk_pelaksanaan_uji', $viewData);
        $pdfContent = $pdf->output();

        // Generate unique filename with timestamp to prevent caching
        $timestamp = now()->format('YmdHis');
        $path = "sk_pelaksanaan_uji/{$jadwalId}_{$timestamp}.pdf";
        Storage::disk('str_moon')->put($path, $pdfContent);
        $fileUrl = "https://moon.lspgatensi.id/files/new_gatensi/{$path}";

        // Save or update SkPelaksanaanUji model
        SkPelaksanaanUji::updateOrCreate(
            ['jadwal_id' => $jadwalId],
            [
                'no_surat' => $nomorSk,
                'link' => $fileUrl,
                'is_approved' => $withTtd,
            ]
        );

        return [
            'nomor_sk' => $nomorSk,
            'url' => $fileUrl,
        ];
    }

    public function ttdSk(Request $request, int $jadwalId)
    {
        try {
            /** ===============================
             *  GET ABSEN KOMTEK DATA (needed for SK Pelaksanaan Uji)
             *  =============================== */
            $absenKomtek = AbsenKomtek::where('jadwal_id', $jadwalId)->firstOrFail();

            /** ===============================
             *  GENERATE / REUSE SK PELAKSANAAN UJI NUMBER
             *  =============================== */
            $existingPelaksanaanUji = SkPelaksanaanUji::where('jadwal_id', $jadwalId)->first();

            if ($existingPelaksanaanUji) {
                $nomorSkPelaksanaanUji = $existingPelaksanaanUji->no_surat;
            } else {
                $last = SkPelaksanaanUji::latest('id')->first();
                $noSk = $last?->no_surat ?? '0/LSP-GATENSI/SK-UJI/I/2024';

                $romawi = [
                    1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV',
                    5 => 'V', 6 => 'VI', 7 => 'VII', 8 => 'VIII',
                    9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII',
                ];

                $parts = explode('/', $noSk);
                $year  = now()->year;

                $parts[0] = ((int)($parts[3] ?? 0) === $year)
                    ? ((int)$parts[0] + 1)
                    : 1;

                $parts[2] = $romawi[now()->month];
                $parts[3] = (string)$year;

                $nomorSkPelaksanaanUji = implode('/', $parts);
            }

            /** ===============================
             *  GENERATE / REUSE SK KOMTEK NUMBER
             *  =============================== */
            $existingKomtek = SkKomtek::where('jadwal_id', $jadwalId)->first();

            if ($existingKomtek) {
                $nomorSkKomtek = $existingKomtek->no_surat;
            } else {
                $last = SkKomtek::latest('id')->first();
                $noSk = $last?->no_surat ?? '0/LSP-GKK-C.003-B/I/2024';

                $romawi = [
                    1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV',
                    5 => 'V', 6 => 'VI', 7 => 'VII', 8 => 'VIII',
                    9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII',
                ];

                $parts = explode('/', $noSk);
                $year  = now()->year;

                $parts[0] = ((int)($parts[3] ?? 0) === $year)
                    ? ((int)$parts[0] + 1)
                    : 1;

                $parts[2] = $romawi[now()->month];
                $parts[3] = (string)$year;

                $nomorSkKomtek = implode('/', $parts);
            }

            /** ===============================
             *  GENERATE BOTH SK DOCUMENTS (with TTD by default)
             *  =============================== */
            $resultPelaksanaanUji = $this->generateSkPelaksanaanUji(
                $jadwalId,
                $nomorSkPelaksanaanUji,
                $absenKomtek->created_at->toDateString(),
                $absenKomtek->no_surat,
                now()->toDateString(),
                true  // withTtd = true
            );

            $resultKomtek = $this->generateSkKomtek(
                $jadwalId,
                $nomorSkKomtek,
                now()->toDateString(),
                true  // withTtd = true
            );

            return response()->json([
                'message' => 'SK documents generated successfully.',
                'data' => [
                    'sk_pelaksanaan_uji' => $resultPelaksanaanUji,
                    'sk_komtek' => $resultKomtek,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    private function generateSkKomtek(
        int $jadwalId,
        string $nomorSk,
        string $tanggalPenetapan,
        bool $withTtd = true
    ): array
    {
        /** ===============================
         *  1. CHECK EXISTING SK KOMTEK
         *  =============================== */
        $existing = SkKomtek::where('jadwal_id', $jadwalId)->first();

        // Use existing no_surat if available, otherwise use the provided one
        if ($existing) {
            $nomorSk = $existing->no_surat;
        }

        /** ===============================
         *  2. GET JADWAL DATA FROM DATABASE
         *  =============================== */
        $jadwal = Jadwal::where('jadwal_id', $jadwalId)->firstOrFail();

        /** ===============================
         *  3. GET KOMTEK MEMBERS
         *  =============================== */
        $getNamaKomtek = function ($nomor) {
            if (!$nomor) return null;

            return User::where('id', $nomor)->value('name');
        };

        $komtekMembers = [];
        $jabatanList = ['Ketua', 'Anggota', 'Anggota'];

        for ($i = 1; $i <= 3; $i++) {
            $komtekNomor = $jadwal->{"komtek{$i}"};
            if ($komtekNomor) {
                $nama = $getNamaKomtek($komtekNomor);
                if ($nama) {
                    $komtekMembers[] = [
                        'nama' => $nama,
                        'jabatan' => $jabatanList[$i - 1] ?? 'Anggota',
                    ];
                }
            }
        }

        /** ===============================
         *  4. GENERATE QR CODE (only if withTtd)
         *  =============================== */
        $qrBase64 = null;

        if ($withTtd) {
            $barcodeUuid = (string) \Ramsey\Uuid\Uuid::uuid4();
            $qrSvg = QrCode::margin(1)->size(120)
                ->generate("https://barcode.lspgatensi.id/{$barcodeUuid}");

            // Convert SVG to PNG image
            $qrImage = $this->convertSvgToPng($qrSvg);

            // Convert PNG to base64 for PDF embedding
            $qrBase64 = base64_encode($qrImage);

            // Save to FTP server
            $path = "barcodes/{$barcodeUuid}.png";
            Storage::disk('str_moon')->put($path, $qrImage);

            // Public image URL
            $urlImage = "https://moon.lspgatensi.id/files/new_gatensi/{$path}";

            // Save / update DB
            Barcode::updateOrCreate(
                [
                    'id_user'       => auth()->id(),
                    'id_izin'       => '2220910001',
                    'jenis_dokumen' => 'SK Komtek',
                ],
                [
                    'id_jadwal' => $jadwalId,
                    'url_image' => $urlImage,
                    'url_ttd'   => "https://barcode.lspgatensi.id/{$barcodeUuid}",
                    'id_role'   => auth()->user()->role_id ?? null,
                ]
            );
        }

        /** ===============================
         *  5. GENERATE PDF
         *  =============================== */
        $viewData = [
            'nomor' => $nomorSk,
            'tanggal_penetapan' => Carbon::parse($tanggalPenetapan)->isoFormat('D MMMM YYYY'),
            'komtek_members' => $komtekMembers,
            'qr' => $qrBase64,
        ];

        $pdf = Pdf::loadView('template-surat.sk_komtek', $viewData);
        $pdfContent = $pdf->output();

        // Generate unique filename with timestamp to prevent caching
        $timestamp = now()->format('YmdHis');
        $path = "sk_komtek/{$jadwalId}_{$timestamp}.pdf";
        Storage::disk('str_moon')->put($path, $pdfContent);
        $fileUrl = "https://moon.lspgatensi.id/files/new_gatensi/{$path}";

        // Save or update SkKomtek model
        SkKomtek::updateOrCreate(
            ['jadwal_id' => $jadwalId],
            [
                'no_surat' => $nomorSk,
                'link' => $fileUrl,
            ]
        );

        return [
            'nomor_sk' => $nomorSk,
            'url' => $fileUrl,
        ];
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

    /**
     * Convert SVG string to PNG image
     */
    private function convertSvgToPng(string $svg): string
    {
        // Create a temporary image from SVG
        $image = new \Imagick();

        // Set the format to SVG
        $image->setFormat('svg');

        // Read the SVG
        $image->readImageBlob($svg);

        // Set the format to PNG
        $image->setFormat('png');

        // Get the image blob
        $pngImage = $image->getImageBlob();

        // Clear the image
        $image->clear();

        return $pngImage;
    }
}
