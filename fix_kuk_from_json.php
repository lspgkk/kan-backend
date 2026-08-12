<?php
/**
 * Fix KUK codes in kuks table and reassign kan_soals.id_kuk
 * based on ALL_SOAL_MERGED_FIXED.json
 *
 * Problem: Many kuks.kode are all "1.1" but JSON has varied KUK codes.
 * This script:
 *   1. Reads ALL_SOAL_MERGED_FIXED.json
 *   2. Finds all unique (unit_kode, kuk_kode) pairs per subunit
 *   3. Updates existing KUK records' kode or creates new ones
 *   4. Reassigns kan_soals.id_kuk to point to correct KUK
 *
 * Usage:
 *   php fix_kuk_from_json.php              # audit + show plan
 *   php fix_kuk_from_json.php --fix        # apply fixes
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$isFix = in_array('--fix', $argv ?? []);

$jsonFile = 'c:/Users/Rafie Pradipta/Downloads/ALL_SOAL_MERGED_FIXED.json';

if (!file_exists($jsonFile)) {
    die("ERROR: JSON file not found: {$jsonFile}\n");
}

echo str_repeat("=", 70) . "\n";
echo "  FIX KUK FROM JSON (ALL_SOAL_MERGED_FIXED.json)\n";
echo str_repeat("=", 70) . "\n\n";
echo "Mode: " . ($isFix ? "FIX" : "AUDIT ONLY") . "\n\n";

// ─── 1. LOAD JSON ───
$json = json_decode(file_get_contents($jsonFile), true);

function normalizeName(string $name): string {
    $name = trim($name);
    $name = preg_replace('/^\d+\.\s*/', '', $name);
    return mb_strtolower($name);
}

function cleanKode(string $kode): string {
    $kode = trim($kode);
    $kode = preg_replace('/^Kode\s*Unit\s*:\s*/i', '', $kode);
    $kode = str_replace('*', '', $kode);
    $kode = str_replace(',', '.', $kode);
    $kode = rtrim($kode, '.');
    $kode = preg_replace('/\p{Zs}/u', ' ', $kode);
    return trim($kode);
}

// Extract all (jabatan, tipe, no, kode_unit, kuk_kode) from JSON
$jsonEntries = [];
foreach ($json as $skemaName => $skema) {
    $normName = normalizeName($skemaName);
    foreach (['ia04b', 'ia05', 'ia06'] as $tipe) {
        if (empty($skema[$tipe])) continue;
        foreach ($skema[$tipe] as $q) {
            $no = (int) $q['no'];
            $kodeKukRaw = $q['kode_kuk'] ?? '';
            $kodeUnitRaw = $q['kode_unit'] ?? '';

            $kodeUnit = '';
            $kukKode = '';

            if (!empty($kodeKukRaw)) {
                $cleaned = cleanKode($kodeKukRaw);
                // kode_kuk format: "UNIT_CODE\nKUK_CODE" (actual newline from JSON decode)
                $parts = preg_split('/\s+/', $cleaned, 2);
                $kodeUnit = $parts[0] ?? '';
                $kukKode = $parts[1] ?? '';
            } elseif (!empty($kodeUnitRaw)) {
                $cleaned = cleanKode($kodeUnitRaw);
                $lines = array_values(array_filter(array_map('trim', explode("\n", $cleaned))));
                $kodeUnit = $lines[0] ?? '';
            }

            // Clean up unit code: strip trailing dots (can happen when dot is before newline)
            $kodeUnit = rtrim($kodeUnit, '.');
            if (empty($kodeUnit)) continue;
            if (strlen($kodeUnit) < 5 || substr_count($kodeUnit, '.') < 2) continue;
            if (strpos($kodeUnit, ' ') !== false) continue;

            $jsonEntries[] = [
                'skema_name' => trim($skemaName),
                'norm_name' => $normName,
                'tipe' => $tipe,
                'no' => $no,
                'kode_unit' => $kodeUnit,
                'kuk_kode' => $kukKode,
            ];
        }
    }
}

echo "[1] JSON entries parsed: " . count($jsonEntries) . "\n";

// Count entries with KUK code (ia05/ia06) vs without (ia04b)
$withKuk = count(array_filter($jsonEntries, fn($e) => !empty($e['kuk_kode'])));
echo "    With KUK code: {$withKuk}\n";
echo "    Without KUK (ia04b): " . (count($jsonEntries) - $withKuk) . "\n\n";

// ─── 2. LOAD DB LOOKUPS ───
echo "─── DATABASE LOOKUPS ───\n";

// unit_kompetensis: kode → id
$unitKodeToId = [];
$unitIdToKode = [];
$units = DB::table('unit_kompetensis')->select('id', 'kode')->get();
foreach ($units as $u) {
    $kode = trim($u->kode);
    $unitIdToKode[$u->id] = $kode;
    $unitKodeToId[$kode] = $u->id;
}
echo "unit_kompetensis: " . count($unitKodeToId) . "\n";

// subunit_kompetensis: unit_id → [subunit rows...]
$subunitsByUnitId = [];
$subunitById = [];
$subunits = DB::table('subunit_kompetensis')->select('id', 'kode', 'id_unitkompetensi')->get();
foreach ($subunits as $s) {
    $subunitById[$s->id] = $s;
    $subunitsByUnitId[$s->id_unitkompetensi][] = $s;
}
echo "subunit_kompetensis: " . count($subunitById) . "\n";

// kuks: subunit_id → [kuk rows...]
$kuksBySubunitId = [];
$kukById = [];
$allKuks = DB::table('kuks')->select('id', 'kode', 'nama', 'id_subunitkompetensi')->get();
foreach ($allKuks as $k) {
    $kukById[$k->id] = $k;
    $kuksBySubunitId[$k->id_subunitkompetensi][] = $k;
}
echo "kuks: " . count($kukById) . "\n\n";

// ─── 3. BUILD DB SOAL INDEX ───
echo "─── BUILDING DB INDEX ───\n";

$dokToTipe = ['FR.IA.04.B' => 'ia04b', 'FR.IA.05' => 'ia05', 'FR.IA.06' => 'ia06'];

$allSoals = DB::table('kan_soals as s')
    ->join('jabatan_dokumens as jd', 's.id_dokumen', '=', 'jd.id')
    ->join('jabatan_kerjas as jk', 'jd.id_jabatan', '=', 'jk.id_jabatan_kerja')
    ->select(
        's.id', 's.no', 's.id_dokumen',
        's.id_unitkompetensi', 's.id_subunitkompetensi', 's.id_kuk',
        'jd.kode as dok_kode',
        'jk.jabatan_kerja', 'jk.id_jabatan_kerja'
    )
    ->get();

$dbIndex = []; // norm_name|no|tipe → [soal rows]
foreach ($allSoals as $s) {
    $tipeKey = $dokToTipe[$s->dok_kode] ?? null;
    if (!$tipeKey) continue;
    $normName = normalizeName($s->jabatan_kerja);
    $key = $normName . '|' . (int)$s->no . '|' . $tipeKey;
    if (!isset($dbIndex[$key])) $dbIndex[$key] = [];
    $dbIndex[$key][] = $s;
}

echo "DB unique keys: " . count($dbIndex) . "\n\n";

// ─── 4. RESOLVE UNIT ID (with fallbacks) ───
function resolveUnitId(string $kodeUnit, array $unitKodeToId): ?int {
    // 0. Clean: trim whitespace/nbsp, strip * suffix
    $kodeUnit = trim($kodeUnit, " \t\n\r\0\x0B\xC2\xA0*");
    // Also strip * from end specifically (in case trim didn't catch)
    $kodeUnit = rtrim($kodeUnit, '*');

    // 1. Exact match
    if (isset($unitKodeToId[$kodeUnit])) return $unitKodeToId[$kodeUnit];

    // 2. Single-digit ↔ double-digit version suffix
    if (preg_match('/^(.+)\.(\d)$/', $kodeUnit, $m)) {
        $try = $m[1] . '.0' . $m[2];
        if (isset($unitKodeToId[$try])) return $unitKodeToId[$try];
    }
    if (preg_match('/^(.+)\.0(\d)$/', $kodeUnit, $m)) {
        $try = $m[1] . '.' . $m[2];
        if (isset($unitKodeToId[$try])) return $unitKodeToId[$try];
    }

    // 3. Try other version suffixes (.1 → .2, .3, .4, .5, .6, .01, .02)
    if (preg_match('/^(.+)\.(\d+)$/', $kodeUnit, $m)) {
        $base = $m[1];
        for ($v = 1; $v <= 9; $v++) {
            $try = $base . '.' . $v;
            if ($v !== (int)$m[2] && isset($unitKodeToId[$try])) return $unitKodeToId[$try];
            // Also try double-digit
            $try02 = $base . '.0' . $v;
            if (isset($unitKodeToId[$try02])) return $unitKodeToId[$try02];
        }
    }

    // 4. Strip trailing .N suffix
    if (preg_match('/^(.+)\.\d+$/', $kodeUnit, $m)) {
        if (isset($unitKodeToId[$m[1]])) return $unitKodeToId[$m[1]];
    }

    // 5. Append .1 and .01
    if (isset($unitKodeToId[$kodeUnit . '.1'])) return $unitKodeToId[$kodeUnit . '.1'];
    if (isset($unitKodeToId[$kodeUnit . '.01'])) return $unitKodeToId[$kodeUnit . '.01'];

    return null;
}

function resolveUnitIdFuzzy(string $kodeUnit, array $unitKodeToId, array $unitIdToKode, array $subunitsByUnitId): ?int {
    // Try exact and fallback matching first
    $result = resolveUnitId($kodeUnit, $unitKodeToId);
    if ($result !== null && isset($subunitsByUnitId[$result]) && count($subunitsByUnitId[$result]) > 0) {
        return $result;
    }

    // Fuzzy: try levenshtein distance 1 against all known unit codes
    $bestId = null;
    $bestDist = PHP_INT_MAX;
    foreach ($unitIdToKode as $id => $dbKode) {
        $dbTrimmed = trim($dbKode);
        if (!isset($subunitsByUnitId[$id]) || count($subunitsByUnitId[$id]) < 1) continue;
        $dist = levenshtein($kodeUnit, $dbTrimmed);
        if ($dist <= 1 && $dist < $bestDist) {
            $bestDist = $dist;
            $bestId = $id;
        }
    }

    return $bestId ?? $result;
}

// ─── 5. BUILD FIX PLAN ───
echo "─── MATCHING & BUILDING FIX PLAN ───\n";

$fixPlan = [];       // soal_id → {action, ...}
$newKuks = [];       // subunit_id → [kuk_kode => true] — KUKs we need to create
$updateKukIds = [];  // kuk_id → new_kode — KUKs whose kode needs updating
$stats = [
    'matched' => 0, 'not_matched' => 0,
    'kuk_exists' => 0, 'kuk_need_create' => 0, 'kuk_need_update' => 0,
    'kuk_already_correct' => 0, 'kuk_will_reassign' => 0,
    'no_kuk_needed' => 0, // ia04b
];

foreach ($jsonEntries as $entry) {
    $key = $entry['norm_name'] . '|' . $entry['no'] . '|' . $entry['tipe'];

    if (!isset($dbIndex[$key])) {
        $stats['not_matched']++;
        continue;
    }
    $stats['matched']++;

    // Skip ia04b (no KUK)
    if (empty($entry['kuk_kode'])) {
        $stats['no_kuk_needed']++;
        continue;
    }

    $kodeUnit = $entry['kode_unit'];
    $kukKode = $entry['kuk_kode'];

    $unitId = resolveUnitIdFuzzy($kodeUnit, $unitKodeToId, $unitIdToKode, $subunitsByUnitId);
    if (!$unitId) continue;

    $subunits = $subunitsByUnitId[$unitId] ?? [];
    // Fallback: if resolved unit has no subunits, find alternative with same kode
    if (count($subunits) < 1) {
        $cleanKode = str_replace(' ', '', $kodeUnit);
        foreach ($unitIdToKode as $altId => $altKode) {
            $altClean = str_replace(' ', '', trim($altKode));
            if (($altKode === $kodeUnit || $altClean === $kodeUnit || $altClean === $cleanKode)
                && isset($subunitsByUnitId[$altId])
                && count($subunitsByUnitId[$altId]) > 0) {
                $unitId = $altId;
                $subunits = $subunitsByUnitId[$unitId];
                break;
            }
        }
    }
    if (count($subunits) < 1) continue;
    $subunitId = $subunits[0]->id;

    // Check if a KUK with matching kode exists in this subunit
    $existingKukId = null;
    $kuks = $kuksBySubunitId[$subunitId] ?? [];
    foreach ($kuks as $k) {
        if ($k->kode === $kukKode) {
            $existingKukId = $k->id;
            break;
        }
    }

    foreach ($dbIndex[$key] as $dbSoal) {
        // If no KUK with matching kode exists, mark for creation
        if (!$existingKukId) {
            $newKuks[$subunitId][$kukKode] = true;
            $stats['kuk_need_create']++;
        }

        // Check if soal's id_unitkompetensi, id_subunitkompetensi, id_kuk need fixing
        $currentUnitId = $dbSoal->id_unitkompetensi;
        $currentSubunitId = $dbSoal->id_subunitkompetensi;
        $currentKukId = $dbSoal->id_kuk;
        $currentKukKode = $currentKukId ? ($kukById[$currentKukId]->kode ?? null) : null;

        $needUnitFix = is_null($currentUnitId) || $currentUnitId != $unitId;
        $needSubunitFix = is_null($currentSubunitId) || $currentSubunitId != $subunitId;
        $needKukFix = ($currentKukKode !== $kukKode) || ($currentKukId !== $existingKukId);

        if (!$needUnitFix && !$needSubunitFix && !$needKukFix) {
            $stats['kuk_already_correct']++;
            continue;
        }

        // Need to fix: unit, subunit, and/or KUK
        $fixPlan[$dbSoal->id] = [
            'soal_id' => $dbSoal->id,
            'no' => $dbSoal->no,
            'dok_kode' => $dbSoal->dok_kode,
            'jabatan' => $dbSoal->jabatan_kerja,
            'unit_id' => $unitId,
            'unit_kode' => $kodeUnit,
            'subunit_id' => $subunitId,
            'correct_kuk_kode' => $kukKode,
            'current_unit_id' => $currentUnitId,
            'current_subunit_id' => $currentSubunitId,
            'current_kuk_id' => $currentKukId,
            'current_kuk_kode' => $currentKukKode,
            'existing_kuk_id' => $existingKukId,
            'need_unit_fix' => $needUnitFix,
            'need_subunit_fix' => $needSubunitFix,
            'need_kuk_fix' => $needKukFix,
        ];
        $stats['kuk_will_reassign']++;
    }
}

echo "Matched: {$stats['matched']}\n";
echo "Not matched: {$stats['not_matched']}\n";
echo "IA04b (no KUK): {$stats['no_kuk_needed']}\n";
echo "KUK already correct: {$stats['kuk_already_correct']}\n";
echo "KUK needs new record in kuks table: {$stats['kuk_need_create']}\n";
echo "KUK will reassign in kan_soals: {$stats['kuk_will_reassign']}\n";
echo "Unique soal to fix: " . count($fixPlan) . "\n";
echo "Unique new KUK codes to create: " . count($newKuks, COUNT_RECURSIVE) - count($newKuks) . "\n\n";

// ─── 6. SHOW FIX PLAN DETAIL ───
if (count($fixPlan) > 0) {
    echo "─── FIX PLAN SAMPLES (first 15) ───\n";
    $i = 0;
    foreach ($fixPlan as $plan) {
        $fixes = [];
        if ($plan['need_unit_fix']) $fixes[] = 'unit';
        if ($plan['need_subunit_fix']) $fixes[] = 'subunit';
        if ($plan['need_kuk_fix']) $fixes[] = 'kuk';
        echo sprintf("  soal_id=%d | no=%d | %s | %s",
            $plan['soal_id'], $plan['no'], $plan['dok_kode'], $plan['jabatan']) . "\n";
        echo sprintf("    Current: unit=%s, subunit=%s, kuk_id=%s, kode=%s",
            $plan['current_unit_id'] ?? 'NULL', $plan['current_subunit_id'] ?? 'NULL',
            $plan['current_kuk_id'] ?? 'NULL', $plan['current_kuk_kode'] ?? 'NULL') . "\n";
        echo sprintf("    Correct: unit=%d (%s), subunit=%d, kuk_kode=%s",
            $plan['unit_id'], $plan['unit_kode'], $plan['subunit_id'], $plan['correct_kuk_kode']) . "\n";
        echo "    → Will fix: " . implode(', ', $fixes) . "\n";
        $i++;
        if ($i >= 15) break;
    }
    if (count($fixPlan) > 15) {
        echo "  ... and " . (count($fixPlan) - 15) . " more\n";
    }
    echo "\n";
}

// ─── 7. SHOW NEW KUK SAMPLES ───
if (count($newKuks) > 0) {
    echo "─── NEW KUK CODES TO CREATE (first 15) ───\n";
    $i = 0;
    foreach ($newKuks as $subunitId => $kodes) {
        $subunitKode = $subunitById[$subunitId]->kode ?? '?';
        echo "  subunit_id={$subunitId} (kode={$subunitKode}):\n";
        foreach ($kodes as $kode => $_) {
            echo "    - {$kode}\n";
        }
        $i++;
        if ($i >= 15) break;
    }
    if (count($newKuks) > 15) {
        echo "  ... and " . (count($newKuks) - 15) . " more subunits\n";
    }
    echo "\n";
}

// ─── 8. APPLY FIXES ───
if ($isFix && (count($fixPlan) > 0 || count($newKuks) > 0)) {
    echo str_repeat("=", 70) . "\n";
    echo "  APPLYING FIXES\n";
    echo str_repeat("=", 70) . "\n\n";

    $createdKukIds = []; // subunit_id|kode → new_kuk_id
    $updatedSoal = 0;
    $createdKukCount = 0;
    $errors = 0;

    // Step 1: Create missing KUK records
    foreach ($newKuks as $subunitId => $kodes) {
        foreach ($kodes as $kukKode => $_) {
            try {
                $newId = DB::table('kuks')->insertGetId([
                    'kode' => $kukKode,
                    'nama' => '',
                    'id_subunitkompetensi' => $subunitId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $createdKukIds[$subunitId . '|' . $kukKode] = $newId;
                $createdKukCount++;
                if ($createdKukCount <= 10) {
                    echo "  + Created kuk: id={$newId}, kode={$kukKode}, subunit_id={$subunitId}\n";
                }
            } catch (\Exception $e) {
                echo "  X Error creating kuk (subunit={$subunitId}, kode={$kukKode}): {$e->getMessage()}\n";
                $errors++;
            }
        }
    }
    echo "  Total KUK records created: {$createdKukCount}\n\n";

    // Reload kuks lookup after creation
    $allKuks = DB::table('kuks')->select('id', 'kode', 'id_subunitkompetensi')->get();
    $kuksBySubunitId = [];
    foreach ($allKuks as $k) {
        $kuksBySubunitId[$k->id_subunitkompetensi][] = $k;
    }

    // Step 2: Reassign kan_soals.id_kuk
    foreach ($fixPlan as $soalId => $plan) {
        // Find correct kuk_id
        $kukId = null;
        $kuks = $kuksBySubunitId[$plan['subunit_id']] ?? [];
        foreach ($kuks as $k) {
            if ($k->kode === $plan['correct_kuk_kode']) {
                $kukId = $k->id;
                break;
            }
        }

        if (!$kukId) {
            echo "  X Cannot find kuk for subunit={$plan['subunit_id']}, kode={$plan['correct_kuk_kode']}\n";
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
            if ($updatedSoal <= 15) {
                $changes = [];
                if (array_key_exists('id_unitkompetensi', $updateData)) $changes[] = 'unit:' . ($plan['current_unit_id'] ?? 'NULL') . '→' . $plan['unit_id'];
                if (array_key_exists('id_subunitkompetensi', $updateData)) $changes[] = 'subunit:' . ($plan['current_subunit_id'] ?? 'NULL') . '→' . $plan['subunit_id'];
                if (array_key_exists('id_kuk', $updateData)) $changes[] = 'kuk:' . ($plan['current_kuk_kode'] ?? 'NULL') . '→' . $plan['correct_kuk_kode'];
                echo sprintf("  ✓ soal_id=%d (no=%d, %s): %s\n",
                    $soalId, $plan['no'], $plan['dok_kode'], implode(', ', $changes)
                );
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
    echo "  New KUK to create: ~" . (count($newKuks, COUNT_RECURSIVE) - count($newKuks)) . " records\n";
    echo "  Run: php fix_kuk_from_json.php --fix\n";
    echo str_repeat("=", 70) . "\n";
}

echo "\nDone.\n";
