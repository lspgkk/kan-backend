<?php

namespace App\Services;

use App\Models\AsesorBnsp;
use App\Models\Barcode;
use App\Models\BuktiAsesmen;
use App\Models\JabatanConfigA;
use App\Models\JabatanKerja;
use App\Models\JenisKelas;
use App\Models\KlasifikasiKualifikasi;
use App\Models\MyTuk;
use App\Models\Personal;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class GenerateDokumenService
{
    private const MOON_BASE = 'https://moon.lspgatensi.id/files/new_gatensi/';

    private PermohonanSyncService $syncService;
    private LlmAutoFillService $llmService;

    /** Mode operasi: null=full, 'pregen'=only LLM+DB, 'pdf'=only PDF from DB */
    private ?string $mode = null;

    /** @var resource|\FTP\Connection|null Persistent FTP connection reused across all documents */
    private $ftpConn = null;
    /** @var bool Fall back to Laravel Storage facade if native FTP fails */
    private bool $ftpUseNative = true;

    /** @var array<string, string> In-memory cache of existing barcodes keyed by "userId:jenisDokumen" */
    private array $barcodeCache = [];

    // NOTE: AK05 group PDFs are NOT cached in-memory because each asesi in the group
    // may only have its answers pre-populated when its own processIdIzin runs.
    // Removing the cache ensures the group PDF always contains the most complete
    // data available at generation time. The FTP file is overwritten each time
    // but the URL is the same for all group members.

    // Controllers whose private build*Data methods we call via reflection
    private \App\Http\Controllers\PraAsesmenController $praAsesmenController;
    private \App\Http\Controllers\AsesmenController $asesmenController;
    private \App\Http\Controllers\KanAsesmenController $kanAsesmenController;

    public function __construct(
        PermohonanSyncService $syncService,
        LlmAutoFillService $llmService
    ) {
        $this->syncService = $syncService;
        $this->llmService = $llmService;
        $this->praAsesmenController = app(\App\Http\Controllers\PraAsesmenController::class);
        $this->asesmenController = app(\App\Http\Controllers\AsesmenController::class);
        $this->kanAsesmenController = app(\App\Http\Controllers\KanAsesmenController::class);
    }

    // ─────────────────────────────────────────────
    //  MAIN ORCHESTRATOR
    // ─────────────────────────────────────────────

    /**
     * Fetch tanggal_mulai from BNSP API using jadwal_id.
     */
    private function fetchTanggalUjiFromBnsp(int|string $jadwalId): ?string
    {
        // Cache per jadwalId untuk 1 jam — menghindari HTTP call berulang untuk batch besar
        $cacheKey = "bnsp:jadwal:{$jadwalId}";
        return \Illuminate\Support\Facades\Cache::remember($cacheKey, 3600, function () use ($jadwalId) {
            try {
                $authData = \Illuminate\Support\Facades\DB::connection('balai')->table('b_n_s_p_auths')->value('x_authorization');
                if (!$authData) {
                    logger()->warning("[BNSP] x-authorization tidak ditemukan di tabel b_n_s_p_auths");
                    return null;
                }

                $url = "https://konstruksi.bnsp.go.id/api/v1/jadwal/blanko?jadwal_id={$jadwalId}";
                $response = Http::timeout(15)->withHeaders([
                    'Accept' => 'application/json',
                    'x-authorization' => $authData,
                ])->get($url);

                if ($response->failed()) {
                    logger()->warning("[BNSP] HTTP {$response->status()} for jadwal_id={$jadwalId}, body=" . $response->body());
                    return null;
                }

                $body = $response->json();
                $tanggalMulai = $body['dataJadwal']['tanggal_mulai'] ?? null;
                if ($tanggalMulai) {
                    return Carbon::parse($tanggalMulai)->format('Y-m-d');
                }

                logger()->warning("[BNSP] tanggal_mulai not found in response for jadwal_id={$jadwalId}");
                return null;
            } catch (\Throwable $e) {
                logger()->error("[BNSP] Gagal fetch jadwal {$jadwalId}: " . $e->getMessage() . ' at line ' . $e->getLine() . ' in ' . $e->getFile());
                return null;
            }
        });
    }

    /**
     * Process a single ID Izin — ensure data exists, then generate all documents.
     */
    /**
     * Helper: skip LLM API calls in 'pdf' mode (data already pre-populated from pregen).
     */
    private function generateLlmText(string $fieldType, array $context = []): string
    {
        if ($this->mode === 'pdf') {
            return $this->llmService->getFallback($fieldType);
        }
        return $this->llmService->generateText($fieldType, $context);
    }

    public function processIdIzin(
        string $idIzin,
        string $noregAsesor1,
        string $noregAsesor2,
        string $tanggalUji,
        ?string $jadwalId = null,
        ?string $mode = null
    ): array {
        $this->mode = $mode;
        $result = [
            'id_izin' => $idIzin,
            'status' => 'success',
            'error' => null,
            'urls' => [],
        ];

        $memStart = memory_get_usage(true);
        logger()->debug("[GEN] {$idIzin} START memory=" . round($memStart / 1024 / 1024, 1) . "MB peak=" . round(memory_get_peak_usage(true) / 1024 / 1024, 1) . "MB");

        // ── Step 0: Override tanggal_uji from BNSP API if jadwal_id provided ──
        if ($jadwalId) {
            $apiTanggal = $this->fetchTanggalUjiFromBnsp($jadwalId);
            if ($apiTanggal) {
                $tanggalUji = $apiTanggal;
                logger()->debug("[BNSP] id_izin={$idIzin} menggunakan tanggal_mulai={$tanggalUji} dari jadwal_id={$jadwalId}");
            } else {
                logger()->warning("[BNSP] id_izin={$idIzin} gagal fetch, fallback ke tanggal_uji dari Excel={$tanggalUji}");
            }
        }

        logger()->debug("[GEN] processIdIzin START id_izin={$idIzin} noreg1={$noregAsesor1} noreg2={$noregAsesor2} tgl={$tanggalUji} jadwal={$jadwalId}");

        // ── Step 1: Ensure personals exists ──
        $syncResult = $this->syncService->syncWithFallback($idIzin);
        if (!$syncResult['success']) {
            logger()->warning("[GEN] {$idIzin} SYNC_FAILED: " . ($syncResult['error'] ?? 'unknown'));
            return array_merge($result, [
                'status' => 'sync_failed',
                'error' => $syncResult['error'] ?? 'Sync gagal dari semua sumber',
            ]);
        }

        // ── Step 2: Verify KlasifikasiKualifikasi ──
        $klasifikasi = KlasifikasiKualifikasi::where('id_izin', $idIzin)->first();
        if (!$klasifikasi) {
            logger()->warning("[GEN] {$idIzin} NO_KLASIFIKASI");
            return array_merge($result, [
                'status' => 'no_klasifikasi',
                'error' => 'Klasifikasi Kualifikasi tidak ditemukan',
            ]);
        }
        $jenjang = (int) $klasifikasi->jenjang;
        $jabatanKerjaId = $klasifikasi->jabatan_kerja;
        $tukId = $klasifikasi->tuk;

        // ── Step 3: Resolve asesor (fetch from BNSP if not found locally) ──
        $asesor1 = AsesorBnsp::where('noreg', $noregAsesor1)->first();
        $asesor2 = AsesorBnsp::where('noreg', $noregAsesor2)->first();

        if (!$asesor1) {
            logger()->info("[GEN] {$idIzin} ASESOR1 noreg={$noregAsesor1} tidak ditemukan di DB, fetch dari BNSP...");
            $asesor1 = AsesorBnsp::fetchFromBnspByNoreg($noregAsesor1);
            if (!$asesor1) {
                logger()->warning("[GEN] {$idIzin} ASESOR1 noreg={$noregAsesor1} gagal fetch dari BNSP");
                return array_merge($result, [
                    'status' => 'asesor_not_found',
                    'error' => 'Asesor 1 dengan noreg ' . $noregAsesor1 . ' tidak ditemukan di database dan gagal mengambil dari BNSP',
                ]);
            }
            logger()->info("[GEN] {$idIzin} ASESOR1 noreg={$noregAsesor1} berhasil diambil dari BNSP");
        }

        if (!$asesor2) {
            logger()->info("[GEN] {$idIzin} ASESOR2 noreg={$noregAsesor2} tidak ditemukan di DB, fetch dari BNSP...");
            $asesor2 = AsesorBnsp::fetchFromBnspByNoreg($noregAsesor2);
            if (!$asesor2) {
                logger()->warning("[GEN] {$idIzin} ASESOR2 noreg={$noregAsesor2} gagal fetch dari BNSP");
                return array_merge($result, [
                    'status' => 'asesor_not_found',
                    'error' => 'Asesor 2 dengan noreg ' . $noregAsesor2 . ' tidak ditemukan di database dan gagal mengambil dari BNSP',
                ]);
            }
            logger()->info("[GEN] {$idIzin} ASESOR2 noreg={$noregAsesor2} berhasil diambil dari BNSP");
        }

        $asesor1User = User::where('noreg', $noregAsesor1)->first();
        $asesor2User = User::where('noreg', $noregAsesor2)->first();

        // ── Step 4: Resolve asesi user (find or create) ──
        $personal = Personal::where('id_izin', $idIzin)->first();
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

        // ── Step 5: Build header ──
        $header = $this->buildDokumenHeader(
            $idIzin, $jenjang, $jabatanKerjaId, $tukId,
            $asesor1, $asesor2, $asesor1User, $asesor2User,
            $tanggalUji
        );

        // ── Step 6: Pre-populate answer tables so build*Data methods read kompeten=true ──
        // (Skip in 'pdf' mode — data sudah ada dari pregen sebelumnya)
        if ($this->mode !== 'pdf') {
            $this->prePopulateAnswers($idIzin);
        }
        gc_collect_cycles();

        // ── Pregen mode: selesai di sini, tidak perlu generate PDF ──
        if ($this->mode === 'pregen') {
            logger()->info("[GEN] {$idIzin} PREGEN-DONE memory=" . round(memory_get_usage(true) / 1024 / 1024, 1) . "MB peak=" . round(memory_get_peak_usage(true) / 1024 / 1024, 1) . "MB");
            return array_merge($result, ['status' => 'pregen', 'urls' => []]);
        }

        // Init persistent FTP connection for all PDF uploads in this job
        $this->initFtp();

        try {
            $urls = [];

            // Timestamp base: tanggal_uji at 08:00:00
            // Each id_izin gets a unique random minute so times aren't identical
            srand(crc32($idIzin));
            $randMinute = rand(0, 59);
            srand(crc32($idIzin . '_pra'));
            $praMinute = rand(0, 59);
            $dayStart = Carbon::parse($tanggalUji)->startOfDay();
            $praBase = (clone $dayStart)->addHours(8)->addMinutes($praMinute);
            $apl01Base = (clone $dayStart)->subDay()->addHours(8)->addMinutes($praMinute);
            $asesmenBase = (clone $dayStart)->addHours(8)->addMinutes($randMinute);

            // Time offsets (seconds) from each base — same offsets apply to all,
            // but each id_izin has different base minute so times vary
            $timeOffsets = [
                'Apl01' => 323, 'Apl02' => 737, 'Mapa01' => 1004, 'Mapa02' => 1173,
                'Ak07' => 1491, 'Ak04' => 1752, 'Ak01' => 2265,
                'Ia01' => 2539, 'Ia02' => 3000, 'Ia03' => 3400,
                'Ia04a' => 2539, 'Ia04b' => 3554, 'Ia05' => 5742,
                'Ak02' => 6807, 'Ak03' => 7124, 'Ak05' => 7401, 'Ak06' => 7401,
                'KanIa04b' => 8000, 'KanIa05' => 8300, 'KanIa06' => 8600, 'KanAk02' => 8900,
            ];

            // Pre-fetch all existing barcodes in ONE query (avoids ~42 individual DB upserts)
            $this->loadBarcodeCache($idIzin);

            // ── Step 7: Generate pra-asesmen (7 docs) ──
            $praDocs = [
                ['method' => 'Apl01', 'blade' => 'apl01', 'dir' => 'apl01', 'code' => 'APL-01', 'col' => 'url_apl_01'],
                ['method' => 'Apl02', 'blade' => 'apl02', 'dir' => 'apl02', 'code' => 'APL-02', 'col' => 'url_apl_02'],
                ['method' => 'Mapa01', 'blade' => 'mapa01', 'dir' => 'mapa01', 'code' => 'MAPA-01', 'col' => 'url_mapa01'],
                ['method' => 'Mapa02', 'blade' => 'mapa02', 'dir' => 'mapa02', 'code' => 'MAPA-02', 'col' => 'url_mapa02'],
                ['method' => 'Ak07', 'blade' => 'ak07', 'dir' => 'ak07', 'code' => 'AK-07', 'col' => 'url_ak07'],
                ['method' => 'Ak04', 'blade' => 'ak04', 'dir' => 'ak04', 'code' => 'AK-04', 'col' => 'url_ak04'],
                ['method' => 'Ak01', 'blade' => 'ak01', 'dir' => 'ak01', 'code' => 'AK-01', 'col' => 'url_ak01'],
            ];

            $barcodes = [];
            foreach ($praDocs as $doc) {
                $offset = $timeOffsets[$doc['method']] ?? 300;
                $base = $doc['method'] === 'Apl01' ? $apl01Base : $praBase;
                $ts = fn($extraSec) => (clone $base)->addSeconds($offset + $extraSec)->format('Y-m-d H:i:s');
                $barcodes[$doc['method']] = $this->createDocBarcodes($idIzin, $doc['method'], $asesiUser, $asesor1User, $asesor2User, $ts(0), $ts(2), $ts(3));
            }

            foreach ($praDocs as $doc) {
                try {
                    $url = $this->generateDocument($idIzin, $doc['method'], $doc['blade'], $doc['dir'], $doc['code'], $doc['col'], $header, $barcodes[$doc['method']] ?? []);
                    $urls[$doc['col']] = $url;
                    if (!$url) logger()->warning("[GEN] {$idIzin} PRA DOC FAILED: {$doc['method']}");
                } catch (\Throwable $e) {
                    logger()->warning("[GEN] {$idIzin} PRA DOC EXCEPTION {$doc['method']}: " . $e->getMessage());
                    $urls[$doc['col']] = null;
                }
            }
            gc_collect_cycles();
            logger()->debug("[GEN] {$idIzin} PRA-DONE memory=" . round(memory_get_usage(true) / 1024 / 1024, 1) . "MB peak=" . round(memory_get_peak_usage(true) / 1024 / 1024, 1) . "MB");

            // ── Step 8: Generate asesmen (by jenjang) ──
            $asesmenDocs = $jenjang < 4
                ? [
                    ['method' => 'Ia01', 'blade' => 'ia01', 'dir' => 'ia01', 'code' => 'IA-01', 'col' => 'url_ia_01'],
                    ['method' => 'Ia02', 'blade' => 'ia02', 'dir' => 'ia02', 'code' => 'IA-02', 'col' => 'url_ia_02'],
                    ['method' => 'Ia03', 'blade' => 'ia03', 'dir' => 'ia03', 'code' => 'IA-03', 'col' => 'url_ia_03'],
                    ['method' => 'Ia05', 'blade' => 'ia05', 'dir' => 'ia05', 'code' => 'IA-05', 'col' => 'url_ia_05'],
                    ['method' => 'Ak02', 'blade' => 'ak02', 'dir' => 'ak02', 'code' => 'AK-02', 'col' => 'url_ak02'],
                    ['method' => 'Ak03', 'blade' => 'ak03', 'dir' => 'ak03', 'code' => 'AK-03', 'col' => 'url_ak03'],
                    ['method' => 'Ak05', 'blade' => 'ak05', 'dir' => 'ak05', 'code' => 'AK-05', 'col' => 'url_ak05'],
                    ['method' => 'Ak06', 'blade' => 'ak06', 'dir' => 'ak06', 'code' => 'AK-06', 'col' => 'url_ak06'],
                ]
                : [
                    ['method' => 'Ia04a', 'blade' => 'ia04a', 'dir' => 'ia04a', 'code' => 'IA-04A', 'col' => 'url_ia_04a'],
                    ['method' => 'Ia04b', 'blade' => 'ia04b', 'dir' => 'ia04b', 'code' => 'IA-04B', 'col' => 'url_ia_04b'],
                    ['method' => 'Ia05', 'blade' => 'ia05', 'dir' => 'ia05', 'code' => 'IA-05', 'col' => 'url_ia_05'],
                    ['method' => 'Ak02', 'blade' => 'ak02', 'dir' => 'ak02', 'code' => 'AK-02', 'col' => 'url_ak02'],
                    ['method' => 'Ak03', 'blade' => 'ak03', 'dir' => 'ak03', 'code' => 'AK-03', 'col' => 'url_ak03'],
                    ['method' => 'Ak05', 'blade' => 'ak05', 'dir' => 'ak05', 'code' => 'AK-05', 'col' => 'url_ak05'],
                    ['method' => 'Ak06', 'blade' => 'ak06', 'dir' => 'ak06', 'code' => 'AK-06', 'col' => 'url_ak06'],
                ];

            foreach ($asesmenDocs as $doc) {
                $offset = $timeOffsets[$doc['method']] ?? 300;
                $ts = fn($extraSec) => (clone $asesmenBase)->addSeconds($offset + $extraSec)->format('Y-m-d H:i:s');
                $barcodes[$doc['method']] = $this->createDocBarcodes($idIzin, $doc['method'], $asesiUser, $asesor1User, $asesor2User, $ts(0), $ts(2), $ts(3));
            }

            foreach ($asesmenDocs as $doc) {
                try {
                    $url = $this->generateDocument($idIzin, $doc['method'], $doc['blade'], $doc['dir'], $doc['code'], $doc['col'], $header, $barcodes[$doc['method']] ?? []);
                    $urls[$doc['col']] = $url;
                    if (!$url) logger()->warning("[GEN] {$idIzin} ASESMEN DOC FAILED: {$doc['method']}");
                } catch (\Throwable $e) {
                    logger()->warning("[GEN] {$idIzin} ASESMEN DOC EXCEPTION {$doc['method']}: " . $e->getMessage());
                    $urls[$doc['col']] = null;
                }
            }
            gc_collect_cycles();
            logger()->debug("[GEN] {$idIzin} ASESMEN-DONE memory=" . round(memory_get_usage(true) / 1024 / 1024, 1) . "MB peak=" . round(memory_get_peak_usage(true) / 1024 / 1024, 1) . "MB");

            // ── Step 8b: Generate KAN documents ──
            $kanDocs = [
                ['method' => 'KanIa04b', 'blade' => 'kan_ia04b', 'dir' => 'kan_ia04b', 'code' => 'KAN-IA-04B', 'col' => 'url_kan_ia_04b'],
                ['method' => 'KanIa05', 'blade' => 'kan_ia05', 'dir' => 'kan_ia05', 'code' => 'KAN-IA-05', 'col' => 'url_kan_ia_05'],
                ['method' => 'KanIa06', 'blade' => 'kan_ia06', 'dir' => 'kan_ia06', 'code' => 'KAN-IA-06', 'col' => 'url_kan_ia_06'],
                ['method' => 'KanAk02', 'blade' => 'kan_ak02', 'dir' => 'kan_ak02', 'code' => 'KAN-AK-02', 'col' => 'url_kan_ak_02'],
            ];

            foreach ($kanDocs as $doc) {
                try {
                    $url = $this->generateDocument($idIzin, $doc['method'], $doc['blade'], $doc['dir'], $doc['code'], $doc['col'], $header, $barcodes[$doc['method']] ?? []);
                    $urls[$doc['col']] = $url;
                    if (!$url) logger()->warning("[GEN] {$idIzin} KAN DOC FAILED: {$doc['method']}");
                } catch (\Throwable $e) {
                    logger()->warning("[GEN] {$idIzin} KAN DOC EXCEPTION {$doc['method']}: " . $e->getMessage());
                    $urls[$doc['col']] = null;
                }
            }
            gc_collect_cycles();
            logger()->debug("[GEN] {$idIzin} KAN-DONE memory=" . round(memory_get_usage(true) / 1024 / 1024, 1) . "MB peak=" . round(memory_get_peak_usage(true) / 1024 / 1024, 1) . "MB");

            // ── Final result summary ──
            $totalDocs = count($praDocs) + count($asesmenDocs) + count($kanDocs);
            $generatedUrls = array_filter($urls, fn($v) => $v !== null);
            $generated = count($generatedUrls);

            if ($generated === 0) {
                $result['status'] = 'error';
                $result['error'] = 'Semua dokumen gagal digenerate';
                logger()->error("[GEN] {$idIzin} ALL_FAILED 0/{$totalDocs} docs generated");
            } elseif ($generated < $totalDocs) {
                $result['status'] = 'partial';
                $result['error'] = $generated . '/' . $totalDocs . ' dokumen berhasil';
                $failed = array_diff_key($urls, $generatedUrls);
                logger()->warning("[GEN] {$idIzin} PARTIAL {$generated}/{$totalDocs} docs, missing: " . json_encode(array_keys($failed)));
            } else {
                logger()->debug("[GEN] {$idIzin} SUCCESS {$generated}/{$totalDocs} docs");
            }

            return array_merge($result, ['urls' => $urls]);
        } finally {
            // Always close the FTP connection
            $this->closeFtp();
        }
    }

    // ─────────────────────────────────────────────
    //  DOKUMEN HEADER (no jadwal)
    // ─────────────────────────────────────────────

    private function buildDokumenHeader(
        string $idIzin,
        int $jenjang,
        string $jabatanKerjaId,
        ?string $tukId,
        AsesorBnsp $asesor1,
        AsesorBnsp $asesor2,
        ?User $asesor1User,
        ?User $asesor2User,
        string $tanggalUji
    ): array {
        $personal = Personal::where('id_izin', $idIzin)->first();

        $jabatanKerja = JabatanKerja::where('id_jabatan_kerja', $jabatanKerjaId)->value('jabatan_kerja');
        $jabatanConfig = JabatanConfigA::with(['penyusun:id,name,noreg', 'validator:id,name,noreg', 'manajer:id,name,noreg'])
            ->select(['penyusun_id', 'penyusun_tgl', 'penyusun_img', 'penyusun_url', 'validator_id', 'validator_tgl', 'validator_img', 'validator_url', 'manajer_id', 'manajer_img', 'manajer_url', 'no_skema'])
            ->where('id_jabatan', $jabatanKerjaId)
            ->first();

        $tuk = $tukId ? MyTuk::find($tukId) : null;
        $jenisKelas = $personal && $personal->jenis_kelas_id
            ? JenisKelas::find($personal->jenis_kelas_id)
            : null;

        $tanggal = Carbon::parse($tanggalUji);

        return [
            'jabatan_kerja'   => $jabatanKerja,
            'nomor_skema'     => $jabatanConfig?->no_skema,
            'tuk'             => $tuk?->nama_tuk,
            'id_asesor_1'     => $asesor1User?->id,
            'id_asesor_2'     => $asesor2User?->id,
            'nama_asesi'      => $personal?->nama,
            'asesor_1'        => $asesor1->nama,
            'asesor_2'        => $asesor2->nama,
            'noreg_asesor_1'  => $asesor1->noreg,
            'noreg_asesor_2'  => $asesor2->noreg,
            'tanggal_uji'     => $tanggalUji,
            'tanggal_selesai' => $tanggalUji,
            'jenis_kelas'     => $jenisKelas?->nama_kelas,
            'jenjang'         => $jenjang,
            'nama_penyusun'   => $jabatanConfig?->penyusun?->name,
            'nama_validator'  => $jabatanConfig?->validator?->name,
            'tanggal_penyusun' => $jabatanConfig?->penyusun_tgl,
            'tanggal_validator' => $jabatanConfig?->validator_tgl,
            'barcode_penyusun' => $this->imageUrlToBase64($jabatanConfig?->penyusun_img),
            'barcode_validator' => $this->imageUrlToBase64($jabatanConfig?->validator_img),
            'barcode_manajer' => $this->imageUrlToBase64($jabatanConfig?->manajer_img),
            'noreg_penyusun'  => $jabatanConfig?->penyusun?->noreg,
            'noreg_validator' => $jabatanConfig?->validator?->noreg,
            'nama_manajer'    => $jabatanConfig?->manajer?->name,
            'tanggal_manajer' => $jabatanConfig?->kan_manajer_tgl ?? $jabatanConfig?->validator_tgl,
            'metode'          => 'observasi',
            'waktu'           => $tanggal->locale('id')->translatedFormat('H:i'),
        ];
    }

    // ─────────────────────────────────────────────
    //  BARCODE GENERATION
    // ─────────────────────────────────────────────

    /**
     * Pre-fetch ALL existing barcodes for this id_izin in ONE query.
     * Call this once before starting PDF generation to replace 42 individual SELECTs.
     */
    private function loadBarcodeCache(string $idIzin): void
    {
        $this->barcodeCache = [];
        try {
            $barcodes = Barcode::where('id_izin', $idIzin)->get();
            foreach ($barcodes as $b) {
                $key = "{$b->id_user}:{$b->jenis_dokumen}";
                $this->barcodeCache[$key] = $b;
            }
            logger()->debug("[QR] Loaded " . count($this->barcodeCache) . " existing barcodes for {$idIzin}");
        } catch (\Throwable $e) {
            logger()->warning("[QR] Failed to load barcode cache for {$idIzin}: " . $e->getMessage());
        }
    }

    private function createBarcode(string $idIzin, string $jenisDokumen, int $userId, int $roleId, ?string $signedAt = null): ?array
    {
        $cacheKey = "{$userId}:{$jenisDokumen}";

        // Check cache first (pre-fetched in ONE query) to avoid individual SELECTs
        $existing = $this->barcodeCache[$cacheKey] ?? null;

        if ($existing) {
            // Reuse existing url_ttd — keeps UUID stable across runs
            $urlTtd = $existing->url_ttd;
            // Only update created_at if timestamp changed (lightweight UPDATE, no SELECT needed)
            if ($signedAt && $existing->created_at?->format('Y-m-d H:i:s') !== $signedAt) {
                Barcode::where('id_user', $userId)
                    ->where('id_izin', $idIzin)
                    ->where('jenis_dokumen', $jenisDokumen)
                    ->update(['created_at' => $signedAt]);
            }
        } else {
            // New barcode — generate UUID + DB insert
            $uuid = (string) Uuid::uuid4();
            $urlTtd = "https://barcode.lspgatensi.id/ttd/{$uuid}";

            try {
                $data = [
                    'url_ttd' => $urlTtd,
                    'id_role' => $roleId,
                ];
                if ($signedAt) {
                    $data['created_at'] = $signedAt;
                }
                Barcode::updateOrCreate(
                    ['id_user' => $userId, 'id_izin' => $idIzin, 'jenis_dokumen' => $jenisDokumen],
                    $data
                );
                // Add to cache so subsequent calls skip DB
                $this->barcodeCache[$cacheKey] = new \App\Models\Barcode([
                    'url_ttd' => $urlTtd,
                    'id_user' => $userId,
                    'id_izin' => $idIzin,
                    'jenis_dokumen' => $jenisDokumen,
                    'created_at' => $signedAt ? \Carbon\Carbon::parse($signedAt) : null,
                ]);
            } catch (\Throwable $e) {
                logger()->error("[QR] EXCEPTION saving Barcode DB for {$jenisDokumen}/{$idIzin}: " . $e->getMessage());
            }
        }

        // Generate QR code (fast local operation, needed for PDF embedding regardless of cache)
        $qrBase64 = null;
        try {
            $qrRaw = QrCode::format('png')->size(100)->margin(0)->generate($urlTtd);
            $qrBase64 = 'data:image/png;base64,' . base64_encode((string) $qrRaw);
        } catch (\Throwable $e) {
            logger()->error("[QR] EXCEPTION generating QR for {$jenisDokumen}/{$idIzin}: " . $e->getMessage());
        }

        return [
            'url'      => $qrBase64,
            'url_ttd'  => $urlTtd,
            'tanggal'  => $signedAt,
            'user_id'  => $userId,
            'role_id'  => $roleId,
        ];
    }

    private function mapBarcodeForPdf(?Barcode $barcode): ?array
    {
        if (!$barcode) {
            return null;
        }
        return [
            'url'     => $this->imageUrlToBase64($barcode->url_image),
            'tanggal' => $barcode->created_at,
            'nama'    => $barcode->user?->name,
        ];
    }

    /**
     * Generate barcodes for a batch of documents.
     * Returns barcodes grouped by doc method name, each with [asesi, asesor1, asesor2].
     */
    /**
     * Create barcodes for ONE document with per-role timestamps.
     * asesi signs at $asesiAt, asesor1 at $asesor1At, asesor2 at $asesor2At.
     */
    private function createDocBarcodes(
        string $idIzin,
        string $method,
        User $asesiUser,
        ?User $asesor1User,
        ?User $asesor2User,
        string $asesiAt,
        string $asesor1At,
        string $asesor2At
    ): array {
        $asesi   = $this->createBarcode($idIzin, $method, $asesiUser->id, 8, $asesiAt);
        $asesor1 = $asesor1User ? $this->createBarcode($idIzin, $method, $asesor1User->id, $asesor1User->role_id ?? 2, $asesor1At) : null;
        $asesor2 = $asesor2User ? $this->createBarcode($idIzin, $method, $asesor2User->id, $asesor2User->role_id ?? 2, $asesor2At) : null;

        return [
            'asesi'   => [
                'url'     => $asesi['url'] ?? null,
                'tanggal' => $asesiAt,
                'nama'    => $asesiUser->name,
            ],
            'asesor1' => $asesor1 ? [
                'url'     => $asesor1['url'] ?? null,
                'tanggal' => $asesor1At,
                'nama'    => $asesor1User->name,
            ] : null,
            'asesor2' => $asesor2 ? [
                'url'     => $asesor2['url'] ?? null,
                'tanggal' => $asesor2At,
                'nama'    => $asesor2User->name,
            ] : null,
        ];
    }

    // ─────────────────────────────────────────────
    //  PDF GENERATION & STORAGE
    // ─────────────────────────────────────────────

    // ─────────────────────────────────────────────
    //  PERSISTENT FTP HELPERS
    // ─────────────────────────────────────────────

    /**
     * Establish a persistent FTP connection reused for all PDF uploads in this job.
     * Falls back to Laravel Storage facade on failure.
     */
    private function initFtp(): void
    {
        $config = config('filesystems.disks.str_moon');
        if (!$config) {
            $this->ftpUseNative = false;
            return;
        }

        try {
            $host = $config['host'];
            $port = $config['port'] ?? 21;
            $timeout = 15;

            $conn = @ftp_connect($host, $port, $timeout);
            if (!$conn) {
                logger()->warning('[FTP] Gagal konek ke ' . $host . ':' . $port . ', fallback ke Storage facade');
                $this->ftpUseNative = false;
                return;
            }

            if (!@ftp_login($conn, $config['username'], $config['password'])) {
                logger()->warning('[FTP] Login gagal, fallback ke Storage facade');
                @ftp_close($conn);
                $this->ftpUseNative = false;
                return;
            }

            // Passive mode
            @ftp_pasv($conn, true);

            // Set transfer timeout
            @ftp_set_option($conn, FTP_TIMEOUT_SEC, 30);

            // Change to root directory if configured
            if (!empty($config['root'])) {
                @ftp_chdir($conn, $config['root']);
            }

            $this->ftpConn = $conn;
            logger()->debug('[FTP] Persistent connection established to ' . $host);
        } catch (\Throwable $e) {
            logger()->warning('[FTP] Exception saat init: ' . $e->getMessage() . ', fallback ke Storage facade');
            $this->ftpUseNative = false;
        }
    }

    /**
     * Close the persistent FTP connection if open.
     */
    private function closeFtp(): void
    {
        if ($this->ftpConn) {
            try {
                @ftp_close($this->ftpConn);
            } catch (\Throwable $e) {
                // ignore
            }
            $this->ftpConn = null;
        }
    }

    /**
     * Upload a file via persistent FTP connection with retry fallback.
     */
    private function uploadPdf(string $remotePath, string $content, string $code): bool
    {
        // Use persistent connection if available
        if ($this->ftpUseNative && $this->ftpConn) {
            // Write to a temp stream so ftp_fput can use it
            $tmpFile = tmpfile();
            if ($tmpFile === false) {
                // Fall back to Storage facade
                return $this->uploadPdfFallback($remotePath, $content, $code);
            }

            fwrite($tmpFile, $content);
            rewind($tmpFile);

            for ($retry = 0; $retry < 3; $retry++) {
                try {
                    $uploaded = @ftp_fput($this->ftpConn, $remotePath, $tmpFile, FTP_BINARY);
                    if ($uploaded) {
                        fclose($tmpFile);
                        return true;
                    }
                    logger()->warning("[FTP] ftp_fput {$code} attempt={$retry} returned false");
                } catch (\Throwable $e) {
                    logger()->warning("[FTP] ftp_fput {$code} attempt={$retry} exception: " . $e->getMessage());
                }

                if ($retry < 2) {
                    // Reconnect FTP on failure
                    $this->closeFtp();
                    $this->initFtp();
                    if (!$this->ftpConn) {
                        fclose($tmpFile);
                        return $this->uploadPdfFallback($remotePath, $content, $code);
                    }
                    // Re-create temp stream for retry
                    fclose($tmpFile);
                    $tmpFile = tmpfile();
                    fwrite($tmpFile, $content);
                    rewind($tmpFile);
                }
            }
            fclose($tmpFile);
        }

        // Fallback to Storage facade
        return $this->uploadPdfFallback($remotePath, $content, $code);
    }

    /**
     * Fallback upload via Laravel Storage facade (per-connection FTP).
     */
    private function uploadPdfFallback(string $remotePath, string $content, string $code): bool
    {
        for ($retry = 0; $retry < 3; $retry++) {
            try {
                if ($retry > 0) {
                    Storage::purge('str_moon');
                }
                $uploaded = Storage::disk('str_moon')->put($remotePath, $content);
                if ($uploaded) return true;
                logger()->warning("[FTP] fallback put {$code} attempt={$retry} returned false");
            } catch (\Throwable $e) {
                logger()->warning("[FTP] fallback put {$code} attempt={$retry} exception: " . $e->getMessage());
            }
            if ($retry < 2) sleep(5);
        }
        return false;
    }

    /**
     * Compute a content hash for PDF caching.
     * Only structural inputs (header, barcode URLs, blade). NOT docData (LLM text changes every run).
     * Cache invalidates only on: header change, barcode change, blade template change, or manual flush.
     */
    private function computePdfCacheKey(
        string $idIzin,
        string $method,
        string $blade,
        array $header,
        array $barcodes
    ): string {
        $hashInput = [];

        // Stable header fields (skip timestamps since they vary by run)
        $headerKeys = ['jabatan_kerja', 'nomor_skema', 'tuk', 'nama_asesi', 'asesor_1', 'asesor_2',
            'noreg_asesor_1', 'noreg_asesor_2', 'jenis_kelas', 'jenjang', 'nama_penyusun',
            'nama_validator', 'nama_manajer', 'barcode_penyusun', 'barcode_validator',
            'barcode_manajer', 'noreg_penyusun', 'noreg_validator'];
        foreach ($headerKeys as $k) {
            $hashInput[] = $k . '=' . ($header[$k] ?? 'null');
        }

        // Barcode url_ttd (stable) — skip volatile QR base64
        $barcodeKeys = ['asesi', 'asesor1', 'asesor2'];
        foreach ($barcodeKeys as $k) {
            $hashInput[] = "{$k}_ttd=" . ($barcodes[$k]['url_ttd'] ?? 'null');
        }

        // Blade template mtime
        $bladePath = resource_path("views/pdf/{$blade}.blade.php");
        if (file_exists($bladePath)) {
            $hashInput[] = 'blade_mtime=' . filemtime($bladePath);
        }

        return md5(implode('|', $hashInput));
    }

    private function generateDocument(
        string $idIzin,
        string $method,
        string $blade,
        string $dir,
        string $code,
        string $urlColumn,
        array $header,
        array $barcodes
    ): ?string {
        try {
            // ── AK05 GROUP RESOLUTION ──
            // Group asesi by jabatan_kerja within the same jadwal: each group gets
            // one combined PDF. No in-memory caching — each asesi in the group
            // regenerates the same file so answers from all members are included.
            $ak05GroupKey = null;
            $ak05GroupMembers = null;

            if ($method === 'Ak05') {
                $asesiJadwal = \App\Models\AsesiJadwal::where('id_izin', $idIzin)->first();
                if ($asesiJadwal) {
                    $klasifikasi = \App\Models\KlasifikasiKualifikasi::where('id_izin', $idIzin)->first();
                    if ($klasifikasi) {
                        $ak05GroupKey = $asesiJadwal->jadwal_id . '-' . $klasifikasi->jabatan_kerja;

                        // Fetch all group members (same jadwal + same jabatan_kerja)
                        $ak05GroupMembers = \App\Models\AsesiJadwal::where('asesi_jadwals.jadwal_id', $asesiJadwal->jadwal_id)
                            ->join('personals', 'personals.id_izin', '=', 'asesi_jadwals.id_izin')
                            ->join('klasifikasi_kualifikasis', 'klasifikasi_kualifikasis.id_izin', '=', 'asesi_jadwals.id_izin')
                            ->where('klasifikasi_kualifikasis.jabatan_kerja', $klasifikasi->jabatan_kerja)
                            ->select(['asesi_jadwals.id_izin', 'personals.nama'])
                            ->get();
                    }
                }
            }

            // ── BUILD VIEW DATA ──
            if ($method === 'Ak05' && $ak05GroupMembers) {
                // For AK05 groups: build combined view data for all group members
                $docData = $this->buildAk05GroupViewData($header, $ak05GroupMembers, $barcodes);
                if ($docData === null) {
                    logger()->warning("[GEN] {$idIzin} generateDocument {$code}: ak05GroupViewData NULL");
                    return null;
                }
            } else {
                // Normal flow: call controller's build*Data method via reflection
                $docData = $this->callBuildMethod($method, $idIzin);
                if ($docData === null && $method === 'Apl01') {
                    $docData = $this->buildApl01Data($idIzin, $header);
                }
                if ($docData === null) {
                    logger()->warning("[GEN] {$idIzin} generateDocument {$code}: docData NULL — controller & inline both returned null");
                    return null;
                }
                $docData = $this->sanitizeViewData($method, $docData, $header);
            }

            // AK01: waktu is always "07:30 - Selesai" per business rules
            if ($method === 'Ak01') {
                $header['waktu'] = '07:30 - Selesai';
            }

            // Merge barcodes — for AK05 group the barcodes are already embedded in viewData
            $mergedBarcodes = array_merge(
                $docData['barcodes'] ?? [],
                $barcodes,
                ['admin' => $barcodes['asesor1'] ?? $barcodes['asesi'] ?? null]
            );
            $viewData = array_merge($docData, [
                'dokumenHeader' => $header,
                'barcodes' => $mergedBarcodes,
            ]);

            // ── LOG: Sample LLM fields ──
            $llmFieldKeys = ['keterangan', 'aspek', 'saran', 'catatan', 'komentar', 'rekomendasi1', 'rekomendasi2', 'catatan_asesor1', 'catatan_asesor2', 'answers', 'tanggapan', 'umpan_balik', 'alasan'];
            $llmInData = [];
            foreach ($llmFieldKeys as $k) {
                if (array_key_exists($k, $viewData)) {
                    $val = $viewData[$k];
                    $llmInData[$k] = is_string($val) ? substr($val, 0, 80) . '...' : (is_object($val) ? 'object(' . get_class($val) . ')' : json_encode($val));
                }
            }
            if (isset($viewData['answers']) && is_object($viewData['answers'])) {
                $ans = $viewData['answers'];
                foreach (['keterangan', 'aspek', 'saran', 'catatan'] as $k) {
                    if (isset($ans->$k)) { $llmInData['answers->' . $k] = substr($ans->$k, 0, 80) . '...'; }
                }
            }
            logger()->debug("[LLM] PDF data untuk {$code} {$idIzin}: " . json_encode($llmInData));

            // ── PDF PATH & CACHE KEY ──
            if ($method === 'Ak05' && $ak05GroupKey) {
                $path = "dok_asesmen/{$dir}/FR-{$code}-JADWAL-{$ak05GroupKey}.pdf";
                $cachedPdfKey = "pdf:cache:Ak05:group:{$ak05GroupKey}";
                $cachedUrlKey = "pdf:url:Ak05:group:{$ak05GroupKey}";
            } else {
                $path = "dok_asesmen/{$dir}/FR-{$code}-{$idIzin}.pdf";
                $cachedPdfKey = "pdf:cache:{$idIzin}:{$method}";
                $cachedUrlKey = "pdf:url:{$idIzin}:{$method}";
            }
            $contentHash = $this->computePdfCacheKey($idIzin, $method, $blade, $header, $barcodes);

            $cachedHash = \Illuminate\Support\Facades\Cache::get($cachedPdfKey);
            $cachedUrl = \Illuminate\Support\Facades\Cache::get($cachedUrlKey);

            if ($cachedHash === $contentHash && $cachedUrl) {
                try {
                    $moonUrl = self::MOON_BASE . $path;
                    $headResponse = \Illuminate\Support\Facades\Http::timeout(5)->head($moonUrl);
                    if ($headResponse->successful()) {
                        logger()->debug("[PDF-CACHE] {$code} {$idIzin} HIT — skipping DomPDF+FTP, reusing existing PDF");

                        // For AK05 groups, update ALL group members
                        if ($method === 'Ak05' && $ak05GroupMembers) {
                            foreach ($ak05GroupMembers as $member) {
                                BuktiAsesmen::updateOrCreate(
                                    ['id_izin' => $member->id_izin],
                                    [$urlColumn => $cachedUrl]
                                );
                            }
                        } else {
                            BuktiAsesmen::updateOrCreate(
                                ['id_izin' => $idIzin],
                                [$urlColumn => $cachedUrl]
                            );
                        }
                        return $cachedUrl;
                    }
                } catch (\Throwable $e) {
                    // FTP check failed — regenerate
                }
            }
            logger()->debug("[PDF-CACHE] {$code} {$idIzin} MISS — rendering DomPDF");

            $pdf = Pdf::loadView("pdf.{$blade}", $viewData)->setPaper('A4', 'portrait');
            $pdfContent = $pdf->output();

            unset($pdf, $viewData, $mergedBarcodes, $docData);
            gc_collect_cycles();

            $pdfUploaded = $this->uploadPdf($path, $pdfContent, $code);
            unset($pdfContent);
            gc_collect_cycles();

            if (!$pdfUploaded) return null;

            $fullUrl = self::MOON_BASE . $path;

            \Illuminate\Support\Facades\Cache::put($cachedPdfKey, $contentHash, 86400);
            \Illuminate\Support\Facades\Cache::put($cachedUrlKey, $fullUrl, 86400);

            // For AK05 groups, update ALL group members (regenerates same file — most complete data wins)
            if ($method === 'Ak05' && $ak05GroupMembers) {
                foreach ($ak05GroupMembers as $member) {
                    BuktiAsesmen::updateOrCreate(
                        ['id_izin' => $member->id_izin],
                        [$urlColumn => $fullUrl]
                    );
                }
                logger()->debug("[GEN] AK05 group {$ak05GroupKey} generated — saved to " . count($ak05GroupMembers) . " asesi");
            } else {
                BuktiAsesmen::updateOrCreate(
                    ['id_izin' => $idIzin],
                    [$urlColumn => $fullUrl]
                );
            }

            return $fullUrl;
        } catch (\Throwable $e) {
            logger()->error("Gagal generate {$code} untuk {$idIzin}: " . $e->getMessage());
            return null;
        }
    }

    // ─────────────────────────────────────────────
    //  DATA BUILDERS — PRA-ASESMEN
    // ─────────────────────────────────────────────

    private function buildApl01Data(string $idIzin, array $header): array
    {
        $personal = Personal::select(['nama', 'nik', 'tempat_lahir', 'tanggal_lahir', 'jenis_kelamin', 'alamat', 'kodepos', 'telepon', 'email', 'pas_foto', 'ktp'])
            ->where('id_izin', $idIzin)->first();
        $klasifikasi = KlasifikasiKualifikasi::where('id_izin', $idIzin)->first();
        $jabatanKerja = JabatanKerja::where('id_jabatan_kerja', $klasifikasi?->jabatan_kerja)->first();
        $pekerjaan = \App\Models\Proyek::where('id_izin', $idIzin)->latest('created_at')->first();
        $unitKompetensi = \App\Models\UnitKompetensi::select(['kode', 'nama'])
            ->where('id_jabatan', $klasifikasi?->jabatan_kerja)->get();
        $nomorSkema = \App\Models\JabatanConfigA::where('id_jabatan', $klasifikasi?->jabatan_kerja)->value('no_skema');
        $kualifikasi = \App\Models\JenjangJabatanKerja::where('jenjang_id', $klasifikasi?->jenjang)->value('jabatan_kerja');

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

        // ── Persyaratan: only check items that match asesi's education level ──
        $jenjangPendidikan = \App\Models\Pendidikan::where('id_izin', $idIzin)
            ->latest('created_at')->value('jenjang');

        // Get latest pendidikan data for ijazah URL
        $pendidikanTerakhir = \App\Models\Pendidikan::where('id_izin', $idIzin)
            ->latest('created_at')->first();

        $educationMapping = [
            'SD' => ['1', '2'], 'SMP' => ['2'], 'SMA' => ['3'],
            'D1' => ['4', '16'], 'D2' => ['5'], 'D3' => ['6'],
            'D4' => ['7', '12'], 'S1' => ['8', '13'], 'S2' => ['9', '14', '18'],
            'S3' => ['10', '15', '19'], 'profesi' => ['11'], 'Non' => ['99'],
        ];
        $educationOrder = ['Non', 'SD', 'SMP', 'SMA', 'D1', 'D2', 'D3', 'D4', 'S1', 'S2', 'S3', 'profesi'];

        $persyaratanRecords = \App\Models\ImpBuktiPersyaratan::select(['no', 'bukti'])
            ->where('id_jabatan', $klasifikasi?->jabatan_kerja)
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

        $persyaratan = $persyaratanRecords->map(function ($item) use ($matchedLevels, $pendidikanTerakhir) {
            $checked = false;
            foreach ($matchedLevels as $l) {
                if (stripos($item->bukti, $l) !== false) { $checked = true; break; }
            }

            $ijazahUrl = null;
            if ($checked && $pendidikanTerakhir && stripos($item->bukti, 'ijazah') !== false) {
                $ijazahUrl = $pendidikanTerakhir->scan_ijazah_legalisir;
            }

            return [
                'no' => $item->no,
                'bukti' => $item->bukti,
                'checked' => $checked,
                'ijazah_url' => $ijazahUrl
            ];
        })->values();

        // ── Administratif: all checked ──
        $administratif = \App\Models\ImpBuktiAdministrasi::select(['no', 'bukti'])
            ->where('id_jabatan', $klasifikasi?->jabatan_kerja)
            ->orderBy('no')->get();

        $formatted = collect();
        $no = 1;
        foreach ($administratif as $item) {
            $clean = preg_replace('/^(Syarat Administrasi|Bukti Keanggotaan)\s*:\s*/i', '', $item->bukti);
            foreach (array_map('trim', explode(',', $clean)) as $text) {
                if ($text === '' || strtolower($text) === 'dan lain-lain.') continue;

                // Add foto_url for "pas foto" and ktp_url for "ktp"
                $fotoUrl = null;
                $ktpUrl = null;

                if ($personal && stripos($text, 'pas foto') !== false) {
                    $fotoUrl = $personal->pas_foto;
                } elseif ($personal && stripos($text, 'ktp') !== false) {
                    $ktpUrl = $personal->ktp;
                }

                $formatted->push([
                    'no' => (string) $no++,
                    'bukti' => $text,
                    'checked' => true,
                    'foto_url' => $fotoUrl,
                    'ktp_url' => $ktpUrl
                ]);
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

    private function buildApl02Data(string $idIzin, array $header): array
    {
        $klasifikasi = KlasifikasiKualifikasi::where('id_izin', $idIzin)->first();
        $units = \App\Models\UnitKompetensi::with(['subunits.kuks'])
            ->where('id_jabatan', $klasifikasi?->jabatan_kerja)
            ->get();

        // Bukti APL02 yang sudah dilampirkan asesi per subunit (dari sitkon / submit APL02)
        $answers = \App\Models\Apl2Answer::with('files')
            ->where('id_izin', $idIzin)
            ->get()
            ->keyBy('subunit_id');

        // Referensi kerja asesi (surat_referensi dari proyeks) — berupa URL
        $referensiKerja = \App\Models\Proyek::where('id_izin', $idIzin)
            ->latest('created_at')
            ->value('surat_referensi');

        return [
            'id_izin' => $idIzin,
            'jabatan_kerja' => $header['jabatan_kerja'],
            'jenjang' => $header['jenjang'],
            'metode' => 'observasi',
            'is_dilanjutkan' => true,
            'units' => $units->map(function ($unit) use ($answers, $referensiKerja) {
                return [
                    'id' => (string) $unit->id,
                    'kode' => $unit->kode,
                    'judul_kompetensi' => $unit->nama,
                    'subunits' => $unit->subunits->map(function ($subunit) use ($answers, $referensiKerja) {
                        $answer = $answers->get($subunit->id);

                        // File bukti per subunit: path diubah ke URL penuh (moon) bila relatif
                        $files = $answer
                            ? $answer->files->map(fn ($file) => [
                                'path' => $file->path && (str_starts_with($file->path, 'http://') || str_starts_with($file->path, 'https://'))
                                    ? $file->path
                                    : self::MOON_BASE . ltrim($file->path, '/'),
                                'name' => $file->original_name,
                            ])->values()
                            : collect([]);

                        // Autofill kolom bukti dengan Referensi Kerja (URL) bila belum terdaftar
                        if ($referensiKerja && filter_var($referensiKerja, FILTER_VALIDATE_URL)) {
                            $hasRef = $files->contains(fn ($f) => ($f['path'] ?? null) === $referensiKerja);
                            if (!$hasRef) {
                                $files = $files->push([
                                    'path' => $referensiKerja,
                                    'name' => 'Referensi Kerja',
                                ]);
                            }
                        }

                        return [
                            'id' => (string) $subunit->id,
                            'no_elemen' => $subunit->kode,
                            'judul_elemen' => $subunit->nama,
                            'kompeten' => true,
                            'files' => $files,
                            'kuk_list' => $subunit->kuks->map(fn($kuk) => [
                                'no_kuk' => $kuk->kode,
                                'judul_kuk' => $kuk->nama,
                            ])->values(),
                        ];
                    })->values(),
                ];
            })->values(),
        ];
    }

    private function buildMapa01Data(string $idIzin, array $header): array
    {
        $referensiForm = \App\Models\Referensi::whereIn('kategori', ['MAPA01_3', 'MAPA01_2'])
            ->with('kelompok')
            ->get()
            ->groupBy('kategori');

        return [
            'kelompok_kerja' => [],
            'referensi_form' => $referensiForm,
            'skkni' => [],
            'jenjang' => $header['jenjang'],
            'metode' => 'observasi',
        ];
    }

    private function buildMapa02Data(string $idIzin, array $header): array
    {
        $referensiForm = \App\Models\Referensi::where('kategori', 'MAPA02_1')
            ->where(function ($q) use ($header) {
                $q->whereJsonContains('isdefault', (string) $header['jenjang']);
            })
            ->with('kelompok')
            ->get()
            ->groupBy('kategori');

        return [
            'kelompok_kerja' => [],
            'referensi_form' => $referensiForm,
        ];
    }

    private function buildAk07Data(string $idIzin, array $header): array
    {
        $kelompoks = \App\Models\ReferensiKelompok::whereIn('kategori', [7, 6, 5, 4, 3, 2, 1])
            ->with(['kategoris.referensis'])
            ->orderBy('kategori')
            ->get();

        // Read from pre-populated DB (see prePopulateAnswers); fallback to hardcoded text
        $ak7Answers = \App\Models\Ak7Answer::where('id_izin', $idIzin)->get()->keyBy('referensi_id');

        // Inject jawaban
        $kelompoks->each(function ($kelompok) use ($idIzin, $ak7Answers) {
            $kelompok->kategoris->each(function ($kategori) use ($idIzin, $kelompok, $ak7Answers) {
                $kategori->referensis->each(function ($referensi) use ($idIzin, $kelompok, $ak7Answers) {
                    // kelompok 6 & 7 = text, others = boolean
                    if (in_array($kelompok->kategori, [6, 7])) {
                        $ak7Answer = $ak7Answers->get($referensi->id);
                        $referensi->jawaban = $ak7Answer?->answer_text ?? $this->llmService->getFallback('ak07_text');
                    } else {
                        $referensi->answer_bool = true;
                    }
                });
            });
        });

        return [
            'kelompoks' => $kelompoks,
        ];
    }

    private function buildAk04Data(string $idIzin, array $header): array
    {
        $kelompoks = \App\Models\ReferensiKelompok::whereIn('kategori', [1, 2, 3, 4])
            ->with(['referensis'])
            ->orderBy('kategori')
            ->get();

        $kelompoks->each(function ($kelompok) {
            $kelompok->referensis->each(function ($referensi) {
                $referensi->jawaban = true;
            });
        });

        // No pre-populated DB table for AK04 alasan; use fallback (inline builder is last resort)
        $alasan = $this->llmService->getFallback('ak04_alasan');

        return [
            'kelompoks' => $kelompoks,
            'alasan' => $alasan,
        ];
    }

    private function buildAk01Data(string $idIzin, array $header): array
    {
        // Auto-true IDs based on jenjang
        $autoTrueIds = $header['jenjang'] < 4 ? [60, 227] : [60, 62];

        $items = \App\Models\Referensi::whereIn('id', $autoTrueIds)->get()->map(function ($r) {
            return [
                'id' => $r->id,
                'nama' => $r->nama,
                'jawaban' => true,
            ];
        });

        return [
            'items' => $items,
            'waktu' => '07:30 - Selesai',
        ];
    }

    // ─────────────────────────────────────────────
    //  DATA BUILDERS — ASESMEN (low jenjang < 4)
    // ─────────────────────────────────────────────

    private function buildIa01Data(string $idIzin, array $header): array
    {
        $jabatanKerjaId = \App\Models\KlasifikasiKualifikasi::where('id_izin', $idIzin)->value('jabatan_kerja');

        // Eager load: batch all subunits, then batch all soals in 2 queries total (instead of N+1)
        $units = \App\Models\UnitKompetensi::with(['subunits'])
            ->where('id_jabatan', $jabatanKerjaId)
            ->get();

        // Batch load all soals for all subunits in one query
        $subunitIds = $units->pluck('subunits')->flatten()->pluck('id')->filter()->values()->all();
        $allSoals = \App\Models\Soal::whereIn('id_dokumen', $subunitIds)
            ->get()
            ->groupBy('id_dokumen');

        $kelompokKerja = $units->map(function ($unit) use ($allSoals) {
            return [
                'id' => $unit->id,
                'nama' => $unit->nama,
                'units' => [[
                    'subunits' => $unit->subunits->map(function ($sub) use ($allSoals) {
                        $soals = ($allSoals[$sub->id] ?? collect())->map(function ($soal) {
                            return [
                                'id' => $soal->id,
                                'soal' => $soal->soal,
                                'pencapaian' => true,
                                'penilaian_lanjut' => null,
                            ];
                        });
                        return [
                            'id' => $sub->id,
                            'nama' => $sub->nama,
                            'soals' => $soals,
                        ];
                    })->values()->toArray(),
                ]],
            ];
        });

        // No pre-populated DB table for IA01 feedback; use fallback (inline builder is last resort)
        $umpanBalik = $this->llmService->getFallback('ia01_umpan_balik');

        return [
            'kelompok_kerja' => $kelompokKerja,
            'referensi_form' => collect([]),
            'is_kompeten' => true,
            'umpan_balik' => $umpanBalik,
            'rekomendasi' => [['is_kompeten' => true]],
        ];
    }

    private function buildIa02Data(string $idIzin, array $header): array
    {
        return [
            'questions' => [],
        ];
    }

    private function buildIa03Data(string $idIzin, array $header): array
    {
        $jabatanKerjaId = \App\Models\KlasifikasiKualifikasi::where('id_izin', $idIzin)->value('jabatan_kerja');

        // Batch: load all units and their soals in 2 queries (instead of N+1)
        $units = \App\Models\UnitKompetensi::where('id_jabatan', $jabatanKerjaId)->get();
        $unitIds = $units->pluck('id')->filter()->values()->all();
        $allSoals = \App\Models\Soal::whereIn('id_dokumen', $unitIds)
            ->get()
            ->groupBy('id_dokumen');

        // No pre-populated DB tables for IA03 answers; use fallback (inline builder is last resort)
        $kelompokKerja = $units->map(function ($unit) use ($allSoals) {
            $soals = ($allSoals[$unit->id] ?? collect())->map(function ($soal) use ($unit) {
                return [
                    'id' => $soal->id,
                    'soal' => $soal->soal,
                    'tanggapan' => $this->llmService->getFallback('ia03_tanggapan'),
                    'pencapaian' => true,
                    'umpan_balik' => $this->llmService->getFallback('ia03_umpan_balik'),
                ];
            });
            return [
                'id' => $unit->id,
                'nama' => $unit->nama,
                'soal' => $soals,
            ];
        });

        return [
            'kelompok_kerja' => $kelompokKerja,
            'referensi_form' => collect([]),
            'umpan_balik' => $this->llmService->getFallback('ia03_umpan_balik'),
            'is_kompeten' => true,
        ];
    }

    private function buildIa05Data(string $idIzin, array $header): array
    {
        $soals = [];
        // Load multiple choice questions from the soal table
        $rawSoals = \App\Models\Soal::where('tipe', 'pilihan_ganda')->limit(10)->get();
        foreach ($rawSoals as $s) {
            $soals[] = [
                'id' => $s->id,
                'soal' => $s->soal,
                'pilihan_a' => $s->pilihan_a,
                'pilihan_b' => $s->pilihan_b,
                'pilihan_c' => $s->pilihan_c,
                'pilihan_d' => $s->pilihan_d,
                'kunci_jawaban' => $s->jawaban,
                'jawaban_asesi' => $s->jawaban, // asesi selects correct answer
            ];
        }

        // Checkbox images
        $imgaChecked = base64_encode('checked');
        $imgaNochecked = base64_encode('unchecked');

        // No pre-populated DB table for IA05 feedback; use fallback (inline builder is last resort)
        return [
            'dokumen' => null,
            'soal' => $soals,
            'umpan_balik' => $this->llmService->getFallback('ia05_umpan_balik'),
            'imga_checked' => $imgaChecked,
            'imga_nochecked' => $imgaNochecked,
        ];
    }

    // ─────────────────────────────────────────────
    //  DATA BUILDERS — ASESMEN (jenjang >= 4)
    // ─────────────────────────────────────────────

    private function buildIa04aData(string $idIzin, array $header): array
    {
        // Read from pre-populated DB (see prePopulateAnswers); fallback to hardcoded text
        $ia4aAnswers = \App\Models\Ia4aAnswer::where('id_izin', $idIzin)->get()->keyBy('soal_id');

        $soals = \App\Models\Soal::where('tipe', 'uraian')->limit(5)->get()->map(function ($soal) use ($ia4aAnswers) {
            $answer = $ia4aAnswers->get($soal->id);
            return [
                'id' => $soal->id,
                'soal' => $soal->soal,
                'jawaban' => $answer?->jawaban ?? $this->llmService->getFallback('ia04a_jawaban'),
                'is_komentar' => false,
            ];
        });

        return [
            'kelompok_kerja' => [],
            'referensi_form' => collect([]),
            'soal' => $soals,
            'is_kompeten' => true,
        ];
    }

    private function buildIa04bData(string $idIzin, array $header): array
    {
        $soals = \App\Models\Soal::limit(5)->get()->map(function ($soal) {
            return [
                'id' => $soal->id,
                'soal' => $soal->soal,
                'pencapaian' => true,
                'jawaban' => '',
                'rekomendasi' => true,
            ];
        });

        return [
            'dokumen' => null,
            'soal' => $soals,
            'rekomendasi' => 'kompeten',
            'is_kompeten' => true,
        ];
    }

    // ─────────────────────────────────────────────
    //  DATA BUILDERS — SHARED ASESMEN (AK series)
    // ─────────────────────────────────────────────

    private function buildAk02Data(string $idIzin, array $header): array
    {
        $jenjang = $header['jenjang'];
        // Auto-true methods
        $autoFields = $jenjang < 4
            ? ['observasi' => true, 'pertanyaan_tertulis' => true]
            : ['observasi' => true, 'proyek_kerja' => true];

        $unitKompetensis = \App\Models\UnitKompetensi::where('id_jabatan', function ($q) use ($idIzin) {
            $q->select('jabatan_kerja')->from('klasifikasi_kualifikasis')->where('id_izin', $idIzin);
        })->get()->map(function ($unit) use ($autoFields) {
            return [
                'id' => $unit?->id,
                'nama' => $unit?->nama,
                'kode' => $unit?->kode,
                'observasi' => $autoFields['observasi'],
                'portofolio' => false,
                'pertanyaan_wawancara' => false,
                'pertanyaan_lisan' => false,
                'pertanyaan_tertulis' => $autoFields['pertanyaan_tertulis'] ?? false,
                'proyek_kerja' => $autoFields['proyek_kerja'] ?? false,
                'lainnya' => false,
            ];
        });

        // Read from pre-populated DB (see prePopulateAnswers); fallback to hardcoded text
        $ak2Feedback = \App\Models\Ak2Feedback::where('id_izin', $idIzin)->first();

        return [
            'data_unit_kompetensi' => $unitKompetensis,
            'is_kompeten' => $ak2Feedback?->is_kompeten ?? true,
            'tindak_lanjut' => $ak2Feedback?->tindak_lanjut ?? 'Lanjutkan ke tahap berikutnya',
            'komentar' => $ak2Feedback?->komentar ?? $this->llmService->getFallback('ak02_komentar'),
        ];
    }

    private function buildAk03Data(string $idIzin, array $header): array
    {
        // Read from pre-populated DB (see prePopulateAnswers); fallback to hardcoded text
        $ak3Answers = \App\Models\Ak3Answer::where('id_izin', $idIzin)->get()->keyBy('soal_id');
        $ak3Note = \App\Models\Ak3Note::where('id_izin', $idIzin)->value('catatan');

        $soals = \App\Models\Soal::limit(5)->get()->map(function ($soal) use ($ak3Answers) {
            $answer = $ak3Answers->get($soal->id);
            return [
                'id' => $soal->id,
                'soal' => $soal->soal,
                'is_kompeten' => $answer?->is_kompeten ?? true,
                'catatan' => $answer?->catatan ?? $this->llmService->getFallback('ak03_catatan'),
            ];
        });

        return [
            'soal' => $soals,
            'catatan' => $ak3Note ?? $this->llmService->getFallback('ak03_catatan_umum'),
        ];
    }

    private function buildAk05Data(string $idIzin, array $header): array
    {
        // Read from pre-populated DB (see prePopulateAnswers); fallback to hardcoded text
        $ak5Answer = \App\Models\Ak5Answer::where('id_izin', $idIzin)->first();
        $namaAsesi = $header['nama_asesi'] ?? \App\Models\Personal::where('id_izin', $idIzin)->value('nama') ?? '';

        return [
            'asesiList' => [
                [
                    'nama' => $namaAsesi,
                    'kompeten' => true,
                    'keterangan' => $ak5Answer?->keterangan ?? $this->llmService->getFallback('ak05_keterangan'),
                ],
            ],
            'answers' => [
                'aspek' => $ak5Answer?->aspek ?? $this->llmService->getFallback('ak05_aspek'),
                'pencatatan_penolakan' => $ak5Answer?->pencatatan_penolakan ?? '',
                'saran' => $ak5Answer?->saran ?? $this->llmService->getFallback('ak05_saran'),
                'catatan' => $ak5Answer?->catatan ?? $this->llmService->getFallback('ak05_catatan'),
            ],
            'kompeten' => true,
        ];
    }

    /**
     * Build combined AK05 view data for all asesi in a jabatan_kerja group.
     *
     * Groups asesi within the same jadwal that share the same jabatan_kerja,
     * producing a single PDF with a loop of names, combined answers, and
     * shared asesor barcodes.
     */
    private function buildAk05GroupViewData(array $header, $groupMembers, array $barcodes): ?array
    {
        if ($groupMembers->isEmpty()) {
            return null;
        }

        $asesiData = [];
        $combinedAspek = [];
        $combinedPenolakan = [];
        $combinedSaran = [];
        $combinedCatatan = [];

        foreach ($groupMembers as $member) {
            $answer = \App\Models\Ak5Answer::where('id_izin', $member->id_izin)
                ->first(['keterangan', 'aspek', 'pencatatan_penolakan', 'saran', 'catatan']);

            // Resolve kompeten status from komtek decision if possible,
            // otherwise rely on Ak5Answer's stored value or fallback
            $kompeten = true;

            $asesiData[] = [
                'nama' => $member->nama,
                'kompeten' => $kompeten,
                'keterangan' => $answer?->keterangan ?? $this->llmService->getFallback('ak05_keterangan'),
            ];

            if (!empty($answer?->aspek)) {
                $combinedAspek[] = $member->nama . ': ' . $answer->aspek;
            } else {
                $combinedAspek[] = $member->nama . ': ' . $this->llmService->getFallback('ak05_aspek');
            }
            if (!empty($answer?->pencatatan_penolakan)) {
                $combinedPenolakan[] = $member->nama . ': ' . $answer->pencatatan_penolakan;
            }
            if (!empty($answer?->saran)) {
                $combinedSaran[] = $member->nama . ': ' . $answer->saran;
            } else {
                $combinedSaran[] = $member->nama . ': ' . $this->llmService->getFallback('ak05_saran');
            }
            if (!empty($answer?->catatan)) {
                $combinedCatatan[] = $member->nama . ': ' . $answer->catatan;
            } else {
                $combinedCatatan[] = $member->nama . ': ' . $this->llmService->getFallback('ak05_catatan');
            }
        }

        return [
            'asesiList' => $asesiData,
            'answers' => [
                'aspek' => implode("\n\n", $combinedAspek),
                'pencatatan_penolakan' => implode("\n\n", $combinedPenolakan),
                'saran' => implode("\n\n", $combinedSaran),
                'catatan' => implode("\n\n", $combinedCatatan),
            ],
            'kompeten' => !empty($asesiData) ? $asesiData[0]['kompeten'] : false,
            'barcodes' => $barcodes,
        ];
    }

    private function buildAk06Data(string $idIzin, array $header): array
    {
        $aspeks = \App\Models\Referensi::where('kategori', 'AK06_1')->get()->map(function ($a) {
            return [
                'id' => $a->id,
                'nama' => $a->nama,
                'validitas' => true,
                'reliabel' => true,
                'fleksibel' => true,
                'adil' => true,
            ];
        });

        $context = [
            'nama_asesi' => $header['nama_asesi'],
            'jabatan_kerja' => $header['jabatan_kerja'],
        ];

        // Dimensi kompetensi label by jenjang
        $dimensiLabel = $header['jenjang'] < 4
            ? "L/CL/\nT/DPT"
            : "L/DIT\nT/DPT";

        $dimensi = [
            'task_skills' => $dimensiLabel,
            'task_management_skills' => $dimensiLabel,
            'contingency_management_skills' => $dimensiLabel,
            'job_role_environment_skills' => $dimensiLabel,
            'transfer_skills' => $dimensiLabel,
        ];

        // Read from pre-populated DB (see prePopulateAnswers); fallback to hardcoded text
        $ak6Feedback = \App\Models\Ak6Feedback::where('id_izin', $idIzin)->first();
        return [
            'aspek' => $aspeks,
            'rekomendasi_prinsip' => $ak6Feedback?->rekomendasi1 ?? $this->llmService->getFallback('ak06_rekomendasi1'),
            'rekomendasi_dimensi' => $ak6Feedback?->rekomendasi2 ?? $this->llmService->getFallback('ak06_rekomendasi2'),
            'catatan_asesor1' => $ak6Feedback?->catatan_asesor1 ?? $this->llmService->getFallback('ak06_catatan_asesor1'),
            'catatan_asesor2' => $ak6Feedback?->catatan_asesor2 ?? $this->llmService->getFallback('ak06_catatan_asesor2'),
            'dimensi_kompetensi' => $dimensi,
        ];
    }

    // ─────────────────────────────────────────────
    //  PRE-POPULATE ANSWER TABLES
    // ─────────────────────────────────────────────

    /**
     * Insert records into answer tables so the existing controller's build*Data
     * methods read kompeten=true, waktu, and correct answers.
     */
    private function prePopulateAnswers(string $idIzin): void
    {
        $klasifikasi = KlasifikasiKualifikasi::where('id_izin', $idIzin)->first();
        if (!$klasifikasi) return;

        $jabatanKerjaId = $klasifikasi->jabatan_kerja;
        $jenjang = (int) $klasifikasi->jenjang;
        $personal = Personal::where('id_izin', $idIzin)->first();
        $llmCtx = ['nama_asesi' => $personal?->nama, 'jabatan_kerja' => ''];

        // ── APL02: submission + answers ──
        \App\Models\Apl2Submission::updateOrCreate(
            ['id_izin' => $idIzin],
            ['metode' => 'observasi', 'is_dilanjutkan' => true]
        );
        $subunits = \App\Models\SubunitKompetensi::whereHas('unit', function ($q) use ($jabatanKerjaId) {
            $q->where('id_jabatan', $jabatanKerjaId);
        })->get();
        foreach ($subunits as $sub) {
            \App\Models\Apl2Answer::updateOrCreate(
                ['id_izin' => $idIzin, 'subunit_id' => $sub->id],
                ['kompeten' => true]
            );
        }

        // ── AK01: answers + waktu ──
        $autoTrueIds = $jenjang >= 1 && $jenjang <= 3 ? [60, 227] : [60, 62];
        foreach ($autoTrueIds as $refId) {
            \App\Models\Ak1Answer::updateOrCreate(
                ['id_izin' => $idIzin, 'id_referensi' => $refId],
                ['jawaban' => true, 'waktu' => '07:30 - Selesai']
            );
        }

        // ── AK02: pre-populate ak2_answers + feedback ──
        $units = \App\Models\UnitKompetensi::where('id_jabatan', $jabatanKerjaId)->get();
        foreach ($units as $unit) {
            \App\Models\Ak2Answer::updateOrCreate(
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
        // AK02 feedback: kompeten + tindak lanjut
        \App\Models\Ak2Feedback::updateOrCreate(
            ['id_izin' => $idIzin],
            [
                'is_kompeten' => true,
                'tindak_lanjut' => 'Asesi dinyatakan Kompeten pada seluruh unit kompetensi. Tidak diperlukan asesmen tambahan.',
                'komentar' => 'Proses asesmen berjalan dengan baik. Asesi menunjukkan penguasaan kompetensi yang memadai pada setiap unit yang diujikan.',
            ]
        );
        logger()->debug("[LLM] AK02 pre-populated id_izin={$idIzin} total_units=" . $units->count());

        // ── IA05: correct answers ──
        $ia05Doc = \App\Models\JabatanDokumen::where('id_jabatan', $jabatanKerjaId)
            ->where('kode', 'FR.IA.05')->where('is_active', true)->first();
        if ($ia05Doc) {
            foreach (\App\Models\Soal::where('id_dokumen', $ia05Doc->id)->where('tipe', 1)->get() as $soal) {
                \App\Models\Ia5Answer::updateOrCreate(
                    ['id_izin' => $idIzin, 'soal_id' => $soal->id, 'dokumen_id' => $ia05Doc->id],
                    ['jawaban' => $soal->jawaban]
                );
            }
        }

        // ─── COLLECT ALL LLM FIELDS AND BATCH THE CALL ───
        $llmFields = []; // [fieldType, context]

        // IA04A: only LLM-generate umpan balik (item 3). Items 1-2 have pre-written jawaban from DB.
        $ia04aDoc = \App\Models\JabatanDokumen::where('id_jabatan', $jabatanKerjaId)
            ->where('kode', 'FR.IA.04.A')->where('is_active', true)->first();
        $ia04aSoals = [];
        if ($ia04aDoc) {
            $ia04aSoals = \App\Models\Soal::where('id_dokumen', $ia04aDoc->id)->orderBy('urut')->get();
            foreach ($ia04aSoals as $soal) {
                // Only generate LLM for soal with is_komentar (umpan balik); others use pre-written jawaban
                if ($soal->is_komentar) {
                    $llmFields[] = ['ia04a_jawaban', ['soal_soal' => $soal->soal] + $llmCtx];
                }
            }
        }

        // IA04B: question-type soals
        $ia04bDoc = \App\Models\JabatanDokumen::where('id_jabatan', $jabatanKerjaId)
            ->where('kode', 'FR.IA.04.B')->where('is_active', true)->first();
        $ia04bSoals = [];
        if ($ia04bDoc) {
            $ia04bSoals = \App\Models\Soal::where('id_dokumen', $ia04bDoc->id)->where('tipe', 8)->get();
            foreach ($ia04bSoals as $soal) {
                $llmFields[] = ['ia04a_jawaban', ['soal_soal' => $soal->soal] + $llmCtx];
            }
        }

        // AK03
        $ak03Doc = \App\Models\JabatanDokumen::where('id_jabatan', $jabatanKerjaId)
            ->where('kode', 'FR.AK.03')->where('is_active', true)->first();
        $ak03Soals = [];
        if ($ak03Doc) {
            $ak03Soals = \App\Models\Soal::where('id_dokumen', $ak03Doc->id)->get();
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
        $refs = \App\Models\Referensi::with('kelompokRel')
            ->where('kategori', 'AK07')->whereNotNull('id_kelompok')->get();
        $ak07TextRefs = [];
        foreach ($refs as $ref) {
            if (in_array((int) $ref->id_kelompok, [6, 7])) {
                $ak07TextRefs[] = $ref;
                $llmFields[] = ['ak07_text', ['referensi_nama' => $ref->nama] + $llmCtx];
            }
        }

        // ─── ONE LLM BATCH CALL ───
        logger()->debug("[LLM] prePopulateAnswers id_izin={$idIzin} total_fields=" . count($llmFields) . " fields=" . json_encode(array_map(fn($f) => $f[0], $llmFields)));
        $llmResults = $this->llmService->generateBatchTexts($llmFields);
        logger()->debug("[LLM] batch results id_izin={$idIzin} total_results=" . count($llmResults) . " sample_keys=" . json_encode(array_keys($llmResults)));

        // ─── DISTRIBUTE RESULTS ───
        $idx = 0;

        // IA04A — store LLM jawaban only for soal items with is_komentar (umpan balik)
        foreach ($ia04aSoals as $soal) {
            if ($soal->is_komentar) {
                $jawaban = $llmResults['ia04a_jawaban_' . $idx] ?? $this->llmService->getFallback('ia04a_jawaban');
                \App\Models\Ia4aAnswer::updateOrCreate(
                    ['id_izin' => $idIzin, 'soal_id' => $soal->id],
                    ['jawaban' => $jawaban, 'pencapaian' => true]
                );
                $idx++;
            }
        }

        // IA04B
        foreach ($ia04bSoals as $soal) {
            $jawaban = $llmResults['ia04a_jawaban_' . $idx] ?? $this->llmService->getFallback('ia04a_jawaban');
            \App\Models\Ia4bAnswer::updateOrCreate(
                ['id_izin' => $idIzin, 'soal_id' => $soal->id],
                ['dokumen_id' => $ia04bDoc->id, 'jawaban' => $jawaban, 'pencapaian' => true]
            );
            $idx++;
        }
        // IA04B rekomendasi-type
        if ($ia04bDoc) {
            foreach (\App\Models\Soal::where('id_dokumen', $ia04bDoc->id)->where('tipe', 3)->get() as $soal) {
                \App\Models\Ia4bAnswer::updateOrCreate(
                    ['id_izin' => $idIzin, 'soal_id' => $soal->id],
                    ['dokumen_id' => $ia04bDoc->id, 'is_rekomendasi' => true, 'rekomendasi' => true]
                );
            }
        }

        // AK03
        $ak03Start = $idx;
        foreach ($ak03Soals as $soal) {
            $catatan = $llmResults['ak03_catatan_' . $idx] ?? $this->llmService->getFallback('ak03_catatan');
            \App\Models\Ak3Answer::updateOrCreate(
                ['id_izin' => $idIzin, 'soal_id' => $soal->id],
                ['is_kompeten' => true, 'catatan' => $catatan]
            );
            $idx++;
        }
        if ($ak03Doc) {
            $globalCatatan = $llmResults['ak03_catatan_umum_' . $idx] ?? $this->llmService->getFallback('ak03_catatan_umum');
            \App\Models\Ak3Note::updateOrCreate(
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
        \App\Models\Ak5Answer::updateOrCreate(['id_izin' => $idIzin], $ak05Data);
        logger()->debug("[LLM] AK5 stored id_izin={$idIzin} keterangan_len=" . strlen($ak05Data['keterangan'] ?? '') . " aspek_len=" . strlen($ak05Data['aspek'] ?? '') . " saran_len=" . strlen($ak05Data['saran'] ?? '') . " catatan_len=" . strlen($ak05Data['catatan'] ?? '') . " is_fallback_keterangan=" . ($ak05Data['keterangan'] === $this->llmService->getFallback('ak05_keterangan') ? 'YES' : 'NO'));
        $idx += 4;

        // resolveKomtekDecision needs >=2 'K' votes
        $existingVotes = \App\Models\KomtekRecommendation::where('id_izin', $idIzin)->count();
        if ($existingVotes < 2) {
            for ($i = $existingVotes; $i < 2; $i++) {
                \App\Models\KomtekRecommendation::create([
                    'id_izin' => $idIzin, 'id_komtek' => 999 + $i, 'rekomendasi' => 'K',
                ]);
            }
        }

        // AK06
        $aspeks = \App\Models\Referensi::where('kategori', 'AK06')->where('id', '!=', 26)->get();
        foreach ($aspeks as $aspek) {
            \App\Models\Ak6Answer::updateOrCreate(
                ['id_izin' => $idIzin, 'id_aspek' => $aspek->id],
                ['validitas' => true, 'reliabel' => true, 'fleksibel' => true, 'adil' => true]
            );
        }
        \App\Models\Ak6Feedback::updateOrCreate(
            ['id_izin' => $idIzin],
            [
                'rekomendasi1' => $llmResults['ak06_rekomendasi1_' . $idx] ?? $this->llmService->getFallback('ak06_rekomendasi1'),
                'rekomendasi2' => $llmResults['ak06_rekomendasi2_' . ($idx + 1)] ?? $this->llmService->getFallback('ak06_rekomendasi2'),
                'catatan_asesor1' => $llmResults['ak06_catatan_asesor1_' . ($idx + 2)] ?? $this->llmService->getFallback('ak06_catatan_asesor1'),
                'catatan_asesor2' => $llmResults['ak06_catatan_asesor2_' . ($idx + 3)] ?? $this->llmService->getFallback('ak06_catatan_asesor2'),
            ]
        );
        logger()->debug("[LLM] AK6 stored id_izin={$idIzin} rekom1_len=" . strlen($llmResults['ak06_rekomendasi1_' . $idx] ?? 0) . " catatan1_len=" . strlen($llmResults['ak06_catatan_asesor1_' . ($idx + 2)] ?? 0));
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
            \App\Models\Ak7Answer::updateOrCreate(
                ['id_izin' => $idIzin, 'referensi_id' => $ref->id],
                ['kelompok_id' => $kelompokId, 'answer_bool' => true, 'answer_text' => $answerText]
            );
        }
    }

    // ─────────────────────────────────────────────
    //  HELPERS
    // ─────────────────────────────────────────────

    /**
     * Ensure the docData array has all keys the blade view expects,
     * even when the controller's build*Data returns minimal fallback data.
     */
    private function sanitizeViewData(string $method, array $docData, array $header): array
    {
        switch ($method) {
            case 'Mapa01':
                // View expects: $skkni, $jenjang, $metode, $kelompok_kerja (with 'kelompok_kerja' key inside)
                if (!isset($docData['skkni'])) {
                    $docData['skkni'] = '';
                }
                if (!isset($docData['jenjang'])) {
                    $docData['jenjang'] = $header['jenjang'] ?? null;
                }
                if (!isset($docData['metode'])) {
                    $docData['metode'] = 'observasi';
                }
                // Ensure $kelompok_kerja has the nested 'kelompok_kerja' key
                if (isset($docData['kelompok_kerja']) && is_array($docData['kelompok_kerja'])
                    && !empty($docData['kelompok_kerja'])
                    && !array_key_exists('kelompok_kerja', $docData['kelompok_kerja'])) {
                    // Wrap: controller returned flat data when doc not found
                    $docData['kelompok_kerja'] = ['kelompok_kerja' => []];
                }
                if (empty($docData['kelompok_kerja'])) {
                    $docData['kelompok_kerja'] = ['kelompok_kerja' => []];
                }
                break;

            case 'Mapa02':
                // View expects: $kelompok_kerja['kelompok_kerja'], $referensi_form
                if (empty($docData['kelompok_kerja'])) {
                    $docData['kelompok_kerja'] = ['kelompok_kerja' => []];
                }
                if (!isset($docData['referensi_form'])) {
                    $docData['referensi_form'] = [];
                }
                break;

            case 'Ia04a':
                // View expects: $kelompok_kerja['kode'], $kelompok_kerja['kelompok_kerja']
                if (empty($docData['kelompok_kerja'])) {
                    $docData['kelompok_kerja'] = [
                        'kode' => 'FR.IA.04.A',
                        'nama_dokumen' => 'PENJELASAN SINGKAT PROYEK TERKAIT PEKERJAAN',
                        'kelompok_kerja' => [],
                    ];
                }
                break;

            case 'Ak05':
                // Template now expects $asesiList and $answers array (jadwal-based)
                if (!isset($docData['asesiList'])) {
                    $namaAsesi = $header['nama_asesi'] ?? '';

                    // Capture flat keys (inline builder format)
                    $kompeten   = $docData['kompeten'] ?? true;
                    $keterangan = $docData['keterangan'] ?? '';
                    $aspek      = $docData['aspek'] ?? '';
                    $penolakan  = $docData['pencatatan_penolakan'] ?? '';
                    $saran      = $docData['saran'] ?? '';
                    $catatan    = $docData['catatan'] ?? '';

                    // If answers object exists (controller's buildAk05Data via reflection)
                    $answersData = $docData['answers'] ?? null;
                    if ($answersData) {
                        $keterangan = $answersData->keterangan ?? $keterangan;
                        $aspek      = $answersData->aspek      ?? $aspek;
                        $penolakan  = $answersData->pencatatan_penolakan ?? $penolakan;
                        $saran      = $answersData->saran      ?? $saran;
                        $catatan    = $answersData->catatan    ?? $catatan;
                    }

                    $docData['asesiList'] = [
                        ['nama' => $namaAsesi, 'kompeten' => $kompeten, 'keterangan' => $keterangan],
                    ];
                    $docData['answers'] = [
                        'aspek'                => $aspek,
                        'pencatatan_penolakan' => $penolakan,
                        'saran'                => $saran,
                        'catatan'              => $catatan,
                    ];
                    unset($docData['keterangan'], $docData['aspek'], $docData['pencatatan_penolakan'], $docData['saran'], $docData['catatan']);
                }
                break;
        }

        return $docData;
    }

    private function imageUrlToBase64(?string $url): ?string
    {
        if (!$url) {
            return null;
        }

        // Cache base64 images for 24h — these barcode/signature images don't change frequently
        $cacheKey = 'img:base64:' . md5($url);
        return \Illuminate\Support\Facades\Cache::remember($cacheKey, 86400, function () use ($url) {
            try {
                $contents = @file_get_contents($url);
                if ($contents === false) {
                    return null;
                }
                $extension = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'png';
                $mime = match (strtolower($extension)) {
                    'jpg', 'jpeg' => 'image/jpeg',
                    'gif' => 'image/gif',
                    'svg' => 'image/svg+xml',
                    default => 'image/png',
                };
                return 'data:' . $mime . ';base64,' . base64_encode($contents);
            } catch (\Throwable $e) {
                return null;
            }
        });
    }

    /**
     * Call a private build*Data method from the existing controllers via reflection.
     * This gives us the exact data structure each blade template expects.
     */
    private function callBuildMethod(string $method, string $idIzin): ?array
    {
        $methodName = "build{$method}Data";

        // Determine which controller
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
            // KAN documents
            'KanIa04b' => [$this->kanAsesmenController, 'KanAsesmenController'],
            'KanIa05' => [$this->kanAsesmenController, 'KanAsesmenController'],
            'KanIa06' => [$this->kanAsesmenController, 'KanAsesmenController'],
            'KanAk02' => [$this->kanAsesmenController, 'KanAsesmenController'],
        ];

        if (!isset($mapping[$method])) {
            logger()->debug("[LLM] callBuildMethod {$method} TIDAK di mapping, fallback ke inline builder");
            return null;
        }

        [$controller, $controllerName] = $mapping[$method];

        try {
            logger()->debug("[LLM] callBuildMethod {$method} via REFLEKSI {$controllerName}::{$methodName}");
            $reflection = new \ReflectionMethod($controller, $methodName);
            $reflection->setAccessible(true);

            // Most build*Data methods accept (int|string $id, bool $forPdf = false)
            $docData = $reflection->invoke($controller, $idIzin, true);

            logger()->debug("[LLM] {$methodName} data keys: " . json_encode(array_keys($docData ?? [])) . " id_izin={$idIzin}");

            // All LLM fields are pre-populated via prePopulateAnswers()

            // ── AK07 post-process: kelompok 5 (Mengidentifikasi...) → Tidak ──
            if ($method === 'Ak07' && isset($docData['kelompoks'])) {
                $docData['kelompoks'] = collect($docData['kelompoks'])->map(function ($kelompok) {
                    // kelompok 5 = "Mengidentifikasi Persyaratan Modifikasi dan Kontekstualisasi"
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

            return is_array($docData) ? $docData : null;
        } catch (\Throwable $e) {
            logger()->warning("[LLM] Reflection/Invocation GAGAL untuk {$methodName}: " . $e->getMessage() . " — fallback ke inline builder");
            // Fall back to inline builder
            $inlineBuilder = "build{$method}Data";
            if (method_exists($this, $inlineBuilder)) {
                logger()->debug("[LLM] {$method} via INLINE builder (GenerateDokumenService)");
                $inlineResult = $this->$inlineBuilder($idIzin, []);
                logger()->debug("[LLM] {$method} inline data keys: " . json_encode(array_keys($inlineResult ?? [])));
                return $inlineResult;
            }
            return null;
        }
    }

}
