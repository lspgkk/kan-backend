<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$json = json_decode(file_get_contents('c:/Users/Rafie Pradipta/Downloads/ALL_SOAL_MERGED_FIXED.json'), true);

function normalizeName(string $s): string {
    return mb_strtolower(trim(preg_replace('/^\d+\.\s*/', '', $s)));
}

// Replicate cleanKode from fix script
function cleanKode(string $kode): string {
    $kode = trim($kode);
    $kode = preg_replace('/^Kode\s*Unit\s*:\s*/i', '', $kode);
    $kode = str_replace('*', '', $kode);
    $kode = rtrim($kode, '.');
    $kode = preg_replace('/\p{Zs}/u', ' ', $kode);
    return trim($kode);
}

// Load DB unit codes
$unitKodeToId = [];
$allUnits = DB::table('unit_kompetensis')->select('id', 'kode')->get();
foreach ($allUnits as $u) { $unitKodeToId[trim($u->kode)] = $u->id; }

$subunitsByUnitId = [];
$allSubunits = DB::table('subunit_kompetensis')->select('id', 'kode', 'id_unitkompetensi')->get();
foreach ($allSubunits as $su) { $subunitsByUnitId[$su->id_unitkompetensi][] = $su; }

// Load DB soals for just a few problematic jabatan
$dokToTipe = ['FR.IA.04.B' => 'ia04b', 'FR.IA.05' => 'ia05', 'FR.IA.06' => 'ia06'];

$problematic = [
    'PENGAWAS PEKERJAAN BANGUNAN GEDUNG (LEVEL 5)' => '007',
    'AHLI MUDA TEKNIK JALAN' => '015',
    'AHLI MADYA TEKNIK JALAN' => '016',
    'AHLI UTAMA TEKNIK BANGUNAN GEDUNG' => '062',
    'AHLI UTAMA TEKNIK JALAN' => '079',
    'PELAKSANA LAPANGAN PEKERJAAN SALURAN IRIGASI (LEVEL 4)' => '066',
];

foreach ($problematic as $keyword => $label) {
    echo str_repeat("=", 70) . "\n";
    echo "DEEP DIAGNOSE: $label ($keyword)\n";
    echo str_repeat("=", 70) . "\n";

    // Find JSON key
    $jsonKey = null;
    foreach (array_keys($json) as $k) {
        if (stripos($k, $keyword) !== false) {
            $jsonKey = $k;
            break;
        }
    }
    if (!$jsonKey) {
        echo "  NOT FOUND in JSON!\n\n";
        continue;
    }

    $ia05 = $json[$jsonKey]['ia05'] ?? [];
    $normName = normalizeName($jsonKey);

    // Check first 15 JSON entries for IA05
    echo "  JSON IA05 entries analysis (first 15 with kode_kuk):\n";
    $checked = 0;
    foreach ($ia05 as $item) {
        if ($checked >= 15) break;
        $no = (int)($item['no'] ?? 0);
        $kodeUnitRaw = $item['kode_unit'] ?? '';
        $kodeKukRaw = $item['kode_kuk'] ?? '';

        if (empty($kodeKukRaw)) continue;
        $checked++;

        // Show raw bytes of kode_kuk
        $hex = '';
        for ($j = 0; $j < min(strlen($kodeKukRaw), 40); $j++) {
            $o = ord($kodeKukRaw[$j]);
            if ($o == 10) $hex .= '[LF]';
            elseif ($o == 13) $hex .= '[CR]';
            elseif ($o == 9) $hex .= '[TAB]';
            elseif ($o == 32) $hex .= '[SP]';
            else $hex .= $kodeKukRaw[$j];
        }

        // Now apply cleanKode + split (same as fix script)
        $cleaned = cleanKode($kodeKukRaw);
        $parts = preg_split('/\s+/', $cleaned, 2);
        $kodeUnitFromKuk = $parts[0] ?? '';
        $kukKode = $parts[1] ?? '';

        // Also try kode_unit
        $kodeUnitFromField = cleanKode($kodeUnitRaw);

        $finalUnit = !empty($kodeUnitFromKuk) ? $kodeUnitFromKuk : $kodeUnitFromField;
        $unitId = isset($unitKodeToId[$finalUnit]) ? $unitKodeToId[$finalUnit] : null;

        if (!$unitId) {
            // Try resolveUnitId fallbacks
            // 1. Strip .N suffix
            if (preg_match('/^(.+)\.\d+$/', $finalUnit, $m)) {
                $try = $m[1];
                $unitId = $unitKodeToId[$try] ?? null;
            }
            // 2. Append .1
            if (!$unitId && isset($unitKodeToId[$finalUnit . '.1'])) {
                $unitId = $unitKodeToId[$finalUnit . '.1'];
            }
            // 3. Single↔double digit
            if (!$unitId && preg_match('/^(.+)\.(\d)$/', $finalUnit, $m)) {
                $try = $m[1] . '.0' . $m[2];
                $unitId = $unitKodeToId[$try] ?? null;
            }
            if (!$unitId && preg_match('/^(.+)\.0(\d)$/', $finalUnit, $m)) {
                $try = $m[1] . '.' . $m[2];
                $unitId = $unitKodeToId[$try] ?? null;
            }
        }

        // Check DB for this (normName, no, ia05)
        $dbSoal = DB::table('kan_soals as s')
            ->join('jabatan_dokumens as jd', 's.id_dokumen', '=', 'jd.id')
            ->join('jabatan_kerjas as jk', 'jd.id_jabatan', '=', 'jk.id_jabatan_kerja')
            ->leftJoin('kuks', 's.id_kuk', '=', 'kuks.id')
            ->leftJoin('unit_kompetensis as u', 's.id_unitkompetensi', '=', 'u.id')
            ->where('jd.kode', 'FR.IA.05')
            ->where('s.no', $no)
            ->select('s.id', 'kuks.kode as kuk_kode', 'u.kode as unit_kode',
                     's.id_unitkompetensi', 's.id_kuk', 'jk.jabatan_kerja')
            ->get();

        // Filter to matching jabatan
        $matchingSoal = null;
        foreach ($dbSoal as $ds) {
            if (normalizeName($ds->jabatan_kerja) === $normName) {
                $matchingSoal = $ds;
                break;
            }
        }

        $status = '?';
        if ($matchingSoal) {
            if ($matchingSoal->kuk_kode === $kukKode) {
                $status = '✓ MATCH';
            } else {
                $status = "✗ MISMATCH (exp=$kukKode, act={$matchingSoal->kuk_kode})";
            }
        } else {
            $status = '? NO DB MATCH';
        }

        $unitStatus = $unitId ? "✓ unit={$finalUnit}→{$unitId}" : "✗ unit={$finalUnit} NOT FOUND";
        $kukStatus = $kukKode ? "kuk={$kukKode}" : "KUK=EMPTY";
        $kuField = $kodeUnitRaw ? "ku_raw=[$kodeUnitRaw]" : "ku_raw=NULL";
        echo "    no={$no} {$kuField} kk_raw=[{$hex}] → {$kukStatus} {$unitStatus} → {$status}\n";

        if ($matchingSoal && $matchingSoal->kuk_kode !== $kukKode) {
            echo "      DB: soal_id={$matchingSoal->id} id_unitkompetensi={$matchingSoal->id_unitkompetensi} id_kuk={$matchingSoal->id_kuk} unit={$matchingSoal->unit_kode} kuk={$matchingSoal->kuk_kode}\n";
        }
    }

    echo "\n";
}

echo "Done.\n";
