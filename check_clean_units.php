<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;

echo "=== CLEAN vs SPACED UNITS ===\n\n";

// Check clean units
$cleanCodes = ['F.410140.001.01', 'F.410140.002.01', 'F.410140.003.01', 'F.410140.004.01', 'F.410140.005.01',
               'F.42ATJ00.001.2', 'F.42ATJ00.002.2', 'F.42ATJ00.003.2', 'F.42ATJ00.004.2', 'F.42ATJ00.007.2'];
echo "CLEAN units:\n";
foreach ($cleanCodes as $code) {
    $unit = DB::table('unit_kompetensis')->where('kode', $code)->first();
    if ($unit) {
        $su = DB::table('subunit_kompetensis')->where('id_unitkompetensi', $unit->id)->count();
        $so = DB::table('kan_soals')->where('id_unitkompetensi', $unit->id)->count();
        echo sprintf("  %s id=%d: %d subunits, %d soals", $code, $unit->id, $su, $so);
        if ($su === 0) echo " <-- NO SUBUNITS!";
        echo "\n";
    }
}

echo "\nSPACED units with soals:\n";
$spacedUnits = DB::table('unit_kompetensis')
    ->where('kode', 'like', '% %')
    ->select('id', 'kode')
    ->get();
foreach ($spacedUnits as $u) {
    $su = DB::table('subunit_kompetensis')->where('id_unitkompetensi', $u->id)->count();
    $so = DB::table('kan_soals')->where('id_unitkompetensi', $u->id)->count();
    if ($so > 0 || $su > 0) {
        // Find matching clean unit
        $cleanKode = str_replace(' ', '', $u->kode);
        $cleanUnit = DB::table('unit_kompetensis')->where('kode', $cleanKode)->first();
        $cleanId = $cleanUnit ? $cleanUnit->id : 'N/A';
        $cleanSu = $cleanUnit ? DB::table('subunit_kompetensis')->where('id_unitkompetensi', $cleanUnit->id)->count() : 0;
        echo sprintf("  [%s] id=%d: %d subunits, %d soals → clean [%s] id=%s: %d subunits",
                     $u->kode, $u->id, $su, $so, $cleanKode, $cleanId, $cleanSu);
        if ($su > 0 && $cleanSu === 0) echo " <-- NEEDS SUBUNIT MIGRATION!";
        echo "\n";
    }
}

echo "\nDone.\n";
