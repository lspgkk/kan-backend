<?php

namespace App\Jobs;

use App\Services\GenerateDokumenService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

class GenerateDokumenJob implements ShouldQueue
{
    use Queueable;

    public string $idIzin;
    public string $noregAsesor1;
    public string $noregAsesor2;
    public string $tanggalUji;
    public ?string $jadwalId;
    public string $batchId;
    public ?string $mode;

    public $timeout = 600;  // 10 min per job
    public $tries = 3;
    public $maxExceptions = 3;
    public $backoff = [10, 30, 60];   // retry after 10s, then 30s, then 60s

    public function __construct(
        string $idIzin,
        string $noregAsesor1,
        string $noregAsesor2,
        string $tanggalUji,
        string $batchId,
        ?string $jadwalId = null,
        ?string $mode = null
    ) {
        $this->idIzin = $idIzin;
        $this->noregAsesor1 = $noregAsesor1;
        $this->noregAsesor2 = $noregAsesor2;
        $this->tanggalUji = $tanggalUji;
        $this->batchId = $batchId;
        $this->jadwalId = $jadwalId;
        $this->mode = $mode;

        // Pregen mode is fast: only LLM + DB, no PDF
        if ($mode === 'pregen') {
            $this->timeout = 120;
        }
    }

    public function handle(GenerateDokumenService $service): void
    {
        // ── Default: full mode (pregen + pdf in one job, no synchronization between workers) ──
        // Explicit 'pregen' or 'pdf' still available for manual two-phase runs
        $this->runPhase($service, $this->mode ?? 'full');
    }

    /**
     * Run pregen (LLM + DB prep), then dispatch a pdf job for the same id_izin.
     * This prevents DeepSeek rate limiting — all pregen jobs finish before pdf jobs start.
     */
    private function runPregenThenPdf(GenerateDokumenService $service): void
    {
        $start = microtime(true);

        // Phase 1: Pregen (LLM batch call + DB population)
        $pregenResult = $this->runPhase($service, 'pregen');

        if ($pregenResult['status'] === 'pregen') {
            logger()->info("[JOB] PREGEN-DONE id_izin={$this->idIzin} — dispatching pdf phase");

            // Phase 2: Dispatch PDF job (no LLM calls — reads pre-populated data from DB)
            self::dispatch(
                idIzin: $this->idIzin,
                noregAsesor1: $this->noregAsesor1,
                noregAsesor2: $this->noregAsesor2,
                tanggalUji: $this->tanggalUji,
                batchId: $this->batchId,
                jadwalId: $this->jadwalId,
                mode: 'pdf'
            )->onQueue('generate-dokumen');

            // Store pregen result in cache for the pdf phase to merge
            Cache::put(
                "batch:{$this->batchId}:pregen:{$this->idIzin}",
                ['status' => 'pregen', 'id_izin' => $this->idIzin],
                3600
            );
        } else {
            // Pregen failed — store error result
            $elapsed = round((microtime(true) - $start) * 1000);
            logger()->warning("[JOB] PREGEN-FAILED id_izin={$this->idIzin} status={$pregenResult['status']} elapsed_ms={$elapsed}");

            Cache::put(
                "batch:{$this->batchId}:result:{$this->idIzin}",
                $pregenResult,
                86400
            );

            // Write JSON fallback
            $this->saveJsonFallback($pregenResult);
        }
    }

    /**
     * Run a single phase (pregen, pdf, or full) and store results.
     */
    private function runPhase(GenerateDokumenService $service, ?string $mode): array
    {
        // ── Duplicate job guard: per-batch lock to prevent queue-retry from running 2x concurrently ──
        $lockKey = "gen:lock:{$this->batchId}:{$this->idIzin}";
        $lock = Cache::lock($lockKey, 600); // 600s = max job duration

        if (!$lock->get()) {
            logger()->warning("[JOB] SKIP id_izin={$this->idIzin} batch={$this->batchId} — another instance is already running");
            return ['id_izin' => $this->idIzin, 'status' => 'skipped', 'error' => null, 'urls' => []];
        }

        try {
            // Ensure memory limit — worker default may be lower than 512M
            if (ini_get('memory_limit') !== false && ini_get('memory_limit') !== '-1') {
                @ini_set('memory_limit', '512M');
            }

            logger()->debug("[JOB] START id_izin={$this->idIzin} batch={$this->batchId} mode={$mode} memory=" . round(memory_get_usage(true) / 1024 / 1024, 1) . "MB");

            $start = microtime(true);

            $result = $service->processIdIzin(
                $this->idIzin,
                $this->noregAsesor1,
                $this->noregAsesor2,
                $this->tanggalUji,
                $this->jadwalId,
                $mode
            );

            $elapsed = round((microtime(true) - $start) * 1000);
            $urlCount = count($result['urls'] ?? []);
            logger()->info("[JOB] DONE id_izin={$this->idIzin} mode={$mode} status={$result['status']} urls={$urlCount} elapsed_ms={$elapsed} memory=" . round(memory_get_usage(true) / 1024 / 1024, 1) . "MB peak=" . round(memory_get_peak_usage(true) / 1024 / 1024, 1) . "MB");

            // Store final results (only for pdf/full/pregen-failure; not for successful pregen)
            if ($mode !== 'pregen' || $result['status'] !== 'pregen') {
                Cache::put(
                    "batch:{$this->batchId}:result:{$this->idIzin}",
                    $result,
                    86400
                );

                $this->saveJsonFallback($result);
            }

            return $result;
        } catch (\Throwable $e) {
            $elapsed = round((microtime(true) - $start) * 1000);
            logger()->error("[JOB] EXCEPTION id_izin={$this->idIzin} mode={$mode} elapsed_ms={$elapsed}: " . $e->getMessage());
            throw $e;
        } finally {
            $lock->release();
        }
    }

    /**
     * Write result as JSON file fallback (belt and suspenders).
     */
    private function saveJsonFallback(array $result): void
    {
        $dir = storage_path("app/batches/{$this->batchId}");
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $filePath = "{$dir}/{$this->idIzin}.json";
        file_put_contents($filePath, json_encode($result));
    }

    public function tags(): array
    {
        return ['generate-dokumen', "batch:{$this->batchId}"];
    }
}
