<?php

namespace App\Console\Commands;

use App\Models\KanIa4bAnswer;
use App\Models\KanIa5Answer;
use App\Models\KanIa6Answer;
use App\Models\KanPraktisiJabatan;
use App\Models\KanSoal;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CrosscheckJsonAnswers extends Command
{
    protected $signature = 'kan:crosscheck-json-answers
                            {file : Path ke a1_a10_extract JSON}
                            {--fix : Update jawaban di database sesuai JSON}
                            {--skema= : Filter skema tertentu (partial match)}
                            {--asesor= : Filter asesor tertentu (partial match)}
                            {--dry-run : Hanya tampilkan statistik tanpa detail per soal}';

    protected $description = 'Crosscheck dan fix jawaban praktisi (IA04B/IA05/IA06) berdasarkan JSON A1-A10 mapping';

    private const DOK_KODES = [
        'Pg'    => 'FR.IA.05',
        'Esai'  => 'FR.IA.06',
        'Lisan' => 'FR.IA.04.B',
    ];

    private const ANSWER_MODELS = [
        'Pg'    => KanIa5Answer::class,
        'Esai'  => KanIa6Answer::class,
        'Lisan' => KanIa4bAnswer::class,
    ];

    private const TIPE_SOAL = [
        'Pg'    => 1,
        'Esai'  => 8,
        'Lisan' => 8,
    ];

    private array $globalStats = [
        'entries_processed' => 0,
        'entries_skipped'   => 0,
        'entries_with_issues' => 0,
        'total_checked'     => 0,
        'total_correct'     => 0,
        'total_wrong_skor'  => 0,
        'total_missing'     => 0,
        'total_fixed'       => 0,
    ];

    // Caches
    private array $skemaMap = [];
    private array $userCache = [];
    private array $kpjCache = [];

    public function handle(): int
    {
        $filePath = $this->argument('file');
        if (!file_exists($filePath)) {
            $this->error("File tidak ditemukan: {$filePath}");
            return 1;
        }

        $json = json_decode(file_get_contents($filePath), true);
        if (!$json || !isset($json['data'])) {
            $this->error('Format JSON tidak valid (harus punya key "data").');
            return 1;
        }

        $skemaFilter = $this->option('skema');
        $asesorFilter = $this->option('asesor');
        $isFix = $this->option('fix');
        $isDryRun = $this->option('dry-run');

        $this->info('╔══════════════════════════════════════════════════╗');
        $this->info('║  CROSSCHECK JAWABAN PRAKTISI vs JSON A1-A10     ║');
        $this->info('╚══════════════════════════════════════════════════╝');
        $this->newLine();
        $this->info("File JSON : {$filePath}");
        $this->info("Total entries dalam JSON: {$json['total']}");
        $this->info("Mode: " . ($isFix ? 'FIX' : 'CHECK ONLY'));
        if ($skemaFilter) $this->info("Filter skema: {$skemaFilter}");
        if ($asesorFilter) $this->info("Filter asesor: {$asesorFilter}");
        $this->newLine();

        // Build caches
        $this->buildCaches($json['data']);

        foreach ($json['data'] as $entryIdx => $entry) {
            $skemaName = trim($entry['skema'] ?? 'Unknown');

            // Filter by skema
            if ($skemaFilter && stripos($skemaName, $skemaFilter) === false) {
                continue;
            }

            // Find jabatan_kerja by name matching
            $idJabatanKerja = $this->findJabatanKerja($skemaName);
            if (!$idJabatanKerja) {
                $this->warn("[#{$entry['no']}] SKIP: Tidak ditemukan jabatan_kerja untuk '{$skemaName}'");
                $this->globalStats['entries_skipped']++;
                continue;
            }

            $this->info("── [#{$entry['no']}] {$skemaName} ──");
            $this->info("    Jabker ID: {$idJabatanKerja}");
            $this->globalStats['entries_processed']++;

            // Get dokumens
            $dokumens = $this->getDokumens($idJabatanKerja);
            $asesorNames = $entry['asesor_names'] ?? [];

            $entryHadIssues = false;

            foreach (['Pg', 'Esai', 'Lisan'] as $scoreType) {
                if (empty($entry['scores'][$scoreType])) {
                    continue;
                }

                $dokKey = $this->dokKodeToKey(self::DOK_KODES[$scoreType]);

                if (empty($dokumens[$dokKey])) {
                    if (!$isDryRun) {
                        $this->line("    <fg=yellow>⚠ {$scoreType}: Tidak ada dokumen " . self::DOK_KODES[$scoreType] . "</>");
                    }
                    continue;
                }

                $dokumen = $dokumens[$dokKey];
                $soals = $this->getSoals($dokumen->id, self::TIPE_SOAL[$scoreType]);

                if (empty($soals)) {
                    if (!$isDryRun) {
                        $this->line("    <fg=yellow>⚠ {$scoreType}: Tidak ada soal tipe " . self::TIPE_SOAL[$scoreType] . " di dok#{$dokumen->id}</>");
                    }
                    continue;
                }

                $jsonQuestions = $entry['scores'][$scoreType];

                foreach ($asesorNames as $aseKey => $aseName) {
                    $aseName = trim($aseName);

                    if ($asesorFilter && stripos($aseName, $asesorFilter) === false) {
                        continue;
                    }

                    // Find user
                    $userId = $this->findUserByName($aseName);
                    if (!$userId) {
                        if (!$isDryRun) {
                            $this->line("    <fg=red>✗ {$aseKey} ({$aseName}): User tidak ditemukan!</>");
                        }
                        continue;
                    }

                    // Find kan_praktisi_jabatan
                    $kpjId = $this->findKpj($idJabatanKerja, $userId);
                    if (!$kpjId) {
                        if (!$isDryRun) {
                            $this->line("    <fg=yellow>⚠ {$aseKey} ({$aseName}): Tidak ada KPJ record</>");
                        }
                        continue;
                    }

                    $answerModel = self::ANSWER_MODELS[$scoreType];
                    $missingCount = 0;
                    $wrongSkorCount = 0;
                    $correctCount = 0;
                    $fixedCount = 0;

                    // Get all existing answers for this KPJ + dokumen (batch)
                    $existingAnswers = $answerModel::where('kan_praktisi_jabatan_id', $kpjId)
                        ->where('dokumen_id', $dokumen->id)
                        ->get()
                        ->keyBy('soal_id');

                    foreach ($jsonQuestions as $qIdx => $qScores) {
                        if (!isset($soals[$qIdx])) {
                            break;
                        }

                        $soal = $soals[$qIdx];
                        $jsonSkor = (int) ($qScores[$aseKey] ?? 0);

                        $existing = $existingAnswers->get($soal->id);

                        if (!$existing) {
                            $missingCount++;
                            if ($isFix) {
                                $this->createAnswer($scoreType, $kpjId, $dokumen->id, $soal, $jsonSkor);
                                $fixedCount++;
                            }
                            continue;
                        }

                        $dbSkor = (int) $existing->skor;
                        if ($dbSkor !== $jsonSkor) {
                            $wrongSkorCount++;
                            if ($isFix) {
                                $this->fixAnswer($scoreType, $existing, $soal, $jsonSkor);
                                $fixedCount++;
                            }
                        } else {
                            $correctCount++;
                        }
                    }

                    $this->globalStats['total_checked'] += count($jsonQuestions);
                    $this->globalStats['total_correct'] += $correctCount;
                    $this->globalStats['total_wrong_skor'] += $wrongSkorCount;
                    $this->globalStats['total_missing'] += $missingCount;
                    $this->globalStats['total_fixed'] += $fixedCount;

                    if ($missingCount > 0 || $wrongSkorCount > 0) {
                        $entryHadIssues = true;
                        if (!$isDryRun) {
                            $icon = $isFix ? '🔧' : '⚠';
                            $this->line("    {$icon} {$aseKey} ({$aseName}) [{$scoreType}]: {$correctCount}✓, {$wrongSkorCount}✗skor, {$missingCount}?missing" . ($isFix ? " → {$fixedCount} fixed" : ""));
                        }
                    } elseif (!$isDryRun) {
                        $totalForAse = $correctCount + $wrongSkorCount + $missingCount;
                        if ($totalForAse > 0) {
                            $this->line("    ✓ {$aseKey} ({$aseName}) [{$scoreType}]: {$correctCount}/{$totalForAse} OK");
                        }
                    }
                }
            }

            if ($entryHadIssues) {
                $this->globalStats['entries_with_issues']++;
            }
            $this->newLine();
        }

        // Summary
        $this->info('╔══════════════════════════════════════════════════╗');
        $this->info('║  RINGKASAN                                      ║');
        $this->info('╚══════════════════════════════════════════════════╝');
        $this->info("Entries diproses:      {$this->globalStats['entries_processed']}");
        $this->info("Entries diskip:        {$this->globalStats['entries_skipped']}");
        $this->info("Entries dgn masalah:   {$this->globalStats['entries_with_issues']}");
        $this->info("Total jawaban dicek:   {$this->globalStats['total_checked']}");
        $this->info("Total OK:              {$this->globalStats['total_correct']}");
        $this->info("Total skor salah:      {$this->globalStats['total_wrong_skor']}");
        $this->info("Total missing:         {$this->globalStats['total_missing']}");
        $this->info("Total difix:           {$this->globalStats['total_fixed']}");

        if (!$isFix && ($this->globalStats['total_wrong_skor'] > 0 || $this->globalStats['total_missing'] > 0)) {
            $this->newLine();
            $this->warn('Jalankan dengan --fix untuk memperbaiki jawaban.');
        }

        return 0;
    }

    // ═══════════════════════════════════════════════════════════════
    //  CACHES
    // ═══════════════════════════════════════════════════════════════

    private function buildCaches(array $entries): void
    {
        // 1. Build skema → id_jabatan_kerja map
        $allSkemaNames = array_unique(array_map(fn($e) => trim($e['skema']), $entries));
        $lowerNames = array_map('mb_strtolower', $allSkemaNames);

        $placeholders = implode(',', array_fill(0, count($lowerNames), '?'));
        $rows = DB::table('jabatan_kerjas')
            ->select('id_jabatan_kerja', 'jabatan_kerja')
            ->whereRaw("LOWER(jabatan_kerja) IN ({$placeholders})", $lowerNames)
            ->orderBy('id_jabatan_kerja', 'desc')
            ->get();

        foreach ($rows as $row) {
            $key = mb_strtolower($row->jabatan_kerja);
            if (!isset($this->skemaMap[$key])) {
                $this->skemaMap[$key] = $row->id_jabatan_kerja;
            }
        }

        // 2. Build user name → id cache (praktisi: role_id = 16)
        $allAseNames = [];
        foreach ($entries as $entry) {
            foreach (($entry['asesor_names'] ?? []) as $name) {
                $allAseNames[] = trim($name);
            }
        }
        $allAseNames = array_unique($allAseNames);

        $userRows = DB::table('users')
            ->whereIn('name', $allAseNames)
            ->where('role_id', 16)
            ->select('id', 'name')
            ->get();

        foreach ($userRows as $u) {
            $key = mb_strtolower($u->name);
            $this->userCache[$key] = $u->id;
        }

        // Also try role_id = 15 (asesor) as fallback — some users might be asesors
        // First, collect names NOT yet matched
        $unmatchedNames = array_diff(
            array_map('mb_strtolower', $allAseNames),
            array_keys($this->userCache)
        );

        if (!empty($unmatchedNames)) {
            $unmatchedOriginal = [];
            foreach ($allAseNames as $n) {
                if (!isset($this->userCache[mb_strtolower($n)])) {
                    $unmatchedOriginal[] = $n;
                }
            }
            if (!empty($unmatchedOriginal)) {
                $extraRows = DB::table('users')
                    ->whereIn('name', $unmatchedOriginal)
                    ->select('id', 'name', 'role_id')
                    ->get();
                foreach ($extraRows as $u) {
                    $key = mb_strtolower($u->name);
                    if (!isset($this->userCache[$key])) {
                        $this->userCache[$key] = $u->id;
                    }
                }
            }
        }

        // 3. Build KPJ cache
        $allJabkerIds = array_unique(array_values($this->skemaMap));
        $allUserIds = array_unique(array_values($this->userCache));

        if (!empty($allJabkerIds) && !empty($allUserIds)) {
            $kpjRows = DB::table('kan_praktisi_jabatan')
                ->whereIn('id_jabatan_kerja', $allJabkerIds)
                ->whereIn('id_user', $allUserIds)
                ->select('id', 'id_jabatan_kerja', 'id_user')
                ->get();

            foreach ($kpjRows as $row) {
                $key = $row->id_jabatan_kerja . '|' . $row->id_user;
                $this->kpjCache[$key] = $row->id;
            }
        }

        $this->info("Cache built: " . count($this->skemaMap) . " skema, " . count($this->userCache) . " users, " . count($this->kpjCache) . " KPJ records.");
        $this->newLine();
    }

    // ═══════════════════════════════════════════════════════════════
    //  HELPERS
    // ═══════════════════════════════════════════════════════════════

    private function findJabatanKerja(string $skemaName): ?string
    {
        $key = mb_strtolower(trim($skemaName));
        return $this->skemaMap[$key] ?? null;
    }

    private function findUserByName(string $name): ?int
    {
        $key = mb_strtolower(trim($name));
        if (isset($this->userCache[$key])) {
            return $this->userCache[$key];
        }

        // Try fuzzy: check each cached name against input
        foreach ($this->userCache as $cachedName => $userId) {
            if (str_contains($cachedName, $key) || str_contains($key, $cachedName)) {
                return $userId;
            }
        }

        return null;
    }

    private function findKpj(string $idJabatanKerja, int $userId): ?int
    {
        $key = $idJabatanKerja . '|' . $userId;
        return $this->kpjCache[$key] ?? null;
    }

    private function getDokumens(string $jabkerId): array
    {
        $rows = DB::table('jabatan_dokumens')
            ->select('id', 'kode')
            ->where('id_jabatan', $jabkerId)
            ->whereIn('kode', array_values(self::DOK_KODES))
            ->where('is_active', true)
            ->get()
            ->keyBy('kode');

        $result = ['ia04b' => null, 'ia05' => null, 'ia06' => null];
        foreach (self::DOK_KODES as $code) {
            $resultKey = $this->dokKodeToKey($code);
            $result[$resultKey] = $rows->get($code);
        }
        return $result;
    }

    private function dokKodeToKey(string $kode): string
    {
        return match ($kode) {
            'FR.IA.05'   => 'ia05',
            'FR.IA.06'   => 'ia06',
            'FR.IA.04.B' => 'ia04b',
            default      => strtolower(str_replace(['FR.', '.'], '', $kode)),
        };
    }

    private function getSoals(int $dokumenId, int $tipe): array
    {
        return KanSoal::where('id_dokumen', $dokumenId)
            ->where('tipe', $tipe)
            ->orderBy('no', 'asc')
            ->get()
            ->all();
    }

    // ═══════════════════════════════════════════════════════════════
    //  CREATE / FIX ANSWERS
    // ═══════════════════════════════════════════════════════════════

    private function createAnswer(string $scoreType, int $kpjId, int $dokId, $soal, int $correct): void
    {
        $timestamp = now()->toDateTimeString();

        if ($scoreType === 'Pg') {
            $kunci = strtoupper($soal->jawaban ?? '');
            $wrongOptions = array_values(array_filter(['A', 'B', 'C', 'D'], fn($o) => $o !== $kunci));
            $jawaban = $correct ? $kunci : ($wrongOptions[array_rand($wrongOptions)] ?? 'A');

            KanIa5Answer::create([
                'kan_praktisi_jabatan_id' => $kpjId,
                'dokumen_id'              => $dokId,
                'soal_id'                 => $soal->id,
                'jawaban'                 => $jawaban,
                'skor'                    => $correct,
                'created_at'              => $timestamp,
                'updated_at'              => $timestamp,
            ]);
        } elseif ($scoreType === 'Esai') {
            KanIa6Answer::create([
                'kan_praktisi_jabatan_id' => $kpjId,
                'dokumen_id'              => $dokId,
                'soal_id'                 => $soal->id,
                'jawaban'                 => $correct ? 'Jawaban benar (auto)' : 'Jawaban salah (auto)',
                'skor'                    => $correct ? random_int(2, 3) : 0,
                'created_at'              => $timestamp,
                'updated_at'              => $timestamp,
            ]);
        } else {
            KanIa4bAnswer::create([
                'kan_praktisi_jabatan_id' => $kpjId,
                'dokumen_id'              => $dokId,
                'soal_id'                 => $soal->id,
                'jawaban'                 => $correct ? 'Jawaban benar (auto)' : 'Jawaban salah (auto)',
                'skor'                    => $correct ? random_int(2, 3) : 0,
                'is_rekomendasi'          => false,
                'created_at'              => $timestamp,
                'updated_at'              => $timestamp,
            ]);
        }
    }

    private function fixAnswer(string $scoreType, $existing, $soal, int $correct): void
    {
        $now = now()->toDateTimeString();

        if ($scoreType === 'Pg') {
            $kunci = strtoupper($soal->jawaban ?? '');
            $wrongOptions = array_values(array_filter(['A', 'B', 'C', 'D'], fn($o) => $o !== $kunci));
            $jawaban = $correct ? $kunci : ($wrongOptions[array_rand($wrongOptions)] ?? 'A');
            $existing->update([
                'jawaban'    => $jawaban,
                'skor'       => $correct,
                'updated_at' => $now,
            ]);
        } elseif ($scoreType === 'Esai') {
            $existing->update([
                'skor'       => $correct ? max(2, (int) $existing->skor) : 0,
                'updated_at' => $now,
            ]);
        } else {
            $existing->update([
                'skor'       => $correct ? max(2, (int) $existing->skor) : 0,
                'updated_at' => $now,
            ]);
        }
    }
}
