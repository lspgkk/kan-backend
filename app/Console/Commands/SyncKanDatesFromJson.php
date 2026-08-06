<?php

namespace App\Console\Commands;

use App\Models\KanPraktisiJabatan;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncKanDatesFromJson extends Command
{
    protected $signature = 'kan:sync-dates-from-json
                            {file? : Path to JSON file (default: database/data/kan_date_sync_161.json)}
                            {--dry-run : Simulasi saja, jangan simpan perubahan}
                            {--check-only : Read-only check praktisi barcode dates (tidak melakukan update)}
                            {--part1 : Jalankan hanya Part 1 (update jabatan_config_a)}
                            {--part2 : Jalankan hanya Part 2 (check praktisi barcode dates)}';

    protected $description = 'Sync kan_penyusun_tgl, kan_validator_tgl, and kan_manajer_tgl from JSON mapping, and check praktisi barcode dates';

    private array $skippedNoMatch = [];
    private array $skippedNoConfig = [];
    private int $penyusunUpdated = 0;
    private int $penyusunPreserved = 0;
    private int $validatorUpdated = 0;
    private int $validatorPreserved = 0;
    private int $manajerCascaded = 0;

    public function handle(): int
    {
        $filePath = $this->argument('file')
            ?? database_path('data/kan_date_sync_161.json');

        if (!file_exists($filePath)) {
            $this->error("JSON file not found: {$filePath}");
            return 1;
        }

        $json = json_decode(file_get_contents($filePath), true);
        if (!$json || !isset($json['data'])) {
            $this->error('Invalid JSON format — expected {data: [...]}');
            return 1;
        }

        $entries = $json['data'];
        $total = count($entries);
        $this->info("Loaded {$total} entries from JSON.");

        $runPart1 = !$this->option('part2');
        $runPart2 = !$this->option('part1');
        $dryRun = $this->option('dry-run');
        $checkOnly = $this->option('check-only');

        // ── Resolve skema → id_jabatan_kerja mapping ──
        $skemaMap = $this->resolveSkemaMapping($entries);

        if ($runPart1) {
            if ($dryRun || $checkOnly) {
                $this->warn('DRY RUN / CHECK ONLY — tidak ada perubahan yang disimpan.');
            }
            $this->part1UpdateDates($entries, $skemaMap, $dryRun || $checkOnly);
        }

        if ($runPart2) {
            $this->part2CheckPraktisiBarcodes($entries, $skemaMap);
        }

        return 0;
    }

    // ═══════════════════════════════════════════════
    //  PART 1: Update jabatan_config_a dates
    // ═══════════════════════════════════════════════

    private function part1UpdateDates(array $entries, array $skemaMap, bool $dryRun): void
    {
        $this->newLine();
        $this->info('═══ PART 1: Update jabatan_config_a dates ═══');

        $bar = $this->output->createProgressBar(count($entries));
        $bar->start();

        foreach ($entries as $entry) {
            $bar->advance();

            $skemaName = trim($entry['skema']);
            $lowerName = mb_strtolower($skemaName);

            $idJabatanKerja = $skemaMap[$lowerName] ?? null;
            if (!$idJabatanKerja) {
                $this->skippedNoMatch[] = $skemaName;
                continue;
            }

            $existing = DB::table('jabatan_config_a')
                ->select(['kan_penyusun_tgl', 'kan_penyusun_img', 'kan_validator_tgl', 'kan_validator_img', 'kan_manajer_tgl'])
                ->where('id_jabatan', $idJabatanKerja)
                ->first();

            if (!$existing) {
                $this->skippedNoConfig[] = "{$skemaName} (id_jabatan={$idJabatanKerja})";
                continue;
            }

            $newPenyusunTgl = $this->parseDate($entry['tgl_penyusun'] ?? null);
            $newValidatorTgl = $this->parseDate($entry['tgl_validator'] ?? null);

            // TTD img guard — preserve existing if already signed
            if (!empty($existing->kan_penyusun_img)) {
                $newPenyusunTgl = $existing->kan_penyusun_tgl;
            }
            if (!empty($existing->kan_validator_img)) {
                $newValidatorTgl = $existing->kan_validator_tgl;
            }

            $updateData = [];
            $changed = [];

            if ($newPenyusunTgl !== null && $existing->kan_penyusun_tgl !== $newPenyusunTgl) {
                $updateData['kan_penyusun_tgl'] = $newPenyusunTgl;
                if (!empty($existing->kan_penyusun_img)) {
                    $changed[] = 'penyusun(preserved)';
                    $this->penyusunPreserved++;
                } else {
                    $changed[] = 'penyusun';
                    $this->penyusunUpdated++;
                }
            }

            if ($newValidatorTgl !== null && $existing->kan_validator_tgl !== $newValidatorTgl) {
                $updateData['kan_validator_tgl'] = $newValidatorTgl;
                if (!empty($existing->kan_validator_img)) {
                    $changed[] = 'validator(preserved)';
                    $this->validatorPreserved++;
                } else {
                    $changed[] = 'validator';
                    $this->validatorUpdated++;
                    // Cascade kan_manajer_tgl to match new validator_tgl
                    $updateData['kan_manajer_tgl'] = $newValidatorTgl;
                    $this->manajerCascaded++;
                }
            }

            if (!empty($updateData)) {
                if (!$dryRun) {
                    DB::table('jabatan_config_a')
                        ->where('id_jabatan', $idJabatanKerja)
                        ->update($updateData);
                }

                if ($this->output->isVerbose()) {
                    $this->line("\n  <info>{$skemaName}</info>: " . implode(', ', $changed));
                }
            }
        }

        $bar->finish();
        $this->newLine(2);

        // ── Summary ──
        $this->info('═══ PART 1 SUMMARY ═══');
        $this->info("Total entries:         {$this->countEntries($entries)}");
        $this->info("Matched skema:         " . (count($entries) - count($this->skippedNoMatch)));
        $this->info("Penyusun updated:      {$this->penyusunUpdated}");
        $this->info("Penyusun preserved:    {$this->penyusunPreserved}");
        $this->info("Validator updated:     {$this->validatorUpdated}");
        $this->info("Validator preserved:   {$this->validatorPreserved}");
        $this->info("Manajer cascaded:      {$this->manajerCascaded}");

        if (!empty($this->skippedNoMatch)) {
            $this->warn("\nSkema NOT matched in jabatan_kerjas ({$this->countEntries($this->skippedNoMatch)}):");
            foreach ($this->skippedNoMatch as $name) {
                $this->line("  - {$name}");
            }
        }

        if (!empty($this->skippedNoConfig)) {
            $this->warn("\nNo jabatan_config_a row ({$this->countEntries($this->skippedNoConfig)}):");
            foreach ($this->skippedNoConfig as $detail) {
                $this->line("  - {$detail}");
            }
        }

        if ($dryRun) {
            $this->warn("\nDRY RUN — tidak ada perubahan yang disimpan.");
        }
    }

    // ═══════════════════════════════════════════════
    //  PART 2: Check praktisi barcode dates
    // ═══════════════════════════════════════════════

    private function part2CheckPraktisiBarcodes(array $entries, array $skemaMap): void
    {
        $this->newLine();
        $this->info('═══ PART 2: Check Praktisi Barcode Dates vs tgl_asesmen ═══');
        $this->warn('(Read-only — no changes are being made)');

        // Collect all praktisi names from JSON
        $allNames = [];
        foreach ($entries as $entry) {
            if (empty($entry['asesor'])) continue;
            foreach ($entry['asesor'] as $label => $name) {
                $allNames[] = trim($name);
            }
        }
        $allNames = array_unique($allNames);

        // Batch-fetch praktisi users (role_id = 16)
        // Use case-insensitive lookup: MySQL WHERE IN is case-insensitive,
        // but pluck keys are PHP case-sensitive — normalize to lowercase.
        $userRows = DB::table('users')
            ->whereIn('name', $allNames)
            ->where('role_id', 16)
            ->select('id', 'name')
            ->get();

        $users = []; // lowercase_name => id
        foreach ($userRows as $u) {
            $key = mb_strtolower($u->name);
            // Keep first match only; also map all variations from JSON
            $users[$key] = $u->id;
        }
        // Also map exact JSON names to found user IDs (case variation handling)
        $jsonNameToLower = [];
        foreach ($allNames as $n) {
            $jsonNameToLower[mb_strtolower($n)] = true;
        }

        // Name correction map for known typos in JSON
        $nameCorrections = [
            mb_strtolower('Ir. IR. SAHAT OLOAN SIHOMBING') => 5722, // Ir. SAHAT OLOAN SIHOMBING
        ];
        foreach ($nameCorrections as $lower => $id) {
            if (!isset($users[$lower])) {
                $users[$lower] = $id;
            }
        }

        // Handle case-variant duplicates (Nuryono vs NURYONO)
        // When lookup fails for one variant, try the other known user ID.
        $fallbackUsers = [
            'nuryono' => [5497, 5623], // both Nuryono variants
        ];

        // Collect all id_jabatan_kerja values
        $allJabatanIds = array_unique(array_values($skemaMap));

        // Batch-fetch all kan_praktisi_jabatan records
        $kpjRows = DB::table('kan_praktisi_jabatan')
            ->whereIn('id_jabatan_kerja', $allJabatanIds)
            ->get();

        $kpjMap = []; // "id_jabatan_kerja|id_user" => kpj_id
        foreach ($kpjRows as $row) {
            $key = $row->id_jabatan_kerja . '|' . $row->id_user;
            $kpjMap[$key] = $row->id;
        }

        // Batch-fetch all barcodes for all kpj IDs
        $allKpjIds = array_values($kpjMap);
        $barcodes = DB::table('barcodes')
            ->whereIn('kan_praktisi_jabatan_id', $allKpjIds)
            ->whereIn('jenis_dokumen', ['IA04B', 'IA05', 'IA06', 'AK02'])
            ->get()
            ->groupBy('kan_praktisi_jabatan_id');

        $jenisDok = ['IA04B', 'IA05', 'IA06', 'AK02'];

        // ── Per-entry check ──
        $mismatches = [];
        $missing = [];
        $notFound = [];
        $notAssigned = [];
        $ok = 0;
        $totalChecked = 0;

        $bar = $this->output->createProgressBar(count($entries));
        $bar->start();

        foreach ($entries as $entry) {
            $bar->advance();

            $skemaName = trim($entry['skema']);
            $lowerName = mb_strtolower($skemaName);
            $idJabatanKerja = $skemaMap[$lowerName] ?? null;

            if (!$idJabatanKerja) continue;

            $tglAsesmen = $this->parseDate($entry['tgl_asesmen'] ?? null);
            if (!$tglAsesmen) continue;

            $asesor = $entry['asesor'] ?? [];
            if (empty($asesor)) continue;

            foreach ($asesor as $label => $name) {
                $name = trim($name);
                $lowerName = mb_strtolower($name);
                $userId = $users[$lowerName] ?? null;

                // Handle case-variant duplicates (e.g. Nuryono vs NURYONO):
                // if primary user has no kpj assignment for this skema,
                // try fallback users that DO have an assignment.
                if ($userId && isset($fallbackUsers[$lowerName])) {
                    $kpjKey = $idJabatanKerja . '|' . $userId;
                    if (!isset($kpjMap[$kpjKey])) {
                        // Primary user not assigned — try fallbacks
                        foreach ($fallbackUsers[$lowerName] as $fbId) {
                            $fbKey = $idJabatanKerja . '|' . $fbId;
                            if (isset($kpjMap[$fbKey])) {
                                $userId = $fbId;
                                break;
                            }
                        }
                    }
                } elseif (!$userId && isset($fallbackUsers[$lowerName])) {
                    // Primary not found in users table — use first fallback
                    $userId = $fallbackUsers[$lowerName][0];
                }

                if (!$userId) {
                    $notFound[] = ['skema' => $skemaName, 'asesor' => $name];
                    continue;
                }

                $kpjKey = $idJabatanKerja . '|' . $userId;
                $kpjId = $kpjMap[$kpjKey] ?? null;

                if (!$kpjId) {
                    $notAssigned[] = ['skema' => $skemaName, 'asesor' => $name];
                    continue;
                }

                $userBarcodes = $barcodes[$kpjId] ?? collect();

                foreach ($jenisDok as $jenis) {
                    $totalChecked++;
                    $bc = $userBarcodes->firstWhere('jenis_dokumen', $jenis);

                    if (!$bc) {
                        $missing[] = [
                            'skema' => $skemaName,
                            'asesor' => $name,
                            'jenis' => $jenis,
                        ];
                        continue;
                    }

                    if (!$bc->created_at) {
                        $mismatches[] = [
                            'skema' => $skemaName,
                            'asesor' => $name,
                            'jenis' => $jenis,
                            'expected' => $tglAsesmen,
                            'actual' => 'NULL',
                        ];
                        continue;
                    }

                    $bcDate = Carbon::parse($bc->created_at)->format('Y-m-d');
                    if ($bcDate !== $tglAsesmen) {
                        $mismatches[] = [
                            'skema' => $skemaName,
                            'asesor' => $name,
                            'jenis' => $jenis,
                            'expected' => $tglAsesmen,
                            'actual' => $bcDate,
                        ];
                    } else {
                        $ok++;
                    }
                }
            }
        }

        $bar->finish();
        $this->newLine(2);

        // ── Summary ──
        $this->info('═══ PART 2 SUMMARY ═══');
        $this->info("Total checked:     {$totalChecked}");
        $this->info("OK:                {$ok}");
        $this->info("Mismatches:        " . count($mismatches));
        $this->info("Missing barcodes:  " . count($missing));
        $this->info("User not found:    " . count($notFound));
        $this->info("Not assigned:      " . count($notAssigned));

        // ── Show mismatches ──
        if (!empty($mismatches)) {
            $this->warn("\n── Mismatches (created_at != tgl_asesmen) ──");
            $headers = ['Skema', 'Asesor', 'Jenis', 'Expected', 'Actual'];
            $rows = array_slice(array_map(fn($m) => [$m['skema'], $m['asesor'], $m['jenis'], $m['expected'], $m['actual']], $mismatches), 0, 50);
            $this->table($headers, $rows);
            if (count($mismatches) > 50) {
                $this->warn("  ... and " . (count($mismatches) - 50) . " more mismatches.");
            }
        }

        // ── Show missing barcodes ──
        if (!empty($missing)) {
            $this->warn("\n── Missing Barcodes ──");
            $headers = ['Skema', 'Asesor', 'Jenis'];
            $rows = array_slice(array_map(fn($m) => [$m['skema'], $m['asesor'], $m['jenis']], $missing), 0, 30);
            $this->table($headers, $rows);
            if (count($missing) > 30) {
                $this->warn("  ... and " . (count($missing) - 30) . " more missing.");
            }
        }

        // ── Show user not found ──
        if (!empty($notFound)) {
            $this->warn("\n── Asesor NOT Found in Users ──");
            $headers = ['Skema', 'Asesor Name'];
            $rows = array_slice(array_map(fn($n) => [$n['skema'], $n['asesor']], $notFound), 0, 20);
            $this->table($headers, $rows);
            if (count($notFound) > 20) {
                $this->warn("  ... and " . (count($notFound) - 20) . " more not found.");
            }
        }

        // ── Show not assigned ──
        if (!empty($notAssigned)) {
            $this->warn("\n── Asesor NOT Assigned to Skema ──");
            $headers = ['Skema', 'Asesor Name'];
            $rows = array_slice(array_map(fn($n) => [$n['skema'], $n['asesor']], $notAssigned), 0, 20);
            $this->table($headers, $rows);
            if (count($notAssigned) > 20) {
                $this->warn("  ... and " . (count($notAssigned) - 20) . " more not assigned.");
            }
        }
    }

    // ═══════════════════════════════════════════════
    //  HELPERS
    // ═══════════════════════════════════════════════

    /**
     * Resolve skema names → id_jabatan_kerja.
     * Returns [lower_skema_name => id_jabatan_kerja].
     */
    private function resolveSkemaMapping(array $entries): array
    {
        $allNames = [];
        foreach ($entries as $entry) {
            $allNames[] = trim($entry['skema']);
        }
        $allNames = array_unique($allNames);
        $lowerNames = array_map('mb_strtolower', $allNames);

        $placeholders = implode(',', array_fill(0, count($lowerNames), '?'));
        $rows = DB::table('jabatan_kerjas')
            ->select('id_jabatan_kerja', 'jabatan_kerja')
            ->whereRaw("LOWER(jabatan_kerja) IN ({$placeholders})", $lowerNames)
            ->orderBy('id_jabatan_kerja', 'desc')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $key = mb_strtolower($row->jabatan_kerja);
            if (!isset($map[$key])) {
                $map[$key] = $row->id_jabatan_kerja;
            }
        }

        return $map;
    }

    private function parseDate(mixed $value): ?string
    {
        if (empty($value)) {
            return null;
        }
        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function countEntries(array $arr): int
    {
        return count($arr);
    }
}
