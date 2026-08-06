<?php

namespace App\Console\Commands;

use App\Models\Barcode;
use App\Models\JabatanConfigA;
use App\Models\KlasifikasiKualifikasi;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class FixPenyusunValidatorQr extends Command
{
    protected $signature = 'kan:fix-penyusun-validator-qr
                            {--dry-run : Simulasi, jangan simpan perubahan}
                            {--sync-dates-only : Hanya sinkronkan tanggal, jangan regenerate QR}
                            {--regenerate-qr : Hanya regenerate QR, jangan sinkronkan tanggal}
                            {--id-jabatan= : Hanya proses jabatan tertentu}
                            {--force : Regenerate QR meskipun URL sudah benar}';

    protected $description = 'Fix penyusun/validator barcodes:
    1) Sinkronkan barcodes.created_at dengan kan_penyusun_tgl / kan_validator_tgl
    2) Regenerate QR image dengan URL /penyusun-validator/ yang benar';

    private int $penyusunDatesSynced = 0;
    private int $validatorDatesSynced = 0;
    private int $penyusunQrRegenerated = 0;
    private int $validatorQrRegenerated = 0;
    private int $penyusunBarcodesUpdated = 0;
    private int $validatorBarcodesUpdated = 0;
    private int $skipped = 0;
    private int $errors = 0;

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $syncOnly = $this->option('sync-dates-only');
        $qrOnly = $this->option('regenerate-qr');
        $force = $this->option('force');
        $idJabatan = $this->option('id-jabatan');

        // Default: do both
        $doSync = !$qrOnly;
        $doRegen = !$syncOnly;

        if ($dryRun) {
            $this->warn('═══ DRY RUN — tidak ada perubahan yang disimpan ═══');
        }

        // ── Build query for JabatanConfigA ──
        $query = JabatanConfigA::select([
            'id_jabatan',
            'kan_penyusun_id', 'kan_penyusun_tgl', 'kan_penyusun_img', 'kan_penyusun_url',
            'kan_validator_id', 'kan_validator_tgl', 'kan_validator_img', 'kan_validator_url',
        ]);

        if ($idJabatan) {
            $query->where('id_jabatan', $idJabatan);
        }

        $configs = $query->get();

        $this->info('Found ' . $configs->count() . ' jabatan_config_a records.');
        $bar = $this->output->createProgressBar($configs->count());
        $bar->start();

        foreach ($configs as $config) {
            $bar->advance();

            // ── PART A: Sync dates ──
            if ($doSync) {
                $this->syncPenyusunDates($config, $dryRun);
                $this->syncValidatorDates($config, $dryRun);
            }

            // ── PART B: Regenerate QR ──
            if ($doRegen) {
                $this->regeneratePenyusunQr($config, $dryRun, $force);
                $this->regenerateValidatorQr($config, $dryRun, $force);
            }
        }

        $bar->finish();
        $this->newLine(2);

        // ── Summary ──
        $this->info('═══ SUMMARY ═══');

        if ($doSync) {
            $this->info("Penyusun dates synced:     {$this->penyusunDatesSynced}");
            $this->info("Validator dates synced:    {$this->validatorDatesSynced}");
        }

        if ($doRegen) {
            $this->info("Penyusun QR regenerated:   {$this->penyusunQrRegenerated}");
            $this->info("Validator QR regenerated:  {$this->validatorQrRegenerated}");
            $this->info("Penyusun barcodes updated: {$this->penyusunBarcodesUpdated}");
            $this->info("Validator barcodes updated:{$this->validatorBarcodesUpdated}");
        }

        $this->info("Skipped (no change):       {$this->skipped}");
        $this->info("Errors:                    {$this->errors}");

        if ($dryRun) {
            $this->warn('DRY RUN — tidak ada perubahan yang disimpan.');
        }

        return 0;
    }

    // ═══════════════════════════════════════════════
    //  PART A: DATE SYNC
    // ═══════════════════════════════════════════════

    /**
     * Sync barcodes.created_at with kan_penyusun_tgl for all asesi
     * under this jabatan that have a PENYUSUN or PENYUSUN_KAN barcode
     * by this penyusun.
     */
    private function syncPenyusunDates(JabatanConfigA $config, bool $dryRun): void
    {
        if (!$config->kan_penyusun_id || !$config->kan_penyusun_tgl) {
            return;
        }

        $expectedDate = $this->parseDate($config->kan_penyusun_tgl);
        if (!$expectedDate) return;

        // Find all asesi under this jabatan_kerja
        $idIzins = KlasifikasiKualifikasi::where('jabatan_kerja', $config->id_jabatan)
            ->pluck('id_izin');

        // Also include the synthetic id_izin used by KanConfigController
        $allIdIzins = $idIzins->merge(['PENYUSUN_KAN_' . $config->id_jabatan]);

        // Find all PENYUSUN & PENYUSUN_KAN barcode records for this penyusun
        $barcodes = Barcode::where('id_user', $config->kan_penyusun_id)
            ->whereIn('id_izin', $allIdIzins)
            ->whereIn('jenis_dokumen', ['PENYUSUN', 'PENYUSUN_KAN'])
            ->get();

        foreach ($barcodes as $barcode) {
            $currentDate = $barcode->created_at
                ? Carbon::parse($barcode->created_at)->format('Y-m-d')
                : null;

            if ($currentDate === $expectedDate) {
                continue;
            }

            $this->line("\n  <info>PENYUSUN date sync</info>: id_izin={$barcode->id_izin} jenis={$barcode->jenis_dokumen} {$currentDate} → {$expectedDate}");

            if (!$dryRun) {
                $barcode->update([
                    'created_at' => $expectedDate,
                    'updated_at' => $expectedDate,
                ]);
            }
            $this->penyusunDatesSynced++;
        }
    }

    /**
     * Sync barcodes.created_at with kan_validator_tgl for all asesi
     * under this jabatan that have a VALIDATOR or VALIDATOR_KAN barcode
     * by this validator.
     */
    private function syncValidatorDates(JabatanConfigA $config, bool $dryRun): void
    {
        if (!$config->kan_validator_id || !$config->kan_validator_tgl) {
            return;
        }

        $expectedDate = $this->parseDate($config->kan_validator_tgl);
        if (!$expectedDate) return;

        // Find all asesi under this jabatan_kerja
        $idIzins = KlasifikasiKualifikasi::where('jabatan_kerja', $config->id_jabatan)
            ->pluck('id_izin');

        // Also include the synthetic id_izin used by KanConfigController
        $allIdIzins = $idIzins->merge(['VALIDATOR_KAN_' . $config->id_jabatan]);

        // Find all VALIDATOR & VALIDATOR_KAN barcode records for this validator
        $barcodes = Barcode::where('id_user', $config->kan_validator_id)
            ->whereIn('id_izin', $allIdIzins)
            ->whereIn('jenis_dokumen', ['VALIDATOR', 'VALIDATOR_KAN'])
            ->get();

        foreach ($barcodes as $barcode) {
            $currentDate = $barcode->created_at
                ? Carbon::parse($barcode->created_at)->format('Y-m-d')
                : null;

            if ($currentDate === $expectedDate) {
                continue;
            }

            $this->line("\n  <info>VALIDATOR date sync</info>: id_izin={$barcode->id_izin} jenis={$barcode->jenis_dokumen} {$currentDate} → {$expectedDate}");

            if (!$dryRun) {
                $barcode->update([
                    'created_at' => $expectedDate,
                    'updated_at' => $expectedDate,
                ]);
            }
            $this->validatorDatesSynced++;
        }
    }

    // ═══════════════════════════════════════════════
    //  PART B: QR REGENERATION
    // ═══════════════════════════════════════════════

    /**
     * Regenerate QR for penyusun if URL still uses /ttd/ format,
     * or if the image file was generated by the old ttd() method
     * (filename starts with "ttd_penyusun_" — encodes /ttd/ URL).
     */
    private function regeneratePenyusunQr(JabatanConfigA $config, bool $dryRun, bool $force): void
    {
        if (!$config->kan_penyusun_id) {
            $this->skipped++;
            return;
        }

        $needsRegen = $force
            || !$config->kan_penyusun_img
            || !$config->kan_penyusun_url
            || $this->usesTtdUrl($config->kan_penyusun_url)
            || $this->hasOldTtdFilename($config->kan_penyusun_img);

        if (!$needsRegen) {
            return;
        }

        $this->line("\n  <comment>Regen PENYUSUN QR</comment>: id_jabatan={$config->id_jabatan}");

        try {
            // Generate new QR with correct /penyusun-validator/ URL
            $uuid = (string) Uuid::uuid4();
            $urlTtd = "https://barcode.lspgatensi.id/penyusun-validator/{$uuid}";
            $filename = 'qr_penyusun_' . Str::uuid() . '.png';

            $qrImage = QrCode::format('png')->size(300)->margin(1)->generate($urlTtd);
            $path = "barcodes/{$filename}";

            if (!$dryRun) {
                Storage::disk('str_moon')->put($path, $qrImage);
            }

            $urlImage = "https://moon.lspgatensi.id/files/new_gatensi/{$path}";

            // Update JabatanConfigA
            if (!$dryRun) {
                JabatanConfigA::where('id_jabatan', $config->id_jabatan)->update([
                    'kan_penyusun_url' => $urlTtd,
                    'kan_penyusun_img' => $urlImage,
                ]);
            }

            // Update ALL Barcode PENYUSUN records for this jabatan
            $this->updatePenyusunBarcodes($config, $urlTtd, $urlImage, $dryRun);

            $this->penyusunQrRegenerated++;
        } catch (\Throwable $e) {
            $this->error("\n  ERROR PENYUSUN QR id_jabatan={$config->id_jabatan}: " . $e->getMessage());
            $this->errors++;
        }
    }

    /**
     * Regenerate QR for validator if URL still uses /ttd/ format,
     * or if the image file was generated by the old ttd() method
     * (filename starts with "ttd_validator_" — encodes /ttd/ URL).
     */
    private function regenerateValidatorQr(JabatanConfigA $config, bool $dryRun, bool $force): void
    {
        if (!$config->kan_validator_id) {
            return;
        }

        $needsRegen = $force
            || !$config->kan_validator_img
            || !$config->kan_validator_url
            || $this->usesTtdUrl($config->kan_validator_url)
            || $this->hasOldTtdFilename($config->kan_validator_img);

        if (!$needsRegen) {
            return;
        }

        $this->line("\n  <comment>Regen VALIDATOR QR</comment>: id_jabatan={$config->id_jabatan}");

        try {
            // Generate new QR with correct /penyusun-validator/ URL
            $uuid = (string) Uuid::uuid4();
            $urlTtd = "https://barcode.lspgatensi.id/penyusun-validator/{$uuid}";
            $filename = 'qr_validator_' . Str::uuid() . '.png';

            $qrImage = QrCode::format('png')->size(300)->margin(1)->generate($urlTtd);
            $path = "barcodes/{$filename}";

            if (!$dryRun) {
                Storage::disk('str_moon')->put($path, $qrImage);
            }

            $urlImage = "https://moon.lspgatensi.id/files/new_gatensi/{$path}";

            // Update JabatanConfigA
            if (!$dryRun) {
                JabatanConfigA::where('id_jabatan', $config->id_jabatan)->update([
                    'kan_validator_url' => $urlTtd,
                    'kan_validator_img' => $urlImage,
                ]);
            }

            // Update ALL Barcode VALIDATOR records for this jabatan
            $this->updateValidatorBarcodes($config, $urlTtd, $urlImage, $dryRun);

            $this->validatorQrRegenerated++;
        } catch (\Throwable $e) {
            $this->error("\n  ERROR VALIDATOR QR id_jabatan={$config->id_jabatan}: " . $e->getMessage());
            $this->errors++;
        }
    }

    /**
     * Update all Barcode PENYUSUN/PENYUSUN_KAN records under this jabatan
     * with the new QR URL and image.
     */
    private function updatePenyusunBarcodes(
        JabatanConfigA $config,
        string $newUrlTtd,
        string $newUrlImage,
        bool $dryRun
    ): void {
        $idIzins = KlasifikasiKualifikasi::where('jabatan_kerja', $config->id_jabatan)
            ->pluck('id_izin');

        $allIdIzins = $idIzins->merge(['PENYUSUN_KAN_' . $config->id_jabatan]);

        if ($allIdIzins->isEmpty()) return;

        $barcodes = Barcode::where('id_user', $config->kan_penyusun_id)
            ->whereIn('id_izin', $allIdIzins)
            ->whereIn('jenis_dokumen', ['PENYUSUN', 'PENYUSUN_KAN'])
            ->get();

        foreach ($barcodes as $barcode) {
            if (!$dryRun) {
                $barcode->update([
                    'url_ttd'   => $newUrlTtd,
                    'url_image' => $newUrlImage,
                ]);
            }
            $this->penyusunBarcodesUpdated++;
        }
    }

    /**
     * Update all Barcode VALIDATOR/VALIDATOR_KAN records under this jabatan
     * with the new QR URL and image.
     */
    private function updateValidatorBarcodes(
        JabatanConfigA $config,
        string $newUrlTtd,
        string $newUrlImage,
        bool $dryRun
    ): void {
        $idIzins = KlasifikasiKualifikasi::where('jabatan_kerja', $config->id_jabatan)
            ->pluck('id_izin');

        $allIdIzins = $idIzins->merge(['VALIDATOR_KAN_' . $config->id_jabatan]);

        if ($allIdIzins->isEmpty()) return;

        $barcodes = Barcode::where('id_user', $config->kan_validator_id)
            ->whereIn('id_izin', $allIdIzins)
            ->whereIn('jenis_dokumen', ['VALIDATOR', 'VALIDATOR_KAN'])
            ->get();

        foreach ($barcodes as $barcode) {
            if (!$dryRun) {
                $barcode->update([
                    'url_ttd'   => $newUrlTtd,
                    'url_image' => $newUrlImage,
                ]);
            }
            $this->validatorBarcodesUpdated++;
        }
    }

    // ═══════════════════════════════════════════════
    //  HELPERS
    // ═══════════════════════════════════════════════

    /**
     * Check if a URL still uses the old /ttd/ format
     * (should be /penyusun-validator/).
     */
    private function usesTtdUrl(?string $url): bool
    {
        if (empty($url)) return false;
        // Check if URL contains /ttd/ but NOT /penyusun-validator/ (edge case)
        return str_contains($url, '/ttd/') && !str_contains($url, '/penyusun-validator/');
    }

    /**
     * Check if the image filename was generated by the old ttd() flow.
     * Old format: ttd_penyusun_<uuid>.png or ttd_validator_<uuid>.png
     * These QR images encode /ttd/{uuid} URL (wrong).
     *
     * New format from KanConfigController & this command:
     *   qr_penyusun_<uuid>.png or qr_validator_<uuid>.png
     * These encode /penyusun-validator/{uuid} URL (correct).
     */
    private function hasOldTtdFilename(?string $imgUrl): bool
    {
        if (empty($imgUrl)) return false;

        $filename = basename(parse_url($imgUrl, PHP_URL_PATH) ?: '');
        if (empty($filename)) return false;

        // Old files start with "ttd_penyusun_" or "ttd_validator_"
        return str_starts_with($filename, 'ttd_penyusun_')
            || str_starts_with($filename, 'ttd_validator_');
    }

    private function parseDate(mixed $value): ?string
    {
        if (empty($value)) return null;
        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}
