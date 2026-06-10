<?php

namespace App\Http\Controllers;

use App\Models\Ak1Answer;
use App\Models\Ak2Answer;
use App\Models\Ak2Feedback;
use App\Models\Ak3Answer;
use App\Models\Ak3Note;
use App\Models\Ak4Reason;
use App\Models\Ak5Answer;
use App\Models\Ak6Answer;
use App\Models\Ak6DimensiKompetensi;
use App\Models\Ak6Feedback;
use App\Models\Ak7Answer;
use App\Models\Apl2Answer;
use App\Models\Apl2Submission;
use App\Models\AsesorBnsp;
use App\Models\Barcode;
use App\Models\BuktiAsesmen;
use App\Models\Ia4aAnswer;
use App\Models\Ia4bAnswer;
use App\Models\Ia5Answer;
use App\Models\ImpBuktiAdministrasi;
use App\Models\ImpBuktiPersyaratan;
use App\Models\JabatanConfigA;
use App\Models\JabatanDokumen;
use App\Models\JabatanKerja;
use App\Models\JenisKelas;
use App\Models\JenjangJabatanKerja;
use App\Models\KlasifikasiKualifikasi;
use App\Models\KomtekRecommendation;
use App\Models\MyTuk;
use App\Models\Pendidikan;
use App\Models\Personal;
use App\Models\Proyek;
use App\Models\Referensi;
use App\Models\Soal;
use App\Models\SubunitKompetensi;
use App\Models\UnitKompetensi;
use App\Models\User;
use App\Services\LlmAutoFillService;
use App\Services\PermohonanSyncService;
use App\Services\SmartDataSyncService;
use App\Traits\ResolvesKomtekDecision;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * Internal API for document generation data.
 * Used by the external document generator service to fetch data without direct DB access.
 * All routes protected by static-api-key middleware.
 */
class InternalDocumentDataController extends Controller
{
    use ResolvesKomtekDecision;

    private PraAsesmenController $praAsesmenController;
    private AsesmenController $asesmenController;
    private LlmAutoFillService $llmService;
    private PermohonanSyncService $syncService;
    private SmartDataSyncService $smartSyncService;

    public function __construct(
        LlmAutoFillService $llmService,
        PermohonanSyncService $syncService,
        SmartDataSyncService $smartSyncService
    ) {
        $this->llmService = $llmService;
        $this->syncService = $syncService;
        $this->smartSyncService = $smartSyncService;
        $this->praAsesmenController = app(PraAsesmenController::class);
        $this->asesmenController = app(AsesmenController::class);
    }

    // ─────────────────────────────────────────────
    //  INITIALIZATION — sync, check, resolve all base data
    // ─────────────────────────────────────────────

    /**
     * One-shot initialization: sync data, verify klasifikasi, resolve asesors & users.
     * Returns everything needed to start document generation.
     */
    public function initialize(string $idIzin, Request $request): JsonResponse
    {
        $noregAsesor1 = $request->query('noreg_asesor_1');
        $noregAsesor2 = $request->query('noreg_asesor_2');

        // Step 1: Sync data if needed
        $syncResult = $this->syncService->syncWithFallback($idIzin);
        if (!$syncResult['success']) {
            return response()->json([
                'success' => false,
                'error' => $syncResult['error'] ?? 'Sync gagal dari semua sumber',
                'status' => 'sync_failed',
            ]);
        }

        // Step 2: Check KlasifikasiKualifikasi
        $klasifikasi = KlasifikasiKualifikasi::where('id_izin', $idIzin)->first();
        if (!$klasifikasi) {
            return response()->json([
                'success' => false,
                'error' => 'Klasifikasi Kualifikasi tidak ditemukan',
                'status' => 'no_klasifikasi',
            ]);
        }

        $jenjang = (int) $klasifikasi->jenjang;
        $jabatanKerjaId = $klasifikasi->jabatan_kerja;
        $tukId = $klasifikasi->tuk;

        // Step 3: Resolve asesors
        $asesor1 = $noregAsesor1 ? $this->resolveAsesor($noregAsesor1) : null;
        $asesor2 = $noregAsesor2 ? $this->resolveAsesor($noregAsesor2) : null;

        if (!$asesor1) {
            return response()->json([
                'success' => false,
                'error' => 'Asesor 1 dengan noreg ' . $noregAsesor1 . ' tidak ditemukan',
                'status' => 'asesor_not_found',
                'noreg' => $noregAsesor1,
            ]);
        }
        // Asesor 2 optional: hanya error jika noreg diisi tapi tidak ditemukan
        if ($noregAsesor2 && !$asesor2) {
            return response()->json([
                'success' => false,
                'error' => 'Asesor 2 dengan noreg ' . $noregAsesor2 . ' tidak ditemukan',
                'status' => 'asesor_not_found',
                'noreg' => $noregAsesor2,
            ]);
        }

        // Step 4: Resolve user accounts
        $asesor1User = $noregAsesor1 ? User::where('noreg', $noregAsesor1)->first() : null;
        $asesor2User = $noregAsesor2 ? User::where('noreg', $noregAsesor2)->first() : null;

        $personal = Personal::where('id_izin', $idIzin)->first();
        $asesiUser = $this->findOrCreateAsesiUser($personal, $idIzin);

        return response()->json([
            'success' => true,
            'data' => [
                'klasifikasi' => [
                    'exists' => true,
                    'jenjang' => $jenjang,
                    'jabatan_kerja_id' => $jabatanKerjaId,
                    'tuk_id' => $tukId,
                ],
                'asesor_1' => $asesor1 ? [
                    'nama' => $asesor1->nama,
                    'noreg' => $asesor1->noreg,
                ] : null,
                'asesor_2' => $asesor2 ? [
                    'nama' => $asesor2->nama,
                    'noreg' => $asesor2->noreg,
                ] : null,
                'asesor_1_user' => $asesor1User ? [
                    'id' => $asesor1User->id,
                    'name' => $asesor1User->name,
                    'role_id' => $asesor1User->role_id,
                ] : null,
                'asesor_2_user' => $asesor2User ? [
                    'id' => $asesor2User->id,
                    'name' => $asesor2User->name,
                    'role_id' => $asesor2User->role_id,
                ] : null,
                'personal' => $personal ? $personal->toArray() : null,
                'asesi_user' => $asesiUser ? [
                    'id' => $asesiUser->id,
                    'name' => $asesiUser->name,
                ] : null,
            ],
        ]);
    }

    // ─────────────────────────────────────────────
    //  DOKUMEN HEADER
    // ─────────────────────────────────────────────

    /**
     * Build dokumen header data.
     * Accepts all HEADER inputs as query params so the caller doesn't need to send them separately.
     */
    public function dokumenHeader(string $idIzin, Request $request): JsonResponse
    {
        $personal = Personal::where('id_izin', $idIzin)->first();
        $jabatanKerjaId = $request->query('jabatan_kerja_id');
        $tukId = $request->query('tuk_id');
        $jenjang = (int) $request->query('jenjang', 0);
        $tanggalUji = $request->query('tanggal_uji', date('Y-m-d'));

        if (!$jabatanKerjaId) {
            return response()->json(['success' => false, 'error' => 'jabatan_kerja_id required'], 400);
        }

        $jabatanKerja = JabatanKerja::where('id_jabatan_kerja', $jabatanKerjaId)->value('jabatan_kerja');
        $jabatanConfig = JabatanConfigA::with(['penyusun:id,name,noreg', 'validator:id,name,noreg', 'manajer:id,name,noreg'])
            ->select(['penyusun_id', 'penyusun_tgl', 'penyusun_img', 'penyusun_url', 'validator_id', 'validator_tgl', 'validator_img', 'validator_url', 'manajer_id', 'manajer_img', 'manajer_url', 'no_skema'])
            ->where('id_jabatan', $jabatanKerjaId)
            ->first();

        $tuk = $tukId ? MyTuk::find($tukId) : null;
        $jenisKelas = $personal && $personal->jenis_kelas_id
            ? JenisKelas::find($personal->jenis_kelas_id)
            : null;

        return response()->json([
            'success' => true,
            'data' => [
                'jabatan_kerja'    => $jabatanKerja,
                'nomor_skema'      => $jabatanConfig?->no_skema,
                'tuk'              => $tuk?->nama_tuk,
                'nama_asesi'       => $personal?->nama,
                'tanggal_uji'      => $tanggalUji,
                'tanggal_selesai'  => $tanggalUji,
                'jenis_kelas'      => $jenisKelas?->nama_kelas,
                'jenjang'          => $jenjang,
                'nama_penyusun'    => $jabatanConfig?->penyusun?->name,
                'nama_validator'   => $jabatanConfig?->validator?->name,
                'tanggal_penyusun' => $jabatanConfig?->penyusun_tgl,
                'tanggal_validator' => $jabatanConfig?->validator_tgl,
                'barcode_penyusun' => $jabatanConfig?->penyusun_img,
                'barcode_validator' => $jabatanConfig?->validator_img,
                'barcode_manajer'  => $jabatanConfig?->manajer_img,
                'noreg_penyusun'   => $jabatanConfig?->penyusun?->noreg,
                'noreg_validator'  => $jabatanConfig?->validator?->noreg,
                'nama_manajer'     => $jabatanConfig?->manajer?->name,
                'tanggal_manajer'  => $jabatanConfig?->penyusun_tgl,
                'metode'           => 'observasi',
            ],
        ]);
    }

    // ─────────────────────────────────────────────
    //  BUILD*DATA via Reflection
    // ─────────────────────────────────────────────

    /**
     * Call a build*Data method from the appropriate controller via reflection.
     */
    public function buildData(string $method, string $idIzin): JsonResponse
    {
        $methodName = "build{$method}Data";

        // Mapping of method -> controller instance
        $mapping = [
            'Apl02' => [$this->praAsesmenController, 'PraAsesmenController'],
            'Mapa01' => [$this->praAsesmenController, 'PraAsesmenController'],
            'Mapa02' => [$this->praAsesmenController, 'PraAsesmenController'],
            'Ak07' => [$this->praAsesmenController, 'PraAsesmenController'],
            'Ak04' => [$this->praAsesmenController, 'PraAsesmenController'],
            'Ak01' => [$this->praAsesmenController, 'PraAsesmenController'],
            'Ia01' => [$this->asesmenController, 'AsesmenController'],
            'Ia02' => [$this->asesmenController, 'AsesmenController'],
            'Ia03' => [$this->asesmenController, 'AsesmenController'],
            'Ia04a' => [$this->asesmenController, 'AsesmenController'],
            'Ia04b' => [$this->asesmenController, 'AsesmenController'],
            'Ia05' => [$this->asesmenController, 'AsesmenController'],
            'Ak02' => [$this->asesmenController, 'AsesmenController'],
            'Ak03' => [$this->asesmenController, 'AsesmenController'],
            'Ak05' => [$this->asesmenController, 'AsesmenController'],
            'Ak06' => [$this->asesmenController, 'AsesmenController'],
        ];

        if (!isset($mapping[$method])) {
            // If not in mapping, try inline builder in this controller
            if (method_exists($this, $methodName)) {
                $data = $this->$methodName($idIzin);
                return response()->json(['success' => true, 'data' => $data]);
            }
            return response()->json(['success' => false, 'error' => "No builder for {$method}"], 404);
        }

        [$controller, $controllerName] = $mapping[$method];

        try {
            $reflection = new \ReflectionMethod($controller, $methodName);
            $reflection->setAccessible(true);
            $docData = $reflection->invoke($controller, $idIzin, true);

            // Post-process: AK07 kelompok 5 -> false
            if ($method === 'Ak07' && isset($docData['kelompoks'])) {
                $docData['kelompoks'] = collect($docData['kelompoks'])->map(function ($kelompok) {
                    if (($kelompok['id'] ?? null) == 5) {
                        $kelompok['kategoris'] = collect($kelompok['kategoris'] ?? [])->map(function ($kategori) {
                            $kategori['referensis'] = collect($kategori['referensis'] ?? [])->map(function ($item) {
                                $item['jawaban'] = false;
                                return $item;
                            })->values()->all();
                            return $kategori;
                        })->values()->all();
                    }
                    return $kelompok;
                })->values()->all();
            }

            return response()->json(['success' => true, 'data' => $docData]);
        } catch (\Throwable $e) {
            // Fallback: try inline builder
            if (method_exists($this, $methodName)) {
                $data = $this->$methodName($idIzin);
                return response()->json(['success' => true, 'data' => $data]);
            }
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // ─────────────────────────────────────────────
    //  INLINE APL01 Builder
    //  (APL01 doesn't have a private build method in PraAsesmenController)
    // ─────────────────────────────────────────────

    private function buildApl01Data(string $idIzin): array
    {
        $personal = Personal::select(['nama', 'nik', 'tempat_lahir', 'tanggal_lahir', 'jenis_kelamin', 'alamat', 'kodepos', 'telepon', 'email'])
            ->where('id_izin', $idIzin)->first();
        $klasifikasi = KlasifikasiKualifikasi::where('id_izin', $idIzin)->first();
        if (!$klasifikasi) {
            return ['data_pribadi' => [], 'data_pekerjaan' => [], 'sertifikasi' => [], 'unit_kompetensi' => [], 'bukti_persyaratan' => [], 'bukti_administratif' => []];
        }
        $jabatanKerja = JabatanKerja::where('id_jabatan_kerja', $klasifikasi->jabatan_kerja)->first();
        $pekerjaan = Proyek::where('id_izin', $idIzin)->latest('created_at')->first();
        $unitKompetensi = UnitKompetensi::select(['kode', 'nama'])
            ->where('id_jabatan', $klasifikasi->jabatan_kerja)->get();
        $nomorSkema = JabatanConfigA::where('id_jabatan', $klasifikasi->jabatan_kerja)->value('no_skema');
        $kualifikasi = JenjangJabatanKerja::where('jenjang_id', $klasifikasi->jenjang)->value('jabatan_kerja');

        $dataPribadi = [
            'nama'          => $personal?->nama,
            'nik'           => $personal?->nik,
            'tempat_lahir'  => $personal?->tempat_lahir,
            'tanggal_lahir' => $personal?->tanggal_lahir,
            'jenis_kelamin' => $personal?->jenis_kelamin,
            'kebangsaan'    => 'ID',
            'alamat'        => $personal?->alamat,
            'telepon_rumah' => null,
            'telepon_hp'    => $personal?->telepon,
            'kode_pos'      => $personal?->kodepos,
            'email'         => $personal?->email,
            'kualifikasi'   => $kualifikasi,
        ];

        $dataPekerjaan = [
            'perusahaan' => $pekerjaan?->pemberi_kerja,
            'jabatan'    => $pekerjaan?->jabatan,
            'alamat'     => $pekerjaan?->alamat,
            'kode_pos'   => $pekerjaan?->kode_pos,
            'telepon'    => $pekerjaan?->telepon,
            'fax'        => $pekerjaan?->fax,
            'email'      => $pekerjaan?->email,
        ];

        $sertifikasi = [
            'judul' => $jabatanKerja?->jabatan_kerja,
            'nomor' => $nomorSkema,
            'skkni' => $jabatanKerja?->acuan,
        ];

        $jenjangPendidikan = Pendidikan::where('id_izin', $idIzin)
            ->latest('created_at')->value('jenjang');

        $educationMapping = [
            'SD' => ['1', '2'], 'SMP' => ['2'], 'SMA' => ['3'],
            'D1' => ['4', '16'], 'D2' => ['5'], 'D3' => ['6'],
            'D4' => ['7', '12'], 'S1' => ['8', '13'], 'S2' => ['9', '14', '18'],
            'S3' => ['10', '15', '19'], 'profesi' => ['11'], 'Non' => ['99'],
        ];
        $educationOrder = ['Non', 'SD', 'SMP', 'SMA', 'D1', 'D2', 'D3', 'D4', 'S1', 'S2', 'S3', 'profesi'];

        $persyaratanRecords = ImpBuktiPersyaratan::select(['no', 'bukti'])
            ->where('id_jabatan', $klasifikasi->jabatan_kerja)
            ->where('bukti', 'not like', 'Syarat Administrasi%')
            ->where('bukti', 'not like', 'Bukti Keanggotaan%')
            ->orderBy('no')->get();

        $allText = $persyaratanRecords->pluck('bukti')->implode(' ');
        $userLevel = null;
        foreach ($educationMapping as $level => $jenjangs) {
            if (in_array($jenjangPendidikan, $jenjangs)) { $userLevel = $level; break; }
        }

        $matchedLevels = [];
        if ($userLevel) {
            if (stripos($allText, $userLevel) !== false) {
                $matchedLevels = [$userLevel, ucfirst($userLevel)];
            } else {
                $idx = array_search($userLevel, $educationOrder);
                if ($idx !== false) {
                    for ($i = $idx - 1; $i >= 0; $i--) {
                        if (stripos($allText, $educationOrder[$i]) !== false) {
                            $matchedLevels = [$educationOrder[$i], ucfirst($educationOrder[$i])];
                            break;
                        }
                    }
                }
            }
        }

        $persyaratan = $persyaratanRecords->map(function ($item) use ($matchedLevels) {
            $checked = false;
            foreach ($matchedLevels as $l) {
                if (stripos($item->bukti, $l) !== false) { $checked = true; break; }
            }
            return ['no' => $item->no, 'bukti' => $item->bukti, 'checked' => $checked];
        })->values();

        $administratif = ImpBuktiAdministrasi::select(['no', 'bukti'])
            ->where('id_jabatan', $klasifikasi->jabatan_kerja)
            ->orderBy('no')->get();

        $formatted = collect();
        $no = 1;
        foreach ($administratif as $item) {
            $clean = preg_replace('/^(Syarat Administrasi|Bukti Keanggotaan)\s*:\s*/i', '', $item->bukti);
            foreach (array_map('trim', explode(',', $clean)) as $text) {
                if ($text === '' || strtolower($text) === 'dan lain-lain.') continue;
                $formatted->push(['no' => (string) $no++, 'bukti' => $text, 'checked' => true]);
            }
        }

        return [
            'data_pribadi'        => $dataPribadi,
            'data_pekerjaan'      => $dataPekerjaan,
            'sertifikasi'         => $sertifikasi,
            'unit_kompetensi'     => $unitKompetensi,
            'bukti_persyaratan'   => $persyaratan,
            'bukti_administratif' => $formatted,
        ];
    }

    // ─────────────────────────────────────────────
    //  PRE-POPULATE ANSWER TABLES
    // ─────────────────────────────────────────────

    /**
     * Pre-populate answer tables so build*Data methods read kompeten=true, etc.
     * This mirrors the exact logic in GenerateDokumenService::prePopulateAnswers.
     */
    public function prePopulate(string $idIzin): JsonResponse
    {
        DB::beginTransaction();
        try {
            $klasifikasi = KlasifikasiKualifikasi::where('id_izin', $idIzin)->first();
            if (!$klasifikasi) {
                DB::rollBack();
                return response()->json(['success' => false, 'error' => 'Klasifikasi tidak ditemukan'], 404);
            }

            $jabatanKerjaId = $klasifikasi->jabatan_kerja;
            $jenjang = (int) $klasifikasi->jenjang;
            $personal = Personal::where('id_izin', $idIzin)->first();
            $llmCtx = ['nama_asesi' => $personal?->nama, 'jabatan_kerja' => ''];

            // ── APL02 ──
            Apl2Submission::updateOrCreate(
                ['id_izin' => $idIzin],
                ['metode' => 'observasi', 'is_dilanjutkan' => true]
            );
            $subunits = SubunitKompetensi::whereHas('unit', function ($q) use ($jabatanKerjaId) {
                $q->where('id_jabatan', $jabatanKerjaId);
            })->get();
            foreach ($subunits as $sub) {
                Apl2Answer::updateOrCreate(
                    ['id_izin' => $idIzin, 'subunit_id' => $sub->id],
                    ['kompeten' => true]
                );
            }

            // ── AK01 ──
            $autoTrueIds = $jenjang >= 1 && $jenjang <= 3 ? [60, 227] : [60, 62];
            foreach ($autoTrueIds as $refId) {
                Ak1Answer::updateOrCreate(
                    ['id_izin' => $idIzin, 'id_referensi' => $refId],
                    ['jawaban' => true, 'waktu' => '07:30 - Selesai']
                );
            }

            // ── AK02 ──
            $units = UnitKompetensi::where('id_jabatan', $jabatanKerjaId)->get();
            foreach ($units as $unit) {
                Ak2Answer::updateOrCreate(
                    ['id_izin' => $idIzin, 'id_unit_kompetensi' => $unit->id],
                    [
                        'observasi' => true,
                        'portofolio' => false,
                        'pertanyaan_wawancara' => false,
                        'pertanyaan_lisan' => false,
                        'pertanyaan_tertulis' => $jenjang < 9,
                        'proyek_kerja' => $jenjang >= 4,
                        'lainnya' => false,
                    ]
                );
            }
            Ak2Feedback::updateOrCreate(
                ['id_izin' => $idIzin],
                [
                    'is_kompeten' => true,
                    'tindak_lanjut' => 'Asesi dinyatakan Kompeten pada seluruh unit kompetensi. Tidak diperlukan asesmen tambahan.',
                    'komentar' => 'Proses asesmen berjalan dengan baik. Asesi menunjukkan penguasaan kompetensi yang memadai pada setiap unit yang diujikan.',
                ]
            );

            // ── IA05 ──
            $ia05Doc = JabatanDokumen::where('id_jabatan', $jabatanKerjaId)
                ->where('kode', 'FR.IA.05')->where('is_active', true)->first();
            if ($ia05Doc) {
                foreach (Soal::where('id_dokumen', $ia05Doc->id)->where('tipe', 1)->get() as $soal) {
                    Ia5Answer::updateOrCreate(
                        ['id_izin' => $idIzin, 'soal_id' => $soal->id, 'dokumen_id' => $ia05Doc->id],
                        ['jawaban' => $soal->jawaban]
                    );
                }
            }

            // ─── LLM Fields ───
            $llmFields = [];

            // IA04A
            $ia04aDoc = JabatanDokumen::where('id_jabatan', $jabatanKerjaId)
                ->where('kode', 'FR.IA.04.A')->where('is_active', true)->first();
            $ia04aSoals = [];
            if ($ia04aDoc) {
                $ia04aSoals = Soal::where('id_dokumen', $ia04aDoc->id)->orderBy('urut')->get();
                foreach ($ia04aSoals as $soal) {
                    if ($soal->is_komentar) {
                        $llmFields[] = ['ia04a_jawaban', ['soal_soal' => $soal->soal] + $llmCtx];
                    }
                }
            }

            // IA04B
            $ia04bDoc = JabatanDokumen::where('id_jabatan', $jabatanKerjaId)
                ->where('kode', 'FR.IA.04.B')->where('is_active', true)->first();
            $ia04bSoals = [];
            if ($ia04bDoc) {
                $ia04bSoals = Soal::where('id_dokumen', $ia04bDoc->id)->where('tipe', 8)->get();
                foreach ($ia04bSoals as $soal) {
                    $llmFields[] = ['ia04a_jawaban', ['soal_soal' => $soal->soal] + $llmCtx];
                }
            }

            // AK03
            $ak03Doc = JabatanDokumen::where('id_jabatan', $jabatanKerjaId)
                ->where('kode', 'FR.AK.03')->where('is_active', true)->first();
            $ak03Soals = [];
            if ($ak03Doc) {
                $ak03Soals = Soal::where('id_dokumen', $ak03Doc->id)->get();
                foreach ($ak03Soals as $soal) {
                    $llmFields[] = ['ak03_catatan', ['soal_soal' => $soal->soal] + $llmCtx];
                }
                $llmFields[] = ['ak03_catatan_umum', $llmCtx];
            }

            // AK05
            $llmFields[] = ['ak05_keterangan', $llmCtx];
            $llmFields[] = ['ak05_aspek', $llmCtx];
            $llmFields[] = ['ak05_saran', $llmCtx];
            $llmFields[] = ['ak05_catatan', $llmCtx];

            // AK06
            $llmFields[] = ['ak06_rekomendasi1', $llmCtx];
            $llmFields[] = ['ak06_rekomendasi2', $llmCtx];
            $llmFields[] = ['ak06_catatan_asesor1', $llmCtx];
            $llmFields[] = ['ak06_catatan_asesor2', $llmCtx];

            // AK07
            $refs = Referensi::with('kelompokRel')
                ->where('kategori', 'AK07')->whereNotNull('id_kelompok')->get();
            foreach ($refs as $ref) {
                if (in_array((int) $ref->id_kelompok, [6, 7])) {
                    $llmFields[] = ['ak07_text', ['referensi_nama' => $ref->nama] + $llmCtx];
                }
            }

            // Generate LLM texts in batch
            $llmResults = $this->llmService->generateBatchTexts($llmFields);
            $idx = 0;

            // IA04A store
            foreach ($ia04aSoals as $soal) {
                if ($soal->is_komentar) {
                    $jawaban = $llmResults['ia04a_jawaban_' . $idx] ?? $this->llmService->getFallback('ia04a_jawaban');
                    Ia4aAnswer::updateOrCreate(
                        ['id_izin' => $idIzin, 'soal_id' => $soal->id],
                        ['jawaban' => $jawaban, 'pencapaian' => true]
                    );
                    $idx++;
                }
            }

            // IA04B store
            foreach ($ia04bSoals as $soal) {
                $jawaban = $llmResults['ia04a_jawaban_' . $idx] ?? $this->llmService->getFallback('ia04a_jawaban');
                Ia4bAnswer::updateOrCreate(
                    ['id_izin' => $idIzin, 'soal_id' => $soal->id],
                    ['dokumen_id' => $ia04bDoc->id, 'jawaban' => $jawaban, 'pencapaian' => true]
                );
                $idx++;
            }
            // IA04B rekomendasi
            if ($ia04bDoc) {
                foreach (Soal::where('id_dokumen', $ia04bDoc->id)->where('tipe', 3)->get() as $soal) {
                    Ia4bAnswer::updateOrCreate(
                        ['id_izin' => $idIzin, 'soal_id' => $soal->id],
                        ['dokumen_id' => $ia04bDoc->id, 'is_rekomendasi' => true, 'rekomendasi' => true]
                    );
                }
            }

            // AK03 store
            foreach ($ak03Soals as $soal) {
                $catatan = $llmResults['ak03_catatan_' . $idx] ?? $this->llmService->getFallback('ak03_catatan');
                Ak3Answer::updateOrCreate(
                    ['id_izin' => $idIzin, 'soal_id' => $soal->id],
                    ['is_kompeten' => true, 'catatan' => $catatan]
                );
                $idx++;
            }
            if ($ak03Doc) {
                $globalCatatan = $llmResults['ak03_catatan_umum_' . $idx] ?? $this->llmService->getFallback('ak03_catatan_umum');
                Ak3Note::updateOrCreate(
                    ['id_izin' => $idIzin],
                    ['catatan' => $globalCatatan]
                );
                $idx++;
            }

            // AK05
            $ak05Data = [
                'keterangan' => $llmResults['ak05_keterangan_' . $idx] ?? $this->llmService->getFallback('ak05_keterangan'),
                'aspek' => $llmResults['ak05_aspek_' . ($idx + 1)] ?? $this->llmService->getFallback('ak05_aspek'),
                'pencatatan_penolakan' => '',
                'saran' => $llmResults['ak05_saran_' . ($idx + 2)] ?? $this->llmService->getFallback('ak05_saran'),
                'catatan' => $llmResults['ak05_catatan_' . ($idx + 3)] ?? $this->llmService->getFallback('ak05_catatan'),
            ];
            Ak5Answer::updateOrCreate(['id_izin' => $idIzin], $ak05Data);
            $idx += 4;

            // Komtek recommendations
            $existingVotes = KomtekRecommendation::where('id_izin', $idIzin)->count();
            if ($existingVotes < 2) {
                for ($i = $existingVotes; $i < 2; $i++) {
                    KomtekRecommendation::create([
                        'id_izin' => $idIzin, 'id_komtek' => 999 + $i, 'rekomendasi' => 'K',
                    ]);
                }
            }

            // AK06
            $aspeks = Referensi::where('kategori', 'AK06')->where('id', '!=', 26)->get();
            foreach ($aspeks as $aspek) {
                Ak6Answer::updateOrCreate(
                    ['id_izin' => $idIzin, 'id_aspek' => $aspek->id],
                    ['validitas' => true, 'reliabel' => true, 'fleksibel' => true, 'adil' => true]
                );
            }
            Ak6Feedback::updateOrCreate(
                ['id_izin' => $idIzin],
                [
                    'rekomendasi1' => $llmResults['ak06_rekomendasi1_' . $idx] ?? $this->llmService->getFallback('ak06_rekomendasi1'),
                    'rekomendasi2' => $llmResults['ak06_rekomendasi2_' . ($idx + 1)] ?? $this->llmService->getFallback('ak06_rekomendasi2'),
                    'catatan_asesor1' => $llmResults['ak06_catatan_asesor1_' . ($idx + 2)] ?? $this->llmService->getFallback('ak06_catatan_asesor1'),
                    'catatan_asesor2' => $llmResults['ak06_catatan_asesor2_' . ($idx + 3)] ?? $this->llmService->getFallback('ak06_catatan_asesor2'),
                ]
            );
            $idx += 4;

            // AK07
            foreach ($refs as $ref) {
                $kelompokId = $ref->id_kelompok;
                $isTextGroup = in_array((int) $kelompokId, [6, 7]);
                $answerText = null;
                if ($isTextGroup) {
                    $answerText = $llmResults['ak07_text_' . $idx] ?? $this->llmService->getFallback('ak07_text');
                    $idx++;
                }
                Ak7Answer::updateOrCreate(
                    ['id_izin' => $idIzin, 'referensi_id' => $ref->id],
                    ['kelompok_id' => $kelompokId, 'answer_bool' => true, 'answer_text' => $answerText]
                );
            }

            DB::commit();
            return response()->json(['success' => true]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // ─────────────────────────────────────────────
    //  BARCODE OPERATIONS
    // ─────────────────────────────────────────────

    /**
     * Get all barcodes for an id_izin.
     */
    public function getBarcodes(string $idIzin): JsonResponse
    {
        $barcodes = Barcode::where('id_izin', $idIzin)->get();
        $result = [];
        foreach ($barcodes as $b) {
            $key = "{$b->id_user}:{$b->jenis_dokumen}";
            $result[$key] = [
                'id' => $b->id,
                'id_user' => $b->id_user,
                'id_izin' => $b->id_izin,
                'jenis_dokumen' => $b->jenis_dokumen,
                'url_ttd' => $b->url_ttd,
                'url_image' => $b->url_image,
                'id_role' => $b->id_role,
                'created_at' => $b->created_at?->format('Y-m-d H:i:s'),
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * Create or update a barcode record.
     * Request: { id_izin, jenis_dokumen, id_user, role_id, signed_at? }
     */
    public function saveBarcode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_izin' => 'required|string',
            'jenis_dokumen' => 'required|string',
            'id_user' => 'required|integer',
            'role_id' => 'required|integer',
            'signed_at' => 'nullable|string',
        ]);

        $existing = Barcode::where('id_user', $validated['id_user'])
            ->where('id_izin', $validated['id_izin'])
            ->where('jenis_dokumen', $validated['jenis_dokumen'])
            ->first();

        if ($existing) {
            $urlTtd = $existing->url_ttd;
            if ($validated['signed_at'] && $existing->created_at?->format('Y-m-d H:i:s') !== $validated['signed_at']) {
                $existing->update(['created_at' => $validated['signed_at']]);
            }
        } else {
            $uuid = (string) Str::uuid();
            $urlTtd = "https://barcode.lspgatensi.id/ttd/{$uuid}";
            $data = [
                'url_ttd' => $urlTtd,
                'id_role' => $validated['role_id'],
            ];
            if ($validated['signed_at']) {
                $data['created_at'] = $validated['signed_at'];
            }
            Barcode::updateOrCreate(
                ['id_user' => $validated['id_user'], 'id_izin' => $validated['id_izin'], 'jenis_dokumen' => $validated['jenis_dokumen']],
                $data
            );
        }

        return response()->json([
            'success' => true,
            'data' => [
                'url_ttd' => $urlTtd ?? $existing->url_ttd,
            ],
        ]);
    }

    /**
     * Batch generate barcodes for all document types of one id_izin.
     * Creates/updates barcode records in DB, optionally generates QR codes.
     *
     * When skip_qr=true: hanya create/update DB record + return UUID (ringan, tanpa QR).
     * When skip_qr=false: create/update DB + generate QR code + return semuanya.
     *
     * Request: {
     *   skip_qr?: boolean,
     *   id_izin: string,
     *   items: [{
     *     jenis_dokumen: string,
     *     barcodes: [{
     *       role: "asesi"|"asesor1"|"asesor2",
     *       user_id: int,
     *       role_id: int,
     *       signed_at: string|null,
     *       nama: string
     *     }]
     *   }]
     * }
     */
    public function generateAllBarcodes(Request $request): JsonResponse
    {
        set_time_limit(120);

        $validated = $request->validate([
            'skip_qr' => 'nullable|boolean',
            'id_izin' => 'required|string',
            'items' => 'required|array|min:1',
            'items.*.jenis_dokumen' => 'required|string',
            'items.*.barcodes' => 'required|array|min:1',
            'items.*.barcodes.*.role' => 'required|string|in:asesi,asesor1,asesor2',
            'items.*.barcodes.*.user_id' => 'required|integer',
            'items.*.barcodes.*.role_id' => 'required|integer',
            'items.*.barcodes.*.signed_at' => 'nullable|string',
            'items.*.barcodes.*.nama' => 'nullable|string',
        ]);

        $skipQr = $validated['skip_qr'] ?? false;
        $idIzin = $validated['id_izin'];
        $result = [];

        foreach ($validated['items'] as $item) {
            $jenisDokumen = $item['jenis_dokumen'];
            $docBarcodes = [];

            foreach ($item['barcodes'] as $bc) {
                // Check if record already exists — reuse UUID if so
                $existing = Barcode::where('id_user', $bc['user_id'])
                    ->where('id_izin', $idIzin)
                    ->where('jenis_dokumen', $jenisDokumen)
                    ->first();

                if ($existing) {
                    $urlTtd = $existing->url_ttd;
                    $updateData = [];
                    if ($bc['signed_at'] && $existing->created_at?->format('Y-m-d H:i:s') !== $bc['signed_at']) {
                        $updateData['created_at'] = $bc['signed_at'];
                    }
                    if (!empty($updateData)) {
                        $existing->update($updateData);
                    }
                } else {
                    $uuid = (string) Str::uuid();
                    $urlTtd = "https://barcode.lspgatensi.id/ttd/{$uuid}";
                    $data = [
                        'url_ttd' => $urlTtd,
                        'id_role' => $bc['role_id'],
                    ];
                    if ($bc['signed_at']) {
                        $data['created_at'] = $bc['signed_at'];
                    }
                    Barcode::updateOrCreate(
                        [
                            'id_user' => $bc['user_id'],
                            'id_izin' => $idIzin,
                            'jenis_dokumen' => $jenisDokumen,
                        ],
                        $data
                    );
                }

                $entry = [
                    'url_ttd'  => $urlTtd,
                    'tanggal'  => $bc['signed_at'],
                    'nama'     => $bc['nama'] ?? '',
                    'user_id'  => $bc['user_id'],
                ];

                // Generate QR code (SVG) only if not skipping (save server resources)
                if (!$skipQr) {
                    try {
                        $qrRaw = QrCode::format('svg')->size(100)->margin(0)->generate($urlTtd);
                        $entry['url'] = 'data:image/svg+xml;base64,' . base64_encode((string) $qrRaw);
                    } catch (\Throwable $e) {
                        logger()->error("[QR] EXCEPTION generating QR for {$jenisDokumen}/{$idIzin}: " . $e->getMessage());
                    }
                }

                $docBarcodes[$bc['role']] = $entry;
            }

            $result[$jenisDokumen] = $docBarcodes;
        }

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    // ─────────────────────────────────────────────
    //  BUKTI ASESMEN OPERATIONS
    // ─────────────────────────────────────────────

    /**
     * Get bukti asesmen record.
     */
    public function getBuktiAsesmen(string $idIzin): JsonResponse
    {
        $bukti = BuktiAsesmen::where('id_izin', $idIzin)->first();
        return response()->json([
            'success' => true,
            'data' => $bukti ? $bukti->toArray() : null,
        ]);
    }

    /**
     * Update a single URL column in bukti asesmen.
     * Request: { column: 'url_apl_01', value: 'https://...' }
     */
    public function updateBuktiAsesmen(string $idIzin, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'column' => 'required|string',
            'value' => 'nullable|string',
        ]);

        BuktiAsesmen::updateOrCreate(
            ['id_izin' => $idIzin],
            [$validated['column'] => $validated['value']]
        );

        return response()->json(['success' => true]);
    }

    // ─────────────────────────────────────────────
    //  ASESOR & USER HELPERS
    // ─────────────────────────────────────────────

    /**
     * Get asesor by noreg (fetches from BNSP if not found locally).
     */
    public function getAsesor(string $noreg): JsonResponse
    {
        $asesor = AsesorBnsp::where('noreg', $noreg)->first();
        if (!$asesor) {
            $asesor = AsesorBnsp::fetchFromBnspByNoreg($noreg);
        }
        if (!$asesor) {
            return response()->json(['success' => false, 'error' => 'Asesor not found'], 404);
        }
        return response()->json([
            'success' => true,
            'data' => [
                'nama' => $asesor->nama,
                'noreg' => $asesor->noreg,
            ],
        ]);
    }

    /**
     * Get user by noreg.
     */
    public function getUserByNoreg(string $noreg): JsonResponse
    {
        $user = User::where('noreg', $noreg)->first();
        if (!$user) {
            return response()->json(['success' => false, 'error' => 'User not found'], 404);
        }
        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'role_id' => $user->role_id,
            ],
        ]);
    }

    /**
     * Check personal data exists.
     */
    public function getPersonal(string $idIzin): JsonResponse
    {
        $personal = Personal::where('id_izin', $idIzin)->first();
        if (!$personal) {
            return response()->json(['success' => false, 'error' => 'Personal not found'], 404);
        }
        return response()->json([
            'success' => true,
            'data' => [
                'nama' => $personal->nama,
                'nik' => $personal->nik,
                'tempat_lahir' => $personal->tempat_lahir,
                'tanggal_lahir' => $personal->tanggal_lahir,
                'jenis_kelamin' => $personal->jenis_kelamin,
                'alamat' => $personal->alamat,
                'kodepos' => $personal->kodepos,
                'telepon' => $personal->telepon,
                'email' => $personal->email,
                'jenis_kelas_id' => $personal->jenis_kelas_id,
            ],
        ]);
    }

    // ─────────────────────────────────────────────
    //  KOMTEK DECISION
    // ─────────────────────────────────────────────

    /**
     * Resolve K/BK from komtek votes.
     */
    public function komtekDecision(string $idIzin): JsonResponse
    {
        $decision = $this->resolveKomtekDecision($idIzin);
        return response()->json([
            'success' => true,
            'data' => ['decision' => $decision],
        ]);
    }

    // ─────────────────────────────────────────────
    //  JABATAN KERJA DATA
    // ─────────────────────────────────────────────

    /**
     * Get jabatan kerja details.
     */
    public function getJabatanKerja(string $idJabatan): JsonResponse
    {
        $jk = JabatanKerja::where('id_jabatan_kerja', $idJabatan)->first();
        if (!$jk) {
            return response()->json(['success' => false, 'error' => 'Not found'], 404);
        }
        return response()->json([
            'success' => true,
            'data' => [
                'id_jabatan_kerja' => $jk->id_jabatan_kerja,
                'jabatan_kerja' => $jk->jabatan_kerja,
                'acuan' => $jk->acuan,
            ],
        ]);
    }

	    // ─────────────────────────────────────────────
    //  SMART DATA SYNC
    // ─────────────────────────────────────────────

    /**
     * Sync all smart-database tables that are missing locally.
     * GET /internal/smart-sync
     */
    public function syncSmartAll(): JsonResponse
    {
        if (!$this->smartSyncService->isConnectionAlive()) {
            return response()->json([
                'success' => false,
                'error' => 'Cannot connect to the smart database. Check DB_SMART_* env vars.',
            ], 503);
        }

        $result = $this->smartSyncService->syncAll();

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * Sync a single smart-database table.
     * GET /internal/smart-sync/{table}
     *
     * Available tables: JabatanConfigA, JabatanDokumen, UnitKompetensi,
     *                   SubunitKompetensi, Soal, Referensi
     */
    public function syncSmartTable(string $table): JsonResponse
    {
        $map = [
            'JabatanConfigA'   => \App\Models\JabatanConfigA::class,
            'JabatanDokumen'   => \App\Models\JabatanDokumen::class,
            'UnitKompetensi'   => \App\Models\UnitKompetensi::class,
            'SubunitKompetensi'=> \App\Models\SubunitKompetensi::class,
            'Soal'             => \App\Models\Soal::class,
            'Referensi'        => \App\Models\Referensi::class,
        ];

        $modelClass = $map[$table] ?? $map[ucfirst($table)] ?? null;
        if (!$modelClass) {
            return response()->json([
                'success' => false,
                'error' => "Unknown table: {$table}. Available: " . implode(', ', array_keys($map)),
            ], 404);
        }

        if (!$this->smartSyncService->isConnectionAlive()) {
            return response()->json([
                'success' => false,
                'error' => 'Cannot connect to the smart database. Check DB_SMART_* env vars.',
            ], 503);
        }

        $result = $this->smartSyncService->syncTable($modelClass);

        return response()->json([
            'success' => true,
            'data' => [
                'table' => $table,
                'inserted' => $result['inserted'],
                'skipped' => $result['skipped'],
            ],
        ]);
    }

    // ─────────────────────────────────────────────
    //  PRIVATE HELPERS
    // ─────────────────────────────────────────────

    private function resolveAsesor(string $noreg): ?AsesorBnsp
    {
        $asesor = AsesorBnsp::where('noreg', $noreg)->first();
        if (!$asesor) {
            $asesor = AsesorBnsp::fetchFromBnspByNoreg($noreg);
        }
        return $asesor;
    }

    private function findOrCreateAsesiUser(?Personal $personal, string $idIzin): User
    {
        $asesiUser = User::where('name', $personal?->nama)->first();
        if (!$asesiUser) {
            $asesiUser = User::create([
                'name'    => $personal?->nama ?? $idIzin,
                'email'   => $idIzin . '@asesi.generated.local',
                'phone'   => $personal?->telepon,
                'password'=> bcrypt(Str::random(16)),
                'role_id' => 8,
            ]);
        }
        return $asesiUser;
    }
}
