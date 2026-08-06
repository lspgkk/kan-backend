<?php

namespace App\Console\Commands;

use App\Models\JabatanDokumen;
use App\Models\KanIa4bAnswer;
use App\Models\KanIa5Answer;
use App\Models\KanIa6Answer;
use App\Models\KanPraktisiJabatan;
use App\Models\KanSoal;
use App\Services\LlmAutoFillService;
use Illuminate\Console\Command;

class FixNuryonoAnswers extends Command
{
    protected $signature = 'kan:fix-nuryono-answers';
    protected $description = 'Generate missing KAN answers for NURYONO across 4 jabatan kerja';

    private const DOKUMEN_KODES = [
        'ia04b' => 'FR.IA.04.B',
        'ia05'  => 'FR.IA.05',
        'ia06'  => 'FR.IA.06',
    ];

    // NURYONO's kan_praktisi_jabatan IDs
    private const NURYONO_IDS = [5836, 5837, 5840, 5841];

    private LlmAutoFillService $llm;

    public function __construct()
    {
        parent::__construct();
        $this->llm = app(LlmAutoFillService::class);
    }

    public function handle(): int
    {
        $this->info('=== Generate Missing KAN Answers for NURYONO ===');
        $this->newLine();

        $timestamp = now()->toDateTimeString();
        $totalStats = ['ia05' => 0, 'ia06' => 0, 'ia04b' => 0];

        foreach (self::NURYONO_IDS as $kpjId) {
            $kpj = KanPraktisiJabatan::with('jabatanKerja')->find($kpjId);
            if (!$kpj) {
                $this->warn("KanPraktisiJabatan ID {$kpjId} tidak ditemukan, skip.");
                continue;
            }

            $jabkerName = $kpj->jabatanKerja->jabatan_kerja ?? 'Unknown';
            $idJabatanKerja = $kpj->id_jabatan_kerja;
            $userId = $kpj->id_user;

            $this->info("Processing: {$jabkerName} (KPJ ID: {$kpjId}, Jabker: {$idJabatanKerja})");

            // Get dokumens
            $dokumens = $this->getDokumens($idJabatanKerja);
            if (empty($dokumens['ia05']) && empty($dokumens['ia06']) && empty($dokumens['ia04b'])) {
                $this->warn("  Tidak ada dokumen KAN untuk jabker ini, skip.");
                continue;
            }

            // IA05: Pilihan Ganda
            if (!empty($dokumens['ia05'])) {
                $existing = KanIa5Answer::where('kan_praktisi_jabatan_id', $kpjId)->count();
                if ($existing > 0) {
                    $this->info("  IA05: sudah ada {$existing} jawaban, skip.");
                } else {
                    $count = $this->generateIa05($dokumens['ia05'], $kpjId, $userId, $timestamp);
                    $totalStats['ia05'] += $count;
                    $this->info("  IA05: generated {$count} jawaban PG.");
                }
            }

            // IA06: Esai (LLM)
            if (!empty($dokumens['ia06'])) {
                $existing = KanIa6Answer::where('kan_praktisi_jabatan_id', $kpjId)->count();
                if ($existing > 0) {
                    $this->info("  IA06: sudah ada {$existing} jawaban, skip.");
                } else {
                    $count = $this->generateIa06($dokumens['ia06'], $kpjId, $userId, $jabkerName, $timestamp);
                    $totalStats['ia06'] += $count;
                    $this->info("  IA06: generated {$count} jawaban esai (LLM).");
                }
            }

            // IA04B: Lisan (LLM)
            if (!empty($dokumens['ia04b'])) {
                $existing = KanIa4bAnswer::where('kan_praktisi_jabatan_id', $kpjId)->count();
                if ($existing > 0) {
                    $this->info("  IA04B: sudah ada {$existing} jawaban, skip.");
                } else {
                    $count = $this->generateIa04b($dokumens['ia04b'], $kpjId, $userId, $jabkerName, $timestamp);
                    $totalStats['ia04b'] += $count;
                    $this->info("  IA04B: generated {$count} jawaban lisan (LLM).");
                }
            }

            $this->newLine();
        }

        $this->info('═══ HASIL ═══');
        $this->info("IA05 (PG) generated:     {$totalStats['ia05']}");
        $this->info("IA06 (Esai) generated:   {$totalStats['ia06']}");
        $this->info("IA04B (Lisan) generated: {$totalStats['ia04b']}");
        $this->info('Done!');

        return 0;
    }

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

    // ─── IA05: PILIHAN GANDA ───────────────────────────────

    private function generateIa05($dokumen, int $kpjId, int $userId, string $timestamp): int
    {
        $soals = KanSoal::where('id_dokumen', $dokumen->id)
            ->where('tipe', 1)
            ->orderBy('no', 'asc')
            ->get()->all();

        if (empty($soals)) {
            $this->warn("  IA05: tidak ada soal tipe 1.");
            return 0;
        }

        $rows = [];
        foreach ($soals as $soal) {
            $kunci = strtoupper($soal->jawaban ?? '');
            $wrongOptions = array_values(array_filter(['A', 'B', 'C', 'D'], fn($o) => $o !== $kunci));

            // ~75% chance correct (matching existing praktisi pattern)
            $isCorrect = random_int(1, 100) <= 75;
            $jawaban = $isCorrect ? $kunci : $wrongOptions[array_rand($wrongOptions)];
            $skor = $isCorrect ? 1 : 0;

            $rows[] = [
                'kan_praktisi_jabatan_id' => $kpjId,
                'dokumen_id'              => $dokumen->id,
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

        return count($rows);
    }

    // ─── IA06: ESAI (LLM) ──────────────────────────────────

    private function generateIa06($dokumen, int $kpjId, int $userId, string $jabkerName, string $timestamp): int
    {
        $soals = KanSoal::where('id_dokumen', $dokumen->id)
            ->where('tipe', 8)
            ->orderBy('no', 'asc')
            ->get()->all();

        if (empty($soals)) {
            $this->warn("  IA06: tidak ada soal tipe 8.");
            return 0;
        }

        $userName = 'NURYONO';
        $noSkema = 'NURYONO-' . $kpjId;

        $chunkSize = 5;
        $soalChunks = array_chunk($soals, $chunkSize);
        $rows = [];

        foreach ($soalChunks as $chunkIdx => $chunk) {
            $fields = [];
            $fieldKeys = [];

            foreach ($chunk as $i => $soal) {
                // Alternate correct/wrong
                $isCorrect = (($chunkIdx * $chunkSize + $i) % 3) !== 0;
                $fieldType = $isCorrect ? 'kan_esai_benar' : 'kan_esai_salah';

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

            $this->info("  IA06 chunk " . ($chunkIdx + 1) . "/" . count($soalChunks) . ": " . count($fields) . " fields...");
            $llmResults = $this->llm->generateBatchTexts($fields);

            foreach ($fieldKeys as $fieldIdx => [$soalId, $isCorrect]) {
                $fieldType = $isCorrect ? 'kan_esai_benar' : 'kan_esai_salah';
                $resultKey = $fieldType . "_{$fieldIdx}";
                $jawaban = $llmResults[$resultKey] ?? $this->llm->getFallback($fieldType);

                $rows[] = [
                    'kan_praktisi_jabatan_id' => $kpjId,
                    'dokumen_id'              => $dokumen->id,
                    'soal_id'                 => $soalId,
                    'jawaban'                 => $jawaban,
                    'skor'                    => $isCorrect ? random_int(2, 3) : 0,
                    'updated_at'              => $timestamp,
                    'created_at'              => $timestamp,
                ];
            }
        }

        if (!empty($rows)) {
            KanIa6Answer::upsert(
                $rows,
                ['kan_praktisi_jabatan_id', 'dokumen_id', 'soal_id'],
                ['jawaban', 'skor', 'updated_at']
            );
        }

        return count($rows);
    }

    // ─── IA04B: LISAN (LLM) ─────────────────────────────────

    private function generateIa04b($dokumen, int $kpjId, int $userId, string $jabkerName, string $timestamp): int
    {
        $soals = KanSoal::where('id_dokumen', $dokumen->id)
            ->where('tipe', 8)
            ->orderBy('no', 'asc')
            ->get()->all();

        if (empty($soals)) {
            $this->warn("  IA04B: tidak ada soal tipe 8.");
            return 0;
        }

        $userName = 'NURYONO';
        $noSkema = 'NURYONO-' . $kpjId;

        $chunkSize = 3;
        $soalChunks = array_chunk($soals, $chunkSize);
        $rows = [];

        foreach ($soalChunks as $chunkIdx => $chunk) {
            $fields = [];
            $fieldKeys = [];

            foreach ($chunk as $i => $soal) {
                $soalText = implode("\n", array_filter([$soal->soal ?? '', $soal->soal1 ?? '', $soal->soal2 ?? '']));

                // Alternate correct/wrong
                $isCorrect = (($chunkIdx * $chunkSize + $i) % 3) !== 0;
                $fieldType = $isCorrect ? 'kan_lisan_benar' : 'kan_lisan_salah';

                $fieldIdx = count($fields);
                $fields[] = [$fieldType, [
                    'nama_asesi' => $userName,
                    'jabatan_kerja' => $jabkerName,
                    'nomor_skema' => $noSkema,
                    'asesor' => 'Asesor KAN',
                    'soal' => $soalText ?: ($soal->soal ?? ''),
                ]];
                $fieldKeys[$fieldIdx] = [$soal->id, $isCorrect];
            }

            if (empty($fields)) continue;

            $this->info("  IA04B chunk " . ($chunkIdx + 1) . "/" . count($soalChunks) . ": " . count($fields) . " fields...");
            $llmResults = $this->llm->generateBatchTexts($fields);

            foreach ($fieldKeys as $fieldIdx => [$soalId, $isCorrect]) {
                $fieldType = $isCorrect ? 'kan_lisan_benar' : 'kan_lisan_salah';
                $resultKey = $fieldType . "_{$fieldIdx}";
                $jawaban = $llmResults[$resultKey] ?? $this->llm->getFallback($fieldType);

                $rows[] = [
                    'kan_praktisi_jabatan_id' => $kpjId,
                    'dokumen_id'              => $dokumen->id,
                    'soal_id'                 => $soalId,
                    'jawaban'                 => $jawaban,
                    'skor'                    => $isCorrect ? random_int(2, 3) : 0,
                    'is_rekomendasi'          => false,
                    'updated_at'              => $timestamp,
                    'created_at'              => $timestamp,
                ];
            }
        }

        if (!empty($rows)) {
            KanIa4bAnswer::upsert(
                $rows,
                ['kan_praktisi_jabatan_id', 'dokumen_id', 'soal_id'],
                ['jawaban', 'skor', 'is_rekomendasi', 'updated_at']
            );
        }

        return count($rows);
    }
}
