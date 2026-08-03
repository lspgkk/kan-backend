<?php

namespace App\Jobs;

use App\Models\KanPraktisiJabatan;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class DownloadPraktisiJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 2;
    public $timeout = 300;

    private int $kpjId;
    private string $tempDir;
    private string $jabatanFolder;
    private string $praktisiFolder;
    private string $cacheKey;

    public function __construct(int $kpjId, string $tempDir, string $jabatanFolder, string $praktisiFolder, string $cacheKey)
    {
        $this->kpjId = $kpjId;
        $this->tempDir = $tempDir;
        $this->jabatanFolder = $jabatanFolder;
        $this->praktisiFolder = $praktisiFolder;
        $this->cacheKey = $cacheKey;
    }

    public function handle(): void
    {
        $kpj = KanPraktisiJabatan::with(['user:id,name', 'jabatanKerja'])->find($this->kpjId);
        if (!$kpj) {
            return;
        }

        $disk = 'str_moon';
        $publicBaseUrl = 'https://moon.lspgatensi.id/files/new_gatensi/';
        $docTypes = \App\Http\Controllers\KanAsesmenController::KAN_DOC_TYPES;

        $targetDir = $this->tempDir . '/' . $this->jabatanFolder . $this->praktisiFolder;
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0755, true);
        }

        $missingLabels = [];
        foreach ($docTypes as $doc) {
            if (empty($kpj->{$doc['url_col']})) {
                $missingLabels[] = $doc['label'];
            }
        }

        $generatedFiles = [];
        if (!empty($missingLabels)) {
            $controller = app(\App\Http\Controllers\KanAsesmenController::class);
            $generatedFiles = $controller->generatePdfFiles($this->kpjId, $missingLabels, true);
        }

        foreach ($docTypes as $doc) {
            $label = $doc['label'];
            $matched = array_values(array_filter($generatedFiles, fn($f) => $f['label'] === $label));
            if (!empty($matched)) {
                file_put_contents($targetDir . $matched[0]['filename'], $matched[0]['content']);
                continue;
            }

            $url = $kpj->{$doc['url_col']};
            if (empty($url)) {
                continue;
            }

            $path = str_replace($publicBaseUrl, '', $url);
            $filename = basename($path);

            try {
                $content = Storage::disk($disk)->get($path);
                if ($content) {
                    file_put_contents($targetDir . $filename, $content);
                }
            } catch (\Throwable $e) {
                // Skip gagal
            }
        }

        // Increment counter — progress endpoint baca ini
        Cache::increment("{$this->cacheKey}_done_count");
    }
}
