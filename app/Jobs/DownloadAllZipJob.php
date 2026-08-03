<?php

namespace App\Jobs;

use App\Models\KanPraktisiJabatan;
use Illuminate\Bus\Queueable;
use Illuminate\Bus\Batch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Bus;
use Throwable;
use ZipArchive;

class DownloadAllZipJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;
    public $timeout = 7200;

    private string $token;
    private string $cacheKey;

    public function __construct(string $token)
    {
        $this->token = $token;
        $this->cacheKey = "download_all_{$token}";
    }

    public function handle(): void
    {
        $records = KanPraktisiJabatan::with(['user:id,name', 'jabatanKerja'])
            ->orderBy('id_jabatan_kerja')
            ->get();

        $totalRecords = $records->count();
        $grouped = $records->groupBy('id_jabatan_kerja');

        $tempDir = storage_path('app/kan-zips/temp-' . $this->token);
        if (!is_dir($tempDir)) {
            @mkdir($tempDir, 0755, true);
        }

        $jobs = [];
        foreach ($grouped as $idJabatanKerja => $praktisiList) {
            $jabatanKerja = $praktisiList->first()->jabatanKerja;
            $namaJabatan = $jabatanKerja ? $jabatanKerja->jabatan_kerja : 'Unknown';
            $jabatanFolder = $idJabatanKerja . ' - ' . $namaJabatan;

            foreach ($praktisiList as $kpj) {
                $userName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $kpj->user?->name ?? 'praktisi-' . $kpj->id_user);
                $praktisiFolder = $userName . '-' . $kpj->id;

                $jobs[] = new DownloadPraktisiJob(
                    $kpj->id,
                    $tempDir,
                    $jabatanFolder,
                    $praktisiFolder,
                    $this->cacheKey
                );
            }
        }

        Cache::put($this->cacheKey, [
            'total'  => $totalRecords,
            'done'   => 0,
            'status' => 'processing',
        ], 7200);

        $batch = Bus::batch($jobs)
            ->then(function (Batch $batch) use ($tempDir, $totalRecords) {
                $this->buildZip($tempDir, $totalRecords);
                $this->cleanup($tempDir);
            })
            ->catch(function (Batch $batch, Throwable $e) {
                Cache::put($this->cacheKey, [
                    'status' => 'error',
                    'error'  => $e->getMessage(),
                ], 7200);
            })
            ->progress(function (Batch $batch) {
                $processed = $batch->processedJobs();
                $total = $batch->totalJobs;
                Cache::put($this->cacheKey, [
                    'total'  => $total,
                    'done'   => $processed,
                    'status' => 'processing',
                ], 7200);
            })
            ->dispatch();

        Cache::put($this->cacheKey, [
            'total'   => $totalRecords,
            'done'    => 0,
            'status'  => 'processing',
            'batchId' => $batch->id,
        ], 7200);
    }

    private function buildZip(string $tempDir, int $totalRecords): void
    {
        $zipDir = storage_path('app/kan-zips');
        if (!is_dir($zipDir)) {
            @mkdir($zipDir, 0755, true);
        }

        $zipName = 'dokumen-praktisi-all.zip';
        $zipPath = $zipDir . '/' . str_replace(['{', '}', '/'], '', $this->cacheKey) . '.zip';

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            Cache::put($this->cacheKey, ['status' => 'error', 'error' => 'Gagal membuat ZIP'], 7200);
            return;
        }

        $this->addFolderToZip($zip, $tempDir, '');
        $zip->close();

        Cache::put($this->cacheKey, [
            'total'    => $totalRecords,
            'done'     => $totalRecords,
            'status'   => 'done',
            'zip_path' => $zipPath,
        ], 7200);
    }

    private function addFolderToZip(ZipArchive $zip, string $folder, string $prefix): void
    {
        $files = scandir($folder);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $path = $folder . '/' . $file;
            $localPath = $prefix === '' ? $file : $prefix . '/' . $file;

            if (is_dir($path)) {
                $zip->addEmptyDir($localPath);
                $this->addFolderToZip($zip, $path, $localPath);
            } else {
                $zip->addFile($path, $localPath);
            }
        }
    }

    private function cleanup(string $tempDir): void
    {
        $this->deleteDirectory($tempDir);
    }

    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
