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
        ?string $jadwalId = null
    ) {
        $this->idIzin = $idIzin;
        $this->noregAsesor1 = $noregAsesor1;
        $this->noregAsesor2 = $noregAsesor2;
        $this->tanggalUji = $tanggalUji;
        $this->batchId = $batchId;
        $this->jadwalId = $jadwalId;
    }

    public function handle(GenerateDokumenService $service): void
    {
        // ── Duplicate job guard: per-batch lock to prevent queue-retry from running 2x concurrently ──
        $lockKey = "gen:lock:{$this->batchId}:{$this->idIzin}";
        $lock = Cache::lock($lockKey, 600); // 600s = max job duration

        if (!$lock->get()) {
            logger()->warning("[JOB] SKIP id_izin={$this->idIzin} batch={$this->batchId} — another instance is already running");
            return;
        }

        try {
            // Ensure memory limit — worker default may be lower than 512M
            if (ini_get('memory_limit') !== false && ini_get('memory_limit') !== '-1') {
                @ini_set('memory_limit', '512M');
            }

            logger()->debug("[JOB] START id_izin={$this->idIzin} batch={$this->batchId} memory=" . round(memory_get_usage(true) / 1024 / 1024, 1) . "MB");

            $start = microtime(true);

            $result = $service->processIdIzin(
                $this->idIzin,
                $this->noregAsesor1,
                $this->noregAsesor2,
                $this->tanggalUji,
                $this->jadwalId
            );

            $elapsed = round((microtime(true) - $start) * 1000);
            $urlCount = count($result['urls'] ?? []);
            logger()->info("[JOB] DONE id_izin={$this->idIzin} status={$result['status']} urls={$urlCount} elapsed_ms={$elapsed} memory=" . round(memory_get_usage(true) / 1024 / 1024, 1) . "MB peak=" . round(memory_get_peak_usage(true) / 1024 / 1024, 1) . "MB");

            // Store in Cache (database driver)
            Cache::put(
                "batch:{$this->batchId}:result:{$this->idIzin}",
                $result,
                86400
            );

            // Also store as JSON file (belt and suspenders)
            $dir = storage_path("app/batches/{$this->batchId}");
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            $filePath = "{$dir}/{$this->idIzin}.json";
            file_put_contents($filePath, json_encode($result));
        } catch (\Throwable $e) {
            $elapsed = round((microtime(true) - $start) * 1000);
            logger()->error("[JOB] EXCEPTION id_izin={$this->idIzin} elapsed_ms={$elapsed}: " . $e->getMessage());
            throw $e;
        } finally {
            $lock->release();
        }
    }

    public function tags(): array
    {
        return ['generate-dokumen', "batch:{$this->batchId}"];
    }
}
