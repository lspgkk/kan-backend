<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use ZipArchive;

class BuildZipAfterDownloadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 60; // Retry 60x = 30 menit (delay 30 detik)
    public $timeout = 300;

    private string $token;
    private string $tempDir;
    private int $totalJobs;

    public function __construct(string $token, string $tempDir, int $totalJobs)
    {
        $this->token = $token;
        $this->tempDir = $tempDir;
        $this->totalJobs = $totalJobs;
    }

    public function handle(): void
    {
        $cacheKey = "download_all_{$this->token}";

        // Cek berapa banyak yang udah selesai
        $done = Cache::get("{$cacheKey}_done_count", 0);

        if ($done < $this->totalJobs) {
            // Belum selesai semua, retry setelah 30 detik
            $this->release(30);
            return;
        }

        // Semua selesai, build ZIP
        $zipDir = storage_path('app/kan-zips');
        if (!is_dir($zipDir)) {
            @mkdir($zipDir, 0755, true);
        }

        $zipPath = $zipDir . '/' . str_replace(['{', '}', '/'], '', $cacheKey) . '.zip';

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            Cache::put($cacheKey, ['status' => 'error', 'error' => 'Gagal membuat ZIP'], 7200);
            return;
        }

        $this->addFolderToZip($zip, $this->tempDir, '');
        $zip->close();

        // Cleanup temp
        $this->deleteDirectory($this->tempDir);

        // Update cache
        $total = Cache::get($cacheKey)['total'] ?? 0;
        Cache::put($cacheKey, [
            'total'    => $total,
            'done'     => $total,
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
