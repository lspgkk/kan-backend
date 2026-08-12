<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== DIAGNOSTIC: unit ↔ subunit ↔ kuk relationship ===\n\n";

// 1. Show some sample soals with their unit IDs
echo "--- Sample kan_soals with unit info ---\n";
$samples = DB::table('kan_soals')
    ->whereNotNull('id_unitkompetensi')
    ->select('id', 'no', 'id_unitkompetensi')
    ->limit(5)
    ->get();

foreach ($samples as $s) {
    $unit = DB::table('unit_kompetensis')->where('id', $s->id_unitkompetensi)->first();
    echo "  soal_id={$s->id} no={$s->no} unit_id={$s->id_unitkompetensi}";
    if ($unit) {
        echo " unit_kode={$unit->kode} unit_nama=" . mb_substr($unit->nama, 0, 40) . "\n";
    } else {
        echo " UNIT NOT FOUND!\n";
    }
}

// 2. Check if subunit references match unit IDs
echo "\n--- Subunit distribution ---\n";
$subunitUnitIds = DB::table('subunit_kompetensis')
    ->select('id_unitkompetensi', DB::raw('COUNT(*) as cnt'))
    ->groupBy('id_unitkompetensi')
    ->orderBy('cnt', 'desc')
    ->limit(10)
    ->get();

echo "Top 10 id_unitkompetensi values in subunit_kompetensis:\n";
foreach ($subunitUnitIds as $su) {
    $unit = DB::table('unit_kompetensis')->where('id', $su->id_unitkompetensi)->first();
    $found = $unit ? "✓ {$unit->kode}" : "✗ NOT FOUND";
    echo "  unit_id={$su->id_unitkompetensi} cnt={$su->cnt} [{$found}]\n";
}

// 3. Check a specific unit from kan_soals
echo "\n--- Check a specific unit from kan_soals ---\n";
$randomSoal = DB::table('kan_soals')->whereNotNull('id_unitkompetensi')->whereNotNull('id_kuk')->first();
if ($randomSoal) {
    echo "  soal_id={$randomSoal->id} id_unitkompetensi={$randomSoal->id_unitkompetensi} id_kuk={$randomSoal->id_kuk}\n";

    // What unit?
    $unit = DB::table('unit_kompetensis')->where('id', $randomSoal->id_unitkompetensi)->first();
    echo "  unit: " . ($unit ? "id={$unit->id} kode={$unit->kode}" : "NOT FOUND") . "\n";

    // What subunits belong to this unit?
    $subs = DB::table('subunit_kompetensis')->where('id_unitkompetensi', $randomSoal->id_unitkompetensi)->get();
    echo "  subunits for this unit: " . count($subs) . "\n";
    foreach ($subs as $sub) {
        echo "    subunit id={$sub->id} kode={$sub->kode}\n";
        $kuks = DB::table('kuks')->where('id_subunitkompetensi', $sub->id)->limit(3)->get();
        foreach ($kuks as $k) {
            echo "      → kuk id={$k->id} kode={$k->kode}\n";
        }
    }

    // What kuk is currently assigned?
    $kuk = DB::table('kuks')->where('id', $randomSoal->id_kuk)->first();
    echo "  current kuk: " . ($kuk ? "id={$kuk->id} kode={$kuk->kode}" : "NOT FOUND") . "\n";
    if ($kuk) {
        $kukSubunit = DB::table('subunit_kompetensis')->where('id', $kuk->id_subunitkompetensi)->first();
        echo "  kuk's subunit: " . ($kukSubunit ? "id={$kukSubunit->id} kode={$kukSubunit->kode} unit_id={$kukSubunit->id_unitkompetensi}" : "NOT FOUND") . "\n";
    }
}

// 4. Check a soal that has null id_kuk
echo "\n--- Check soal with NULL id_kuk ---\n";
$nullKukSoal = DB::table('kan_soals')->whereNotNull('id_unitkompetensi')->whereNull('id_kuk')->first();
if ($nullKukSoal) {
    echo "  soal_id={$nullKukSoal->id} id_unitkompetensi={$nullKukSoal->id_unitkompetensi}\n";
    $unit = DB::table('unit_kompetensis')->where('id', $nullKukSoal->id_unitkompetensi)->first();
    echo "  unit: " . ($unit ? "id={$unit->id} kode={$unit->kode}" : "NOT FOUND") . "\n";
    $subs = DB::table('subunit_kompetensis')->where('id_unitkompetensi', $nullKukSoal->id_unitkompetensi)->get();
    echo "  subunits: " . count($subs) . "\n";
    foreach ($subs as $sub) {
        echo "    subunit id={$sub->id} kode={$sub->kode}\n";
        $kuks = DB::table('kuks')->where('id_subunitkompetensi', $sub->id)->limit(3)->get();
        foreach ($kuks as $k) {
            echo "      → kuk id={$k->id} kode={$k->kode}\n";
        }
    }
}

// 5. Verify: does the unit_kompetensis table have a different PK?
echo "\n--- Unit Kompetensi table structure ---\n";
$firstUnit = DB::table('unit_kompetensis')->first();
if ($firstUnit) {
    echo "  Columns: " . implode(', ', array_keys((array)$firstUnit)) . "\n";
    echo "  First: id={$firstUnit->id} kode={$firstUnit->kode}\n";
}

echo "\n--- Subunit Kompetensi table structure ---\n";
$firstSub = DB::table('subunit_kompetensis')->first();
if ($firstSub) {
    echo "  Columns: " . implode(', ', array_keys((array)$firstSub)) . "\n";
    echo "  First: id={$firstSub->id} kode={$firstSub->kode} id_unitkompetensi={$firstSub->id_unitkompetensi}\n";
}

echo "\n--- Kuks table structure ---\n";
$firstKuk = DB::table('kuks')->first();
if ($firstKuk) {
    echo "  Columns: " . implode(', ', array_keys((array)$firstKuk)) . "\n";
    echo "  First: id={$firstKuk->id} kode={$firstKuk->kode} id_subunitkompetensi={$firstKuk->id_subunitkompetensi}\n";
}

echo "\nDone.\n";
