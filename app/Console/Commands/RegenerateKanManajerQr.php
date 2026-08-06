<?php

namespace App\Console\Commands;

use App\Models\Barcode;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class RegenerateKanManajerQr extends Command
{
    protected $signature = 'kan:regenerate-manajer-qr
                            {--dry-run : Simulasi saja, jangan simpan perubahan}';
    protected $description = 'Regenerate manajer barcodes for KAN records — uses kan_manajer_tgl as the manajer date, stores in Barcode with matching created_at';

    private const JENIS_DOKUMEN = 'MANAGER_MUTU_KAN';
    private const ROLE_MANAJER = 7; // Manajer sertifikasi LSP

    public function handle(): int
    {
        // Fetch all KAN records that have manajer_img from main DB but no kan_manajer_img
        // Uses kan_manajer_tgl (fallback: kan_penyusun_tgl → penyusun_tgl) as the manajer date
        $records = DB::table('jabatan_config_a')
            ->select([
                'id_jabatan',
                'kan_no_skema',
                'manajer_id',
                'manajer_img',
                'manajer_url',
                'kan_manajer_tgl',
                'kan_penyusun_tgl',
                'penyusun_tgl',
            ])
            ->whereNotNull('kan_no_skema')
            ->whereNotNull('manajer_img')
            ->where('manajer_img', '!=', '')
            ->where(function ($q) {
                $q->whereNull('kan_manajer_img')->orWhere('kan_manajer_img', '');
            })
            ->get();

        $total = $records->count();
        $this->info("Found {$total} KAN records with main-DB manajer barcodes to regenerate.");

        if ($total === 0) {
            $this->info('Nothing to do.');
            return 0;
        }

        $dryRun = $this->option('dry-run');
        if ($dryRun) {
            $this->warn('DRY RUN — no changes will be saved.');
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $stats = ['generated' => 0, 'skipped' => 0, 'errors' => 0];

        foreach ($records as $record) {
            $bar->advance();
            try {
                $result = $this->processRecord($record, $dryRun);
                $stats['generated'] += $result['generated'];
                $stats['skipped'] += $result['skipped'];
            } catch (\Throwable $e) {
                $stats['errors']++;
                $msg = "{$record->id_jabatan} ({$record->kan_no_skema}): " . $e->getMessage();
                $this->warn("\n  ⚠ {$msg}");
                logger()->error("[RegenerateKanManajerQr] {$msg}", ['trace' => $e->getTraceAsString()]);
            }
        }

        $bar->finish();
        $this->newLine(2);

        $this->info('═══ HASIL REGENERATE MANAJER QR ═══');
        $this->info("QR baru:  {$stats['generated']}");
        $this->info("Diskip:   {$stats['skipped']}");
        $this->info("Errors:   {$stats['errors']}");

        if ($dryRun) {
            $this->warn('DRY RUN — tidak ada perubahan yang disimpan.');
        }

        return 0;
    }

    private function processRecord($record, bool $dryRun): array
    {
        // Validate manajer exists
        if (empty($record->manajer_id)) {
            $this->warn("\n  ⚠ {$record->id_jabatan}: manajer_id kosong, skip");
            return ['generated' => 0, 'skipped' => 1];
        }

        // Check if user exists
        $userExists = DB::table('users')->where('id', $record->manajer_id)->exists();
        if (!$userExists) {
            $this->warn("\n  ⚠ {$record->id_jabatan}: user #{$record->manajer_id} not found, skip");
            return ['generated' => 0, 'skipped' => 1];
        }

        // Generate new QR code
        $uuid = (string) Uuid::uuid4();
        $urlTtd = "https://barcode.lspgatensi.id/ttd/{$uuid}";
        $filename = 'qr_manajer_kan_' . Str::uuid() . '.png';

        $qrImage = QrCode::format('png')->size(300)->margin(1)->generate($urlTtd);
        $path = "barcodes/{$filename}";

        if (!$dryRun) {
            Storage::disk('str_moon')->put($path, $qrImage);
        }

        $urlImage = "https://moon.lspgatensi.id/files/new_gatensi/{$path}";

        // Use kan_manajer_tgl as the manajer date, fallback to kan_penyusun_tgl → penyusun_tgl
        $tglManajer = $record->kan_manajer_tgl ?? $record->kan_penyusun_tgl ?? $record->penyusun_tgl;
        if (empty($tglManajer)) {
            $this->warn("\n  ⚠ {$record->id_jabatan}: kan_manajer_tgl, kan_penyusun_tgl & penyusun_tgl kosong, skip");
            return ['generated' => 0, 'skipped' => 1];
        }

        if (!$dryRun) {
            // Save to Barcode table
            Barcode::updateOrCreate(
                [
                    'id_user'       => (int) $record->manajer_id,
                    'id_izin'       => 'MANAGER_MUTU_KAN_' . $record->id_jabatan,
                    'jenis_dokumen' => self::JENIS_DOKUMEN,
                ],
                [
                    'url_image'  => $urlImage,
                    'url_ttd'    => $urlTtd,
                    'id_role'    => self::ROLE_MANAJER,
                    'created_at' => $tglManajer,
                    'updated_at' => $tglManajer,
                ]
            );

            // Update jabatan_config_a with new KAN manajer URLs
            DB::table('jabatan_config_a')
                ->where('id_jabatan', $record->id_jabatan)
                ->update([
                    'kan_manajer_id'  => (int) $record->manajer_id,
                    'kan_manajer_tgl' => $tglManajer,
                    'kan_manajer_img' => $urlImage,
                    'kan_manajer_url' => $urlTtd,
                ]);
        }

        return ['generated' => 1, 'skipped' => 0];
    }
}
