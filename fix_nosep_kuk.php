<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$isFix = in_array('--fix', $argv ?? []);
echo str_repeat("=", 70) . "\n";
echo "  FIX NO-SEPARATOR KUK (kode_kuk without \\n in JSON)\n";
echo str_repeat("=", 70) . "\n";
echo "Mode: " . ($isFix ? "FIX" : "AUDIT ONLY") . "\n\n";

// ─── 1. LOAD JSON ───
$json = json_decode(file_get_contents('c:/Users/Rafie Pradipta/Downloads/ALL_SOAL_MERGED_FIXED.json'), true);

// ─── 2. LOAD DB LOOKUPS ───
$unitKodeToId = [];
$unitById = [];
$allUnits = DB::table('unit_kompetensis')->select('id', 'kode')->get();
foreach ($allUnits as $u) { $unitKodeToId[$u->kode] = $u->id; $unitById[$u->id] = $u; }

$subunitsByUnitId = [];
$allSubunits = DB::table('subunit_kompetensis')->select('id', 'kode', 'id_unitkompetensi')->get();
foreach ($allSubunits as $su) { $subunitsByUnitId[$su->id_unitkompetensi][] = $su; }

$kuksBySubunitId = [];
$allKuks = DB::table('kuks')->select('id', 'kode', 'id_subunitkompetensi')->get();
foreach ($allKuks as $k) { $kuksBySubunitId[$k->id_subunitkompetensi][] = $k; }

// ─── 3. BUILD DB SOAL INDEX ───
$dokToTipe = ['FR.IA.04.B' => 'ia04b', 'FR.IA.05' => 'ia05', 'FR.IA.06' => 'ia06'];
$tipeToDok = array_flip($dokToTipe);

function normalizeName(string $s): string {
    return mb_strtolower(trim(preg_replace('/^\d+\.\s*/', '', $s)));
}

$allSoals = DB::table('kan_soals as s')
    ->join('jabatan_dokumens as jd', 's.id_dokumen', '=', 'jd.id')
    ->join('jabatan_kerjas as jk', 'jd.id_jabatan', '=', 'jk.id_jabatan_kerja')
    ->select('s.id', 's.no', 's.id_unitkompetensi', 's.id_subunitkompetensi', 's.id_kuk',
             'jd.kode as dok_kode', 'jk.jabatan_kerja')
    ->get();

$dbIndex = [];
$dbById = [];
foreach ($allSoals as $s) {
    $tipeKey = $dokToTipe[$s->dok_kode] ?? null;
    if (!$tipeKey) continue;
    $key = normalizeName($s->jabatan_kerja) . '|' . (int)$s->no . '|' . $tipeKey;
    if (!isset($dbIndex[$key])) $dbIndex[$key] = [];
    $dbIndex[$key][] = $s;
    $dbById[$s->id] = $s;
}

// ─── 4. FUNCTION: SPLIT CONCATENATED KODE_KUK ───
function splitConcatenated(string $kodeKuk, array $unitKodeToId): ?array {
    $len = strlen($kodeKuk);
    // Try progressively shorter KUK suffixes
    // KUK pattern: N.N, N.N.N, NN.N, N.NN, etc.
    // Minimum KUK: 3 chars (e.g. "1.1")
    for ($tryLen = $len - 3; $tryLen >= 0; $tryLen--) {
        $candidateUnit = substr($kodeKuk, 0, $tryLen);
        $candidateKuk = substr($kodeKuk, $tryLen);

        // Validate KUK pattern: must look like N.N, N.N.N (e.g. 1.1, 2.3, 10.1, 1.15, 1.1.1)
        if (!preg_match('/^\d+(?:\.\d+)+$/', $candidateKuk)) continue;

        // Check if candidateUnit matches a known unit
        if (isset($unitKodeToId[$candidateUnit])) {
            return [
                'unit_kode' => $candidateUnit,
                'unit_id' => $unitKodeToId[$candidateUnit],
                'kuk_kode' => $candidateKuk,
            ];
        }
    }
    return null;
}

// ─── 5. FIND & FIX NO-SEPARATOR ENTRIES ───
$fixPlan = [];
$stats = ['total' => 0, 'parsed' => 0, 'failed_parse' => 0, 'no_db_match' => 0, 'already_correct' => 0, 'will_fix' => 0];
$failedSamples = [];
$dokKodes = ['ia04b' => 'FR.IA.04.B', 'ia05' => 'FR.IA.05', 'ia06' => 'FR.IA.06'];

foreach ($json as $jabatanName => $jabatanData) {
    $normName = normalizeName($jabatanName);

    foreach (['ia05', 'ia06', 'ia04b'] as $tipe) {
        if (!isset($jabatanData[$tipe])) continue;
        $dokKode = $dokKodes[$tipe];

        foreach ($jabatanData[$tipe] as $item) {
            $kodeUnit = $item['kode_unit'] ?? '';
            $kodeKuk = $item['kode_kuk'] ?? '';
            $no = (int)($item['no'] ?? 0);

            // Only target TRUE no-separator entries:
            // - kode_unit is NULL/empty in JSON
            // - kode_kuk has NO whitespace/newline separator (concatenated format)
            // - kode_kuk is not empty
            if (empty($kodeKuk)) continue;
            if (!empty($kodeUnit)) continue;           // already has unit code (handled by first fix)
            if (preg_match('/[\s\n]/', $kodeKuk)) continue; // has separator (handled by first fix)

            $stats['total']++;

            // Parse the concatenated kode_kuk: try to split into unit code + KUK code
            $parsed = splitConcatenated($kodeKuk, $unitKodeToId);

            if (!$parsed) {
                $stats['failed_parse']++;
                if (count($failedSamples) < 10) {
                    $failedSamples[] = ['name' => $jabatanName, 'tipe' => $tipe, 'no' => $no, 'kode_kuk' => $kodeKuk];
                }
                continue;
            }

            $stats['parsed']++;

            // Find matching DB record
            $key = $normName . '|' . $no . '|' . $tipe;
            $dbSoals = $dbIndex[$key] ?? [];

            if (empty($dbSoals)) {
                $stats['no_db_match']++;
                continue;
            }

            $unitId = $parsed['unit_id'];
            $kukKode = $parsed['kuk_kode'];

            // Find subunit
            $subunits = $subunitsByUnitId[$unitId] ?? [];
            $subunitId = !empty($subunits) ? $subunits[0]->id : null;

            if (!$subunitId) continue;

            // Find KUK
            $kuks = $kuksBySubunitId[$subunitId] ?? [];
            $existingKukId = null;
            foreach ($kuks as $k) {
                if ($k->kode === $kukKode) { $existingKukId = $k; break; }
            }

            foreach ($dbSoals as $dbSoal) {
                $currentKukId = $dbSoal->id_kuk;
                $currentKukKode = null;
                foreach ($kuks as $k) {
                    if ($k->id === $currentKukId) { $currentKukKode = $k->kode; break; }
                }
                $currentUnitId = $dbSoal->id_unitkompetensi;
                $currentSubunitId = $dbSoal->id_subunitkompetensi;

                $needUnitFix = is_null($currentUnitId) || $currentUnitId != $unitId;
                $needSubunitFix = is_null($currentSubunitId) || $currentSubunitId != $subunitId;
                $needKukFix = ($currentKukKode !== $kukKode);

                if (!$needUnitFix && !$needSubunitFix && !$needKukFix) {
                    $stats['already_correct']++;
                    continue;
                }

                $stats['will_fix']++;
                $fixPlan[$dbSoal->id] = [
                    'soal_id' => $dbSoal->id,
                    'no' => $dbSoal->no,
                    'dok_kode' => $dbSoal->dok_kode,
                    'jabatan' => $dbSoal->jabatan_kerja,
                    'unit_id' => $unitId,
                    'unit_kode' => $parsed['unit_kode'],
                    'subunit_id' => $subunitId,
                    'kuk_kode' => $kukKode,
                    'existing_kuk_id' => $existingKukId,
                    'current_unit_id' => $currentUnitId,
                    'current_subunit_id' => $currentSubunitId,
                    'current_kuk_id' => $currentKukId,
                    'current_kuk_kode' => $currentKukKode,
                    'need_unit_fix' => $needUnitFix,
                    'need_subunit_fix' => $needSubunitFix,
                    'need_kuk_fix' => $needKukFix,
                ];
            }
        }
    }
}

// ─── 6. REPORT ───
echo "─── RESULTS ───\n";
echo "Total no-separator entries: {$stats['total']}\n";
echo "Successfully parsed: {$stats['parsed']}\n";
echo "Failed to parse: {$stats['failed_parse']}\n";
echo "No DB match: {$stats['no_db_match']}\n";
echo "Already correct: {$stats['already_correct']}\n";
echo "Will fix: {$stats['will_fix']}\n";
echo "Unique soal to fix: " . count($fixPlan) . "\n\n";

if ($stats['failed_parse'] > 0) {
    echo "─── FAILED PARSE SAMPLES ───\n";
    foreach ($failedSamples as $f) {
        echo "  {$f['name']} / {$f['tipe']} / no={$f['no']} / kode_kuk={$f['kode_kuk']}\n";
    }
    echo "\n";
}

// Samples
echo "─── FIX PLAN SAMPLES (first 20) ───\n";
$i = 0;
foreach ($fixPlan as $plan) {
    $fixes = [];
    if ($plan['need_unit_fix']) $fixes[] = 'unit:' . ($plan['current_unit_id'] ?? 'NULL') . '→' . $plan['unit_id'];
    if ($plan['need_subunit_fix']) $fixes[] = 'subunit:' . ($plan['current_subunit_id'] ?? 'NULL') . '→' . $plan['subunit_id'];
    if ($plan['need_kuk_fix']) $fixes[] = 'kuk:' . ($plan['current_kuk_kode'] ?? 'NULL') . '→' . $plan['kuk_kode'];
    echo sprintf("  soal_id=%d | no=%d | %s\n    %s → %s\n",
        $plan['soal_id'], $plan['no'], $plan['jabatan'],
        implode(', ', $fixes), $plan['unit_kode'] . '/' . $plan['kuk_kode']);
    $i++;
    if ($i >= 20) break;
}
if (count($fixPlan) > 20) echo "  ... and " . (count($fixPlan) - 20) . " more\n";
echo "\n";

// ─── 7. NEW KUK NEEDED ───
$newKuks = [];
foreach ($fixPlan as $plan) {
    if (!$plan['existing_kuk_id']) {
        $newKuks[$plan['subunit_id']][$plan['kuk_kode']] = true;
    }
}
echo "New KUK codes to create: " . (count($newKuks, COUNT_RECURSIVE) - count($newKuks)) . "\n\n";

// ─── 8. APPLY FIXES ───
if ($isFix && count($fixPlan) > 0) {
    echo str_repeat("=", 70) . "\n";
    echo "  APPLYING FIXES\n";
    echo str_repeat("=", 70) . "\n\n";

    $createdKukCount = 0;
    $updatedSoal = 0;
    $errors = 0;

    // Create missing KUKs
    foreach ($newKuks as $subunitId => $kodes) {
        foreach ($kodes as $kukKode => $_) {
            try {
                DB::table('kuks')->insertGetId([
                    'kode' => $kukKode,
                    'nama' => '',
                    'id_subunitkompetensi' => $subunitId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $createdKukCount++;
            } catch (\Exception $e) {
                echo "  X Error creating kuk (subunit={$subunitId}, kode={$kukKode}): {$e->getMessage()}\n";
                $errors++;
            }
        }
    }
    echo "  KUK records created: {$createdKukCount}\n\n";

    // Reload kuks
    $allKuks = DB::table('kuks')->select('id', 'kode', 'id_subunitkompetensi')->get();
    $kuksBySubunitId = [];
    foreach ($allKuks as $k) { $kuksBySubunitId[$k->id_subunitkompetensi][] = $k; }

    // Update soals
    foreach ($fixPlan as $soalId => $plan) {
        // Find kuk_id
        $kukId = null;
        $kuks = $kuksBySubunitId[$plan['subunit_id']] ?? [];
        foreach ($kuks as $k) {
            if ($k->kode === $plan['kuk_kode']) { $kukId = $k->id; break; }
        }
        if (!$kukId) {
            echo "  X Cannot find kuk for subunit={$plan['subunit_id']}, kode={$plan['kuk_kode']}\n";
            $errors++;
            continue;
        }

        try {
            $updateData = ['updated_at' => now()];
            if ($plan['need_kuk_fix']) $updateData['id_kuk'] = $kukId;
            if ($plan['need_unit_fix'] || $plan['need_kuk_fix']) $updateData['id_unitkompetensi'] = $plan['unit_id'];
            if ($plan['need_subunit_fix'] || $plan['need_kuk_fix']) $updateData['id_subunitkompetensi'] = $plan['subunit_id'];

            DB::table('kan_soals')->where('id', $soalId)->update($updateData);
            $updatedSoal++;
            if ($updatedSoal <= 20) {
                $changes = [];
                if (array_key_exists('id_unitkompetensi', $updateData)) $changes[] = 'unit:' . ($plan['current_unit_id'] ?? 'NULL') . '→' . $plan['unit_id'];
                if (array_key_exists('id_subunitkompetensi', $updateData)) $changes[] = 'subunit:' . ($plan['current_subunit_id'] ?? 'NULL') . '→' . $plan['subunit_id'];
                if (array_key_exists('id_kuk', $updateData)) $changes[] = 'kuk:' . ($plan['current_kuk_kode'] ?? 'NULL') . '→' . $plan['kuk_kode'];
                echo sprintf("  ✓ soal_id=%d (no=%d, %s): %s\n",
                    $soalId, $plan['no'], $plan['jabatan'], implode(', ', $changes));
            }
        } catch (\Exception $e) {
            echo "  X Error updating soal_id={$soalId}: {$e->getMessage()}\n";
            $errors++;
        }
    }

    echo "\n─── RESULT ───\n";
    echo "KUK records created: {$createdKukCount}\n";
    echo "Soal records updated: {$updatedSoal}\n";
    echo "Errors: {$errors}\n";
} elseif (!$isFix && count($fixPlan) > 0) {
    echo str_repeat("=", 70) . "\n";
    echo "  READY TO FIX: " . count($fixPlan) . " soal records\n";
    echo "  Run: php fix_nosep_kuk.php --fix\n";
    echo str_repeat("=", 70) . "\n";
}

echo "\nDone.\n";
