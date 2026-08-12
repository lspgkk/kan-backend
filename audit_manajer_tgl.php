<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$isFix = in_array('--fix', $argv ?? []);

echo str_repeat("=", 70) . "\n";
echo "  AUDIT: kan_manajer_tgl vs kan_validator_tgl (jabatan_config_a)\n";
echo str_repeat("=", 70) . "\n";
echo "Mode: " . ($isFix ? "FIX" : "AUDIT ONLY") . "\n\n";

// Load all configs
$configs = DB::table('jabatan_config_a')
    ->select('id_jabatan', 'kan_validator_tgl', 'kan_manajer_tgl')
    ->get();

echo "Total records: " . count($configs) . "\n\n";

// Check mismatches
$mismatches = [];
$nullManajer = [];
$nullValidator = [];
$match = 0;

foreach ($configs as $c) {
    $vt = $c->kan_validator_tgl;
    $mt = $c->kan_manajer_tgl;

    // Both null? Skip
    if ($vt === null && $mt === null) {
        // Both null is fine, nothing to compare
        continue;
    }

    // Validator has date, manajer is null
    if ($vt !== null && $mt === null) {
        $nullManajer[] = $c;
        continue;
    }

    // Manajer has date, validator is null
    if ($vt === null && $mt !== null) {
        $nullValidator[] = $c;
        continue;
    }

    // Both have values - compare
    if ($vt !== $mt) {
        $mismatches[] = $c;
    } else {
        $match++;
    }
}

echo "─── RESULTS ───\n";
echo "Both null (ok): " . ($configs->count() - count($mismatches) - count($nullManajer) - count($nullValidator) - $match) . "\n";
echo "Matching: {$match}\n";
echo "kan_manajer_tgl NULL but kan_validator_tgl exists: " . count($nullManajer) . "\n";
echo "kan_validator_tgl NULL but kan_manajer_tgl exists: " . count($nullValidator) . "\n";
echo "Dates DIFFER: " . count($mismatches) . "\n";

$totalToFix = count($mismatches) + count($nullManajer);
echo "\nTotal to fix (set manajer = validator): {$totalToFix}\n";

if ($totalToFix > 0) {
    echo "\n─── SAMPLES ───\n";
    $samples = array_merge(
        array_slice($mismatches, 0, 5),
        array_slice($nullManajer, 0, 5)
    );
    foreach ($samples as $c) {
        $vt = $c->kan_validator_tgl ?? 'NULL';
        $mt = $c->kan_manajer_tgl ?? 'NULL';
        echo "  id_jabatan={$c->id_jabatan}: validator={$vt} → manajer={$mt}\n";
    }
}

if ($isFix && $totalToFix > 0) {
    echo "\n─── APPLYING FIXES ───\n";
    $errors = 0;
    $fixed = 0;

    // Fix mismatches (different dates)
    foreach ($mismatches as $c) {
        try {
            DB::table('jabatan_config_a')
                ->where('id_jabatan', $c->id_jabatan)
                ->update([
                    'kan_manajer_tgl' => $c->kan_validator_tgl,
                ]);
            $fixed++;
        } catch (\Exception $e) {
            echo "  X Error id_jabatan={$c->id_jabatan}: {$e->getMessage()}\n";
            $errors++;
        }
    }

    // Fix null manajer
    foreach ($nullManajer as $c) {
        try {
            DB::table('jabatan_config_a')
                ->where('id_jabatan', $c->id_jabatan)
                ->update([
                    'kan_manajer_tgl' => $c->kan_validator_tgl,
                ]);
            $fixed++;
        } catch (\Exception $e) {
            echo "  X Error id_jabatan={$c->id_jabatan}: {$e->getMessage()}\n";
            $errors++;
        }
    }

    echo "  Fixed: {$fixed}\n";
    echo "  Errors: {$errors}\n";
    echo "\n  ✅ All kan_manajer_tgl now match kan_validator_tgl!\n";
} elseif (!$isFix && $totalToFix > 0) {
    echo "\n  Run: php audit_manajer_tgl.php --fix\n";
} else {
    echo "\n  ✅ All kan_manajer_tgl already match kan_validator_tgl!\n";
}

echo "\nDone.\n";
