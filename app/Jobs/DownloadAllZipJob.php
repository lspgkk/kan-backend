<?php

namespace App\Jobs;

use App\Models\KanPraktisiJabatan;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
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

        // Dispatch semua DownloadPraktisiJob sekaligus
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

        // Dispatch semua job tanpa batch — biar parallel
        foreach ($jobs as $job) {
            dispatch($job->onQueue('download-all'));
        }

        // Dispatch job untuk tunggu semua selesai & build ZIP
        dispatch((new BuildZipAfterDownloadJob($this->token, $tempDir, count($jobs)))->onQueue('download-all'));
    }
}
