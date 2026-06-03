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
        logger()->debug("[JOB] START id_izin={$this->idIzin} batch={$this->batchId}");

        $start = microtime(true);

        try {
            $result = $service->processIdIzin(
                $this->idIzin,
                $this->noregAsesor1,
                $this->noregAsesor2,
                $this->tanggalUji,
                $this->jadwalId
            );

            $elapsed = round((microtime(true) - $start) * 1000);
            $urlCount = count($result['urls'] ?? []);
            logger()->info("[JOB] DONE id_izin={$this->idIzin} status={$result['status']} urls={$urlCount} elapsed_ms={$elapsed}");

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
        }
    }

    public function tags(): array
    {
        return ['generate-dokumen', "batch:{$this->batchId}"];
    }
}
