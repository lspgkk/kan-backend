<?php

namespace App\Http\Controllers;

use App\Models\AbsenKomtek;
use App\Traits\BantuanTrait;
use App\Traits\ResolvesKomtekDecision;
use App\Models\AsesiJadwal;
use App\Models\AsesorBnsp;
use App\Models\BaKomtek;
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
    use BantuanTrait, ResolvesKomtekDecision;

    public function index(Request $request)
    {
        $query = Jadwal::select(['id', 'jadwal_id', 'nama_kegiatan', 'tanggal_uji', 'status', 'jenis_kelas'])->whereIn('status', ['sinkronisasi-bnsp', 'draft']);

        if ($request->filled('jenis_kelas')) {
            $query->where('jenis_kelas', $request->jenis_kelas);
        }

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

        if ($request->filled('jenis_kelas')) {
            $query->where('jenis_kelas', $request->jenis_kelas);
        }

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

        if ($request->filled('jenis_kelas')) {
            $query->where('jenis_kelas', $request->jenis_kelas);
        }

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
        $jadwal = Jadwal::where('id', $id)
            ->with(['sptAsesor', 'sptKomtek', 'asesiJadwals.personal', 'asesiJadwals.skema', 'tuk:id,nama,alamat'])
            ->select([
                'id',
                'jadwal_id',
                'tuk_id',
                'admin_id',
                'asesor_id',
                'asesor2_id',
                'jenis_mohon',
                'keterangan',
                'jenjang_id',
                'komtek1',
                'komtek2',
                'komtek3',
                'ketua_lsp',
                'mgr_sertif',
                'tanggal_uji',
                'sesi',
                'jenis_kelas',
                'jenis_anggaran',
                'tanggal_selesai',
                'subklasifikasi',
                'kualifikasi',
                'nama_kegiatan',
                'status',
                'started_at'
            ])
            ->first();

        if (!$jadwal) {
            return response()->json([
                'success' => false,
                'message' => 'Jadwal not found'
            ], 404);
        }

        // Transform to only include links from SPT relationships
        $data = $jadwal->toArray();
        unset($data['asesi_jadwals']); // Remove the full asesi_jadwals relationship
        $data['spt_asesor'] = $jadwal->sptAsesor ? $jadwal->sptAsesor->link : null;
        $data['spt_komtek'] = $jadwal->sptKomtek ? $jadwal->sptKomtek->link : null;

        // Add TUK info
        $data['tuk'] = $jadwal->tuk ? [
            'id' => $jadwal->tuk->id,
            'nama' => $jadwal->tuk->nama,
            'alamat' => $jadwal->tuk->alamat,
        ] : null;

        // Add list of asesi
        $data['asesi'] = $jadwal->asesiJadwals->map(function ($asesi) {
            return [
                'id_izin' => $asesi->id_izin,
                'skema_id' => $asesi->skema_id,
                'skema' => $asesi->skema ? $asesi->skema->nama : null,
                'account' => $asesi->account,
                'password' => $asesi->password_plain,
                'kompeten' => $this->resolveKomtekDecision($asesi->id_izin),
                'nama' => $asesi->personal ? $asesi->personal->nama : null,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'message' => 'Jadwal retrieved successfully',
            'data' => $data
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
        $skemaResult = $this->resolveBnspSkemaIdsFromIdIzinList($validated['all_id_izin'], $authData);

        if (!$skemaResult['success']) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil skema dari BNSP',
                'error'   => $skemaResult['error'],
            ], 500);
        }

        $allSkemaIds = $skemaResult['skema_ids'];
        $skemaSorted = $allSkemaIds;
        sort($skemaSorted);

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
         *  4️⃣ IDEMPOTENCY CHECK
         *  =============================== */
        $requestHash = hash('sha256', json_encode([
            'tuk_id' => (string)$idTuk,
            'nama_jadwal' => (string)$validated['nama_jadwal'],
            'subklasifikasi' => (string)$validated['subklasifikasi'],
            'kualifikasi' => (string)$validated['kualifikasi'],
            'tanggal_mulai' => (string)$validated['tanggal_mulai'],
            'tanggal_selesai' => (string)$validated['tanggal_selesai'],
            'skema_ids' => $skemaSorted,
        ], JSON_UNESCAPED_UNICODE));

        $lockKey = 'jadwal:' . substr($requestHash, 0, 32);
        $locked = DB::selectOne("SELECT GET_LOCK(?, 10) AS l", [$lockKey])->l ?? 0;

        if ((int)$locked !== 1) {
            return response()->json([
                'success' => false,
                'message' => 'Sistem sibuk, coba ulang.',
            ], 429);
        }

        $jadwalId = null;

        try {
            $existing = DB::table('jadwals')
                ->where('request_hash', $requestHash)
                ->first();

            if ($existing) {
                $jadwalId = $existing->jadwal_id !== null ? (int)$existing->jadwal_id : null;

                Log::info('STORE JADWAL: reuse existing jadwal', [
                    'request_hash' => $requestHash,
                    'jadwal_id' => $jadwalId,
                    'tuk_id' => $idTuk,
                ]);
            } else {
                /** ===============================
                 *  5️⃣ CREATE JADWAL BNSP
                 *  🚨 TEMPORARILY DISABLED - Using mock ID instead
                 *  =============================== */
                // DRAFT: No BNSP push - set jadwal_id to null and status to draft
                $jadwalId = null;

                Log::info('STORE JADWAL: Creating draft jadwal (no BNSP sync)', [
                    'request_hash' => $requestHash,
                ]);

                // Create a placeholder jadwal record with request_hash for idempotency
                DB::table('jadwals')->insertOrIgnore([
                    'request_hash' => $requestHash,
                    'jadwal_id' => $jadwalId,
                    'nama_kegiatan' => $validated['nama_jadwal'],
                    'status' => 'draft',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        } finally {
            DB::select("SELECT RELEASE_LOCK(?)", [$lockKey]);
        }

        /** ===============================
         *  6️⃣ ADD PESERTA (COLLECT ERRORS)
         *  =============================== */
        $errors = [];

        foreach ($validated['all_id_izin'] as $id_izin) {
            try {
                $personalsData = Personal::where('id_izin', $id_izin)->select('nik', 'nama', 'tempat_lahir', 'tanggal_lahir', 'jenis_kelamin', 'alamat', 'kabupaten', 'propinsi', 'telepon', 'email', 'account', 'password_plain', 'type')->first();

                // SIKI pre-check with type-specific endpoint
                $personalType = $personalsData->type ?? 'reguler';
                $suffixMap = ['reguler' => '/', 'balai' => '-balai/', 'fg' => '-fg/'];
                $sikiSuffix = $suffixMap[$personalType] ?? '/';
                $sikiUrl = 'https://siki.pu.go.id/siki-api/v1/permohonan-skk' . $sikiSuffix . $id_izin;
                try {
                    $sikiResponse = \Illuminate\Support\Facades\Http::withHeaders([
                        'Content-Type' => 'application/json',
                        'token' => env('SIKI_TOKEN'),
                    ])->timeout(15)->get($sikiUrl);
                    if (!$sikiResponse->successful()) {
                        \Illuminate\Support\Facades\Log::warning("SIKI pre-check failed for {$id_izin} (type: {$personalType})");
                    }
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::warning("SIKI pre-check unreachable for {$id_izin}: {$e->getMessage()}");
                }

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
                $skema_id = DB::table('skema')->whereRaw("UPPER(TRIM(REPLACE(nama, '`', ''))) = ?", [$normalizedJabker])->orderBy('id', 'desc')->pluck('id')->first();
                if (!$skema_id) {
                    $similarSkema = DB::table('skema')
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
                        'total_skema_tersedia' => DB::table('skema')
                            ->count()
                    ], 404);
                }

                $tuk = MyTuk::select(['nama_tuk'])->where('id', $klasifikasi->tuk)->first();
                $tuk_id = MyTukBnsp::select('id')->where('Nama', $tuk->nama_tuk)->first();

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


                $personalType = $personalsData->type ?? 'reguler';
                $isFg = $personalType === 'fg';

                if ($jenjang_id == 99) {
                    $jenjang_id = $isFg ? 20 : 3;
                    $no_ijasah = "1";
                    $nama_sekolah = "Non Pendidikan";
                    $prodi = "UMUM";
                    $tahun_lulus = 0;
                } else if ($pendidikansData->jenjang == 8) {
                    $jenjang_id = 7;
                }

                $apiData = [
                    "jadwal_id" => $jadwal_id,
                    "tuk_id" => $tuk_id->id,
                    "asesor_id" => $asesor_id,
                    "is_fg" => $isFg ? 1 : 0,
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

                // Include second asesor when set (optional for balai/fg)
                if ($asesor2_id) {
                    $apiData["asesor2_id"] = $asesor2_id;
                }

                // 🚨 TEMPORARY: Skip BNSP participant API call, directly create local records
                // Original BNSP API code commented out:
                // if(App::environment() == "production") {
                //    $httpClient = new \GuzzleHttp\Client();
                //    $authData = DB::table('b_n_s_p_auths')->select('x_authorization')->first()->x_authorization;
                //    $apiEndpoint = 'https://konstruksi.bnsp.go.id/api/v1/jadwal/peserta';
                //    try {
                //        $response = $httpClient->request('POST', $apiEndpoint, [
                //            'headers' => [
                //                'x-authorization' => $authData,
                //                'Content-Type' => 'application/json',
                //                'Accept' => 'application/json',
                //            ],
                //            'json' => $apiData,
                //        ]);
                //        $responseBody = $response->getBody()->getContents();
                //        $responseData = json_decode($responseBody, true);
                //    } catch (RequestException $e) {
                //        // Error handling
                //    }
                // }

                Log::info('STORE JADWAL: TEMPORARY - Skipping BNSP participant API', [
                    'jadwal_id' => $apiData['jadwal_id'],
                    'id_izin' => $id_izin,
                ]);

                try {
                    // DRAFT: create or update Jadwal (once per request_hash)
                    $jadwal = Jadwal::where('request_hash', $requestHash)->first();

                    if (!$jadwal) {
                        // Create Jadwal only once per request_hash
                        $jadwal = Jadwal::create([
                            "jadwal_id" => null,
                            "admin_id" => $validated['admin_tuk'],
                            "tuk_id" => $apiData['tuk_id'],
                            "asesor_id" => $apiData['asesor_id'],
                            "asesor2_id" => $asesor2_id,
                            "jenis_mohon" => $apiData['jenis_mohon'],
                            "keterangan" => $apiData['keterangan'],
                            "jenjang_id" => $apiData['jenjang_id'],
                            "komtek1" => $request->input("komtek1"),
                            "komtek2" => $request->input("komtek2"),
                            "komtek3" => $request->input("komtek3"),
                            "ketua_lsp" => $validated['ketua_lsp'],
                            "mgr_sertif" => $validated['manajer_sertifikasi'],
                            "sesi" => $validated['sesi'],
                            "jenis_kelas" => $validated['jenis_kelas'],
                            "jenis_anggaran" => $validated['jenis_anggaran'],
                            "tanggal_uji" => $validated['tanggal_mulai'],
                            "tanggal_selesai" => $validated['tanggal_selesai'],
                            "subklasifikasi" => $validated['subklasifikasi'],
                            "kualifikasi" => $validated['kualifikasi'],
                            "status" => "draft",
                            "tahap" => 0,
                        ]);
                    } elseif (!$jadwal->asesor_id || !$jadwal->tuk_id) {
                        // Update only if jadwal is missing critical data (from insertOrIgnore)
                        $jadwal->update([
                            "admin_id" => $validated['admin_tuk'],
                            "tuk_id" => $apiData['tuk_id'],
                            "asesor_id" => $apiData['asesor_id'],
                            "asesor2_id" => $asesor2_id,
                            "jenis_mohon" => $apiData['jenis_mohon'],
                            "keterangan" => $apiData['keterangan'],
                            "jenjang_id" => $apiData['jenjang_id'],
                            "komtek1" => $request->input("komtek1"),
                            "komtek2" => $request->input("komtek2"),
                            "komtek3" => $request->input("komtek3"),
                            "ketua_lsp" => $validated['ketua_lsp'],
                            "mgr_sertif" => $validated['manajer_sertifikasi'],
                            "sesi" => $validated['sesi'],
                            "jenis_kelas" => $validated['jenis_kelas'],
                            "jenis_anggaran" => $validated['jenis_anggaran'],
                            "tanggal_uji" => $validated['tanggal_mulai'],
                            "tanggal_selesai" => $validated['tanggal_selesai'],
                            "subklasifikasi" => $validated['subklasifikasi'],
                            "kualifikasi" => $validated['kualifikasi'],
                        ]);
                    }

                    // For drafts, use local PK as temporary jadwal_id so Eloquent relationships work
                    if ($jadwal->jadwal_id === null) {
                        $jadwal->jadwal_id = $jadwal->id;
                        $jadwal->save();
                    }

                    // Reuse credentials from Personal (set during verification) if available
                    if ($personalsData->account && $personalsData->password_plain) {
                        $account = $personalsData->account;
                        $password = $personalsData->password_plain;
                        $userExists = User::where('email', $account)->exists();
                    } else {
                        $account = strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8));
                        $password = strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8));
                        $userExists = false;
                    }

                    // Create AsesiJadwal for each asesi
                    AsesiJadwal::create([
                        "jadwal_id" => $jadwal->jadwal_id,
                        "id_izin" => $id_izin,
                        "skema_id" => $skema_id,
                        "account" => $account,
                        "password_plain" => $password,
                        "kompeten" => "BK",
                        "is_started" => false,
                    ]);

                    // Create User only if not already created during verification
                    if (!$userExists) {
                        User::create([
                            "name" => $personalsData->nama,
                            "email" => $account,
                            "phone" => $personalsData->telepon ?? null,
                            "password" => bcrypt($password),
                            "address" => $personalsData->alamat ?? null,
                            "role_id" => 8, // Asesi role
                        ]);
                    }
                } catch (\Throwable $e) {
                $errors[] = [
                    'id_izin' => $id_izin,
                    'error'   => $e->getMessage(),
                ];
            }
            } catch (\Exception $e) {
                $errors[] = [
                    'id_izin' => $id_izin,
                    'error'   => $e->getMessage(),
                ];
            }
        }

        /** ===============================
         *  📄 GENERATE INITIAL DOCUMENTS (DRAFT MODE)
         *  Uses local jadwal ID (jadwal_id = local PK for drafts)
         *  =============================== */
        $createdJadwal = Jadwal::where('request_hash', $requestHash)->first();
        if ($createdJadwal) {
            $this->generateInitialDocuments($createdJadwal->jadwal_id);
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

    /**
     * Push draft jadwal to BNSP and update status to sinkronisasi-bnsp
     */
    public function postToBnsp($id)
    {
        $jadwal = Jadwal::with('asesiJadwals')->where('id', $id)->first();

        if (!$jadwal) {
            return response()->json([
                'success' => false,
                'message' => 'Jadwal tidak ditemukan',
            ], 404);
        }

        if ($jadwal->status !== 'draft') {
            return response()->json([
                'success' => false,
                'message' => 'Status jadwal bukan draft',
            ], 400);
        }

        $authData = DB::table('b_n_s_p_auths')->value('x_authorization');

        // Load participant id_izin list
        $idIzinList = $jadwal->asesiJadwals->pluck('id_izin')->toArray();

        if (empty($idIzinList)) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada peserta pada jadwal ini',
            ], 400);
        }

        // Resolve skema IDs from BNSP
        $skemaResult = $this->resolveBnspSkemaIdsFromIdIzinList($idIzinList, $authData);

        if (!$skemaResult['success']) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil skema dari BNSP',
                'error'   => $skemaResult['error'],
            ], 500);
        }

        $allSkemaIds = $skemaResult['skema_ids'];
        $idTuk = (int) $jadwal->tuk_id;

        // Push jadwal to BNSP
        $validated = [
            'jenis_anggaran'  => $jadwal->jenis_anggaran,
            'nama_jadwal'     => $jadwal->nama_kegiatan,
            'subklasifikasi'  => $jadwal->subklasifikasi,
            'tanggal_mulai'   => $jadwal->tanggal_uji,
            'tanggal_selesai' => $jadwal->tanggal_selesai,
        ];

        $bnspResult = $this->pushJadwalToBnsp(new Request(), $validated, $idTuk, $allSkemaIds, $authData);

        if (!$bnspResult['success']) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat jadwal di BNSP',
                'error'   => $bnspResult['error'],
            ], 500);
        }

        $realJadwalId = $this->extractBnspJadwalId($bnspResult['data'] ?? []);

        if (!$realJadwalId) {
            return response()->json([
                'success' => false,
                'message' => 'Jadwal dibuat di BNSP, tapi jadwal_id tidak ditemukan.',
            ], 500);
        }

        // Update local jadwal with real BNSP jadwal_id and status
        $localIdBefore = $jadwal->jadwal_id; // Save temp local id for AsesiJadwal lookup
        $jadwal->update([
            'jadwal_id' => $realJadwalId,
            'status'    => 'sinkronisasi-bnsp',
        ]);

        // Update related AsesiJadwal records with the real BNSP jadwal_id
        AsesiJadwal::where('jadwal_id', $localIdBefore)->update(['jadwal_id' => $realJadwalId]);

        // Push each participant (asesi) to BNSP
        $participantErrors = [];

        foreach ($idIzinList as $id_izin) {
            try {
                $personalsData = Personal::where('id_izin', $id_izin)
                    ->select('nik', 'nama', 'tempat_lahir', 'tanggal_lahir', 'jenis_kelamin', 'alamat', 'kabupaten', 'propinsi', 'telepon', 'email', 'type')
                    ->first();

                if (!$personalsData) {
                    throw new \Exception("Data personal tidak ditemukan untuk id_izin {$id_izin}");
                }

                $asesor_id  = (int) $jadwal->asesor_id;
                $asesor2_id = $jadwal->asesor2_id;
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

                $klasifikasi = KlasifikasiKualifikasi::where('id_izin', $id_izin)
                    ->select(['jabatan_kerja', 'tuk'])
                    ->first();

                if (!$klasifikasi) {
                    throw new \Exception("Data klasifikasi tidak ditemukan untuk id_izin {$id_izin}");
                }

                $jabker = JabatanKerja::where('id_jabatan_kerja', $klasifikasi->jabatan_kerja)
                    ->orderBy('InputDate', 'desc')
                    ->pluck('jabatan_kerja')
                    ->first();
                $normalizedJabker = strtoupper(trim($jabker));
                $skema_id = DB::table('skema')
                    ->whereRaw("UPPER(TRIM(REPLACE(nama, '`', ''))) = ?", [$normalizedJabker])
                    ->orderBy('id', 'desc')
                    ->pluck('id')
                    ->first();

                if (!$skema_id) {
                    throw new \Exception("Skema tidak ditemukan untuk jabatan: {$jabker} (id_izin: {$id_izin})");
                }

                $tuk = MyTuk::select(['nama_tuk'])->where('id', $klasifikasi->tuk)->first();
                $tuk_id = MyTukBnsp::select('id')->where('Nama', $tuk->nama_tuk)->first();

                if (!$tuk_id) {
                    throw new \Exception("TUK BNSP tidak ditemukan untuk TUK: {$tuk->nama_tuk} (id_izin: {$id_izin})");
                }

                $pendidikansData = Pendidikan::where('id_izin', $id_izin)
                    ->select('program_studi', 'no_ijazah', 'tahun_lulus', 'propinsi', 'jenjang', 'kabupaten', 'nama_sekolah_perguruan_tinggi', 'scan_surat_keterangan', 'scan_ijazah_legalisir')
                    ->first();

                if (!$pendidikansData) {
                    throw new \Exception("Data pendidikan tidak ditemukan untuk id_izin {$id_izin}");
                }

                $prodi = $pendidikansData->program_studi;
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

                $personalType = $personalsData->type ?? 'reguler';
                $isFg = $personalType === 'fg';

                if ($jenjang_id == 99) {
                    $jenjang_id = $isFg ? 20 : 3;
                    $no_ijasah = "1";
                    $nama_sekolah = "Non Pendidikan";
                    $prodi = "UMUM";
                    $tahun_lulus = 0;
                } elseif ($pendidikansData->jenjang == 8) {
                    $jenjang_id = 7;
                }

                $apiData = [
                    "jadwal_id"         => $realJadwalId,
                    "tuk_id"            => $tuk_id->id,
                    "asesor_id"         => $asesor_id,
                    "is_fg"             => $isFg ? 1 : 0,
                    "nik"               => $nik,
                    "nib"               => "",
                    "nama"              => $nama,
                    "tempat_lahir"      => $tempat_lahir,
                    "tanggal_lahir"     => $tanggal_lahir,
                    "jenis_kelamin"     => $jenis_kelamin,
                    "alamat"            => $alamat ?? null,
                    "kota_id"           => $kota_id,
                    "provinsi_id"       => $provinsi_id,
                    "negara_id"         => 1,
                    "telepon"           => $telepon,
                    "email"             => $email,
                    "jenis_mohon"       => 1,
                    "skema_id"          => $skema_id,
                    "keterangan"        => "-",
                    "jenjang_id"        => (int)$jenjang_id,
                    "prodi"             => $prodi,
                    "no_ijasah"         => $no_ijasah,
                    "tanggal_ijazah"    => '0000-00-00',
                    "tahun_lulus"       => $tahun_lulus,
                    "kota_sekolah"      => (int)$kota_sekolah,
                    "prov_sekolah"      => (int)$prov_sekolah,
                    "negara_sekolah"    => 1,
                    "nama_sekolah"      => $nama_sekolah,
                    "pekerjaan"         => 89,
                    "instansi_pekerjaan" => "",
                    "jabatan_pekerjaan" => $jabker,
                    "file_foto"         => $file_foto,
                    "file_ktp"          => $file_ktp,
                    "file_nib"          => "-",
                    "file_ijazah"       => $file_ijazah,
                ];

                // Include second asesor when set (optional for balai/fg)
                if ($asesor2_id) {
                    $apiData["asesor2_id"] = $asesor2_id;
                }

                $httpClient = new \GuzzleHttp\Client([
                    'timeout' => 30,
                    'connect_timeout' => 10,
                ]);
                $apiEndpoint = 'https://konstruksi.bnsp.go.id/api/v1/jadwal/peserta';

                $response = $httpClient->request('POST', $apiEndpoint, [
                    'headers' => [
                        'x-authorization' => $authData,
                        'Content-Type'    => 'application/json',
                        'Accept'          => 'application/json',
                    ],
                    'json' => $apiData,
                ]);

                $statusCode = $response->getStatusCode();
                Log::info('POST KE BNSP: Participant pushed successfully', [
                    'id_izin'    => $id_izin,
                    'jadwal_id'  => $realJadwalId,
                    'status'     => $statusCode,
                ]);
            } catch (\Throwable $e) {
                $participantErrors[] = [
                    'id_izin' => $id_izin,
                    'error'   => $e->getMessage(),
                ];

                Log::error('POST KE BNSP: Participant push failed', [
                    'id_izin'   => $id_izin,
                    'jadwal_id' => $realJadwalId,
                    'error'     => $e->getMessage(),
                ]);
            }
        }

        // Generate documents with the real jadwal_id
        $this->generateInitialDocuments($realJadwalId);

        return response()->json([
            'success'             => true,
            'message'             => 'Jadwal berhasil di-sync ke BNSP',
            'jadwal_id'           => $realJadwalId,
            'participant_errors'  => $participantErrors,
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

            $romawi = $this->bulanRomawi(now()->month);

            $parts = explode('/', $noSpt);
            $year  = now()->year;

            $parts[0] = ((int) $parts[3] === $year) ? ((int) $parts[0] + 1) : 1;
            $parts[2] = $this->bulanRomawi(now()->month);
            $parts[3] = (string) $year;

            $noSpt = implode('/', $parts);
        }

        /** ===============================
         *  3. RESOLVE ASESOR & TUK
         *  =============================== */
        $jadwal = Jadwal::where('jadwal_id', $jadwalId)->firstOrFail();

        $asesor1 = DB::table('asesor_bnsp')
            ->where('id', $jadwal->asesor_id)
            ->first();

        if (!$asesor1) {
            throw new \Exception('Asesor 1 tidak ditemukan');
        }

        $asesor2 = null;
        if ($jadwal->asesor2_id) {
            $asesor2 = DB::table('asesor_bnsp')
                ->where('id', $jadwal->asesor2_id)
                ->first();
        }

        $tuk = DB::table('mytukbnsp')
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

        $direkturName = User::where('role_id', 6)->where('is_deleted', false)->value('name')
            ?? 'Radinal Efendy, S.T.';

        $viewData = [
            'no_surat'      => $noSpt,
            'nama_asesor'   => $asesor1->nama,
            'nama_met'      => $asesor1->noreg,
            'nama_asesor2'  => $asesor2->nama ?? '',
            'nama_met2'     => $asesor2->noreg ?? '',
            'nama_tuk'      => $responseJadwal['dataJadwal']['nama_tuk'],
            'alamat_tuk'    => $tuk->alamat,
            'hari_tanggal'  => Carbon::parse(
                $responseJadwal['dataJadwal']['tanggal_mulai']
            )->isoFormat('dddd, D MMMM YYYY'),
            'jmlh_peserta'  => count($peserta),
            'nama_skema'    => implode(', ', $namaSkema),
            'nama_peserta'  => $peserta,
            'qr'            => $qrSvg, // null if no ttd
            'direktur_name' => $direkturName,
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
            $base = $last?->no_surat ?? '0/LSP GKK B.001-D/I/2024';

            $romawi = $this->bulanRomawi(now()->month);

            $parts = explode('/', $base);
            $year  = now()->year;

            $parts[0] = ((int)($parts[3] ?? 0) === $year)
                ? ((int)$parts[0] + 1)
                : 1;

            $parts[2] = $this->bulanRomawi(now()->month);
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
            'nomor'        => $spt_no,
            'nama_komtek1' => $nama_komtek1,
            'nama_komtek2' => $nama_komtek2,
            'nama_komtek3' => $nama_komtek3,
            'nama_tuk'     => $responseJadwal['dataJadwal']['nama_tuk'],
            'alamat_tuk'   => $foundTuk->alamat,
            'hari_tanggal' => Carbon::parse(
                $responseJadwal['dataJadwal']['tanggal_mulai']
            )->isoFormat('dddd, D MMMM YYYY'),
            'jmlh_peserta' => count($peserta),
            'nama_skema'   => implode(', ', $namaSkema),
            'peserta'      => $peserta,
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
        $hasilSertifikasi = [];
        foreach ($asesiData as $index => $asesi) {
            $hasil = $this->resolveKomtekDecision($asesi['id_izin']) ?? '-';

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
            'nomor_sk' => $nomorSk,
            'tanggal_pleno_komtek' => Carbon::parse($tanggalPlenoKomtek)->isoFormat('D MMMM YYYY'),
            'nomor_pleno_komtek' => $nomorPlenoKomtek,
            'tanggal_uji' => $tanggalUji->isoFormat('D MMMM YYYY'),
            'tanggal_direktur_lsp' => Carbon::parse($tanggalDirekturLsp)->isoFormat('D MMMM YYYY'),
            'peserta' => $hasilSertifikasi,
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
                $noSk = $last?->no_surat ?? '0/LSP GKK B.003-B/I/2024';

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

                $parts[2] = $this->bulanRomawi(now()->month);
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
                $noSk = $last?->no_surat ?? '0/LSP GKK B.003-A/I/2024';

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

                $parts[2] = $this->bulanRomawi(now()->month);
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
                'is_approved' => $withTtd,
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
            'jenjang_id',
            'tanggal_uji',
            'tanggal_selesai',
            'is_started',
            'is_started_praasesmen',
            'jenis_kelas',
            'tahap',
            'sk_pelaksanaan_uji_direktur_approved',
            'spt_asesor_direktur_approved',
            'spt_komtek_direktur_approved',
        ])->with([
            'tuk:id,nama,alamat',
            'asesor:id,nama,noreg',
            'asesor2:id,nama,noreg',
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

    public function getKegiatanAsesi(Request $request)
    {
        if ($resp = $this->checkRole('8')) {
            return $resp;
        }

        $jadwalId = AsesiJadwal::where('account', auth()->user()->email)
            ->value('jadwal_id');

        $asesiJadwal = AsesiJadwal::where('account', auth()->user()->email)
            ->with('skema')
            ->first();

        $jadwals = $this->baseJadwalQuery()
            ->where('jadwal_id', $jadwalId)
            ->where('tahap', '!=', 0)
            ->when($request->filled('search'), function ($q) use ($request) {
                $q->where('nama_kegiatan', 'like', '%' . $request->search . '%');
            })
            ->first();

        if ($jadwals) {
            $jadwalsArray = $jadwals->toArray();
            $jadwalsArray['skema_id'] = $asesiJadwal?->skema_id;
            $jadwalsArray['skema'] = $asesiJadwal?->skema
                ? ['id' => $asesiJadwal->skema->id, 'nama' => $asesiJadwal->skema->nama]
                : null;
            $jadwals = $jadwalsArray;
        }

        return response()->json([
            'message' => 'Kegiatan Asesi retrieved successfully.',
            'data' => $jadwals
        ]);
    }

    public function getKegiatanAsesor(Request $request)
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
            ->when($request->filled('search'), function ($q) use ($request) {
                $q->where('nama_kegiatan', 'like', '%' . $request->search . '%');
            })
            ->when($request->has('tahap'), function ($q) use ($request) {
                $q->where('tahap', (int) $request->tahap);
            })
            ->whereDate('tanggal_uji', today())
            ->with(['asesiJadwals.skema', 'asesiJadwals.personal'])
            ->get()
            ->map(function ($j) {
                $jArr = $j->toArray();
                $jArr['asesi'] = $j->asesiJadwals->map(function ($a) {
                    return [
                        'id_izin'   => $a->id_izin,
                        'skema_id'  => $a->skema_id,
                        'skema'     => $a->skema ? ['id' => $a->skema->id, 'nama' => $a->skema->nama] : null,
                        'kompeten'  => $this->resolveKomtekDecision($a->id_izin),
                        'nama'      => $a->personal?->nama,
                    ];
                })->values();
                unset($jArr['asesi_jadwals']);
                return $jArr;
            });

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

        $jadwals = $this->baseJadwalQuery()
        ->leftJoin('ba_komteks', 'ba_komteks.jadwal_id', '=', 'jadwals.jadwal_id')
        ->where(function ($q) use ($userId) {
            $q->where('komtek1', $userId)
            ->orWhere('komtek2', $userId)
            ->orWhere('komtek3', $userId);
        })
        ->when($request->has('ttd'), function ($q) use ($request, $userId) {
            $ttd = filter_var($request->query('ttd'), FILTER_VALIDATE_BOOLEAN);
            $q->where(function ($sq) use ($ttd, $userId) {
                $sq->where(function ($s) use ($ttd, $userId) {
                    $s->where('komtek1', $userId)
                    ->where(function ($s2) use ($ttd) {
                        if ($ttd) {
                            $s2->where('ba_komteks.has_ttd_komtek1', true);
                        } else {
                            $s2->whereNull('ba_komteks.has_ttd_komtek1')
                                ->orWhere('ba_komteks.has_ttd_komtek1', false);
                        }
                    });
                })
                ->orWhere(function ($s) use ($ttd, $userId) {
                    $s->where('komtek2', $userId)
                    ->where(function ($s2) use ($ttd) {
                        if ($ttd) {
                            $s2->where('ba_komteks.has_ttd_komtek2', true);
                        } else {
                            $s2->whereNull('ba_komteks.has_ttd_komtek2')
                                ->orWhere('ba_komteks.has_ttd_komtek2', false);
                        }
                    });
                })
                ->orWhere(function ($s) use ($ttd, $userId) {
                    $s->where('komtek3', $userId)
                    ->where(function ($s2) use ($ttd) {
                        if ($ttd) {
                            $s2->where('ba_komteks.has_ttd_komtek3', true);
                        } else {
                            $s2->whereNull('ba_komteks.has_ttd_komtek3')
                                ->orWhere('ba_komteks.has_ttd_komtek3', false);
                        }
                    });
                });
            });
        })
        ->select('jadwals.*')
        ->when($request->filled('search'), function ($q) use ($request) {
            $q->where('nama_kegiatan', 'like', '%' . $request->search . '%');
        })
        ->with(['asesiJadwals.skema', 'asesiJadwals.personal'])
        ->paginate($this->perPage($request))
        ->through(function ($j) {
            $jArr = $j->toArray();
            $jArr['asesi'] = $j->asesiJadwals->map(function ($a) {
                return [
                    'id_izin'   => $a->id_izin,
                    'skema_id'  => $a->skema_id,
                    'skema'     => $a->skema ? ['id' => $a->skema->id, 'nama' => $a->skema->nama] : null,
                    'kompeten'  => $this->resolveKomtekDecision($a->id_izin),
                    'nama'      => $a->personal?->nama,
                ];
            })->values();
            unset($jArr['asesi_jadwals']);
            return $jArr;
        });

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
        $stage = $request->query('stage');

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
            ->when($stage === 'sk-komtek', function ($q) {
                $q->where('sk_komtek_direktur_approved', false);
            })
            ->when($stage === 'sk-pelaksanaan-uji', function ($q) {
                $q->where('sk_pelaksanaan_uji_direktur_approved', false);
            })
            ->when($stage === 'spt-asesor', function ($q) {
                $q->where('spt_asesor_direktur_approved', false);
            })
            ->when($stage === 'spt-komtek', function ($q) {
                $q->where('spt_komtek_direktur_approved', false);
            })
            ->when($request->filled('search'), function ($q) use ($request) {
                $q->where('nama_kegiatan', 'like', '%' . $request->search . '%');
            })
            ->with(['asesiJadwals.skema', 'asesiJadwals.personal'])
            ->paginate($this->perPage($request))
            ->through(function ($j) {
                $jArr = $j->toArray();
                $jArr['asesi'] = $j->asesiJadwals->map(function ($a) {
                    return [
                        'id_izin'   => $a->id_izin,
                        'skema_id'  => $a->skema_id,
                        'skema'     => $a->skema ? ['id' => $a->skema->id, 'nama' => $a->skema->nama] : null,
                        'kompeten'  => $this->resolveKomtekDecision($a->id_izin),
                        'nama'      => $a->personal?->nama,
                    ];
                })->values();
                unset($jArr['asesi_jadwals']);
                return $jArr;
            });

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
            ->when($request->filled('search'), function ($q) use ($request) {
                $q->where('nama_kegiatan', 'like', '%' . $request->search . '%');
            })
            ->with(['asesiJadwals.skema', 'asesiJadwals.personal'])
            ->paginate($this->perPage($request))
            ->through(function ($j) {
                $jArr = $j->toArray();
                $jArr['asesi'] = $j->asesiJadwals->map(function ($a) {
                    return [
                        'id_izin'   => $a->id_izin,
                        'skema_id'  => $a->skema_id,
                        'skema'     => $a->skema ? ['id' => $a->skema->id, 'nama' => $a->skema->nama] : null,
                        'kompeten'  => $this->resolveKomtekDecision($a->id_izin),
                        'nama'      => $a->personal?->nama,
                    ];
                })->values();
                unset($jArr['asesi_jadwals']);
                return $jArr;
            });

        return response()->json([
            'message' => 'Kegiatan Admin TUK retrieved successfully.',
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
        })->whereNotIn('id_izin', function ($q) {
            $q->select('id_izin')
            ->from('asesi_jadwals');
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

    /**
     * Resolve BNSP skema IDs from id_izin list
     */
    private function resolveBnspSkemaIdsFromIdIzinList(array $idIzinList, string $authData): array
    {
        $allSkemaIds = [];

        $jabatanKerjaList = KlasifikasiKualifikasi::whereIn('id_izin', $idIzinList)
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
                return [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'skema_ids' => [],
                ];
            }
        }

        return [
            'success' => true,
            'skema_ids' => $allSkemaIds,
        ];
    }

    /**
     * Push jadwal to BNSP API
     */
    private function pushJadwalToBnsp(Request $request, array $validated, int $idTuk, array $allSkemaIds, string $authData): array
    {
        $traceId = (string) \Illuminate\Support\Str::uuid();
        $apiEndpoint = 'https://konstruksi.bnsp.go.id/api/v1/jadwal';

        $apiData = [
            "tuk_id"            => $idTuk,
            "jenis_jadwal"      => 6,
            "jenis_anggaran"    => $validated['jenis_anggaran'],
            "nama_jadwal"       => $validated['nama_jadwal'],
            "klasifikasi_id"    => "SI",
            "subklasifikasi_id" => str_replace('SI', '', $validated['subklasifikasi']),
            "tanggal_mulai"     => \Carbon\Carbon::parse($validated['tanggal_mulai'])->format('Y-m-d'),
            "tanggal_selesai"   => \Carbon\Carbon::parse($validated['tanggal_selesai'])->format('Y-m-d'),
            "keterangan"        => "",
            "jabker_id"         => $allSkemaIds,
        ];

        Log::info('BNSP CREATE JADWAL: request prepared', [
            'trace_id' => $traceId,
            'endpoint' => $apiEndpoint,
            'payload'  => $apiData,
        ]);

        $client = new \GuzzleHttp\Client([
            'timeout' => 30,
            'connect_timeout' => 10,
        ]);

        try {
            $response = $client->post($apiEndpoint, [
                'headers' => [
                    'x-authorization' => $authData,
                    'Accept'          => 'application/json',
                    'Content-Type'    => 'application/json',
                ],
                'json' => $apiData,
            ]);

            $statusCode = $response->getStatusCode();
            $rawBody    = (string) $response->getBody();
            $jsonBody   = json_decode($rawBody, true);

            Log::info('BNSP CREATE JADWAL: success', [
                'trace_id' => $traceId,
                'status'   => $statusCode,
                'endpoint' => $apiEndpoint,
                'payload'  => $apiData,
                'raw_body' => $rawBody,
                'json'     => $jsonBody,
            ]);

            return [
                'success'  => true,
                'trace_id' => $traceId,
                'status'   => $statusCode,
                'data'     => $jsonBody,
                'raw'      => $rawBody,
                'payload'  => $apiData,
            ];
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $statusCode = null;
            $rawBody    = null;
            $jsonBody   = null;

            if ($e->hasResponse()) {
                $res        = $e->getResponse();
                $statusCode = $res->getStatusCode();
                $rawBody    = (string) $res->getBody();
                $jsonBody   = json_decode($rawBody, true);
            }

            Log::error('BNSP CREATE JADWAL: failed', [
                'trace_id' => $traceId,
                'status'   => $statusCode,
                'endpoint' => $apiEndpoint,
                'payload'  => $apiData,
                'raw_body' => $rawBody,
                'json'     => $jsonBody,
                'error'    => $e->getMessage(),
            ]);

            return [
                'success'  => false,
                'trace_id' => $traceId,
                'status'   => $statusCode,
                'data'     => $jsonBody,
                'raw'      => $rawBody,
                'payload'  => $apiData,
                'error'    => $e->getMessage(),
            ];
        }
    }

    /**
     * Extract jadwal_id from BNSP response
     */
    private function extractBnspJadwalId(array $bnspData): ?int
    {
        $candidates = [
            $bnspData['data']['jadwal_id'] ?? null,
            $bnspData['data']['id_jadwal'] ?? null,
            $bnspData['data']['id'] ?? null,
            $bnspData['jadwal_id'] ?? null,
            $bnspData['id'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_numeric($candidate) && (int)$candidate > 0) {
                return (int)$candidate;
            }
        }

        return null;
    }

    /**
     * Generate initial documents for jadwal without QR codes
     * Creates: SkPelaksanaanUji, SPTAsesor, SPTKomtek, BaKomtek
     */
    public function generateInitialDocuments(int $jadwalId): void
    {
        $generators = [
            'sk_pelaksanaan_uji' => fn () => $this->generateSkPelaksanaanUjiWithoutTtd($jadwalId),
            'spt_asesor'         => fn () => $this->generateSptAsesorWithoutTtd($jadwalId),
            'spt_komtek'         => fn () => $this->generateSptKomtekWithoutTtd($jadwalId),
            'ba_komtek'          => fn () => $this->generateBaKomtekWithoutTtd($jadwalId),
        ];

        foreach ($generators as $name => $gen) {
            try {
                $gen();
            } catch (\Throwable $e) {
                Log::error("Gagal generate {$name} (initial)", [
                    'jadwal_id' => $jadwalId,
                    'error'     => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Generate SK Pelaksanaan Uji without TTD/QR
     */
    private function generateSkPelaksanaanUjiWithoutTtd(int $jadwalId): array
    {
        $last = SkPelaksanaanUji::latest('id')->first();
        $noSk = $last?->no_surat ?? '0/LSP GKK B.003-B/I/2024';

        $romawi = [
            1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV',
            5 => 'V', 6 => 'VI', 7 => 'VII', 8 => 'VIII',
            9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII',
        ];

        $parts = explode('/', $noSk);
        $year  = now()->year;

        $parts[0] = ((int)($parts[3] ?? 0) === $year) ? ((int)$parts[0] + 1) : 1;
        $parts[2] = $this->bulanRomawi(now()->month);
        $parts[3] = (string)$year;

        $nomorSk = implode('/', $parts);

        // Get jadwal data
        $jadwal = Jadwal::where('jadwal_id', $jadwalId)->firstOrFail();
        $asesiJadwals = AsesiJadwal::where('jadwal_id', $jadwalId)->with(['personal'])->get();

        $peserta = [];
        foreach ($asesiJadwals as $asesi) {
            $jabatanKerjaId = KlasifikasiKualifikasi::where('id_izin', $asesi->id_izin)->value('jabatan_kerja');
            $namaSkema = JabatanKerja::where('id_jabatan_kerja', $jabatanKerjaId)->value('jabatan_kerja');
            $peserta[] = [
                'nama' => $asesi->personal->nama ?? '',
                'skema' => $namaSkema ?? '',
            ];
        }

        $viewData = [
            'nomor_sk' => $nomorSk,
            'tanggal_uji' => Carbon::parse($jadwal->tanggal_uji)->isoFormat('dddd, D MMMM Y'),
            'peserta' => $peserta,
            'qr' => null, // No QR code
        ];

        $pdf = PDF::loadView('template-surat.sk_pelaksanaan_uji', $viewData);
        $pdfContent = $pdf->output();

        $timestamp = now()->format('YmdHis');
        $path = "sk_pelaksanaan_uji/{$jadwalId}_{$timestamp}.pdf";
        Storage::disk('str_moon')->put($path, $pdfContent);
        $fileUrl = "https://moon.lspgatensi.id/files/new_gatensi/{$path}";

        SkPelaksanaanUji::updateOrCreate(
            ['jadwal_id' => $jadwalId],
            [
                'no_surat' => $nomorSk,
                'link' => $fileUrl,
                'is_approved' => false,
            ]
        );

        return [
            'nomor_sk' => $nomorSk,
            'url' => $fileUrl,
        ];
    }

    /**
     * Generate SPT Asesor without TTD/QR
     */
    private function generateSptAsesorWithoutTtd(int $jadwalId): array
    {
        $last = SPTAsesor::latest('id')->first();
        $noSpt = $last?->spt_no ?? '0/LSP GKK B.012-D/I/2024';

        $romawi = [
            1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV',
            5 => 'V', 6 => 'VI', 7 => 'VII', 8 => 'VIII',
            9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII',
        ];

        $parts = explode('/', $noSpt);
        $year  = now()->year;

        $parts[0] = ((int)($parts[3] ?? 0) === $year) ? ((int)$parts[0] + 1) : 1;
        $parts[2] = $this->bulanRomawi(now()->month);
        $parts[3] = (string)$year;

        $noSpt = implode('/', $parts);

        // Get jadwal data
        $jadwal = Jadwal::where('jadwal_id', $jadwalId)->firstOrFail();
        $asesor1 = AsesorBnsp::where('id', $jadwal->asesor_id)->first();
        $asesor2 = $jadwal->asesor2_id ? AsesorBnsp::where('id', $jadwal->asesor2_id)->first() : null;

        $asesiJadwals = AsesiJadwal::where('jadwal_id', $jadwalId)->with(['personal'])->get();
        $peserta = $asesiJadwals->pluck('personal.nama')->toArray();

        $tuk = $jadwal->tuk;
        $namaSkemaList = [];
        foreach ($asesiJadwals as $asesi) {
            $jabatanKerjaId = KlasifikasiKualifikasi::where('id_izin', $asesi->id_izin)->value('jabatan_kerja');
            $skema = JabatanKerja::where('id_jabatan_kerja', $jabatanKerjaId)->value('jabatan_kerja');
            if ($skema) $namaSkemaList[] = $skema;
        }
        $namaSkemaList = array_unique($namaSkemaList);

        $direkturName = User::where('role_id', 6)->where('is_deleted', false)->value('name')
            ?? 'Radinal Efendy, S.T.';

        $viewData = [
            'no_surat' => $noSpt,
            'nama_asesor' => $asesor1->nama ?? '',
            'nama_met' => $asesor1->noreg ?? '',
            'nama_asesor2' => $asesor2->nama ?? '',
            'nama_met2' => $asesor2->noreg ?? '',
            'nama_tuk' => $tuk->nama ?? '',
            'alamat_tuk' => $tuk->alamat ?? '',
            'hari_tanggal' => Carbon::parse($jadwal->tanggal_uji)->isoFormat('dddd, D MMMM Y'),
            'jmlh_peserta' => count($peserta),
            'nama_skema' => implode(', ', $namaSkemaList),
            'nama_peserta' => $peserta,
            'qr' => null,
            'direktur_name' => $direkturName,
        ];

        $pdf = PDF::loadView('template-surat.spt_asesor', $viewData);
        $pdfContent = $pdf->output();

        $path = "spt_asesor/{$jadwalId}.pdf";
        Storage::disk('str_moon')->put($path, $pdfContent);
        $fileUrl = "https://moon.lspgatensi.id/files/new_gatensi/{$path}";

        SPTAsesor::updateOrCreate(
            ['jadwal_id' => $jadwalId],
            [
                'spt_no' => $noSpt,
                'asesor_id' => $asesor1->IDAsesor ?? null,
                'link' => $fileUrl,
                'send' => 0,
            ]
        );

        return [
            'spt_no' => $noSpt,
            'url' => $fileUrl,
        ];
    }

    /**
     * Generate SPT Komtek without TTD/QR
     */
    private function generateSptKomtekWithoutTtd(int $jadwalId): array
    {
        $last = SPTKomtek::latest('id')->first();
        $base = $last?->no_surat ?? '0/LSP GKK B.001-D/I/2024';

        $romawi = [
            1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV',
            5 => 'V', 6 => 'VI', 7 => 'VII', 8 => 'VIII',
            9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII',
        ];

        $parts = explode('/', $base);
        $year  = now()->year;

        $parts[0] = ((int)($parts[3] ?? 0) === $year) ? ((int)$parts[0] + 1) : 1;
        $parts[2] = $this->bulanRomawi(now()->month);
        $parts[3] = (string)$year;

        $spt_no = implode('/', $parts);

        // Get jadwal data
        $jadwal = Jadwal::where('jadwal_id', $jadwalId)->firstOrFail();

        $getNamaKomtek = function($komtekId) {
            return $komtekId ? User::where('id', $komtekId)->value('name') : null;
        };

        $nama_komtek1 = $getNamaKomtek($jadwal->komtek1);
        $nama_komtek2 = $getNamaKomtek($jadwal->komtek2);
        $nama_komtek3 = $getNamaKomtek($jadwal->komtek3);

        $asesiJadwals = AsesiJadwal::where('jadwal_id', $jadwalId)->with(['personal'])->get();
        $peserta = [];
        foreach ($asesiJadwals as $asesi) {
            $jabatanKerjaId = KlasifikasiKualifikasi::where('id_izin', $asesi->id_izin)->value('jabatan_kerja');
            $namaSkema = JabatanKerja::where('id_jabatan_kerja', $jabatanKerjaId)->value('jabatan_kerja');
            $peserta[] = [
                'nama' => $asesi->personal->nama ?? '',
                'skema' => $namaSkema ?? '',
            ];
        }

        $tuk = $jadwal->tuk;
        $namaSkemaList = [];
        foreach ($peserta as $p) {
            if (!empty($p['skema'])) $namaSkemaList[] = $p['skema'];
        }
        $namaSkemaList = array_unique($namaSkemaList);

        $viewData = [
            'nomor' => $spt_no,
            'tanggal_surat' => now()->isoFormat('dddd, D MMMM Y'),
            'nama_komtek1' => $nama_komtek1,
            'nama_komtek2' => $nama_komtek2,
            'nama_komtek3' => $nama_komtek3,
            'nama_tuk' => $tuk->nama ?? '',
            'alamat_tuk' => $tuk->alamat ?? '',
            'hari_tanggal' => Carbon::parse($jadwal->tanggal_uji)->isoFormat('dddd, D MMMM Y'),
            'jmlh_peserta' => count($peserta),
            'nama_skema' => implode(', ', $namaSkemaList),
            'peserta' => $peserta,
            'qr' => null, // No QR code
        ];

        $pdf = PDF::loadView('template-surat.spt_komtek', $viewData);
        $pdfContent = $pdf->output();

        $path = "spt_komtek/{$jadwalId}.pdf";
        Storage::disk('str_moon')->put($path, $pdfContent);
        $fileUrl = "https://moon.lspgatensi.id/files/new_gatensi/{$path}";

        SPTKomtek::updateOrCreate(
            ['jadwal_id' => $jadwalId],
            [
                'no_surat' => $spt_no,
                'link' => $fileUrl,
                'send' => 0,
                'inputby_id' => auth()->id(),
            ]
        );

        return [
            'spt_no' => $spt_no,
            'url' => $fileUrl,
        ];
    }

    /**
     * Generate BA Komtek without TTD/QR
     */

    private function generateBaKomtekWithoutTtd(int $jadwalId): array
    {
        $last = BaKomtek::latest('id')->first();
        $roman = [1=>'I',2=>'II',3=>'III',4=>'IV',5=>'V',6=>'VI',7=>'VII',8=>'VIII',9=>'IX',10=>'X',11=>'XI',12=>'XII'];
        $now = Carbon::now();

        $num = $last ? ((int) explode('/', $last->no_surat)[0] + 1) : 1;
        $no_ba_komtek = "{$num}/LSP GKK B.002-A/{$roman[$now->month]}/{$now->year}";

        // Get jadwal data
        $jadwal = Jadwal::where('jadwal_id', $jadwalId)->firstOrFail();

        $komtekIds = array_filter([$jadwal->komtek1, $jadwal->komtek2, $jadwal->komtek3]);
        $komtekNames = User::whereIn('id', $komtekIds)->pluck('name', 'id');

        $nama_komtek1 = $komtekNames[$jadwal->komtek1] ?? null;
        $nama_komtek2 = $komtekNames[$jadwal->komtek2] ?? null;
        $nama_komtek3 = $komtekNames[$jadwal->komtek3] ?? null;

        $asesiJadwals = AsesiJadwal::where('jadwal_id', $jadwalId)->with(['personal'])->get();
        $nama_peserta = [];

        foreach ($asesiJadwals as $asesi) {
            $jabatanKerjaId = KlasifikasiKualifikasi::where('id_izin', $asesi->id_izin)->value('jabatan_kerja');
            $namaSkema = JabatanKerja::where('id_jabatan_kerja', $jabatanKerjaId)->value('jabatan_kerja');

            $nama_peserta[] = [
                'nama' => $asesi->personal->nama ?? '',
                'skema' => $namaSkema ?? '',
                'kompeten' => $this->resolveKomtekDecision($asesi->id_izin),
            ];
        }

        $viewData = [
            'nomor' => $no_ba_komtek,
            'tanggal' => now()->isoFormat('dddd, D MMMM Y'),
            'nama_komtek1' => $nama_komtek1,
            'nama_komtek2' => $nama_komtek2,
            'nama_komtek3' => $nama_komtek3,
            'nama_peserta' => $nama_peserta,
            'jml_peserta' => count($nama_peserta),
            'qr1' => null, 'qr2' => null, 'qr3' => null, // No QR code
        ];

        $pdf = PDF::loadView('template-surat.ba_komteks', $viewData);
        $pdfContent = $pdf->output();

        $path = "ba_komtek/{$jadwalId}.pdf";
        Storage::disk('str_moon')->put($path, $pdfContent);
        $fileUrl = "https://moon.lspgatensi.id/files/new_gatensi/{$path}";

        BaKomtek::updateOrCreate(
            ['jadwal_id' => $jadwalId],
            [
                'no_surat' => $no_ba_komtek,
                'link' => $fileUrl,
                'has_ttd_komtek1' => 0,
                'has_ttd_komtek2' => 0,
                'has_ttd_komtek3' => 0,
                'inputby_id' => auth()->id(),
            ]
        );

        return [
            'no_ba' => $no_ba_komtek,
            'url' => $fileUrl,
        ];
    }
}
