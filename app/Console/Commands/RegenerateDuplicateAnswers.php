<?php

namespace App\Console\Commands;

use App\Models\KanIa4bAnswer;
use App\Models\KanIa6Answer;
use App\Models\KanPraktisiJabatan;
use App\Services\LlmAutoFillService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RegenerateDuplicateAnswers extends Command
{
    protected $signature = 'kan:regenerate-dupes
                            {--dokumen= : Filter by dokumen_id}
                            {--dry-run : Just count, don\'t regenerate}
                            {--chunk-size=10 : How many answers per LLM batch call}';

    protected $description = 'Find and regenerate duplicate LLM-generated answers (IA04B & IA06)';

    private LlmAutoFillService $llm;
    private int $regenerated = 0;
    private int $errors = 0;

    public function __construct()
    {
        parent::__construct();
        $this->llm = app(LlmAutoFillService::class);
    }

    public function handle(): int
    {
        $dokFilter = $this->option('dokumen');
        $isDryRun = $this->option('dry-run');

        $this->info('=== REGENERATE DUPLICATE LLM ANSWERS ===');
        $this->info('Mode: ' . ($isDryRun ? 'DRY RUN' : 'REGENERATE'));
        if ($dokFilter) $this->info("Filter dokumen_id: {$dokFilter}");

        foreach (['ia06' => ['table' => 'kan_ia6_answers', 'label' => 'IA06 (Esai)', 'benar' => 'kan_esai_benar', 'salah' => 'kan_esai_salah'],
                   'ia04b' => ['table' => 'kan_ia4b_answers', 'label' => 'IA04B (Lisan)', 'benar' => 'kan_lisan_benar', 'salah' => 'kan_lisan_salah']] as $type => $config) {

            $this->newLine();
            $this->info("── {$config['label']} ──");

            $dupes = $this->findDuplicates($config['table'], $dokFilter);
            if (empty($dupes)) {
                $this->info("  ✅ No duplicates.");
                continue;
            }

            $this->info("  Found " . count($dupes) . " duplicate groups.");

            $toRegen = [];
            foreach ($dupes as $d) {
                $answers = DB::table($config['table'])
                    ->where('dokumen_id', $d->dokumen_id)
                    ->where('soal_id', $d->soal_id)
                    ->where('jawaban', $d->jawaban)
                    ->orderBy('id')
                    ->get();

                // Keep the first one, regenerate the rest
                $keep = $answers->shift();
                foreach ($answers as $ans) {
                    $toRegen[] = [
                        'id' => $ans->id,
                        'kpj_id' => $ans->kan_praktisi_jabatan_id,
                        'dokumen_id' => $ans->dokumen_id,
                        'soal_id' => $ans->soal_id,
                        'skor' => (int) $ans->skor,
                        'old_jawaban' => $ans->jawaban,
                    ];
                }
            }

            $this->info("  Regenerating " . count($toRegen) . " answers...");

            if ($isDryRun) {
                $this->info("  DRY RUN — no changes made.");
                continue;
            }

            $this->regenerateAnswers($toRegen, $config, $type);
        }

        $this->newLine();
        $this->info('═══ DONE ═══');
        $this->info("Regenerated: {$this->regenerated}");
        $this->info("Errors: {$this->errors}");

        return 0;
    }

    private function findDuplicates(string $table, ?string $dokFilter): array
    {
        // Use GROUP BY + HAVING instead of self-join (much faster)
        $query = DB::table($table)
            ->where('jawaban', '!=', '')
            ->whereNotNull('jawaban')
            ->select('dokumen_id', 'soal_id', 'jawaban', DB::raw('COUNT(*) as dup_count'))
            ->groupBy('dokumen_id', 'soal_id', 'jawaban')
            ->having('dup_count', '>', 1)
            ->orderBy('dokumen_id');

        if ($dokFilter) {
            $query->where('dokumen_id', (int) $dokFilter);
        }

        return $query->get()->all();
    }

    private function regenerateAnswers(array $items, array $config, string $type): void
    {
        // Group by dokumen_id for efficient KPJ name lookups
        $byDokumen = [];
        foreach ($items as $item) {
            $byDokumen[$item['dokumen_id']][] = $item;
        }

        $totalItems = count($items);
        $processed = 0;
        $bar = $this->output->createProgressBar($totalItems);
        $bar->start();

        foreach ($byDokumen as $dokId => $dokItems) {
            // Get jabatan name for this dokumen
            $jd = DB::table('jabatan_dokumens')->where('id', $dokId)->first();
            $jabkerName = 'Unknown';
            if ($jd) {
                $jabkerName = DB::table('jabatan_kerjas')
                    ->where('id_jabatan_kerja', $jd->id_jabatan)
                    ->value('jabatan_kerja') ?? 'Unknown';
            }

            // Get soal texts (cached per dokumen)
            $soalIds = array_unique(array_column($dokItems, 'soal_id'));
            $soals = DB::table('kan_soals')->whereIn('id', $soalIds)->get()->keyBy('id');

            // Get KPJ user names
            $kpjIds = array_unique(array_column($dokItems, 'kpj_id'));
            $kpjs = DB::table('kan_praktisi_jabatan as kpj')
                ->join('users as u', 'kpj.id_user', '=', 'u.id')
                ->whereIn('kpj.id', $kpjIds)
                ->select('kpj.id', 'u.name')
                ->get()
                ->keyBy('id');

            // Process in chunks
            $chunkSize = (int) $this->option('chunk-size');
            $chunks = array_chunk($dokItems, $chunkSize);

            foreach ($chunks as $chunkIdx => $chunk) {
                $fields = [];
                $fieldKeys = [];

                foreach ($chunk as $i => $item) {
                    $isCorrect = $item['skor'] > 0;
                    $fieldType = $isCorrect ? $config['benar'] : $config['salah'];

                    $kpj = $kpjs->get($item['kpj_id']);
                    $userName = $kpj ? $kpj->name : 'Praktisi';
                    $soal = $soals->get($item['soal_id']);
                    $soalText = $soal ? ($soal->soal ?? '') : '';

                    $fields[] = [$fieldType, [
                        'nama_asesi' => $userName,
                        'jabatan_kerja' => $jabkerName,
                        'nomor_skema' => 'REGEN-' . $item['kpj_id'],
                        'asesor' => 'Asesor KAN',
                        'soal' => $soalText,
                    ]];
                    $fieldKeys[] = $item['id'];
                }

                if (empty($fields)) continue;

                $this->line("\n  Chunk " . ($chunkIdx + 1) . "/" . count($chunks) . " (" . count($fields) . " items)...");

                try {
                    $llmResults = $this->llm->generateBatchTexts($fields);

                    $updateModel = $type === 'ia04b' ? KanIa4bAnswer::class : KanIa6Answer::class;
                    $now = now()->toDateTimeString();

                    foreach ($fieldKeys as $fieldIdx => $answerId) {
                        $fieldType = $fields[$fieldIdx][0];
                        $resultKey = $fieldType . '_' . $fieldIdx;
                        $newJawaban = $llmResults[$resultKey] ?? null;

                        if ($newJawaban && strlen(trim($newJawaban)) >= 3) {
                            $updateData = ['jawaban' => trim($newJawaban), 'updated_at' => $now];
                            $updateModel::where('id', $answerId)->update($updateData);
                            $this->regenerated++;
                        } else {
                            $this->warn("\n  Failed to generate for answer_id={$answerId}");
                            $this->errors++;
                        }
                    }
                } catch (\Throwable $e) {
                    $this->error("\n  Chunk error: " . $e->getMessage());
                    $this->errors += count($fields);
                }

                $bar->advance(count($chunk));
                // Small delay between chunks to avoid rate limits
                if ($chunkIdx < count($chunks) - 1) {
                    usleep(500000); // 500ms
                }
            }
        }

        $bar->finish();
        $this->newLine();
    }
}
