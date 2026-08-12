<?php
/**
 * Audit & Sync KUK yang kosong di kan_soals menggunakan ALL_SOAL_MERGED.json
 *
 * Usage:
 *   php audit_kuk.php              # audit + tampilkan statistik
 *   php audit_kuk.php --fix        # audit + isi id_subunitkompetensi & id_kuk
 *   php audit_kuk.php --detail     # tampilkan detail per soal (tanpa fix)
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$isFix = in_array('--fix', $argv ?? []);
$isDetail = in_array('--detail', $argv ?? []);

$jsonFile = 'c:/Users/Rafie Pradipta/Downloads/ALL_SOAL_MERGED.json';

if (!file_exists($jsonFile)) {
    die("ERROR: JSON file not found: {$jsonFile}\n");
}

echo str_repeat("═", 60) . "\n";
echo "  AUDIT & SYNC KUK DARI ALL_SOAL_MERGED.JSON\n";
echo str_repeat("═", 60) . "\n\n";

$mode = $isFix ? 'FIX MODE' : 'AUDIT ONLY';
echo "Mode: {$mode}\n\n";

// ═══════════════════════════════════════════════════════════════
// 1. LOAD JSON & NORMALIZE SKEMA NAMES
// ═══════════════════════════════════════════════════════════════
$json = json_decode(file_get_contents($jsonFile), true);

// Normalize JSON skema name: strip "NNN. " prefix, trim, lowercase
function normalizeSkemaName(string $name): string {
    $name = trim($name);
    // Strip leading "NNN. " or "NNN." pattern (with or without space after dot)
    $name = preg_replace('/^\d+\.\s*/', '', $name);
    return mb_strtolower($name);
}

function cleanKodeUnit(string $kode): string {
    $kode = trim($kode);
    // Remove "Kode Unit : " prefix (some JSON entries have this)
    $kode = preg_replace('/^Kode\s*Unit\s*:\s*/i', '', $kode);
    // Remove ALL asterisks (can be in middle for kode_kuk format like "F.43GRT09.002.1* 1.1")
    $kode = str_replace('*', '', $kode);
    // Remove trailing dots (data quality: "F.41PLG00.006.2." → "F.41PLG00.006.2")
    $kode = rtrim($kode, '.');
    // Handle non-breaking spaces and other unicode whitespace
    $kode = preg_replace('/\p{Zs}/u', ' ', $kode);
    $kode = trim($kode);
    return $kode;
}

function isValidKodeUnit(string $kode): bool {
    // Must be at least 5 chars and contain at least 2 dots (e.g. "M.71.xxx")
    // Must NOT contain internal spaces (e.g. reject "F. 410140.009.01")
    return strlen($kode) >= 5 && substr_count($kode, '.') >= 2 && strpos($kode, ' ') === false;
}

$jsonSoals = []; // key: normalized_skema|no|tipe → data
$jsonTipes = ['ia04b', 'ia05', 'ia06'];

// ia04b uses "kode_unit" field → just unit code
// ia05/ia06 use "kode_kuk" field → format "{kode_unit} {nomor_kuk}" e.g. "M.71IGN00.024.2 1.1"
// But some ia05/ia06 may still use "kode_unit" (check both)

foreach ($json as $skemaName => $skema) {
    $normalizedName = normalizeSkemaName($skemaName);
    foreach ($jsonTipes as $tipeKey) {
        if (empty($skema[$tipeKey])) continue;
        foreach ($skema[$tipeKey] as $q) {
            $no = (int) $q['no'];

            // Try kode_kuk first (for ia05/ia06), then kode_unit (for ia04b)
            $kodeKukRaw = $q['kode_kuk'] ?? '';
            $kodeUnitRaw = $q['kode_unit'] ?? '';

            $kodeUnitForDb = '';   // unit_kompetensis code
            $kukCode = '';          // kuks.kode value

            if (!empty($kodeKukRaw)) {
                // Format: "M.71IGN00.024.2 1.1" → unit = "M.71IGN00.024.2", kuk = "1.1"
                $kodeKukRaw = cleanKodeUnit($kodeKukRaw);
                $parts = preg_split('/\s+/', $kodeKukRaw, 2);
                $kodeUnitForDb = $parts[0] ?? '';
                $kukCode = $parts[1] ?? '';
            } elseif (!empty($kodeUnitRaw)) {
                // ia04b format: just unit code, may be multi-line
                $cleaned = cleanKodeUnit($kodeUnitRaw);
                $lines = array_values(array_filter(array_map('trim', explode("\n", $cleaned))));
                $kodeUnitForDb = $lines[0] ?? '';
                // Store full unit codes for multi-unit tracking
                if (count($lines) > 1) {
                    $kukCode = ''; // no KUK for ia04b multi-unit
                }
            }

            // Post-extraction cleanup
            $kodeUnitForDb = rtrim($kodeUnitForDb, '.'); // trailing dots from malformed codes

            if (empty($kodeUnitForDb)) continue;
            if (!isValidKodeUnit($kodeUnitForDb)) continue; // skip invalid like "F." etc.

            $key = $normalizedName . '|' . $no . '|' . $tipeKey;
            $jsonSoals[$key] = [
                'skema_name' => trim($skemaName),
                'no' => $no,
                'tipe_key' => $tipeKey,
                'kode_unit_db' => $kodeUnitForDb,  // unit_kompetensis.kode
                'kuk_code' => $kukCode,             // kuks.kode (from kode_kuk)
                'raw_kode_unit' => $kodeUnitRaw,     // original (for multi-unit tracking)
                'soal_text' => mb_substr($q['soal'] ?? '', 0, 80),
            ];
        }
    }
}

echo "[1] JSON loaded: " . count($jsonSoals) . " soal entries\n";

// ═══════════════════════════════════════════════════════════════
// 2. DATABASE STATE
// ═══════════════════════════════════════════════════════════════
echo "\n─── DATABASE STATE ───\n";

$totalSoal = DB::table('kan_soals')->count();
$nullUnit = DB::table('kan_soals')->whereNull('id_unitkompetensi')->count();
$nullSubunit = DB::table('kan_soals')->whereNull('id_subunitkompetensi')->count();
$nullKuk = DB::table('kan_soals')->whereNull('id_kuk')->count();

echo "Total records in kan_soals: {$totalSoal}\n";
echo "NULL id_unitkompetensi:    {$nullUnit} (" . round($nullUnit/$totalSoal*100, 1) . "%)\n";
echo "NULL id_subunitkompetensi:  {$nullSubunit} (" . round($nullSubunit/$totalSoal*100, 1) . "%)\n";
echo "NULL id_kuk:                {$nullKuk} (" . round($nullKuk/$totalSoal*100, 1) . "%)\n";

// Also check: how many have unit but no kuk?
$haveUnitNoKuk = DB::table('kan_soals')
    ->whereNotNull('id_unitkompetensi')
    ->whereNull('id_kuk')
    ->count();
echo "Have unit but NULL kuk:    {$haveUnitNoKuk}\n";

// ═══════════════════════════════════════════════════════════════
// 3. BUILD LOOKUP MAPS (hierarchy traversal)
// ═══════════════════════════════════════════════════════════════
echo "\n─── BUILDING LOOKUP MAPS ───\n";

// unit_kompetensis: id → kode, kode → id
$unitIdToKode = [];
$unitKodeToId = [];
$units = DB::table('unit_kompetensis')->select('id', 'kode')->get();
foreach ($units as $u) {
    $kode = trim($u->kode); // trim trailing spaces in DB values
    $unitIdToKode[$u->id] = $kode;
    $unitKodeToId[$kode] = $u->id;

    // Add normalized variants to the lookup map
    $variants = [$kode];

    // Variant without asterisks
    $variants[] = str_replace('*', '', $kode);

    // Variant without trailing dots
    $variants[] = rtrim($kode, '.');

    // Variant: zero-pad/unpad the last version segment (.2 ↔ .02)
    if (preg_match('/^(.+)\.(\d)$/', $kode, $m)) {
        $variants[] = $m[1] . '.0' . $m[2]; // .2 → .02
    }
    if (preg_match('/^(.+)\.0(\d)$/', $kode, $m)) {
        $variants[] = $m[1] . '.' . $m[2]; // .02 → .2
    }

    // Variant: strip last .\d+ suffix
    if (preg_match('/^(.+)\.\d+$/', $kode, $m)) {
        $variants[] = $m[1];
    }

    foreach (array_unique($variants) as $v) {
        $v = trim($v);
        if ($v !== '' && $v !== $kode) {
            // Only add if not already set (don't overwrite exact match)
            if (!isset($unitKodeToId[$v])) {
                $unitKodeToId[$v] = $u->id;
            }
        }
    }
}

// Helper to resolve unit kode to ID with fallbacks
function resolveUnitId(string $kodeUnit, array $unitKodeToId): ?int {
    // 1. Exact match
    if (isset($unitKodeToId[$kodeUnit])) {
        return $unitKodeToId[$kodeUnit];
    }

    // 2. Normalize version suffixes: single-digit ↔ double-digit
    // e.g. ".2" → ".02", or ".02" → ".2"
    if (preg_match('/^(.+)\.(\d)$/', $kodeUnit, $m) && strlen($m[2]) === 1) {
        $normalized = $m[1] . '.0' . $m[2]; // .2 → .02
        if (isset($unitKodeToId[$normalized])) {
            return $unitKodeToId[$normalized];
        }
    }
    if (preg_match('/^(.+)\.0(\d)$/', $kodeUnit, $m)) {
        $normalized = $m[1] . '.' . $m[2]; // .02 → .2
        if (isset($unitKodeToId[$normalized])) {
            return $unitKodeToId[$normalized];
        }
    }

    // 3. Try changing last version suffix (.N → try other numbers)
    // e.g. "M.71BGH00.004.1" → "M.71BGH00.004.3"
    if (preg_match('/^(.+)\.(\d+)$/', $kodeUnit, $m)) {
        $base = $m[1];
        for ($v = 1; $v <= 5; $v++) {
            $try = $base . '.' . $v;
            if ($v !== intval($m[2]) && isset($unitKodeToId[$try])) {
                return $unitKodeToId[$try];
            }
        }
    }

    // 4. Try fixing "M.71100" → "M.711000" (missing 0 after 3-digit segment)
    // Pattern like "M.71xxx00" where xxx is 3 digits, should be "M.71xxx000"
    if (preg_match('/^M\.(\d{2})(\d{2})(\d{2})\./', $kodeUnit, $m)) {
        $fixed = 'M.' . $m[1] . $m[2] . '0' . $m[3] . substr($kodeUnit, strlen($m[0]) - 1);
        if (isset($unitKodeToId[$fixed])) {
            return $unitKodeToId[$fixed];
        }
    }

    // 4b. Try inserting "0" in first numeric block for M. codes
    // e.g. "M.71100.001.01" → "M.711000.001.01"
    if (preg_match('/^M\.(\d+)(\..+)$/', $kodeUnit, $m)) {
        $numBlock = $m[1];
        $rest = $m[2];
        // Try padding the numeric block with a 0 at various positions
        for ($i = 0; $i <= strlen($numBlock); $i++) {
            $padded = substr($numBlock, 0, $i) . '0' . substr($numBlock, $i);
            $try = 'M.' . $padded . $rest;
            if (isset($unitKodeToId[$try])) {
                return $unitKodeToId[$try];
            }
        }
    }

    // 5. Detect concatenated kode_kuk (e.g. "F.43GRT09.001.211.1" = unit "F.43GRT09.001.2" + kuk "11.1")
    $segments = explode('.', $kodeUnit);
    if (count($segments) >= 5) {
        // Try splitting the 4th-from-last segment which might be unit_version + kuk_number merged
        $lastKukSeg = array_pop($segments);       // last segment (kuk's last number)
        $mergedSeg = array_pop($segments);         // merged segment
        $unitPrefix = implode('.', $segments);     // first 3 segments = unit prefix

        // Try splitting mergedSeg at each position
        for ($i = 1; $i < strlen($mergedSeg); $i++) {
            $unitVersion = substr($mergedSeg, 0, $i);
            $kukFirst = substr($mergedSeg, $i);
            $tryUnit = $unitPrefix . '.' . $unitVersion;
            if (isset($unitKodeToId[$tryUnit])) {
                return $unitKodeToId[$tryUnit];
            }
        }
        // Also try without the last segment (3-segment codes)
        $tryUnit2 = $unitPrefix . '.' . $mergedSeg;
        if (isset($unitKodeToId[$tryUnit2])) {
            return $unitKodeToId[$tryUnit2];
        }
    }

    // 6. Try stripping trailing .\d+ suffix entirely
    // e.g. "F.41BPC00.007.2" → "F.41BPC00.007"
    if (preg_match('/^(.+)\.\d+$/', $kodeUnit, $m)) {
        $baseKode = $m[1];
        if (isset($unitKodeToId[$baseKode])) {
            return $unitKodeToId[$baseKode];
        }
    }

    // 6. Try appending ".1" (some DB entries omit the default version)
    $withOne = $kodeUnit . '.1';
    if (isset($unitKodeToId[$withOne])) {
        return $unitKodeToId[$withOne];
    }

    return null;
}
echo "unit_kompetensis: " . count($unitIdToKode) . " records\n";

// subunit_kompetensis: id → info, unit_id → [subunits]
$subunitById = [];
$subunitsByUnitId = []; // unit_id → [subunit rows...]
$subunits = DB::table('subunit_kompetensis')->select('id', 'kode', 'id_unitkompetensi')->get();
foreach ($subunits as $s) {
    $subunitById[$s->id] = $s;
    if (!isset($subunitsByUnitId[$s->id_unitkompetensi])) {
        $subunitsByUnitId[$s->id_unitkompetensi] = [];
    }
    $subunitsByUnitId[$s->id_unitkompetensi][] = $s;
}
echo "subunit_kompetensis: " . count($subunitById) . " records\n";

// kuks: id → info, subunit_id → [kuks...]
$kukById = [];
$kuksBySubunitId = []; // subunit_id → [kuk rows...]
$allKuks = DB::table('kuks')->select('id', 'kode', 'nama', 'id_subunitkompetensi')->get();
foreach ($allKuks as $k) {
    $kukById[$k->id] = $k;
    if (!isset($kuksBySubunitId[$k->id_subunitkompetensi])) {
        $kuksBySubunitId[$k->id_subunitkompetensi] = [];
    }
    $kuksBySubunitId[$k->id_subunitkompetensi][] = $k;
}
echo "kuks: " . count($kukById) . " records\n";

// ═══════════════════════════════════════════════════════════════
// 4. BUILD DB INDEX (with normalized name matching)
// ═══════════════════════════════════════════════════════════════
echo "\n─── BUILDING DB INDEX & MATCHING ───\n";

$dokToTipeKey = [
    'FR.IA.04.B' => 'ia04b',
    'FR.IA.05' => 'ia05',
    'FR.IA.06' => 'ia06',
];

$allSoals = DB::table('kan_soals as s')
    ->join('jabatan_dokumens as jd', 's.id_dokumen', '=', 'jd.id')
    ->join('jabatan_kerjas as jk', 'jd.id_jabatan', '=', 'jk.id_jabatan_kerja')
    ->select(
        's.id', 's.no', 's.tipe', 's.id_dokumen',
        's.id_unitkompetensi', 's.id_subunitkompetensi', 's.id_kuk',
        's.soal',
        'jd.kode as dok_kode',
        'jk.jabatan_kerja', 'jk.id_jabatan_kerja'
    )
    ->get();

// Build DB index: normalized_jabatan|no|tipe_key → [soal rows...]
$dbIndex = [];
$dbByNormName = []; // normalized_jabatan → [soal rows...] (for fuzzy matching)

foreach ($allSoals as $s) {
    $tipeKey = $dokToTipeKey[$s->dok_kode] ?? null;
    if (!$tipeKey) continue;

    $normName = normalizeSkemaName($s->jabatan_kerja);
    $key = $normName . '|' . (int)$s->no . '|' . $tipeKey;

    if (!isset($dbIndex[$key])) {
        $dbIndex[$key] = [];
    }
    $dbIndex[$key][] = $s;

    // Also index by normalized name for debug
    if (!isset($dbByNormName[$normName])) {
        $dbByNormName[$normName] = [];
    }
    $dbByNormName[$normName][] = $s;
}

echo "Unique keys in DB index: " . count($dbIndex) . "\n";
echo "Unique normalized jabatan names in DB: " . count($dbByNormName) . "\n";

// ═══════════════════════════════════════════════════════════════
// 5. MATCH JSON TO DB
// ═══════════════════════════════════════════════════════════════
$matchStats = [
    'matched' => 0,
    'not_matched' => 0,
    'multi_kode_unit' => 0,
    'json_kode_not_in_unit_table' => 0,
];

$fixLog = [];        // soal_id → {unit_id, subunit_id, kuk_id, ...}
$unmatchedSamples = [];
$kodeUnitNotFound = [];
$multiUnitSoals = [];

foreach ($jsonSoals as $key => $jsonData) {
    if (isset($dbIndex[$key])) {
        $matchStats['matched']++;
        $dbSoals = $dbIndex[$key];

        // Check if multi kode_unit (from raw_kode_unit newlines)
        $rawKode = $jsonData['raw_kode_unit'] ?? '';
        $multiLines = count(array_filter(explode("\n", $rawKode))) > 1;
        if ($multiLines) {
            $matchStats['multi_kode_unit']++;
        }

        $kodeUnitForDb = $jsonData['kode_unit_db'];
        $kukCode = $jsonData['kuk_code'];
        $tipeKey = $jsonData['tipe_key'];

        // For each matching DB soal, resolve subunit & kuk
        foreach ($dbSoals as $dbSoal) {
            // Look up unit_kompetensis with fallbacks
            $unitId = resolveUnitId($kodeUnitForDb, $unitKodeToId);
            if (!$unitId) {
                $matchStats['json_kode_not_in_unit_table']++;
                if (!isset($kodeUnitNotFound[$kodeUnitForDb])) {
                    $kodeUnitNotFound[$kodeUnitForDb] = 0;
                }
                $kodeUnitNotFound[$kodeUnitForDb]++;
                continue;
            }

            $soalId = $dbSoal->id;
            $needsFix = false;
            $fixData = [
                'soal_id' => $soalId,
                'no' => $dbSoal->no,
                'dok_kode' => $dbSoal->dok_kode,
                'jabatan' => $dbSoal->jabatan_kerja,
            ];

            // Update id_unitkompetensi if null or mismatched
            $hasExistingUnit = !is_null($dbSoal->id_unitkompetensi);
            $unitMismatch = $hasExistingUnit && $dbSoal->id_unitkompetensi != $unitId;
            if (is_null($dbSoal->id_unitkompetensi) || $unitMismatch) {
                $fixData['id_unitkompetensi'] = $unitId;
                $fixData['kode_unit'] = $kodeUnitForDb;
                if ($unitMismatch) $fixData['old_unit_id'] = $dbSoal->id_unitkompetensi;
                $needsFix = true;
            }

            // Resolve subunit from unit
            $subunitId = null;
            $subunitKode = null;
            $subunits = $subunitsByUnitId[$unitId] ?? [];
            if (count($subunits) >= 1) {
                $subunitId = $subunits[0]->id;
                $subunitKode = $subunits[0]->kode;
            }

            if (is_null($dbSoal->id_subunitkompetensi) && $subunitId) {
                $fixData['id_subunitkompetensi'] = $subunitId;
                $fixData['subunit_kode'] = $subunitKode;
                $needsFix = true;
            }

            // Resolve KUK — for ia05/ia06 we have exact kuk_code from kode_kuk field
            $kukId = null;
            $kukKode = null;
            if (!empty($kukCode) && $subunitId) {
                // Find KUK by kode within this subunit
                $kuksInSubunit = $kuksBySubunitId[$subunitId] ?? [];
                foreach ($kuksInSubunit as $k) {
                    if ($k->kode === $kukCode) {
                        $kukId = $k->id;
                        $kukKode = $k->kode;
                        break;
                    }
                }
            }

            // Fallback: if no specific kuk_code, take first KUK in subunit
            if (!$kukId && $subunitId && isset($kuksBySubunitId[$subunitId])) {
                $kuks = $kuksBySubunitId[$subunitId];
                if (count($kuks) >= 1) {
                    $kukId = $kuks[0]->id;
                    $kukKode = $kuks[0]->kode;
                }
            }

            if (is_null($dbSoal->id_kuk) && $kukId) {
                $fixData['id_kuk'] = $kukId;
                $fixData['kuk_kode'] = $kukKode;
                $needsFix = true;
            }

            if ($needsFix) {
                $fixLog[$soalId] = $fixData;
            }
        }
    } else {
        $matchStats['not_matched']++;
        if (count($unmatchedSamples) < 10) {
            $unmatchedSamples[] = $jsonData;
        }
    }
}

echo "\n─── JSON ↔ DB MATCHING ───\n";
echo "Matched:     {$matchStats['matched']}\n";
echo "Not matched: {$matchStats['not_matched']}\n";
echo "Multi kode_unit: {$matchStats['multi_kode_unit']}\n";
echo "Kode_unit not in unit_kompetensis: {$matchStats['json_kode_not_in_unit_table']}\n";

// ═══════════════════════════════════════════════════════════════
// 6. DIRECT RESOLVE: For soals with unit but null subunit/kuk
//    (bypass JSON, just traverse hierarchy)
// ═══════════════════════════════════════════════════════════════
echo "\n─── DIRECT HIERARCHY RESOLVE (unit → subunit → kuk) ───\n";

// Get all soals that have id_unitkompetensi but null id_subunitkompetensi or id_kuk
$orphanSoals = DB::table('kan_soals as s')
    ->join('jabatan_dokumens as jd', 's.id_dokumen', '=', 'jd.id')
    ->join('jabatan_kerjas as jk', 'jd.id_jabatan', '=', 'jk.id_jabatan_kerja')
    ->whereNotNull('s.id_unitkompetensi')
    ->where(function($q) {
        $q->whereNull('s.id_subunitkompetensi')
          ->orWhereNull('s.id_kuk');
    })
    ->select(
        's.id', 's.no', 's.id_dokumen',
        's.id_unitkompetensi', 's.id_subunitkompetensi', 's.id_kuk',
        'jd.kode as dok_kode',
        'jk.jabatan_kerja', 'jk.id_jabatan_kerja'
    )
    ->get();

echo "Soals with unit but missing subunit/kuk: " . count($orphanSoals) . "\n";

$directFixed = 0;
$directUnfixable = 0;
$unfixableReasons = [
    'no_subunit' => 0,   // unit has no subunit
    'no_kuk' => 0,       // subunit has no kuk
];

foreach ($orphanSoals as $soal) {
    $soalId = $soal->id;

    // Skip if already in fixLog (will be handled by JSON path)
    if (isset($fixLog[$soalId])) continue;

    $unitId = $soal->id_unitkompetensi;
    $unitKode = $unitIdToKode[$unitId] ?? '?';

    // Find subunit
    $subunits = $subunitsByUnitId[$unitId] ?? [];
    $subunitId = null;
    $subunitKode = null;
    if (count($subunits) >= 1) {
        $subunitId = $subunits[0]->id;
        $subunitKode = $subunits[0]->kode;
    }

    // Find KUK
    $kukId = null;
    $kukKode = null;
    if ($subunitId && isset($kuksBySubunitId[$subunitId])) {
        $kuks = $kuksBySubunitId[$subunitId];
        if (count($kuks) >= 1) {
            $kukId = $kuks[0]->id;
            $kukKode = $kuks[0]->kode;
        }
    }

    $needsFix = false;
    $fixData = [
        'soal_id' => $soalId,
        'no' => $soal->no,
        'dok_kode' => $soal->dok_kode,
        'jabatan' => $soal->jabatan_kerja,
    ];

    // Only fill NULL fields, don't overwrite existing
    if (is_null($soal->id_unitkompetensi)) {
        $fixData['id_unitkompetensi'] = $unitId;
        $fixData['kode_unit'] = $unitKode;
        $needsFix = true;
    }

    if (is_null($soal->id_subunitkompetensi) && $subunitId) {
        $fixData['id_subunitkompetensi'] = $subunitId;
        $fixData['subunit_kode'] = $subunitKode;
        $needsFix = true;
    } elseif (!$subunitId) {
        $directUnfixable++;
        $unfixableReasons['no_subunit']++;
        continue;
    }

    if (is_null($soal->id_kuk) && $kukId) {
        $fixData['id_kuk'] = $kukId;
        $fixData['kuk_kode'] = $kukKode;
        $needsFix = true;
    } elseif (!$kukId) {
        $directUnfixable++;
        $unfixableReasons['no_kuk']++;
        continue;
    }

    if ($needsFix) {
        $fixLog[$soalId] = $fixData;
        $directFixed++;
    }
}

echo "Fixable via direct hierarchy: {$directFixed}\n";
echo "Unfixable via direct hierarchy: {$directUnfixable}\n";
if ($directUnfixable > 0) {
    echo "  - No subunit for unit: {$unfixableReasons['no_subunit']}\n";
    echo "  - No KUK for subunit: {$unfixableReasons['no_kuk']}\n";
}

// ═══════════════════════════════════════════════════════════════
// 7. STATS BY KOLOM
// ═══════════════════════════════════════════════════════════════
echo "\n─── FIX PLAN ───\n";

$fixUnit = 0;
$fixSubunit = 0;
$fixKuk = 0;
foreach ($fixLog as $f) {
    if (isset($f['id_unitkompetensi'])) $fixUnit++;
    if (isset($f['id_subunitkompetensi'])) $fixSubunit++;
    if (isset($f['id_kuk'])) $fixKuk++;
}

echo "Total unique soal to fix: " . count($fixLog) . "\n";
echo "  - Will set id_unitkompetensi: {$fixUnit}\n";
echo "  - Will set id_subunitkompetensi: {$fixSubunit}\n";
echo "  - Will set id_kuk: {$fixKuk}\n";

// ═══════════════════════════════════════════════════════════════
// 8. DETAIL PER JABATAN (problem areas)
// ═══════════════════════════════════════════════════════════════
echo "\n─── JABATAN WITH NULL KUK (TOP 20) ───\n";

$perJabatan = [];
foreach ($allSoals as $s) {
    $jkId = $s->id_jabatan_kerja;
    if (!isset($perJabatan[$jkId])) {
        $perJabatan[$jkId] = ['name' => $s->jabatan_kerja, 'total' => 0, 'null_kuk' => 0];
    }
    $perJabatan[$jkId]['total']++;
    if (is_null($s->id_kuk)) $perJabatan[$jkId]['null_kuk']++;
}

uasort($perJabatan, fn($a, $b) => $b['null_kuk'] - $a['null_kuk']);

echo str_pad("ID", 12) . str_pad("Jabatan Kerja", 65) . str_pad("Total", 8) . str_pad("Null KUK", 10) . "\n";
echo str_repeat("─", 95) . "\n";
$shown = 0;
foreach ($perJabatan as $jkId => $data) {
    if ($data['null_kuk'] == 0) continue;
    echo str_pad($jkId, 12)
        . str_pad(mb_substr($data['name'], 0, 63), 65)
        . str_pad($data['total'], 8)
        . str_pad($data['null_kuk'], 10) . "\n";
    $shown++;
    if ($shown >= 20) break;
}
echo "\nTotal jabatan with null KUK: {$shown}\n";

// ═══════════════════════════════════════════════════════════════
// 9. MULTI KODE_UNIT SAMPLES
// ═══════════════════════════════════════════════════════════════
if (!empty($multiUnitSoals)) {
    echo "\n─── MULTI KODE_UNIT SOALS (first 5) ───\n";
    foreach (array_slice($multiUnitSoals, 0, 5) as $i => $mu) {
        echo "  #" . ($i+1) . ": {$mu['skema_name']} | no={$mu['no']} | tipe={$mu['tipe_key']}\n";
        echo "     DB matches: {$mu['db_match_count']}\n";
        echo "     Kode units (" . count($mu['kode_units']) . "):\n";
        foreach ($mu['kode_units'] as $ku) {
            $found = isset($unitKodeToId[$ku]) ? "✓" : "✗ NOT FOUND";
            echo "       - {$ku} [{$found}]\n";
        }
        echo "     Soal: {$mu['soal_text']}...\n\n";
    }
    echo "Total multi kode_unit soals: " . count($multiUnitSoals) . "\n";
}

// ═══════════════════════════════════════════════════════════════
// 10. KODE_UNIT NOT IN DB
// ═══════════════════════════════════════════════════════════════
if (!empty($kodeUnitNotFound)) {
    echo "\n─── KODE_UNIT NOT IN unit_kompetensis TABLE ───\n";
    arsort($kodeUnitNotFound);
    foreach (array_slice($kodeUnitNotFound, 0, 20) as $kode => $count) {
        echo "  {$kode}: {$count} soals\n";
    }
    if (count($kodeUnitNotFound) > 20) {
        echo "  ... and " . (count($kodeUnitNotFound) - 20) . " more\n";
    }
}

// ═══════════════════════════════════════════════════════════════
// 11. UNMATCHED SAMPLES (JSON → DB name mismatch)
// ═══════════════════════════════════════════════════════════════
if (!empty($unmatchedSamples)) {
    echo "\n─── JSON SOAL NOT FOUND IN DB (first 5) ───\n";
    foreach (array_slice($unmatchedSamples, 0, 5) as $s) {
        echo "  JSON key: {$s['skema_name']}\n";
        echo "  Normalized: " . normalizeSkemaName($s['skema_name']) . "\n";
        echo "  No: {$s['no']}, Tipe: {$s['tipe_key']}\n";
        echo "  Kode Unit DB: " . ($s['kode_unit_db'] ?? '?') . "\n";
        echo "  KUK Code: " . ($s['kuk_code'] ?? '?') . "\n";
        echo "  Soal: {$s['soal_text']}...\n\n";
    }

    // Debug: show some DB names that are similar
    if (count($unmatchedSamples) > 0) {
        echo "  ── Closest DB name matches for first unmatched: ──\n";
        $sampleNorm = normalizeSkemaName($unmatchedSamples[0]['skema_name']);
        $closest = [];
        foreach ($dbByNormName as $dbName => $rows) {
            similar_text($sampleNorm, $dbName, $pct);
            if ($pct > 50) {
                $closest[$dbName] = $pct;
            }
        }
        arsort($closest);
        foreach (array_slice($closest, 0, 3) as $name => $pct) {
            echo "    '{$name}' ({$pct}%)\n";
        }
    }
}

// ═══════════════════════════════════════════════════════════════
// 12. APPLY FIXES
// ═══════════════════════════════════════════════════════════════
if ($isFix && count($fixLog) > 0) {
    echo "\n";
    echo str_repeat("═", 60) . "\n";
    echo "  APPLYING FIXES\n";
    echo str_repeat("═", 60) . "\n\n";

    $updated = 0;
    $errors = 0;

    foreach ($fixLog as $soalId => $fix) {
        $updateData = ['updated_at' => now()];

        if (isset($fix['id_unitkompetensi'])) {
            $updateData['id_unitkompetensi'] = $fix['id_unitkompetensi'];
        }
        if (isset($fix['id_subunitkompetensi'])) {
            $updateData['id_subunitkompetensi'] = $fix['id_subunitkompetensi'];
        }
        if (isset($fix['id_kuk'])) {
            $updateData['id_kuk'] = $fix['id_kuk'];
        }

        try {
            DB::table('kan_soals')->where('id', $soalId)->update($updateData);

            if ($updated < 15 || $updated % 200 === 0) {
                $parts = [];
                if (isset($fix['kode_unit'])) $parts[] = "unit={$fix['kode_unit']}";
                if (isset($fix['subunit_kode'])) $parts[] = "subunit={$fix['subunit_kode']}";
                if (isset($fix['kuk_kode'])) $parts[] = "kuk={$fix['kuk_kode']}";
                echo "  ✓ soal_id={$soalId} (no={$fix['no']}, {$fix['dok_kode']}): " . implode(' → ', $parts) . "\n";
            }

            $updated++;
        } catch (\Exception $e) {
            echo "  ✗ ERROR soal_id={$soalId}: {$e->getMessage()}\n";
            $errors++;
        }
    }

    echo "\n─── FIX RESULT ───\n";
    echo "Updated: {$updated}\n";
    echo "Errors: {$errors}\n";

    // Re-check
    $nullUnitAfter = DB::table('kan_soals')->whereNull('id_unitkompetensi')->count();
    $nullSubunitAfter = DB::table('kan_soals')->whereNull('id_subunitkompetensi')->count();
    $nullKukAfter = DB::table('kan_soals')->whereNull('id_kuk')->count();

    echo "\n─── AFTER FIX ───\n";
    echo "NULL id_unitkompetensi:    {$nullUnitAfter} (was {$nullUnit})\n";
    echo "NULL id_subunitkompetensi:  {$nullSubunitAfter} (was {$nullSubunit})\n";
    echo "NULL id_kuk:                {$nullKukAfter} (was {$nullKuk})\n";

    $reduction = $nullKuk - $nullKukAfter;
    echo "\nKUK filled: {$reduction} records\n";
}

if (!$isFix && count($fixLog) > 0) {
    echo "\n";
    echo str_repeat("═", 60) . "\n";
    echo "  READY TO FIX: " . count($fixLog) . " soal records\n";
    echo "  Run: php audit_kuk.php --fix\n";
    echo str_repeat("═", 60) . "\n";
}

if ($isDetail && !empty($fixLog)) {
    echo "\n─── DETAIL FIX LOG (first 30) ───\n";
    $i = 0;
    foreach ($fixLog as $soalId => $fix) {
        echo "  soal_id={$soalId} | no={$fix['no']} | {$fix['dok_kode']} | {$fix['jabatan']}\n";
        if (isset($fix['kode_unit'])) echo "    → unit: {$fix['kode_unit']}\n";
        if (isset($fix['subunit_kode'])) echo "    → subunit: {$fix['subunit_kode']}\n";
        if (isset($fix['kuk_kode'])) echo "    → kuk: {$fix['kuk_kode']}\n";
        $i++;
        if ($i >= 30) break;
    }
    echo "  ... and " . (count($fixLog) - 30) . " more\n";
}

echo "\nDone.\n";
