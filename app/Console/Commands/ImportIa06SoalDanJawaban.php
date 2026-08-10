<?php

namespace App\Console\Commands;

use App\Models\KanIa4bAnswer;
use App\Models\KanIa5Answer;
use App\Models\KanIa6Answer;
use App\Models\KanPraktisiJabatan;
use App\Models\KanSoal;
use App\Services\LlmAutoFillService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportIa06SoalDanJawaban extends Command
{
    protected $signature = 'kan:import-ia06 {file : Path ke ALL_SOAL_FIXED.json}';
    protected $description = 'Import IA04B/IA05/IA06 soal dari JSON dan generate jawaban untuk semua praktisi';

    private const MAPPING = [
        '100. TEKNISI SONDIR (LEVEL 4)' => [
            'jabker_id' => 'SI152008',
            'dok_ia06_id' => 14417,
            // IA04B & IA05 already existed, only IA06 was missing
        ],
        '104. TEKNISI PENGEBORAN PENGUJIAN TANAH (LEVEL 4)' => [
            'jabker_id' => 'SI152010',
            'dok_ia06_id' => 14418,
        ],
        '190. PELAKSANA LAPANGAN PEKERJAAN BANGUNAN PENGAMAN PANTAI (LEVEL 4)' => [
            'jabker_id' => 'SI092009',
            'dok_ia06_id' => 14419,
        ],
        '217. OPERATOR UTAMA SURVEI TERESTRIS' => [
            'jabker_id' => 'SI162003',
            'dok_ia04b_id' => 11749,
            'dok_ia05_id' => 8434,
            'dok_ia06_id' => 14420,
        ],
    ];

    private LlmAutoFillService $llm;
    private array $globalUnitCache = [];

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
        if (!$json) {
            $this->error('Format JSON tidak valid.');
            return 1;
        }

        $totalStats = [
            'soal_ia04b_imported' => 0, 'soal_ia05_imported' => 0, 'soal_ia06_imported' => 0,
            'soal_skipped' => 0,
            'jawaban_ia04b' => 0, 'jawaban_ia05' => 0, 'jawaban_ia06' => 0,
        ];
        $timestamp = now()->toDateTimeString();

        foreach (self::MAPPING as $jsonKey => $config) {
            if (!isset($json[$jsonKey])) {
                $this->warn("Key '{$jsonKey}' tidak ditemukan di JSON, skip.");
                continue;
            }

            $entry = $json[$jsonKey];
            $jabkerId = $config['jabker_id'];
            $jabkerName = $entry['nama'] ?? $jsonKey;

            $this->info("=== {$jabkerName} ({$jabkerId}) ===");

            $dokIa04bId = $config['dok_ia04b_id'] ?? null;
            $dokIa05Id  = $config['dok_ia05_id'] ?? null;
            $dokIa06Id  = $config['dok_ia06_id'] ?? null;

            // Build unitkompetensi mapping (shared across all dokumens)
            $unitMap = $this->buildUnitMap($jabkerId);

            // ═══ IA04B: Import soals + generate jawaban ═══
            if ($dokIa04bId && !empty($entry['ia04b'])) {
                $imported = $this->importSoalsTipe8($dokIa04bId, $entry['ia04b'], 'IA04B', $unitMap);
                if ($imported > 0) {
                    $totalStats['soal_ia04b_imported'] += $imported;
                } else {
                    $totalStats['soal_skipped'] += count($entry['ia04b']);
                }

                $jawabanCount = $this->generateAnswersForDokumen(
                    $dokIa04bId, $jabkerId, $jabkerName, 'ia04b', $timestamp
                );
                $totalStats['jawaban_ia04b'] += $jawabanCount;
            }

            // ═══ IA05: Import soals + generate jawaban ═══
            if ($dokIa05Id && !empty($entry['ia05'])) {
                $imported = $this->importSoalsTipe1($dokIa05Id, $entry['ia05'], $unitMap);
                if ($imported > 0) {
                    $totalStats['soal_ia05_imported'] += $imported;
                } else {
                    $totalStats['soal_skipped'] += count($entry['ia05']);
                }

                $jawabanCount = $this->generateIa05Answers($dokIa05Id, $jabkerId, $timestamp);
                $totalStats['jawaban_ia05'] += $jawabanCount;
            }

            // ═══ IA06: Import soals + generate jawaban ═══
            if ($dokIa06Id && !empty($entry['ia06'])) {
                $imported = $this->importSoalsTipe8($dokIa06Id, $entry['ia06'], 'IA06', $unitMap);
                if ($imported > 0) {
                    $totalStats['soal_ia06_imported'] += $imported;
                } else {
                    $totalStats['soal_skipped'] += count($entry['ia06']);
                }

                $jawabanCount = $this->generateAnswersForDokumen(
                    $dokIa06Id, $jabkerId, $jabkerName, 'ia06', $timestamp
                );
                $totalStats['jawaban_ia06'] += $jawabanCount;
            }

            $this->newLine();
        }

        $this->info('═══ HASIL ═══');
        $this->info("IA04B soal diimport: {$totalStats['soal_ia04b_imported']}");
        $this->info("IA05 soal diimport:  {$totalStats['soal_ia05_imported']}");
        $this->info("IA06 soal diimport:  {$totalStats['soal_ia06_imported']}");
        $this->info("Soal diskip:         {$totalStats['soal_skipped']}");
        $this->info("IA04B jawaban dibuat: {$totalStats['jawaban_ia04b']}");
        $this->info("IA05 jawaban dibuat:  {$totalStats['jawaban_ia05']}");
        $this->info("IA06 jawaban dibuat:  {$totalStats['jawaban_ia06']}");
        $this->info('Done!');

        return 0;
    }

    // ═══════════════════════════════════════════════════════════════
    //  SOAL IMPORT
    // ═══════════════════════════════════════════════════════════════

    private function importSoalsTipe8(int $dokId, array $soals, string $label, array $unitMap): int
    {
        $existingCount = KanSoal::where('id_dokumen', $dokId)->where('tipe', 8)->count();
        if ($existingCount >= count($soals)) {
            $this->info("  {$label} soals: sudah ada {$existingCount}, skip import.");
            return 0;
        }

        $imported = 0;
        foreach ($soals as $soal) {
            // IA04B uses 'kode_unit', IA06 uses 'kode_kuk' (format: "UNIT\nELEMEN")
            $kodeUnit = $soal['kode_unit'] ?? '';
            if (empty($kodeUnit)) {
                $kodeKuk = $soal['kode_kuk'] ?? '';
                $parts = explode("\n", $kodeKuk);
                $kodeUnit = $parts[0] ?? '';
            }

            $idUnitKompetensi = $this->resolveUnitId($kodeUnit, $unitMap);

            KanSoal::create([
                'id_dokumen' => $dokId,
                'id_unitkompetensi' => $idUnitKompetensi,
                'no' => $soal['no'],
                'urut' => $soal['no'],
                'tipe' => 8,
                'jenis' => str_replace('IA0', 'IA.', $label),
                'soal' => $soal['soal'],
                'soal1' => $soal['lingkup'] ?? null,
                'is_komentar' => false,
            ]);
            $imported++;
        }
        $this->info("  {$label} soals: imported {$imported} soal (tipe 8).");
        return $imported;
    }

    private function importSoalsTipe1(int $dokId, array $soals, array $unitMap): int
    {
        $existingCount = KanSoal::where('id_dokumen', $dokId)->where('tipe', 1)->count();
        if ($existingCount >= count($soals)) {
            $this->info("  IA05 soals: sudah ada {$existingCount}, skip import.");
            return 0;
        }

        $imported = 0;
        foreach ($soals as $soal) {
            $kodeKuk = $soal['kode_kuk'] ?? '';
            $parts = explode("\n", $kodeKuk);
            $kodeUnit = $parts[0] ?? '';

            $idUnitKompetensi = $this->resolveUnitId($kodeUnit, $unitMap);

            KanSoal::create([
                'id_dokumen' => $dokId,
                'id_unitkompetensi' => $idUnitKompetensi,
                'no' => $soal['no'],
                'urut' => $soal['no'],
                'tipe' => 1,
                'jenis' => 'IA.5',
                'soal' => $soal['soal'],
                'jawaban' => strtoupper($soal['jawaban'] ?? ''),
                'jawab_a' => $soal['jawab_a'] ?? null,
                'jawab_b' => $soal['jawab_b'] ?? null,
                'jawab_c' => $soal['jawab_c'] ?? null,
                'jawab_d' => $soal['jawab_d'] ?? null,
                'is_komentar' => false,
            ]);
            $imported++;
        }
        $this->info("  IA05 soals: imported {$imported} soal (tipe 1).");
        return $imported;
    }

    // ═══════════════════════════════════════════════════════════════
    //  ANSWER GENERATION
    // ═══════════════════════════════════════════════════════════════

    private function generateAnswersForDokumen(
        int $dokId, string $jabkerId, string $jabkerName, string $type, string $timestamp
    ): int {
        $praktisi = KanPraktisiJabatan::where('id_jabatan_kerja', $jabkerId)->get();
        $this->info("  Praktisi: " . count($praktisi));

        $total = 0;
        $answerModel = $type === 'ia04b' ? KanIa4bAnswer::class : KanIa6Answer::class;

        foreach ($praktisi as $p) {
            $existing = $answerModel::where('kan_praktisi_jabatan_id', $p->id)->count();
            if ($existing > 0) {
                $this->info("    KPJ#{$p->id}: sudah ada {$existing} jawaban, skip.");
                continue;
            }

            $count = $this->generateTipe8ForPraktisi(
                $dokId, $p->id, $p->id_user, $jabkerName, $timestamp, $type
            );
            $total += $count;
            $this->info("    KPJ#{$p->id}: generated {$count} jawaban {$type}.");
        }

        return $total;
    }

    private function generateTipe8ForPraktisi(
        int $dokId, int $kpjId, int $userId, string $jabkerName, string $timestamp, string $type
    ): int {
        $soals = KanSoal::where('id_dokumen', $dokId)
            ->where('tipe', 8)
            ->orderBy('no', 'asc')
            ->get()->all();

        if (empty($soals)) return 0;

        $user = DB::table('users')->find($userId);
        $userName = $user->name ?? 'Praktisi';
        $noSkema = 'SOAL-' . $kpjId;

        $chunkSize = $type === 'ia04b' ? 3 : 5;
        $soalChunks = array_chunk($soals, $chunkSize);
        $rows = [];

        foreach ($soalChunks as $chunkIdx => $chunk) {
            $fields = [];
            $fieldKeys = [];

            foreach ($chunk as $i => $soal) {
                $isCorrect = (($chunkIdx * $chunkSize + $i) % 3) !== 0;
                $fieldType = $type === 'ia04b'
                    ? ($isCorrect ? 'kan_lisan_benar' : 'kan_lisan_salah')
                    : ($isCorrect ? 'kan_esai_benar' : 'kan_esai_salah');

                $fieldIdx = count($fields);
                $fields[] = [$fieldType, [
                    'nama_asesi' => $userName,
                    'jabatan_kerja' => $jabkerName,
                    'nomor_skema' => $noSkema,
                    'asesor' => 'Asesor KAN',
                    'soal' => $soal->soal ?? '',
                ]];
                $fieldKeys[$fieldIdx] = [$soal->id, $isCorrect];
            }

            if (empty($fields)) continue;

            $this->line("      chunk " . ($chunkIdx + 1) . "/" . count($soalChunks) . " (" . count($fields) . " fields)...", null, 'vv');
            $llmResults = $this->llm->generateBatchTexts($fields);

            foreach ($fieldKeys as $fieldIdx => [$soalId, $isCorrect]) {
                $esaiType = $type === 'ia04b'
                    ? ($isCorrect ? 'kan_lisan_benar' : 'kan_lisan_salah')
                    : ($isCorrect ? 'kan_esai_benar' : 'kan_esai_salah');
                $resultKey = $esaiType . "_{$fieldIdx}";
                $jawaban = $llmResults[$resultKey] ?? $this->llm->getFallback($esaiType);

                $row = [
                    'kan_praktisi_jabatan_id' => $kpjId,
                    'dokumen_id'              => $dokId,
                    'soal_id'                 => $soalId,
                    'jawaban'                 => $jawaban,
                    'skor'                    => $isCorrect ? random_int(2, 3) : 0,
                    'updated_at'              => $timestamp,
                    'created_at'              => $timestamp,
                ];

                if ($type === 'ia04b') {
                    $row['is_rekomendasi'] = false;
                }

                $rows[] = $row;
            }
        }

        if (!empty($rows)) {
            $uniqueKey = ['kan_praktisi_jabatan_id', 'dokumen_id', 'soal_id'];
            $updateCols = $type === 'ia04b'
                ? ['jawaban', 'skor', 'is_rekomendasi', 'updated_at']
                : ['jawaban', 'skor', 'updated_at'];

            if ($type === 'ia04b') {
                KanIa4bAnswer::upsert($rows, $uniqueKey, $updateCols);
            } else {
                KanIa6Answer::upsert($rows, $uniqueKey, $updateCols);
            }
        }

        return count($rows);
    }

    private function generateIa05Answers(int $dokId, string $jabkerId, string $timestamp): int
    {
        $praktisi = KanPraktisiJabatan::where('id_jabatan_kerja', $jabkerId)->get();
        $this->info("  Praktisi: " . count($praktisi));

        $soals = KanSoal::where('id_dokumen', $dokId)
            ->where('tipe', 1)
            ->orderBy('no', 'asc')
            ->get()->all();

        if (empty($soals)) {
            $this->warn("  IA05: tidak ada soal tipe 1 di dok#{$dokId}!");
            return 0;
        }

        $total = 0;
        foreach ($praktisi as $p) {
            $existing = KanIa5Answer::where('kan_praktisi_jabatan_id', $p->id)->count();
            if ($existing > 0) {
                $this->info("    KPJ#{$p->id}: sudah ada {$existing} jawaban IA05, skip.");
                continue;
            }

            $rows = [];
            foreach ($soals as $soal) {
                // ~75% correct answers
                $isCorrect = (mt_rand(0, 3) > 0);
                $kunci = strtoupper($soal->jawaban ?? '');
                $wrongOptions = array_values(array_filter(
                    ['A', 'B', 'C', 'D'],
                    fn($o) => $o !== $kunci
                ));

                $jawaban = $isCorrect ? $kunci : $wrongOptions[array_rand($wrongOptions)];
                $skor = $isCorrect ? 1 : 0;

                $rows[] = [
                    'kan_praktisi_jabatan_id' => $p->id,
                    'dokumen_id'              => $dokId,
                    'soal_id'                 => $soal->id,
                    'jawaban'                 => $jawaban,
                    'skor'                    => $skor,
                    'updated_at'              => $timestamp,
                    'created_at'              => $timestamp,
                ];
            }

            if (!empty($rows)) {
                KanIa5Answer::upsert(
                    $rows,
                    ['kan_praktisi_jabatan_id', 'dokumen_id', 'soal_id'],
                    ['jawaban', 'skor', 'updated_at']
                );
            }

            $total += count($rows);
            $this->info("    KPJ#{$p->id}: generated " . count($rows) . " jawaban IA05.");
        }

        return $total;
    }

    // ═══════════════════════════════════════════════════════════════
    //  UNIT MAP
    // ═══════════════════════════════════════════════════════════════

    private function buildUnitMap(string $jabkerId): array
    {
        // Get IA04B dokumen
        $dokIa04b = DB::table('jabatan_dokumens')
            ->where('id_jabatan', $jabkerId)
            ->where('kode', 'FR.IA.04.B')
            ->where('is_active', true)
            ->first();

        if (!$dokIa04b) {
            // Try any dokumen that might have soals with unitkompetensi
            $anyDok = DB::table('jabatan_dokumens')
                ->where('id_jabatan', $jabkerId)
                ->where('is_active', true)
                ->first();
            if (!$anyDok) return [];

            $soals = DB::table('kan_soals')
                ->where('id_dokumen', $anyDok->id)
                ->whereNotNull('id_unitkompetensi')
                ->get(['id_unitkompetensi']);
        } else {
            $soals = DB::table('kan_soals')
                ->where('id_dokumen', $dokIa04b->id)
                ->whereNotNull('id_unitkompetensi')
                ->get(['id_unitkompetensi']);
        }

        $unitIds = $soals->pluck('id_unitkompetensi')->unique()->values()->all();

        // If we still have no unitkompetensi references, try getting all units for this jabker
        if (empty($unitIds)) {
            $unitIds = DB::table('unit_kompetensis')
                ->where('id_jabatan', $jabkerId)
                ->pluck('id')
                ->unique()
                ->values()
                ->all();
        }

        $map = [];
        if (!empty($unitIds)) {
            $units = DB::table('unit_kompetensis')
                ->whereIn('id', $unitIds)
                ->get(['id', 'kode']);

            foreach ($units as $u) {
                // Don't overwrite — first match wins (from IA04B soals)
                if (!isset($map[$u->kode])) {
                    $map[$u->kode] = $u->id;
                }
            }
        }

        return $map;
    }

    private function resolveUnitId(string $kodeUnit, array $unitMap): ?int
    {
        // 1) From unitMap (built from jabker's own soals or units)
        if (isset($unitMap[$kodeUnit]) && $unitMap[$kodeUnit] !== null) {
            return $unitMap[$kodeUnit];
        }

        // 2) Global fallback — lookup by kode in unit_kompetensis
        if (array_key_exists($kodeUnit, $this->globalUnitCache)) {
            return $this->globalUnitCache[$kodeUnit];
        }

        $unit = DB::table('unit_kompetensis')
            ->where('kode', $kodeUnit)
            ->first(['id']);

        $id = $unit->id ?? null;
        $this->globalUnitCache[$kodeUnit] = $id;
        return $id;
    }
}
