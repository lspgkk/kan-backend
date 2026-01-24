<?php

namespace App\Http\Controllers;

use App\Models\AsesiJadwal;
use App\Models\AsesorBnsp;
use App\Models\Jadwal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Database\QueryException;
use App\Models\KlasifikasiKualifikasi;
use App\Models\User;

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
        try {
            $validate = $request->validate([
                "jadwal_id" => ['required'],
                "asesor_id" => ['required'],
                "asesor2_id" => ['nullable'],
                "id_izin" => ['required'],
                "komtek1" => ['required'],
                "komtek2" => ['required'],
                "komtek3" => ['required'],
            ]);
        } catch (\Exception $e) {
            return back()->with('error', 'Terjadi kesalahan pada validation: ' . str($e));
        }

        $id_izin = $request->input("id_izin");
        $all_id_izin = explode(",", $id_izin);
        $error_id = [];

        DB::beginTransaction();

        foreach ($all_id_izin as $id_izin) {
            // Retrieve values from the "personals" table
            $id_izin = preg_replace('/\s+/', '', $id_izin);
            $personalsData = DB::table('personals')->where('id_izin', $id_izin)->select('nik', 'nama', 'tempat_lahir', 'tanggal_lahir', 'jenis_kelamin', 'alamat', 'kabupaten', 'propinsi', 'telepon', 'email')->first();

            if (!$personalsData) {
                dd("Hapus spasi pada ID Izin");
            }

            $jadwal_id = (int)$request->input('jadwal_id');
            $asesor_id = (int)$request->input('asesor_id');
            $asesor2_id = (int)$request->input('asesor2_id');
            $jenis_kelamin = $personalsData->jenis_kelamin;
            $nik = $personalsData->nik;
            $nama = $personalsData->nama;
            $tempat_lahir = $personalsData->tempat_lahir;
            $tanggal_lahir = $personalsData->tanggal_lahir;
            $jenis_kelamin = ($personalsData->jenis_kelamin == 'pria') ? 2 : 1;
            $alamat = $personalsData->alamat;
            $kota_id = $personalsData->kabupaten;
            $provinsi_id = $personalsData->propinsi;
            $telepon = $personalsData->telepon;
            $email = $personalsData->email;
            
            $klasifikasi = KlasifikasiKualifikasi::where('id_izin', $id_izin)->select(['jabatan_kerja', 'tuk'])->first();
            $jabker = DB::connection('mygatensi')->table('myjabatankerja')->where('id_jabatan_kerja', $klasifikasi->jabatan_kerja)->orderBy('InputDate', 'desc')->pluck('jabatan_kerja')->first();
            $skema_id = DB::connection('mygatensi')->table('myskemabnsp')->where('nama', $jabker)->orderBy('id', 'desc')->pluck('id')->first();
            
            $tuk = DB::connection("mygatensi")->table('mytuk')->select(['nama_tuk'])->where('id', $klasifikasi->tuk)->first();
            $tuk_id = DB::connection("mygatensi")->table('mytukbnsp')->select('ID')->where('Nama', $tuk->nama_tuk)->first();
            
            // dd($tuk_id->ID);

            // Retrieve values from the "pendidikans" table
            $pendidikansData = DB::table('pendidikans')->where('id_izin', $id_izin)->select('program_studi', 'no_ijazah', 'tahun_lulus', 'propinsi', 'jenjang', 'kabupaten', 'nama_sekolah_perguruan_tinggi', 'scan_surat_keterangan', 'scan_ijazah_legalisir')->first();
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
                "is_fg" => 0,
                "nik" => $nik,
                "nib" => "",
                "nama" => $nama,
                "tempat_lahir" => $tempat_lahir,
                "tanggal_lahir" => $tanggal_lahir,
                "jenis_kelamin" => $jenis_kelamin,
                "alamat" => $alamat,
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
                "jabatan_pekerjaan" => "",
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
                        array_push($error_id, ['id_izin' => $id_izin, 'msg' => $responseData['message']]);
                        continue;
                    }
                    
                    array_push($error_id, ['id_izin' => $id_izin, 'msg' => $responseData]);

                    // Log the Error
                    error_log("API Request here Failed with status code: $statusCode, response body: $responseBody");

                    // Return the error response to the user
                    // return response()->json(['error' => 'Failed to process data', 'message' => $responseData['message']], $statusCode);
                }
                // else {
                //     // Handle the exception if no response is available
                //     return response()->json(['error' => 'Failed to process data', 'message' => 'An error occurred during the API request'], 500);
                // }
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
                // return response()->json(['success' => true, 'apiData' => $apiData]);
            } catch (QueryException $e) {
                continue;
                // DB::rollBack();
                $errorMessage = 'Error putting data into the database: ' . $e->getMessage();
                return response()->json(['error' => $errorMessage], 500);
            }
        }
        DB::commit();
        if (count($error_id) == 0) {
            return redirect('persiapan-uji')->with('success', 'Berhasil input asesi ke jadwal BNSP');
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

        $jadwalId = AsesiJadwal::where('account', auth()->user()->name)
            ->value('jadwal_id');

        $jadwals = $this->baseJadwalQuery()
            ->where('jadwal_id', $jadwalId)
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

        $jadwals = $this->baseJadwalQuery()
            ->where(function ($q) {
                $q->where('komtek1', auth()->id())
                ->orWhere('komtek2', auth()->id())
                ->orWhere('komtek3', auth()->id());
            })
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

        $jadwals = $this->baseJadwalQuery()
            ->where('ketua_lsp', auth()->id())
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
            ->where('tahap', '!=', 2)
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
}
