<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo str_repeat("=", 80) . "\n";
echo "  FINAL VERIFICATION: ALL KUK NULL & MISMATCH CHECK\n";
echo str_repeat("=", 80) . "\n\n";

// ─── 1. CHECK FOR NULL KUK ───
echo "─── NULL KUK CHECK ───\n";
$nullKuk = DB::table('kan_soals as s')
    ->join('jabatan_dokumens as jd', 's.id_dokumen', '=', 'jd.id')
    ->join('jabatan_kerjas as jk', 'jd.id_jabatan', '=', 'jk.id_jabatan_kerja')
    ->whereNull('s.id_kuk')
    ->select('s.id', 's.no', 'jd.kode as dok_kode', 'jk.jabatan_kerja')
    ->get();

$nullByJabatan = [];
foreach ($nullKuk as $s) {
    $key = $s->jabatan_kerja . ' | ' . $s->dok_kode;
    $nullByJabatan[$key][] = $s->no;
}

// Only count IA05 and IA06 (IA04b doesn't need KUK)
$criticalNull = $nullKuk->filter(function($s) { return $s->dok_kode !== 'FR.IA.04.B'; });

echo "NULL id_kuk (IA05/IA06 only): " . count($criticalNull) . "\n";
echo "NULL id_kuk (all including IA04b): " . count($nullKuk) . "\n";

if (count($criticalNull) > 0) {
    echo "\n  ❌ CRITICAL NULL KUKS FOUND:\n";
    foreach ($nullByJabatan as $jabatan => $nos) {
        echo "  $jabatan → nos: " . implode(', ', array_slice($nos, 0, 20));
        if (count($nos) > 20) echo " ...+" . (count($nos) - 20) . " more";
        echo "\n";
    }
} else {
    echo "\n  ✅ NO NULL KUK in IA05/IA06!\n";
}

// ─── 2. CHECK AGAINST JSON ───
echo "\n─── JSON vs DB KUK COMPARISON ───\n";

$json = json_decode(file_get_contents('c:/Users/Rafie Pradipta/Downloads/ALL_SOAL_MERGED_FIXED.json'), true);

$unitKodeToId = [];
$allUnits = DB::table('unit_kompetensis')->select('id', 'kode')->get();
foreach ($allUnits as $u) { $unitKodeToId[$u->kode] = $u->id; }

$subunitsByUnitId = [];
$allSubunits = DB::table('subunit_kompetensis')->select('id', 'kode', 'id_unitkompetensi')->get();
foreach ($allSubunits as $su) { $subunitsByUnitId[$su->id_unitkompetensi][] = $su; }

$kuksBySubunitId = [];
$allKuks = DB::table('kuks')->select('id', 'kode', 'id_subunitkompetensi')->get();
foreach ($allKuks as $k) { $kuksBySubunitId[$k->id_subunitkompetensi][] = $k; }

function normalizeName(string $s): string {
    return mb_strtolower(trim(preg_replace('/^\d+\.\s*/', '', $s)));
}

$dokToTipe = ['FR.IA.04.B' => 'ia04b', 'FR.IA.05' => 'ia05', 'FR.IA.06' => 'ia06'];

$allSoals = DB::table('kan_soals as s')
    ->join('jabatan_dokumens as jd', 's.id_dokumen', '=', 'jd.id')
    ->join('jabatan_kerjas as jk', 'jd.id_jabatan', '=', 'jk.id_jabatan_kerja')
    ->leftJoin('kuks', 's.id_kuk', '=', 'kuks.id')
    ->select('s.id', 's.no', 's.id_unitkompetensi', 's.id_subunitkompetensi', 's.id_kuk',
             'jd.kode as dok_kode', 'jk.jabatan_kerja', 'kuks.kode as kuk_kode')
    ->get();

// Build DB index
$dbIndex = [];
foreach ($allSoals as $s) {
    $tipeKey = $dokToTipe[$s->dok_kode] ?? null;
    if (!$tipeKey) continue;
    $key = normalizeName($s->jabatan_kerja) . '|' . (int)$s->no . '|' . $tipeKey;
    if (!isset($dbIndex[$key])) $dbIndex[$key] = [];
    $dbIndex[$key][] = $s;
}

function splitConcatenated(string $kodeKuk, array $unitKodeToId): ?array {
    $len = strlen($kodeKuk);
    for ($tryLen = $len - 3; $tryLen >= 0; $tryLen--) {
        $candidateUnit = substr($kodeKuk, 0, $tryLen);
        $candidateKuk = substr($kodeKuk, $tryLen);
        if (!preg_match('/^\d+(?:\.\d+)+$/', $candidateKuk)) continue;
        if (isset($unitKodeToId[$candidateUnit])) {
            return ['unit_kode' => $candidateUnit, 'unit_id' => $unitKodeToId[$candidateUnit], 'kuk_kode' => $candidateKuk];
        }
    }
    return null;
}

$mismatches = [];
$totalChecked = 0;
$matched = 0;

foreach ($json as $jabatanName => $jabatanData) {
    $normName = normalizeName($jabatanName);

    foreach (['ia05', 'ia06'] as $tipe) {  // Only IA05/IA06 need KUK
        if (!isset($jabatanData[$tipe])) continue;
        foreach ($jabatanData[$tipe] as $item) {
            $kodeUnit = $item['kode_unit'] ?? '';
            $kodeKuk = $item['kode_kuk'] ?? '';
            $no = (int)($item['no'] ?? 0);
            if (empty($kodeKuk)) continue;

            $key = $normName . '|' . $no . '|' . $tipe;
            $dbSoals = $dbIndex[$key] ?? [];

            if (empty($dbSoals)) continue;

            // Determine expected KUK code
            $expectedKuk = null;
            $hasSep = preg_match('/[\s\n]/', $kodeKuk);

            if (!empty($kodeUnit) && $hasSep) {
                // Standard format: split on separator
                $parts = preg_split('/[\s\n]+/', $kodeKuk, 2);
                $expectedKuk = $parts[1] ?? null;
            } elseif (empty($kodeUnit) && $hasSep) {
                // Has separator but no kode_unit
                $parts = preg_split('/[\s\n]+/', $kodeKuk, 2);
                $expectedKuk = $parts[1] ?? null;
            } elseif (empty($kodeUnit) && !$hasSep) {
                // No separator: try concatenated split
                $parsed = splitConcatenated($kodeKuk, $unitKodeToId);
                $expectedKuk = $parsed['kuk_kode'] ?? null;
            }

            if ($expectedKuk === null) continue;

            // Normalize expected KUK: strip *, trim whitespace/newlines, replace comma with dot
            $expectedKuk = str_replace('*', '', $expectedKuk);
            $expectedKuk = str_replace(',', '.', $expectedKuk);
            $expectedKuk = trim($expectedKuk, " \t\n\r\0\x0B.");
            // If after normalization it's empty, skip
            if ($expectedKuk === '' || $expectedKuk === null) continue;

            $totalChecked++;

            foreach ($dbSoals as $dbSoal) {
                if ($dbSoal->kuk_kode === $expectedKuk) {
                    $matched++;
                } else {
                    $mismatches[] = [
                        'jabatan' => $jabatanName,
                        'tipe' => $tipe,
                        'no' => $no,
                        'expected' => $expectedKuk,
                        'actual' => $dbSoal->kuk_kode,
                        'soal_id' => $dbSoal->id,
                    ];
                }
            }
        }
    }
}

echo "Total entries checked: $totalChecked\n";
echo "Matched: $matched\n";
echo "Mismatches: " . count($mismatches) . "\n";

if (count($mismatches) > 0) {
    echo "\n  ❌ MISMATCHES FOUND:\n";
    // Group by jabatan
    $byJabatan = [];
    foreach ($mismatches as $m) {
        $byJabatan[$m['jabatan']][] = $m;
    }
    foreach ($byJabatan as $name => $items) {
        echo "  $name: " . count($items) . " mismatches\n";
        foreach (array_slice($items, 0, 10) as $m) {
            echo "    no={$m['no']} expected={$m['expected']} actual={$m['actual']} (soal_id={$m['soal_id']})\n";
        }
        if (count($items) > 10) echo "    ...+" . (count($items) - 10) . " more\n";
    }
} else {
    echo "\n  ✅ ALL KUK CODES MATCH JSON!\n";
}

// ─── 3. GLOBAL KUK DIVERSITY CHECK ───
echo "\n─── GLOBAL KUK DIVERSITY ───\n";
foreach (['FR.IA.05', 'FR.IA.06'] as $dk) {
    $totalDk = DB::table('kan_soals as s')
        ->join('jabatan_dokumens as jd', 's.id_dokumen', '=', 'jd.id')
        ->where('jd.kode', $dk)->count();
    $nullDk = DB::table('kan_soals as s')
        ->join('jabatan_dokumens as jd', 's.id_dokumen', '=', 'jd.id')
        ->where('jd.kode', $dk)->whereNull('s.id_kuk')->count();
    $diverse = DB::table('kan_soals as s')
        ->join('jabatan_dokumens as jd', 's.id_dokumen', '=', 'jd.id')
        ->join('kuks', 's.id_kuk', '=', 'kuks.id')
        ->where('jd.kode', $dk)
        ->select('kuks.kode')->distinct()->count();

    echo "$dk: $totalDk total, $nullDk NULL, $diverse unique KUK values\n";
}

echo "\nDone.\n";
