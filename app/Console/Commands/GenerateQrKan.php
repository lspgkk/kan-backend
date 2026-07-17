<?php

namespace App\Console\Commands;

use App\Models\Barcode;
use App\Models\KanPraktisiJabatan;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class GenerateQrKan extends Command
{
    protected $signature = 'kan:generate-qr
                            {file : Path ke JSON file (sama seperti kan:generate-jawaban)}
                            {--force : Re-generate QR meskipun sudah ada}';
    protected $description = 'Generate QR codes for KAN documents (IA04B, IA05, IA06) — run AFTER kan:generate-jawaban selesai';

    private const PRAKTISI_KEYS = ['A1', 'A2', 'A3', 'A4', 'A5', 'A6', 'A7', 'A8', 'A9', 'A10'];
    private const ROLE_PRAKTISI = 16;
    private const DOKUMEN_TYPES = ['IA04B', 'IA05', 'IA06'];

    private array $jabatanKerjaCache = [];
    private array $userCache = [];
    private array $praktisiJabatanCache = [];

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
        $this->info("Total records: {$total}");

        $this->info('Pre-loading database mappings...');
        $this->loadJabatanKerjaCache($records);
        $this->info('Jabatan kerja mappings loaded.');

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $stats = ['qr' => 0, 'skipped' => 0, 'updated' => 0, 'errors' => []];

        foreach ($records as $idx => $record) {
            $bar->advance();
            try {
                $result = $this->processQrRecord($record);
                $stats['qr'] += $result['qr'];
                $stats['skipped'] += $result['skipped'];
                $stats['updated'] += $result['updated'] ?? 0;
            } catch (\Throwable $e) {
                $msg = "Record #{$idx} ({$record['no_skema']}): " . $e->getMessage();
                $stats['errors'][] = $msg;
                $this->warn("\n  ⚠ {$msg}");
                logger()->error("[GenerateQrKan] {$msg}", ['trace' => $e->getTraceAsString()]);
            }
        }

        $bar->finish();
        $this->newLine(2);

        $this->info('═══ HASIL GENERATE QR ═══');
        $this->info("QR dibuat:   {$stats['qr']}");
        $this->info("QR diskip:   {$stats['skipped']}");
        $this->info("Tgl diupdate: {$stats['updated']}");
        $this->info("Errors:      " . count($stats['errors']));

        return 0;
    }

    private function processQrRecord(array $record): array
    {
        $skemaName = trim($record['skema'] ?? '');
        $idJabatanKerja = $this->jabatanKerjaCache[strtolower($skemaName)] ?? null;

        if (!$idJabatanKerja) {
            return ['qr' => 0, 'skipped' => 0];
        }

        $asesorNames = $record['asesor_names'] ?? [];
        $tanggalUji = $record['tanggal_uji_coba_asesmen'] ?? null;

        if (!$tanggalUji) {
            return ['qr' => 0, 'skipped' => 0];
        }

        // Resolve praktisi users & kan_praktisi_jabatan
        $praktisiJabatanIds = [];
        $praktisiUsers = [];
        foreach (self::PRAKTISI_KEYS as $key) {
            $name = trim($asesorNames[$key] ?? '');
            if (empty($name)) continue;
            $userId = $this->resolvePraktisiUser($name);
            $praktisiUsers[$key] = $userId;
            $praktisiJabatanIds[$key] = $this->findOrCreatePraktisiJabatan($idJabatanKerja, $userId);
        }

        $qrRows = [];
        $skipped = 0;
        $updated = 0;
        $force = $this->option('force');

        try {
            $expectedCreatedAt = Carbon::parse($tanggalUji);
        } catch (\Throwable) {
            $expectedCreatedAt = now();
        }

        foreach ($praktisiJabatanIds as $key => $kpjId) {
            $userId = $praktisiUsers[$key];
            foreach (self::DOKUMEN_TYPES as $docType) {
                // Cek existing record
                $existing = Barcode::where('id_user', $userId)
                    ->where('kan_praktisi_jabatan_id', $kpjId)
                    ->where('jenis_dokumen', $docType)
                    ->whereNotNull('url_image')
                    ->whereNotNull('url_ttd')
                    ->first();

                if ($existing) {
                    if ($force) {
                        // Re-generate QR sepenuhnya (url & image baru)
                        $qrRows[] = $this->buildQrRow($userId, $kpjId, $docType, $tanggalUji);
                    } else {
                        // Update created_at & updated_at jika berbeda dari tanggal_uji_coba_asesmen
                        // JANGAN regenerate url_image & url_ttd
                        $currentCreatedDate = Carbon::parse($existing->created_at)->format('Y-m-d');
                        $currentUpdatedDate = Carbon::parse($existing->updated_at)->format('Y-m-d');
                        $expectedDate = $expectedCreatedAt->format('Y-m-d');
                        if ($currentCreatedDate !== $expectedDate || $currentUpdatedDate !== $expectedDate) {
                            $existing->timestamps = false;
                            $existing->created_at = $expectedCreatedAt;
                            $existing->updated_at = $expectedCreatedAt;
                            $existing->save();
                            $updated++;
                        } else {
                            $skipped++;
                        }
                    }
                } else {
                    $qrRows[] = $this->buildQrRow($userId, $kpjId, $docType, $tanggalUji);
                }
            }
        }

        if (!empty($qrRows)) {
            Barcode::upsert(
                $qrRows,
                ['id_user', 'kan_praktisi_jabatan_id', 'jenis_dokumen'],
                ['url_image', 'url_ttd', 'id_role', 'created_at', 'updated_at']
            );
        }

        return ['qr' => count($qrRows), 'skipped' => $skipped, 'updated' => $updated];
    }

    private function buildQrRow(int $userId, int $kpjId, string $docType, string $tanggalUji): array
    {
        $uuid = (string) Uuid::uuid4();
        $urlTtd = "https://barcode.lspgatensi.id/ttd/{$uuid}";

        $filename = 'qr_praktisi_' . Str::uuid() . '.png';
        $qrImage = QrCode::format('png')->size(300)->margin(1)->generate($urlTtd);
        $path = "barcodes/{$filename}";
        Storage::disk('str_moon')->put($path, $qrImage);
        $urlImage = "https://moon.lspgatensi.id/files/new_gatensi/{$path}";

        try {
            $createdAt = Carbon::parse($tanggalUji);
        } catch (\Throwable) {
            $createdAt = now();
        }

        return [
            'id_user'                 => $userId,
            'kan_praktisi_jabatan_id' => $kpjId,
            'jenis_dokumen'           => $docType,
            'url_image'               => $urlImage,
            'url_ttd'                 => $urlTtd,
            'id_role'                 => self::ROLE_PRAKTISI,
            'created_at'              => $createdAt,
            'updated_at'              => now(),
        ];
    }

    // ────────────── Shared helpers (duplicated from GenerateJawabanKan) ──────────────

    private function loadJabatanKerjaCache(array $records): void
    {
        $allSkema = array_unique(array_filter(array_map('trim', array_column($records, 'skema'))));
        $chunks = array_chunk($allSkema, 500);
        foreach ($chunks as $chunk) {
            $lowerNames = array_map('strtolower', $chunk);
            $rows = \App\Models\JabatanKerja::select('id_jabatan_kerja', 'jabatan_kerja')
                ->whereRaw('LOWER(jabatan_kerja) IN (' . implode(',', array_fill(0, count($lowerNames), '?')) . ')', $lowerNames)
                ->orderBy('id_jabatan_kerja', 'desc')
                ->get();
            foreach ($rows as $row) {
                $key = strtolower($row->jabatan_kerja);
                if (!isset($this->jabatanKerjaCache[$key])) {
                    $this->jabatanKerjaCache[$key] = $row->id_jabatan_kerja;
                }
            }
        }
    }

    private function resolvePraktisiUser(string $name): int
    {
        if (isset($this->userCache[$name])) return $this->userCache[$name];
        $user = \App\Models\User::where('name', $name)->where('role_id', self::ROLE_PRAKTISI)->first();
        if (!$user) {
            $email = $this->generateEmailFromName($name);
            $user = \App\Models\User::create([
                'name' => $name, 'email' => $email, 'phone' => '0',
                'role_id' => self::ROLE_PRAKTISI, 'password' => \Illuminate\Support\Facades\Hash::make('123qweasd'),
            ]);
        }
        $this->userCache[$name] = $user->id;
        return $user->id;
    }

    private function findOrCreatePraktisiJabatan(string $idJabatanKerja, int $userId): int
    {
        $cacheKey = "{$idJabatanKerja}:{$userId}";
        if (isset($this->praktisiJabatanCache[$cacheKey])) return $this->praktisiJabatanCache[$cacheKey];
        $record = KanPraktisiJabatan::firstOrCreate(
            ['id_jabatan_kerja' => $idJabatanKerja, 'id_user' => $userId]
        );
        $this->praktisiJabatanCache[$cacheKey] = $record->id;
        return $record->id;
    }

    private function generateEmailFromName(string $name): string
    {
        $sanitized = preg_replace('/[^a-z0-9]/', '', strtolower($name));
        $base = $sanitized . '@lspgatensi.id';
        if (!\App\Models\User::where('email', $base)->exists()) return $base;
        $suffix = 1;
        while (\App\Models\User::where('email', $sanitized . '+' . $suffix . '@lspgatensi.id')->exists()) $suffix++;
        return $sanitized . '+' . $suffix . '@lspgatensi.id';
    }
}
