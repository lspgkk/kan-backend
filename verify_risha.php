<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== BANGUNAN RISHA IA05 — DATABASE vs JSON ===\n\n";

// 1. DB state
$soals = DB::table('kan_soals as s')
    ->join('jabatan_dokumens as jd', 's.id_dokumen', '=', 'jd.id')
    ->join('jabatan_kerjas as jk', 'jd.id_jabatan', '=', 'jk.id_jabatan_kerja')
    ->leftJoin('kuks', 's.id_kuk', '=', 'kuks.id')
    ->leftJoin('unit_kompetensis as u', 's.id_unitkompetensi', '=', 'u.id')
    ->leftJoin('subunit_kompetensis as su', 's.id_subunitkompetensi', '=', 'su.id')
    ->where('jk.jabatan_kerja', 'like', '%ANALIS STRUKTUR BANGUNAN RISHA%')
    ->where('jd.kode', 'FR.IA.05')
    ->select('s.id', 's.no', 's.id_unitkompetensi', 's.id_subunitkompetensi', 's.id_kuk',
             'kuks.kode as kuk_kode', 'u.kode as unit_kode', 'su.kode as subunit_kode')
    ->orderBy('s.no')
    ->get();

echo "DB records: " . $soals->count() . "\n";
echo "Nos 1-15:\n";
foreach ($soals->take(15) as $s) {
    echo sprintf("  DB: id=%d, no=%d, unit_kode=%s, subunit_kode=%s, kuk_kode=%s (kuk_id=%s)\n",
        $s->id, $s->no,
        $s->unit_kode ?? 'NULL',
        $s->subunit_kode ?? 'NULL',
        $s->kuk_kode ?? 'NULL',
        $s->id_kuk ?? 'NULL'
    );
}

// 2. JSON state
$json = json_decode(file_get_contents('c:/Users/Rafie Pradipta/Downloads/ALL_SOAL_MERGED_FIXED.json'), true);
$jsonKey = null;
foreach (array_keys($json) as $k) {
    if (stripos($k, 'BANGUNAN RISHA') !== false && stripos($k, 'ANALIS') !== false) {
        $jsonKey = $k;
        break;
    }
}

if ($jsonKey) {
    $ia05 = $json[$jsonKey]['ia05'] ?? [];
    echo "\nJSON nos 1-15:\n";
    for ($i = 0; $i < 15; $i++) {
        $s = $ia05[$i];
        $kodeUnit = $s['kode_unit'] ?? 'NULL';
        $kodeKuk = $s['kode_kuk'] ?? 'NULL';
        echo sprintf("  JSON: no=%d, kode_unit=%s, kode_kuk=%s\n", $s['no'] ?? '?', $kodeUnit, $kodeKuk);
    }
    echo "\nAll unique kode_kuk formats (first 5):\n";
    $seen = [];
    $i = 0;
    foreach ($ia05 as $s) {
        $kk = $s['kode_kuk'] ?? 'NULL';
        if (!isset($seen[$kk]) && $i < 5) {
            $seen[$kk] = true;
            echo "  " . $kk . "\n";
            $i++;
        }
    }
    // Check if any have \n in kode_kuk
    $withSep = 0;
    $withoutSep = 0;
    foreach ($ia05 as $s) {
        $kk = $s['kode_kuk'] ?? '';
        if (strpos($kk, "\n") !== false) $withSep++;
        elseif (!empty($kk)) $withoutSep++;
    }
    echo "\nKode_kuk WITH \\n separator: $withSep\n";
    echo "Kode_kuk WITHOUT separator: $withoutSep\n";
    echo "Empty kode_kuk: " . (count($ia05) - $withSep - $withoutSep) . "\n";
}

// 3. How the fix script parsed kode_kuk for this entry
echo "\n=== HOW FIX SCRIPT PARSES THESE ENTRIES ===\n";
for ($i = 0; $i < 5; $i++) {
    $s = $ia05[$i] ?? null;
    if (!$s) continue;
    $kodeKukRaw = $s['kode_kuk'] ?? '';
    $parts = preg_split('/[\s\n]+/', $kodeKukRaw, 2);
    echo "no={$s['no']}: raw={$kodeKukRaw} → parts=[" . implode('|', $parts) . "]\n";
    echo "  → kode_unit from parts[0]=" . ($parts[0] ?? 'NULL') . ", kuk_kode from parts[1]=" . ($parts[1] ?? 'NULL') . "\n";
}

echo "\nDone.\n";
