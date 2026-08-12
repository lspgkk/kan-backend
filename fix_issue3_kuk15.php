<?php
/**
 * Fix Issue #3: SI092009 no=69 — subunit salah + KUK 1.5 missing
 *
 * Usage: php fix_issue3_kuk15.php --run
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$isRun = in_array('--run', $argv ?? []);

echo "=== FIX ISSUE #3: SI092009 no=69 ===\n\n";
echo "Mode: " . ($isRun ? "RUN" : "DRY RUN") . "\n\n";

// Step 1: Examine existing KUKs in target subunit 34681
echo "─── Step 1: Examine subunit 34681 ───\n";
$sub34681 = DB::table('subunit_kompetensis')->find(34681);
if (!$sub34681) { die("ERROR: subunit 34681 not found!\n"); }
echo "Subunit: id={$sub34681->id}, kode={$sub34681->kode}, unit_id={$sub34681->id_unitkompetensi}\n";

$unit34681 = DB::table('unit_kompetensis')->find($sub34681->id_unitkompetensi);
echo "Unit: id={$unit34681->id}, kode={$unit34681->kode}\n\n";

$existingKuks = DB::table('kuks')->where('id_subunitkompetensi', 34681)->orderBy('kode')->get();
echo "Existing KUKs in subunit 34681:\n";
foreach ($existingKuks as $k) {
    echo "  id={$k->id}, kode={$k->kode}\n";
    echo "    pertanyaan: " . substr($k->pertanyaan ?? '', 0, 80) . "\n";
}

// Step 2: Check current soal state
echo "\n─── Step 2: Current soal state ───\n";
$soal = DB::table('kan_soals')->find(19152);
if (!$soal) { die("ERROR: soal 19152 not found!\n"); }
echo "Soal id={$soal->id}, no={$soal->no}\n";
echo "  id_unitkompetensi={$soal->id_unitkompetensi}\n";
echo "  id_subunitkompetensi={$soal->id_subunitkompetensi}\n";
echo "  id_kuk={$soal->id_kuk}\n";

$currentSub = DB::table('subunit_kompetensis')->find($soal->id_subunitkompetensi);
$currentUnit = $currentSub ? DB::table('unit_kompetensis')->find($currentSub->id_unitkompetensi) : null;
echo "  Current subunit: " . ($currentSub ? "id={$currentSub->id}, kode={$currentSub->kode}, unit={$currentUnit->kode}" : "NULL") . "\n";
$currentKuk = DB::table('kuks')->find($soal->id_kuk);
echo "  Current KUK: " . ($currentKuk ? "id={$currentKuk->id}, kode={$currentKuk->kode}" : "NULL") . "\n";

// Step 3: Check if KUK 1.5 already exists in subunit 34681
echo "\n─── Step 3: KUK 1.5 check ───\n";
$kuk15 = DB::table('kuks')->where('id_subunitkompetensi', 34681)->where('kode', '1.5')->first();
if ($kuk15) {
    echo "KUK 1.5 ALREADY EXISTS in subunit 34681: id={$kuk15->id}\n";
} else {
    echo "KUK 1.5 NOT in subunit 34681 — needs to be created\n";

    // Use pattern from existing KUKs in this subunit to build the new one
    // KUK headers for KON schema typically have format
    $firstKuk = $existingKuks->first();
    echo "\n  Template from existing KUK:\n";
    if ($firstKuk) {
        foreach (['id_subunitkompetensi', 'id_unitkompetensi', 'id_jabatan_kerja', 'id_dokumen', 'jenis', 'created_at', 'updated_at'] as $col) {
            echo "    {$col}: " . ($firstKuk->$col ?? 'NULL') . "\n";
        }
    }

    if ($isRun) {
        $newKukId = DB::table('kuks')->insertGetId([
            'id_subunitkompetensi' => 34681,
            'kode' => '1.5',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        echo "\n  ✅ Created KUK 1.5: id={$newKukId}\n";
    } else {
        echo "\n  (DRY RUN — would create KUK 1.5 in subunit 34681)\n";
    }
}

// Step 4: Update soal
echo "\n─── Step 4: Update soal 19152 ───\n";
$newKuk15 = DB::table('kuks')->where('id_subunitkompetensi', 34681)->where('kode', '1.5')->first();

if ($newKuk15) {
    echo "Target KUK: id={$newKuk15->id}, kode={$newKuk15->kode}\n";
    echo "Target subunit: id=34681, kode=1\n";
    echo "Target unit: id={$unit34681->id}, kode={$unit34681->kode}\n";

    if ($isRun) {
        DB::table('kan_soals')->where('id', 19152)->update([
            'id_subunitkompetensi' => 34681,
            'id_kuk' => $newKuk15->id,
            'updated_at' => now(),
        ]);
        echo "\n  ✅ Updated soal 19152\n";

        // Verify
        $soalAfter = DB::table('kan_soals')->find(19152);
        $subAfter = DB::table('subunit_kompetensis')->find($soalAfter->id_subunitkompetensi);
        $kukAfter = DB::table('kuks')->find($soalAfter->id_kuk);
        echo "  Verify:\n";
        echo "    subunit: id={$subAfter->id}, kode={$subAfter->kode}, unit_id={$subAfter->id_unitkompetensi}\n";
        echo "    kuk: id={$kukAfter->id}, kode={$kukAfter->kode}\n";
    } else {
        echo "\n  (DRY RUN — would update soal 19152)\n";
    }
} else {
    echo "ERROR: KUK 1.5 still not found after creation attempt\n";
}

echo "\nDone.\n";
