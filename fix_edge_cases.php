<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$isFix = in_array('--fix', $argv ?? []);
echo str_repeat("=", 70) . "\n";
echo "  FIX EDGE CASES: Trailing dots + Comma separators\n";
echo str_repeat("=", 70) . "\n";
echo "Mode: " . ($isFix ? "FIX" : "AUDIT ONLY") . "\n\n";

// ─── 1. FIX TRAILING DOTS IN KUKS.KODE ───
echo "─── TRAILING DOTS IN KUKS TABLE ───\n";
$trailingDotKuks = DB::table('kuks')
    ->where('kode', 'like', '%.')
    ->whereRaw('kode REGEXP ?', ['\.$'])
    ->select('id', 'kode', 'id_subunitkompetensi')
    ->get();

echo "KUK records with trailing dot: " . count($trailingDotKuks) . "\n";

$dotFixPlan = [];
foreach ($trailingDotKuks as $kuk) {
    $newKode = rtrim($kuk->kode, '.');
    if ($newKode !== $kuk->kode) {
        // Check if there's already a KUK with the corrected kode in the same subunit
        $existing = DB::table('kuks')
            ->where('kode', $newKode)
            ->where('id_subunitkompetensi', $kuk->id_subunitkompetensi)
            ->where('id', '!=', $kuk->id)
            ->first();

        if ($existing) {
            // Duplicate: reassign soals from this kuk to existing, then delete this kuk
            $dotFixPlan[$kuk->id] = ['action' => 'merge', 'old_kode' => $kuk->kode, 'new_kode' => $newKode, 'target_id' => $existing->id, 'subunit_id' => $kuk->id_subunitkompetensi];
        } else {
            // Just rename
            $dotFixPlan[$kuk->id] = ['action' => 'rename', 'old_kode' => $kuk->kode, 'new_kode' => $newKode, 'subunit_id' => $kuk->id_subunitkompetensi];
        }
    }
}

$renameCount = count(array_filter($dotFixPlan, fn($p) => $p['action'] === 'rename'));
$mergeCount = count(array_filter($dotFixPlan, fn($p) => $p['action'] === 'merge'));
echo "Will rename: $renameCount\n";
echo "Will merge: $mergeCount\n";
if (count($dotFixPlan) > 0) {
    echo "Samples:\n";
    $i = 0;
    foreach ($dotFixPlan as $id => $plan) {
        echo "  kuk_id=$id: {$plan['old_kode']} → {$plan['new_kode']} ({$plan['action']})\n";
        if (++$i >= 10) break;
    }
}

// ─── 2. FIX COMMA-SEPARATED KUK IN JSON ───
echo "\n─── COMMA IN JSON KODE_KUK ───\n";

$json = json_decode(file_get_contents('c:/Users/Rafie Pradipta/Downloads/ALL_SOAL_MERGED_FIXED.json'), true);

function normalizeName(string $s): string {
    return mb_strtolower(trim(preg_replace('/^\d+\.\s*/', '', $s)));
}

$dokToTipe = ['FR.IA.04.B' => 'ia04b', 'FR.IA.05' => 'ia05', 'FR.IA.06' => 'ia06'];

$allSoals = DB::table('kan_soals as s')
    ->join('jabatan_dokumens as jd', 's.id_dokumen', '=', 'jd.id')
    ->join('jabatan_kerjas as jk', 'jd.id_jabatan', '=', 'jk.id_jabatan_kerja')
    ->leftJoin('kuks', 's.id_kuk', '=', 'kuks.id')
    ->leftJoin('unit_kompetensis as u', 's.id_unitkompetensi', '=', 'u.id')
    ->select('s.id', 's.no', 's.id_unitkompetensi', 's.id_subunitkompetensi', 's.id_kuk',
             'jd.kode as dok_kode', 'jk.jabatan_kerja', 'kuks.kode as kuk_kode', 'u.kode as unit_kode')
    ->get();

$dbIndex = [];
foreach ($allSoals as $s) {
    $tipeKey = $dokToTipe[$s->dok_kode] ?? null;
    if (!$tipeKey) continue;
    $key = normalizeName($s->jabatan_kerja) . '|' . (int)$s->no . '|' . $tipeKey;
    if (!isset($dbIndex[$key])) $dbIndex[$key] = [];
    $dbIndex[$key][] = $s;
}

$unitKodeToId = [];
$allUnits = DB::table('unit_kompetensis')->select('id', 'kode')->get();
foreach ($allUnits as $u) { $unitKodeToId[$u->kode] = $u->id; }

function resolveUnitIdFixed(string $kodeUnit, array $unitKodeToId): ?int {
    $kodeUnit = trim($kodeUnit, " \t\n\r\0\x0B\xC2\xA0*");
    $kodeUnit = rtrim($kodeUnit, '*');
    if (isset($unitKodeToId[$kodeUnit])) return $unitKodeToId[$kodeUnit];
    if (preg_match('/^(.+)\.(\d)$/', $kodeUnit, $m)) {
        $try = $m[1] . '.0' . $m[2];
        if (isset($unitKodeToId[$try])) return $unitKodeToId[$try];
    }
    if (preg_match('/^(.+)\.0(\d)$/', $kodeUnit, $m)) {
        $try = $m[1] . '.' . $m[2];
        if (isset($unitKodeToId[$try])) return $unitKodeToId[$try];
    }
    if (preg_match('/^(.+)\.(\d+)$/', $kodeUnit, $m)) {
        $base = $m[1];
        for ($v = 1; $v <= 9; $v++) {
            $try = $base . '.' . $v;
            if ($v !== (int)$m[2] && isset($unitKodeToId[$try])) return $unitKodeToId[$try];
            $try02 = $base . '.0' . $v;
            if (isset($unitKodeToId[$try02])) return $unitKodeToId[$try02];
        }
    }
    if (preg_match('/^(.+)\.\d+$/', $kodeUnit, $m)) {
        if (isset($unitKodeToId[$m[1]])) return $unitKodeToId[$m[1]];
    }
    if (isset($unitKodeToId[$kodeUnit . '.1'])) return $unitKodeToId[$kodeUnit . '.1'];
    if (isset($unitKodeToId[$kodeUnit . '.01'])) return $unitKodeToId[$kodeUnit . '.01'];
    return null;
}

$subunitsByUnitId = [];
$allSubunits = DB::table('subunit_kompetensis')->select('id', 'kode', 'id_unitkompetensi')->get();
foreach ($allSubunits as $su) { $subunitsByUnitId[$su->id_unitkompetensi][] = $su; }

$kuksBySubunitId = [];
$allKuks = DB::table('kuks')->select('id', 'kode', 'id_subunitkompetensi')->get();
foreach ($allKuks as $k) { $kuksBySubunitId[$k->id_subunitkompetensi][] = $k; }

$commaFixPlan = [];
$commaCount = 0;

foreach ($json as $jabatanName => $jabatanData) {
    $normName = normalizeName($jabatanName);
    foreach (['ia05', 'ia06'] as $tipe) {
        if (!isset($jabatanData[$tipe])) continue;
        foreach ($jabatanData[$tipe] as $item) {
            $kodeKuk = $item['kode_kuk'] ?? '';
            $no = (int)($item['no'] ?? 0);
            if (empty($kodeKuk)) continue;

            // Check for comma in KUK part
            $parts = preg_split('/[\s\n]+/', $kodeKuk, 2);
            $kukPart = $parts[1] ?? ($parts[0] ?? '');
            if (strpos($kukPart, ',') === false) continue;

            $commaCount++;
            $fixedKuk = str_replace(',', '.', $kukPart);

            // Find DB match
            $key = $normName . '|' . $no . '|' . $tipe;
            $dbSoals = $dbIndex[$key] ?? [];
            if (empty($dbSoals)) continue;

            // Resolve unit
            $kodeUnit = $item['kode_unit'] ?? ($parts[0] ?? '');
            if (empty($kodeUnit)) continue;
            $unitId = resolveUnitIdFixed($kodeUnit, $unitKodeToId);
            if (!$unitId) continue;

            $subunits = $subunitsByUnitId[$unitId] ?? [];
            $subunitId = !empty($subunits) ? $subunits[0]->id : null;
            if (!$subunitId) continue;

            foreach ($dbSoals as $dbSoal) {
                if ($dbSoal->kuk_kode === $fixedKuk) continue; // already correct
                $commaFixPlan[$dbSoal->id] = [
                    'soal_id' => $dbSoal->id,
                    'jabatan' => $dbSoal->jabatan_kerja,
                    'tipe' => $tipe,
                    'no' => $no,
                    'old_kuk' => $kukPart,
                    'new_kuk' => $fixedKuk,
                    'unit_id' => $unitId,
                    'subunit_id' => $subunitId,
                ];
            }
        }
    }
}

echo "JSON entries with comma in KUK: $commaCount\n";
echo "DB soals to fix: " . count($commaFixPlan) . "\n";
if (count($commaFixPlan) > 0) {
    echo "Samples:\n";
    $i = 0;
    foreach ($commaFixPlan as $plan) {
        echo "  soal_id={$plan['soal_id']}: {$plan['old_kuk']} → {$plan['new_kuk']} ({$plan['jabatan']} / no={$plan['no']})\n";
        if (++$i >= 10) break;
    }
}

// ─── 3. APPLY FIXES ───
if ($isFix) {
    echo "\n" . str_repeat("=", 70) . "\n";
    echo "  APPLYING FIXES\n";
    echo str_repeat("=", 70) . "\n\n";

    $errors = 0;

    // Fix trailing dots
    foreach ($dotFixPlan as $kukId => $plan) {
        try {
            if ($plan['action'] === 'rename') {
                DB::table('kuks')->where('id', $kukId)->update([
                    'kode' => $plan['new_kode'],
                    'updated_at' => now(),
                ]);
                echo "  ✓ kuk_id=$kukId: renamed {$plan['old_kode']} → {$plan['new_kode']}\n";
            } elseif ($plan['action'] === 'merge') {
                // Reassign soals
                DB::table('kan_soals')->where('id_kuk', $kukId)->update([
                    'id_kuk' => $plan['target_id'],
                    'updated_at' => now(),
                ]);
                // Delete duplicate
                DB::table('kuks')->where('id', $kukId)->delete();
                echo "  ✓ kuk_id=$kukId: merged into {$plan['target_id']}, deleted duplicate\n";
            }
        } catch (\Exception $e) {
            echo "  X Error on kuk_id=$kukId: {$e->getMessage()}\n";
            $errors++;
        }
    }

    // Fix comma entries
    foreach ($commaFixPlan as $soalId => $plan) {
        // Find or create KUK with fixed kode
        $kuks = $kuksBySubunitId[$plan['subunit_id']] ?? [];
        $kukId = null;
        foreach ($kuks as $k) {
            if ($k->kode === $plan['new_kuk']) { $kukId = $k->id; break; }
        }
        if (!$kukId) {
            try {
                $kukId = DB::table('kuks')->insertGetId([
                    'kode' => $plan['new_kuk'],
                    'nama' => '',
                    'id_subunitkompetensi' => $plan['subunit_id'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (\Exception $e) {
                echo "  X Error creating kuk: {$e->getMessage()}\n";
                $errors++;
                continue;
            }
        }

        try {
            DB::table('kan_soals')->where('id', $soalId)->update([
                'id_kuk' => $kukId,
                'id_unitkompetensi' => $plan['unit_id'],
                'id_subunitkompetensi' => $plan['subunit_id'],
                'updated_at' => now(),
            ]);
            echo "  ✓ soal_id=$soalId (no={$plan['no']}, {$plan['jabatan']}): kuk {$plan['old_kuk']}→{$plan['new_kuk']}\n";
        } catch (\Exception $e) {
            echo "  X Error updating soal_id=$soalId: {$e->getMessage()}\n";
            $errors++;
        }
    }

    echo "\n─── RESULT ───\n";
    echo "Trailing dots fixed: " . count($dotFixPlan) . "\n";
    echo "Comma entries fixed: " . count($commaFixPlan) . "\n";
    echo "Errors: $errors\n";
} else {
    echo "\n" . str_repeat("=", 70) . "\n";
    echo "  READY TO FIX\n";
    echo "  Trailing dots: " . count($dotFixPlan) . " records\n";
    echo "  Comma fixes: " . count($commaFixPlan) . " records\n";
    echo "  Run: php fix_edge_cases.php --fix\n";
    echo str_repeat("=", 70) . "\n";
}

echo "\nDone.\n";
