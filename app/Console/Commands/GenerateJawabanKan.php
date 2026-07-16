<?php

namespace App\Console\Commands;

use App\Models\JabatanDokumen;
use App\Models\JabatanKerja;
use App\Models\KanIa4bAnswer;
use App\Models\KanIa5Answer;
use App\Models\KanIa6Answer;
use App\Models\KanPraktisiJabatan;
use App\Models\KanSoal;
use App\Models\User;
use App\Services\LlmAutoFillService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class GenerateJawabanKan extends Command
{
    protected $signature = 'kan:generate-jawaban
                            {file : Path ke JSON file}';
    protected $description = 'Generate KAN answers (IA04B, IA05, IA06) using LLM — all answers must be pure LLM, no fallback';

    private const PRAKTISI_KEYS = ['A1', 'A2', 'A3', 'A4', 'A5', 'A6', 'A7', 'A8', 'A9', 'A10'];
    private const ROLE_PRAKTISI = 16;
    private const DOKUMEN_KODES = [
        'ia04b' => 'FR.IA.04.B',
        'ia05'  => 'FR.IA.05',
        'ia06'  => 'FR.IA.06',
    ];

    private LlmAutoFillService $llm;
    private array $jabatanKerjaCache = [];
    private array $userCache = [];
    private array $praktisiJabatanCache = [];
    private string $timestamp;

    public function __construct()
    {
        parent::__construct();
        $this->llm = app(LlmAutoFillService::class);
    }

    public function handle(): int
    {
        $filePath = $this->argument('file');
        if (!file_exists($filePath)) {
            $this->error("File tidak ditemukan: {$filePath}");
            return 1;
        }

        $json = json_decode(file_get_contents($filePath), true);
        if (!$json || !isset($json['data'])) {
            $this->error('Format JSON tidak valid. Harus punya key "data".');
            return 1;
        }

        $records = $json['data'];
        $total = count($records);
        $this->info("Total records: {$total}");
        $this->info('Mode: Pure LLM — semua jawaban wajib dari LLM, tanpa fallback.');

        $fixedCount = $this->fixJsonTypos($records);
        if ($fixedCount > 0) {
            $this->warn("Fixed {$fixedCount} value typos (non-0/1 → 0)");
        }

        $this->info('Pre-loading database mappings...');
        $this->loadJabatanKerjaCache($records);
        $this->info('Jabatan kerja mappings loaded.');

        $this->timestamp = now()->toDateTimeString();
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $stats = [
            'processed' => 0, 'skipped_skema' => 0, 'skipped_dokumen' => 0,
            'ia05' => 0, 'ia06' => 0, 'ia04b' => 0, 'errors' => [],
        ];

        foreach ($records as $idx => $record) {
            $bar->advance();
            try {
                $result = $this->processRecord($record);
                $stats['processed']++;
                $stats['ia05']  += $result['ia05'];
                $stats['ia06']  += $result['ia06'];
                $stats['ia04b'] += $result['ia04b'];
                if (!$result['skema_found']) $stats['skipped_skema']++;
                if (!$result['dokumen_found']) $stats['skipped_dokumen']++;
            } catch (\Throwable $e) {
                $msg = "Record #{$idx} ({$record['no_skema']}): " . $e->getMessage();
                $stats['errors'][] = $msg;
                $this->warn("\n  ⚠ {$msg}");
                logger()->error("[GenerateJawabanKan] {$msg}", ['trace' => $e->getTraceAsString()]);
            }
        }

        $bar->finish();
        $this->newLine(2);

        $this->info('═══ HASIL GENERATE ═══');
        $this->info("Records diproses:      {$stats['processed']}");
        $this->info("Skema tidak ditemukan: {$stats['skipped_skema']}");
        $this->info("IA05 answers dibuat:   {$stats['ia05']}");
        $this->info("IA06 answers dibuat:   {$stats['ia06']}");
        $this->info("IA04B answers dibuat:  {$stats['ia04b']}");
        $this->info("Errors:                " . count($stats['errors']));

        if (!empty($stats['errors'])) {
            $this->warn('Detail errors (10 pertama):');
            foreach (array_slice($stats['errors'], 0, 10) as $err) {
                $this->line("  - {$err}");
            }
        }

        return 0;
    }

    // ═══════════════════════════════════════════════════════════════
    //  FIX JSON TYPOS
    // ═══════════════════════════════════════════════════════════════

    private function fixJsonTypos(array &$records): int
    {
        $fixed = 0;
        foreach ($records as &$record) {
            if (!isset($record['scores'])) continue;
            foreach (['Pg', 'Esai', 'Lisan'] as $section) {
                if (!isset($record['scores'][$section]) || !is_array($record['scores'][$section])) continue;
                foreach ($record['scores'][$section] as &$entry) {
                    foreach (self::PRAKTISI_KEYS as $key) {
                        if (!isset($entry[$key])) continue;
                        $val = $entry[$key];
                        if ($val !== 0 && $val !== 1 && $val !== '0' && $val !== '1') {
                            $entry[$key] = 0;
                            $fixed++;
                        }
                        if ($val === '0') $entry[$key] = 0;
                        if ($val === '1') $entry[$key] = 1;
                    }
                }
            }
        }
        return $fixed;
    }

    // ═══════════════════════════════════════════════════════════════
    //  PRE-LOAD CACHES
    // ═══════════════════════════════════════════════════════════════

    private function loadJabatanKerjaCache(array $records): void
    {
        $allSkema = array_unique(array_filter(array_map('trim', array_column($records, 'skema'))));
        $chunks = array_chunk($allSkema, 500);
        foreach ($chunks as $chunk) {
            $lowerNames = array_map('strtolower', $chunk);
            $rows = JabatanKerja::select('id_jabatan_kerja', 'jabatan_kerja')
                ->whereRaw('LOWER(jabatan_kerja) IN (' . implode(',', array_fill(0, count($lowerNames), '?')) . ')', $lowerNames)
                ->orderBy('id_jabatan_kerja', 'desc')
                ->get();
            foreach ($rows as $row) {
                $key = strtolower($row->jabatan_kerja);
                if (!isset($this->jabatanKerjaCache[$key])) {
                    $this->jabatanKerjaCache[$key] = $row->id_jabatan_kerja;
                }
            }
        }
    }

    // ═══════════════════════════════════════════════════════════════
    //  PROCESS SINGLE RECORD (bulk upsert per table)
    // ═══════════════════════════════════════════════════════════════

    private function processRecord(array $record): array
    {
        $stats = ['ia05' => 0, 'ia06' => 0, 'ia04b' => 0, 'skema_found' => true, 'dokumen_found' => true];

        $skemaName = trim($record['skema'] ?? '');
        $noSkema = $record['no_skema'] ?? '';
        $idJabatanKerja = $this->jabatanKerjaCache[strtolower($skemaName)] ?? null;

        if (!$idJabatanKerja) {
            $stats['skema_found'] = false;
            return $stats;
        }

        $dokumens = $this->getDokumens($idJabatanKerja);
        if (empty($dokumens['ia05']) && empty($dokumens['ia06']) && empty($dokumens['ia04b'])) {
            $stats['dokumen_found'] = false;
            return $stats;
        }

        $asesorNames = $record['asesor_names'] ?? [];
        $scores = $record['scores'] ?? [];

        // Resolve praktisi users & kan_praktisi_jabatan
        $praktisiJabatanIds = [];
        foreach (self::PRAKTISI_KEYS as $key) {
            $name = trim($asesorNames[$key] ?? '');
            if (empty($name)) continue;
            $praktisiJabatanIds[$key] = $this->findOrCreatePraktisiJabatan(
                $idJabatanKerja,
                $this->resolvePraktisiUser($name)
            );
        }

        // ── Generate answers (pure LLM) ──

        // IA05
        if (!empty($dokumens['ia05']) && !empty($scores['Pg'])) {
            $stats['ia05'] += $this->generateIa05Bulk($dokumens['ia05'], $scores['Pg'], $praktisiJabatanIds);
        }

        // IA06
        if (!empty($dokumens['ia06']) && !empty($scores['Esai']) && is_array($scores['Esai'])) {
            $stats['ia06'] += $this->generateIa06Bulk($dokumens['ia06'], $scores['Esai'], $praktisiJabatanIds, $asesorNames, $skemaName, $noSkema);
        }

        // IA04B
        if (!empty($dokumens['ia04b']) && !empty($scores['Lisan']) && is_array($scores['Lisan'])) {
            $stats['ia04b'] += $this->generateIa04bBulk($dokumens['ia04b'], $scores['Lisan'], $praktisiJabatanIds, $asesorNames, $skemaName, $noSkema);
        }

        // Note: QR codes are generated separately via kan:generate-qr command

        return $stats;
    }

    // ═══════════════════════════════════════════════════════════════
    //  DOKUMEN & SOAL HELPERS
    // ═══════════════════════════════════════════════════════════════

    private function getDokumens(string $idJabatanKerja): array
    {
        $rows = JabatanDokumen::select('id', 'kode')
            ->where('id_jabatan', $idJabatanKerja)
            ->whereIn('kode', array_values(self::DOKUMEN_KODES))
            ->where('is_active', true)
            ->get()->keyBy('kode');
        $result = ['ia04b' => null, 'ia05' => null, 'ia06' => null];
        foreach (self::DOKUMEN_KODES as $key => $kode) {
            $result[$key] = $rows->get($kode);
        }
        return $result;
    }

    private function getSoals($dokumenId, ?array $tipes = null): array
    {
        $query = KanSoal::where('id_dokumen', $dokumenId);
        if ($tipes) $query->whereIn('tipe', $tipes);
        return $query->orderBy('no', 'asc')->get()->all();
    }

    // ═══════════════════════════════════════════════════════════════
    //  USER & PRAKTISI JABATAN
    // ═══════════════════════════════════════════════════════════════

    private function resolvePraktisiUser(string $name): int
    {
        if (isset($this->userCache[$name])) return $this->userCache[$name];
        $user = User::where('name', $name)->where('role_id', self::ROLE_PRAKTISI)->first();
        if (!$user) {
            $email = $this->generateEmailFromName($name);
            $user = User::create([
                'name' => $name, 'email' => $email, 'phone' => '0',
                'role_id' => self::ROLE_PRAKTISI, 'password' => Hash::make('123qweasd'),
            ]);
        }
        $this->userCache[$name] = $user->id;
        return $user->id;
    }

    private function findOrCreatePraktisiJabatan(string $idJabatanKerja, int $userId): int
    {
        $cacheKey = "{$idJabatanKerja}:{$userId}";
        if (isset($this->praktisiJabatanCache[$cacheKey])) return $this->praktisiJabatanCache[$cacheKey];
        $record = KanPraktisiJabatan::firstOrCreate(
            ['id_jabatan_kerja' => $idJabatanKerja, 'id_user' => $userId]
        );
        $this->praktisiJabatanCache[$cacheKey] = $record->id;
        return $record->id;
    }

    private function generateEmailFromName(string $name): string
    {
        $sanitized = preg_replace('/[^a-z0-9]/', '', strtolower($name));
        $base = $sanitized . '@lspgatensi.id';
        if (!User::where('email', $base)->exists()) return $base;
        $suffix = 1;
        while (User::where('email', $sanitized . '+' . $suffix . '@lspgatensi.id')->exists()) $suffix++;
        return $sanitized . '+' . $suffix . '@lspgatensi.id';
    }

    // ═══════════════════════════════════════════════════════════════
    //  IA05: PILIHAN GANDA (bulk upsert)
    // ═══════════════════════════════════════════════════════════════

    private function generateIa05Bulk($dokumen, array $pgScores, array $praktisiJabatanIds): int
    {
        $soals = $this->getSoals($dokumen->id, [1]);
        $rows = [];

        foreach ($pgScores as $i => $scoreEntry) {
            if ($i >= count($soals)) break;
            $soal = $soals[$i];
            $kunci = strtoupper($soal->jawaban ?? '');
            $wrongOptions = array_values(array_filter(['A', 'B', 'C', 'D'], fn($o) => $o !== $kunci));

            foreach (self::PRAKTISI_KEYS as $key) {
                if (!isset($praktisiJabatanIds[$key])) continue;
                $value = (int) ($scoreEntry[$key] ?? 0);
                $jawaban = $value === 1 ? $kunci : $wrongOptions[array_rand($wrongOptions)];
                $skor = $value === 1 ? 1 : 0;

                $rows[] = [
                    'kan_praktisi_jabatan_id' => $praktisiJabatanIds[$key],
                    'dokumen_id'              => $dokumen->id,
                    'soal_id'                 => $soal->id,
                    'jawaban'                 => $jawaban,
                    'skor'                    => $skor,
                    'updated_at'              => $this->timestamp,
                    'created_at'              => $this->timestamp,
                ];
            }
        }

        if (!empty($rows)) {
            KanIa5Answer::upsert($rows, ['kan_praktisi_jabatan_id', 'dokumen_id', 'soal_id'], ['jawaban', 'skor', 'updated_at']);
        }
        return count($rows);
    }

    // ═══════════════════════════════════════════════════════════════
    //  IA06: ESAI (batch 3 soals = 30 fields → ~18KB prompt → 15-20s)
    // ═══════════════════════════════════════════════════════════════

    private function generateIa06Bulk($dokumen, array $esaiScores, array $praktisiJabatanIds, array $asesorNames, string $skemaName, string $noSkema): int
    {
        $soals = $this->getSoals($dokumen->id, [8]);
        $chunkSize = 3; // 3 soals × 10 praktisi = 30 fields → ~18KB
        $soalChunks = array_chunk($esaiScores, $chunkSize, true);
        $rows = [];

        foreach ($soalChunks as $chunk) {
            $fields = [];
            $fieldKeys = []; // fieldIdx => [soalId, kpjId, value, soalIdx]

            foreach ($chunk as $i => $scoreEntry) {
                if ($i >= count($soals)) break 2;
                $soal = $soals[$i];

                foreach (self::PRAKTISI_KEYS as $key) {
                    if (!isset($praktisiJabatanIds[$key])) continue;
                    $value = (int) ($scoreEntry[$key] ?? 0);
                    $fieldType = $value === 1 ? 'kan_esai_benar' : 'kan_esai_salah';
                    $userName = $asesorNames[$key] ?? $key;

                    $fieldIdx = count($fields);
                    $fields[] = [$fieldType, [
                        'nama_asesi' => $userName, 'jabatan_kerja' => $skemaName,
                        'nomor_skema' => $noSkema, 'asesor' => 'Asesor KAN',
                        'soal' => $soal->soal ?? '',
                    ]];
                    $fieldKeys[$fieldIdx] = [$soal->id, $praktisiJabatanIds[$key], $value];
                }
            }

            if (empty($fields)) continue;

            $llmResults = $this->llm->generateBatchTexts($fields);

            foreach ($fieldKeys as $fieldIdx => [$soalId, $kpjId, $value]) {
                $fieldType = $value === 1 ? 'kan_esai_benar' : 'kan_esai_salah';
                $resultKey = $fieldType . "_{$fieldIdx}";
                $jawaban = $llmResults[$resultKey] ?? $this->llm->getFallback($fieldType);

                $rows[] = [
                    'kan_praktisi_jabatan_id' => $kpjId,
                    'dokumen_id'              => $dokumen->id,
                    'soal_id'                 => $soalId,
                    'jawaban'                 => $jawaban,
                    'skor'                    => $value === 1 ? random_int(2, 3) : 0,
                    'updated_at'              => $this->timestamp,
                    'created_at'              => $this->timestamp,
                ];
            }
        }

        if (!empty($rows)) {
            KanIa6Answer::upsert($rows, ['kan_praktisi_jabatan_id', 'dokumen_id', 'soal_id'], ['jawaban', 'skor', 'updated_at']);
        }
        return count($rows);
    }

    // ═══════════════════════════════════════════════════════════════
    //  IA04B: LISAN / DIT (batch 3 soals = 30 fields → ~21KB prompt → 18-22s)
    // ═══════════════════════════════════════════════════════════════

    private function generateIa04bBulk($dokumen, array $lisanScores, array $praktisiJabatanIds, array $asesorNames, string $skemaName, string $noSkema): int
    {
        $soals = $this->getSoals($dokumen->id, [8]);
        $chunkSize = 3; // 3 soals × 10 praktisi = 30 fields → ~21KB
        $soalChunks = array_chunk($lisanScores, $chunkSize, true);
        $rows = [];

        foreach ($soalChunks as $chunk) {
            $fields = [];
            $fieldKeys = [];

            foreach ($chunk as $i => $scoreEntry) {
                if ($i >= count($soals)) break 2;
                $soal = $soals[$i];
                $soalText = implode("\n", array_filter([$soal->soal ?? '', $soal->soal1 ?? '', $soal->soal2 ?? '']));

                foreach (self::PRAKTISI_KEYS as $key) {
                    if (!isset($praktisiJabatanIds[$key])) continue;
                    $value = (int) ($scoreEntry[$key] ?? 0);
                    $fieldType = $value === 1 ? 'kan_lisan_benar' : 'kan_lisan_salah';
                    $userName = $asesorNames[$key] ?? $key;

                    $fieldIdx = count($fields);
                    $fields[] = [$fieldType, [
                        'nama_asesi' => $userName, 'jabatan_kerja' => $skemaName,
                        'nomor_skema' => $noSkema, 'asesor' => 'Asesor KAN',
                        'soal' => $soalText ?: ($soal->soal ?? ''),
                    ]];
                    $fieldKeys[$fieldIdx] = [$soal->id, $praktisiJabatanIds[$key], $value];
                }
            }

            if (empty($fields)) continue;

            $llmResults = $this->llm->generateBatchTexts($fields);

            foreach ($fieldKeys as $fieldIdx => [$soalId, $kpjId, $value]) {
                $fieldType = $value === 1 ? 'kan_lisan_benar' : 'kan_lisan_salah';
                $resultKey = $fieldType . "_{$fieldIdx}";
                $jawaban = $llmResults[$resultKey] ?? $this->llm->getFallback($fieldType);

                $rows[] = [
                    'kan_praktisi_jabatan_id' => $kpjId,
                    'dokumen_id'              => $dokumen->id,
                    'soal_id'                 => $soalId,
                    'jawaban'                 => $jawaban,
                    'skor'                    => $value === 1 ? random_int(2, 3) : 0,
                    'is_rekomendasi'          => false,
                    'updated_at'              => $this->timestamp,
                    'created_at'              => $this->timestamp,
                ];
            }
        }

        if (!empty($rows)) {
            KanIa4bAnswer::upsert($rows, ['kan_praktisi_jabatan_id', 'dokumen_id', 'soal_id'], ['jawaban', 'skor', 'is_rekomendasi', 'updated_at']);
        }
        return count($rows);
    }
}
