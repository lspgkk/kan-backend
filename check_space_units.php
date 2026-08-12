<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== DUPLICATE UNIT CODES WITH/WITHOUT SPACES ===\n\n";

// Check for units with spaces
$spaceUnits = DB::table('unit_kompetensis')
    ->where('kode', 'like', '% %')
    ->select('id', 'kode')
    ->get();

echo "Units with spaces in kode: " . count($spaceUnits) . "\n";
echo "Samples:\n";
foreach ($spaceUnits->take(20) as $u) {
    echo "  id={$u->id} kode=[{$u->kode}]\n";
}

// Check how many soals point to these spaced units
$spaceUnitIds = $spaceUnits->pluck('id')->toArray();
$soalCount = 0;
if (!empty($spaceUnitIds)) {
    $soalCount = DB::table('kan_soals')
        ->whereIn('id_unitkompetensi', $spaceUnitIds)
        ->count();
}
echo "\nSoals pointing to spaced units: {$soalCount}\n";

// Also check: how many 'F.41' prefix units have spaces
echo "\nUnits with spaces in IA05 range:\n";
$spaceUnitsIA05 = DB::table('unit_kompetensis')
    ->where('kode', 'like', 'F.%')
    ->where('kode', 'like', '% %')
    ->select('id', 'kode')
    ->get();
echo "Count: " . count($spaceUnitsIA05) . "\n";
foreach ($spaceUnitsIA05->take(30) as $u) {
    $count = DB::table('kan_soals')->where('id_unitkompetensi', $u->id)->count();
    echo "  id={$u->id} kode=[{$u->kode}] → {$count} soals\n";
}

// Check for M.71 prefix units with spaces
echo "\nUnits with spaces in M.71 range:\n";
$spaceUnitsM71 = DB::table('unit_kompetensis')
    ->where('kode', 'like', 'M.%')
    ->where('kode', 'like', '% %')
    ->select('id', 'kode')
    ->get();
echo "Count: " . count($spaceUnitsM71) . "\n";
foreach ($spaceUnitsM71->take(20) as $u) {
    $count = DB::table('kan_soals')->where('id_unitkompetensi', $u->id)->count();
    echo "  id={$u->id} kode=[{$u->kode}] → {$count} soals\n";
}

echo "\nDone.\n";
