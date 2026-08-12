<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

function normalizeName(string $s): string {
    return mb_strtolower(trim(preg_replace('/^\d+\.\s*/', '', $s)));
}
function cleanKode(string $kode): string {
    $kode = trim($kode);
    $kode = preg_replace('/^Kode\s*Unit\s*:\s*/i', '', $kode);
    $kode = str_replace('*', '', $kode);
    $kode = rtrim($kode, '.');
    $kode = preg_replace('/\p{Zs}/u', ' ', $kode);
    return trim($kode);
}

$json = json_decode(file_get_contents('c:/Users/Rafie Pradipta/Downloads/ALL_SOAL_MERGED_FIXED.json'), true);

// Load exactly like fix_kuk_from_json.php
$unitKodeToId = [];
$unitIdToKode = [];
$units = DB::table('unit_kompetensis')->select('id', 'kode')->get();
foreach ($units as $u) {
    $kode = trim($u->kode);
    $unitIdToKode[$u->id] = $kode;
    $unitKodeToId[$kode] = $u->id;
}

$subunitsByUnitId = [];
$subunits = DB::table('subunit_kompetensis')->select('id', 'kode', 'id_unitkompetensi')->get();
foreach ($subunits as $s) {
    $subunitsByUnitId[$s->id_unitkompetensi][] = $s;
}

$kukById = [];
$kuksBySubunitId = [];
$allKuks = DB::table('kuks')->select('id', 'kode', 'nama', 'id_subunitkompetensi')->get();
foreach ($allKuks as $k) {
    $kukById[$k->id] = $k;
    $kuksBySubunitId[$k->id_subunitkompetensi][] = $k;
}

$dokToTipe = ['FR.IA.04.B' => 'ia04b', 'FR.IA.05' => 'ia05', 'FR.IA.06' => 'ia06'];

// Check specific problematic entries
$checks = [
    ['jabatan' => 'PELAKSANA LAPANGAN PEKERJAAN JALAN (LEVEL 4)', 'no' => 6, 'tipe' => 'ia05', 'json_key' => null],
    ['jabatan' => 'PELAKSANA LAPANGAN PEKERJAAN SALURAN IRIGASI (LEVEL 4)', 'no' => 51, 'tipe' => 'ia05', 'json_key' => null],
    ['jabatan' => 'AHLI UTAMA TEKNIK PERENCANAAN IRIGASI RAWA', 'no' => 4, 'tipe' => 'ia05', 'json_key' => null],
];

echo str_repeat("=", 80) . "\n";
echo "DEEP TRACE: Fix Script Logic for Specific Entries\n";
echo str_repeat("=", 80) . "\n\n";

foreach ($checks as $check) {
    $normName = normalizeName($check['jabatan']);
    $key = $normName . '|' . $check['no'] . '|' . $check['tipe'];

    echo "─── DB Index for key: [$key] ───\n";

    // Build DB index for just this key
    $dbSoals = DB::table('kan_soals as s')
        ->join('jabatan_dokumens as jd', 's.id_dokumen', '=', 'jd.id')
        ->join('jabatan_kerjas as jk', 'jd.id_jabatan', '=', 'jk.id_jabatan_kerja')
        ->select('s.id', 's.no', 's.id_dokumen', 's.id_unitkompetensi', 's.id_subunitkompetensi', 's.id_kuk',
                 'jd.kode as dok_kode', 'jk.jabatan_kerja', 'jk.id_jabatan_kerja')
        ->where('jd.kode', 'FR.IA.05')
        ->where('s.no', $check['no'])
        ->where('jk.jabatan_kerja', 'like', '%' . $check['jabatan'] . '%')
        ->get();

    echo "Matches from DB: " . count($dbSoals) . "\n";
    foreach ($dbSoals as $s) {
        $dbNorm = normalizeName($s->jabatan_kerja);
        $dbTipe = isset($dokToTipe[$s->dok_kode]) ? $dokToTipe[$s->dok_kode] : '?';
        $dbKey = $dbNorm . '|' . (int)$s->no . '|' . $dbTipe;
        echo "  soal_id={$s->id} no={$s->no} dok={$s->dok_kode} tipe={$dbTipe} unit_id={$s->id_unitkompetensi} subunit_id={$s->id_subunitkompetensi} kuk_id={$s->id_kuk}\n";
        echo "    jabatan_kerja=[{$s->jabatan_kerja}] norm=[{$dbNorm}]\n";
        echo "    DB key=[{$dbKey}] matches JSON key=[{$key}]? " . ($dbKey === $key ? 'YES' : 'NO') . "\n";

        // Check: what's the current KUK kode?
        $currentKuk = $kukById[$s->id_kuk] ?? null;
        echo "    current kuk_kode=[" . ($currentKuk->kode ?? 'NULL') . "] kuk_id={$s->id_kuk}\n";
    }

    // Now look up JSON entry
    $jsonKey = null;
    foreach (array_keys($json) as $k) {
        if (stripos($k, $check['jabatan']) !== false) {
            $jsonKey = $k;
            break;
        }
    }
    if ($jsonKey) {
        $tipe = $check['tipe'];
        $entries = $json[$jsonKey][$tipe] ?? [];
        $jsonEntry = null;
        foreach ($entries as $e) {
            if ((int)($e['no'] ?? 0) === $check['no']) {
                $jsonEntry = $e;
                break;
            }
        }
        if ($jsonEntry) {
            $kodeKukRaw = $jsonEntry['kode_kuk'] ?? '';
            $cleaned = cleanKode($kodeKukRaw);
            $parts = preg_split('/\s+/', $cleaned, 2);
            $kodeUnit = $parts[0] ?? '';
            $kukKode = $parts[1] ?? '';

            echo "    JSON: kode_kuk=[{$kodeKukRaw}] → cleaned=[{$cleaned}]\n";
            echo "    JSON: kodeUnit=[{$kodeUnit}] kukKode=[{$kukKode}]\n";

            // Check unit resolution
            $resolvedId = null;
            if (isset($unitKodeToId[$kodeUnit])) {
                $resolvedId = $unitKodeToId[$kodeUnit];
                echo "    JSON: resolveUnitId({$kodeUnit}) = {$resolvedId} (exact match)\n";
            } else {
                echo "    JSON: resolveUnitId({$kodeUnit}) = NOT FOUND in unitKodeToId!\n";
                // Show similar units
                foreach ($unitKodeToId as $uc => $uid) {
                    $lev = levenshtein($kodeUnit, $uc);
                    if ($lev <= 3 && $lev > 0) {
                        echo "      similar: [{$uc}]→{$uid} (dist={$lev})\n";
                    }
                }
            }

            // Check subunit
            if ($resolvedId && isset($subunitsByUnitId[$resolvedId])) {
                $sunits = $subunitsByUnitId[$resolvedId];
                echo "    JSON: subunit count for unit {$resolvedId}: " . count($sunits) . "\n";
                $subunitId = $sunits[0]->id;
                echo "    JSON: first subunit id={$subunitId}\n";

                // Check KUKs in this subunit
                $kuks = $kuksBySubunitId[$subunitId] ?? [];
                $foundKukId = null;
                foreach ($kuks as $k) {
                    if ($k->kode === $kukKode) {
                        $foundKukId = $k->id;
                        break;
                    }
                }
                echo "    JSON: looking for kuk kode=[{$kukKode}] in subunit {$subunitId}\n";
                echo "    JSON: " . ($foundKukId ? "FOUND kuk_id={$foundKukId}" : "NOT FOUND (needs creation)") . "\n";

                // Run the actual comparison
                foreach ($dbSoals as $dbSoal) {
                    $currentUnitId = $dbSoal->id_unitkompetensi;
                    $currentSubunitId = $dbSoal->id_subunitkompetensi;
                    $currentKukId = $dbSoal->id_kuk;
                    $currentKukKode = $currentKukId ? ($kukById[$currentKukId]->kode ?? null) : null;

                    $needUnitFix = is_null($currentUnitId) || $currentUnitId != $resolvedId;
                    $needSubunitFix = is_null($currentSubunitId) || $currentSubunitId != $subunitId;
                    $needKukFix = ($currentKukKode !== $kukKode) || ($currentKukId !== $foundKukId);

                    echo "\n    ── FIX CHECK for soal_id={$dbSoal->id} ──\n";
                    echo "    needUnitFix: ({$currentUnitId} != {$resolvedId}) = " . ($needUnitFix ? 'TRUE' : 'FALSE') . "\n";
                    echo "    needSubunitFix: ({$currentSubunitId} != {$subunitId}) = " . ($needSubunitFix ? 'TRUE' : 'FALSE') . "\n";
                    echo "    needKukFix: ({$currentKukKode} !== {$kukKode})||({$currentKukId} !== {$foundKukId}) = " . ($needKukFix ? 'TRUE' : 'FALSE') . "\n";
                    echo "    → Would be: " . (!$needUnitFix && !$needSubunitFix && !$needKukFix ? 'ALREADY CORRECT (skip)' : 'ADDED TO FIX PLAN') . "\n";
                }
            }
        }
    }
    echo "\n";
}

echo "Done.\n";
