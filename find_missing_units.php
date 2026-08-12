<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$json = json_decode(file_get_contents('c:/Users/Rafie Pradipta/Downloads/ALL_SOAL_MERGED_FIXED.json'), true);

$unitKodeToId = [];
$allUnits = DB::table('unit_kompetensis')->select('id', 'kode')->get();
foreach ($allUnits as $u) { $unitKodeToId[$u->kode] = $u->id; }

// Collect ALL unique unit codes from JSON kode_kuk (first part before \n)
$jsonUnitCodes = [];
foreach ($json as $name => $data) {
    foreach (['ia05', 'ia06', 'ia04b'] as $t) {
        foreach ($data[$t] ?? [] as $s) {
            $kk = $s['kode_kuk'] ?? '';
            if (empty($kk)) continue;
            $parts = preg_split('/[\s\n]+/', $kk, 2);
            $unitCandidate = $parts[0] ?? '';
            if (!empty($unitCandidate)) {
                $jsonUnitCodes[$unitCandidate] = true;
            }
        }
    }
}

echo "Unique unit codes from JSON kode_kuk: " . count($jsonUnitCodes) . "\n";

// Check which ones are MISSING from DB
$missing = [];
$found = [];
foreach ($jsonUnitCodes as $code => $_) {
    if (isset($unitKodeToId[$code])) {
        $found[$code] = $unitKodeToId[$code];
    } else {
        $missing[$code] = true;
    }
}

echo "Found in DB: " . count($found) . "\n";
echo "MISSING from DB: " . count($missing) . "\n";

if (count($missing) > 0) {
    echo "\n─── MISSING UNIT CODES (first 50) ───\n";
    $missingList = array_keys($missing);
    sort($missingList);
    $i = 0;
    foreach ($missingList as $code) {
        echo "  $code\n";
        $i++;
        if ($i >= 50) { echo "  ...+" . (count($missingList) - 50) . " more\n"; break; }
    }

    // Count how many JSON entries use each missing unit code
    echo "\n\n─── MISSING UNIT CODES WITH ENTRY COUNTS ───\n";
    $missingCounts = [];
    foreach ($json as $name => $data) {
        foreach (['ia05', 'ia06'] as $t) {
            foreach ($data[$t] ?? [] as $s) {
                $kk = $s['kode_kuk'] ?? '';
                if (empty($kk)) continue;
                $parts = preg_split('/[\s\n]+/', $kk, 2);
                $unitCandidate = $parts[0] ?? '';
                if (isset($missing[$unitCandidate])) {
                    if (!isset($missingCounts[$unitCandidate])) $missingCounts[$unitCandidate] = 0;
                    $missingCounts[$unitCandidate]++;
                }
            }
        }
    }
    arsort($missingCounts);
    foreach ($missingCounts as $code => $cnt) {
        echo "  $code → $cnt entries affected\n";
    }
}

// Also check: which DB unit codes look similar to the missing ones?
echo "\n\n─── POSSIBLE MATCHES FOR TOP MISSING UNITS ───\n";
$topMissing = array_slice(array_keys($missingCounts), 0, 10);
foreach ($topMissing as $mc) {
    echo "  Missing: $mc\n";
    // Try to find similar codes in DB
    foreach ($unitKodeToId as $dbCode => $dbId) {
        $lev = levenshtein($mc, $dbCode);
        if ($lev <= 2 && $lev > 0) {
            echo "    → similar: $dbCode (dist=$lev)\n";
        }
    }
    // Also try fuzzy: remove spaces, normalize
    $normalized = str_replace(' ', '', $mc);
    foreach ($unitKodeToId as $dbCode => $dbId) {
        if (str_replace(' ', '', $dbCode) === $normalized && $dbCode !== $mc) {
            echo "    → space-diff: $dbCode\n";
        }
    }
}

echo "\nDone.\n";
