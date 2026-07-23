<?php

namespace App\Console\Commands;

use App\Jobs\GenerateJawabanKanJob;
use Illuminate\Console\Command;

class DispatchJawabanKan extends Command
{
    protected $signature = 'kan:dispatch-jawaban
                            {file : Path ke JSON file}
                            {--force : Dispatch ulang meski sudah ada data}';
    protected $description = 'Dispatch GenerateJawabanKanJob ke queue untuk setiap record di JSON';

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
        $dispatched = 0;
        $skipped = 0;
        $force = $this->option('force');

        $this->info("Total records dalam JSON: {$total}");
        $this->info("Mode: " . ($force ? "FORCE (dispatch semua)" : "SKIP jika sudah lengkap"));
        $this->newLine();

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        foreach ($records as $idx => $record) {
            $bar->advance();

            $skemaName = trim($record['skema'] ?? '');
            $noSkema = $record['no_skema'] ?? "record-{$idx}";

            if (empty($skemaName)) {
                $this->warn("\n  ⚠ Record #{$idx}: skema kosong, skip.");
                $skipped++;
                continue;
            }

            if (!$force) {
                // Quick check: does this record already have data?
                // We check via jabatan_kerja lookup
                $jabatan = \App\Models\JabatanKerja::where('jabatan_kerja', $skemaName)
                    ->orderBy('id_jabatan_kerja', 'desc')
                    ->first();

                if ($jabatan) {
                    $dok = \App\Models\JabatanDokumen::where('id_jabatan', $jabatan->id_jabatan_kerja)
                        ->where('kode', 'FR.IA.06')
                        ->where('is_active', true)
                        ->first();

                    if ($dok) {
                        $scores = $record['scores'] ?? [];
                        $esaiCount = isset($scores['Esai']) ? count($scores['Esai']) : 0;
                        $asesorCount = 10; // A1-A10
                        $expectedCount = $esaiCount * $asesorCount;

                        $existing = \App\Models\KanIa6Answer::where('dokumen_id', $dok->id)->count();
                        if ($existing >= $expectedCount && $expectedCount > 0) {
                            $skipped++;
                            continue;
                        }
                    }
                }
            }

            GenerateJawabanKanJob::dispatch($record);
            $dispatched++;
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("═══ DISPATCH RESULT ═══");
        $this->info("Total records:   {$total}");
        $this->info("Dispatched:      {$dispatched}");
        $this->info("Skipped:         {$skipped}");
        $this->newLine();
        $this->info("Jalankan worker: php artisan queue:work --timeout=600 --max-processes=3");

        return 0;
    }
}
