<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== DIAGNOSIS: NO-SEPARATOR KUK ENTRIES ===\n\n";

$json = json_decode(file_get_contents('c:/Users/Rafie Pradipta/Downloads/ALL_SOAL_MERGED_FIXED.json'), true);

// Check all three reported cases + find any others with this pattern
$targetKeywords = [
    'BANGUNAN RISHA' => ['IA05 only', null],
    'TEKNISI GEOTEKNIK (LEVEL 5)' => ['IA05 only', null],
    'SPESIALIS SIG' => ['IA05 only', null],
];

// Load unit kode→id map
$unitKodeToId = [];
$allUnits = DB::table('unit_kompetensis')->select('id', 'kode')->get();
foreach ($allUnits as $u) {
    $unitKodeToId[$u->kode] = $u->id;
}

// Find all JSON entries where kode_kuk has no separator AND kode_unit is NULL/empty
$allNoSep = [];
foreach ($json as $jabatanName => $jabatanData) {
    foreach (['ia05', 'ia06', 'ia04b'] as $tipe) {
        $items = $jabatanData[$tipe] ?? [];
        foreach ($items as $item) {
            $kodeUnit = $item['kode_unit'] ?? null;
            $kodeKuk = $item['kode_kuk'] ?? null;
            if (empty($kodeUnit) && !empty($kodeKuk)) {
                // Check if there's NO whitespace/newline in kode_kuk
                if (preg_match('/[\s\n]/', $kodeKuk)) {
                    // Has separator — was handled by fix script
                    continue;
                }
                // No separator! This was missed
                if (!isset($allNoSep[$jabatanName])) $allNoSep[$jabatanName] = ['ia05' => 0, 'ia06' => 0, 'ia04b' => 0];
                $allNoSep[$jabatanName][$tipe]++;
            }
        }
    }
}

echo "All jabatan with no-separator kode_kuk (not fixed):\n\n";
foreach ($allNoSep as $name => $counts) {
    $total = array_sum($counts);
    echo "  $name:\n";
    foreach ($counts as $tipe => $cnt) {
        if ($cnt > 0) echo "    $tipe: $cnt\n";
    }
}

// Now analyze the three specific cases
echo "\n=== DETAILED ANALYSIS OF 3 REPORTED CASES ===\n";

$reported = [
    ['keyword' => 'BANGUNAN RISHA', 'subkeyword' => 'ANALIS', 'desc' => 'ANALIS STRUKTUR BANGUNAN RISHA'],
    ['keyword' => 'TEKNISI GEOTEKNIK', 'subkeyword' => 'LEVEL 5', 'desc' => 'TEKNISI GEOTEKNIK (LEVEL 5)'],
    ['keyword' => 'SPESIALIS SIG', 'subkeyword' => '', 'desc' => 'SPESIALIS SIG'],
];

foreach ($reported as $r) {
    $jsonKey = null;
    foreach (array_keys($json) as $k) {
        $match = (stripos($k, $r['keyword']) !== false);
        if ($match && !empty($r['subkeyword'])) {
            $match = $match && (stripos($k, $r['subkeyword']) !== false);
        }
        if ($match) { $jsonKey = $k; break; }
    }

    if (!$jsonKey) { echo "\n{$r['desc']}: NOT FOUND\n"; continue; }

    echo "\n--- {$r['desc']} ---\n";
    echo "JSON key: $jsonKey\n";

    $ia05 = $json[$jsonKey]['ia05'] ?? [];
    $noSepCount = 0;
    $noSepSamples = [];

    foreach ($ia05 as $item) {
        $ku = $item['kode_unit'] ?? '';
        $kk = $item['kode_kuk'] ?? '';
        if (empty($ku) && !empty($kk) && !preg_match('/[\s\n]/', $kk)) {
            $noSepCount++;
            if (count($noSepSamples) < 15) {
                $noSepSamples[] = ['no' => $item['no'], 'kode_kuk' => $kk];
            }
        }
    }

    echo "IA05 with no-separator: $noSepCount\n";
    echo "Samples:\n";
    foreach ($noSepSamples as $s) {
        // Try to determine unit + KUK
        $kk = $s['kode_kuk'];
        $parsed = trySplitConcatenated($kk, $unitKodeToId);
        echo sprintf("  no=%d: %s → unit=%s, kuk=%s (method: %s)\n",
            $s['no'], $kk,
            $parsed['unit_kode'] ?? '?',
            $parsed['kuk_kode'] ?? '?',
            $parsed['method'] ?? 'none'
        );
    }
}

echo "\n=== PROPOSED FIX ALGORITHM ===\n";
echo "For kode_kuk without separator:\n";
echo "  1. Try to split at last digit boundary where unit is known in DB\n";
echo "  2. Pattern: unit code ends with .N, KUK code starts with N.N\n";
echo "  3. Ex: F.41GED01.001.11.1 → unit F.41GED01.001.1 + KUK 1.1\n";
echo "  4. Need to try different splits until unit matches DB\n\n";

function trySplitConcatenated(string $kodeKuk, array $unitKodeToId): array {
    // Try to find the split point by trying all possible KUK extractions
    // KUK format: N.N, N.NN, NN.N, etc. (at least \d+\.\d+)
    // The unit code is the prefix that matches a known unit in DB

    // Strategy: progressively strip \d+\.\d+ from the end and check if prefix matches a unit
    $len = strlen($kodeKuk);
    for ($i = $len - 3; $i >= 0; $i--) {
        // Check if position $i starts a valid KUK pattern: \d+\.\d+
        if (preg_match('/^(\d+\.\d+)$/', substr($kodeKuk, $i), $m)) {
            $candidateKuk = $m[1];
            $candidateUnit = substr($kodeKuk, 0, $i);

            // Check if candidateUnit matches a known unit in DB
            foreach ($unitKodeToId as $unitKode => $unitId) {
                // Try exact match
                if ($candidateUnit === $unitKode) {
                    return [
                        'unit_kode' => $unitKode,
                        'unit_id' => $unitId,
                        'kuk_kode' => $candidateKuk,
                        'method' => 'exact'
                    ];
                }
            }

            // If not found, also try without the trailing digit of unit
            // (unit code might have been truncated, e.g., F.41XXX.001 instead of F.41XXX.001.1)
            foreach ($unitKodeToId as $unitKode => $unitId) {
                if (strpos($unitKode, $candidateUnit) === 0) {
                    // candidateUnit is a prefix of some unit code
                    // But we need to be more careful...
                }
            }
        }
    }

    // Try another approach: check if removing the last N.N gives a valid unit prefix
    // Then progressively try longer matches
    return ['method' => 'none'];
}

echo "\nDone.\n";
