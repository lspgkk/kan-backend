<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$json = json_decode(file_get_contents('c:/Users/Rafie Pradipta/Downloads/ALL_SOAL_MERGED_FIXED.json'), true);

function normalizeName(string $s): string {
    return mb_strtolower(trim(preg_replace('/^\d+\.\s*/', '', $s)));
}

// Quick check: for 007.PENGAWAS PEKERJAAN BANGUNAN GEDUNG (LEVEL 5)
// Find matching JSON key and check what KUK codes the JSON expects
$jsonKey = null;
foreach (array_keys($json) as $k) {
    if (stripos($k, 'PENGAWAS PEKERJAAN BANGUNAN GEDUNG') !== false && stripos($k, 'LEVEL 5') !== false) {
        $jsonKey = $k;
        break;
    }
}

echo "=== ANALYSIS: PENGAWAS PEKERJAAN BANGUNAN GEDUNG (LEVEL 5) ===\n\n";

if ($jsonKey) {
    echo "JSON key: $jsonKey\n";
    $ia05 = $json[$jsonKey]['ia05'] ?? [];
    echo "IA05 entries: " . count($ia05) . "\n\n";

    // Show first 5 JSON entries
    echo "First 5 JSON entries:\n";
    for ($i = 0; $i < 5; $i++) {
        $s = $ia05[$i];
        $ku = $s['kode_unit'] ?? 'NULL';
        $kk = $s['kode_kuk'] ?? 'NULL';
        // Show raw bytes for kode_kuk to see if there's a newline
        $hex = '';
        for ($j = 0; $j < min(strlen($kk), 30); $j++) {
            $o = ord($kk[$j]);
            if ($o == 10) $hex .= '[NL]';
            elseif ($o == 13) $hex .= '[CR]';
            else $hex .= $kk[$j];
        }
        echo "  no={$s['no']} ku={$ku} kk={$hex}\n";
    }

    // Show area around no=11-20
    echo "\nJSON nos 11-20:\n";
    for ($i = 10; $i < 20; $i++) {
        $s = $ia05[$i];
        $ku = $s['kode_unit'] ?? 'NULL';
        $kk = $s['kode_kuk'] ?? 'NULL';
        $hex = '';
        for ($j = 0; $j < min(strlen($kk), 30); $j++) {
            $o = ord($kk[$j]);
            if ($o == 10) $hex .= '[NL]';
            elseif ($o == 13) $hex .= '[CR]';
            else $hex .= $kk[$j];
        }
        echo "  no={$s['no']} ku={$ku} kk={$hex}\n";
    }

    // Now check DB state for these soals
    echo "\nDB state for nos 11-20:\n";
    $dbSoals = DB::table('kan_soals as s')
        ->join('jabatan_dokumens as jd', 's.id_dokumen', '=', 'jd.id')
        ->join('jabatan_kerjas as jk', 'jd.id_jabatan', '=', 'jk.id_jabatan_kerja')
        ->leftJoin('kuks', 's.id_kuk', '=', 'kuks.id')
        ->leftJoin('unit_kompetensis as u', 's.id_unitkompetensi', '=', 'u.id')
        ->where('jk.jabatan_kerja', 'like', '%PENGAWAS PEKERJAAN BANGUNAN GEDUNG%')
        ->where('jk.jabatan_kerja', 'like', '%LEVEL 5%')
        ->where('jd.kode', 'FR.IA.05')
        ->whereBetween('s.no', [11, 20])
        ->select('s.id', 's.no', 'u.kode as unit_kode', 'kuks.kode as kuk_kode')
        ->orderBy('s.no')
        ->get();

    foreach ($dbSoals as $s) {
        echo "  DB: id={$s->id}, no={$s->no}, unit={$s->unit_kode}, kuk={$s->kuk_kode}\n";
    }

    // Now check: what are ALL the distinct KUK codes in the DB for this jabatan?
    $allDbKuk = DB::table('kan_soals as s')
        ->join('jabatan_dokumens as jd', 's.id_dokumen', '=', 'jd.id')
        ->join('jabatan_kerjas as jk', 'jd.id_jabatan', '=', 'jk.id_jabatan_kerja')
        ->leftJoin('kuks', 's.id_kuk', '=', 'kuks.id')
        ->where('jk.jabatan_kerja', 'like', '%PENGAWAS PEKERJAAN BANGUNAN GEDUNG%')
        ->where('jk.jabatan_kerja', 'like', '%LEVEL 5%')
        ->where('jd.kode', 'FR.IA.05')
        ->select('kuks.kode')
        ->distinct()
        ->orderBy('kuks.kode')
        ->get()
        ->pluck('kode')
        ->toArray();

    echo "\nAll DB KUK codes: " . implode(', ', $allDbKuk) . "\n";

    // What KUK codes does the JSON expect?
    $jsonKuks = [];
    foreach ($ia05 as $s) {
        $kk = $s['kode_kuk'] ?? '';
        if (empty($kk)) continue;
        $parts = preg_split('/[\s\n]+/', $kk, 2);
        if (count($parts) >= 2) {
            $jsonKuks[$parts[1]] = true;
        } else {
            // No separator - check if concatenated
            if (!preg_match('/[\s\n]/', $kk)) {
                // Try to handle
            }
        }
    }
    ksort($jsonKuks);
    echo "JSON expected KUK codes: " . implode(', ', array_keys($jsonKuks)) . "\n";

    // Now: for each no, compare JSON expected vs DB actual
    echo "\nFull comparison nos 1-30:\n";
    for ($no = 1; $no <= 30; $no++) {
        $jsonS = null;
        foreach ($ia05 as $s) { if (($s['no'] ?? 0) == $no) { $jsonS = $s; break; } }
        $expectedKuk = '?';
        if ($jsonS) {
            $kk = $jsonS['kode_kuk'] ?? '';
            $parts = preg_split('/[\s\n]+/', $kk, 2);
            $expectedKuk = $parts[1] ?? $kk;
        }

        $dbS = DB::table('kan_soals as s')
            ->join('jabatan_dokumens as jd', 's.id_dokumen', '=', 'jd.id')
            ->join('jabatan_kerjas as jk', 'jd.id_jabatan', '=', 'jk.id_jabatan_kerja')
            ->leftJoin('kuks', 's.id_kuk', '=', 'kuks.id')
            ->where('jk.jabatan_kerja', 'like', '%PENGAWAS PEKERJAAN BANGUNAN GEDUNG%')
            ->where('jk.jabatan_kerja', 'like', '%LEVEL 5%')
            ->where('jd.kode', 'FR.IA.05')
            ->where('s.no', $no)
            ->select('s.id', 'kuks.kode as kuk_kode')
            ->get();

        $status = '';
        foreach ($dbS as $dbs) {
            $match = ($dbs->kuk_kode === $expectedKuk) ? '✓' : '✗';
            $status .= " id={$dbs->id}:{$dbs->kuk_kode}{$match}";
        }
        $statusStr = $status ?: ' NOT IN DB';
        echo "  no=$no expected=$expectedKuk → DB:$statusStr\n";
    }

    // Check: are there DUPLICATE DB records for the same no?
    echo "\nDuplicate check:\n";
    $dupes = DB::table('kan_soals as s')
        ->join('jabatan_dokumens as jd', 's.id_dokumen', '=', 'jd.id')
        ->join('jabatan_kerjas as jk', 'jd.id_jabatan', '=', 'jk.id_jabatan_kerja')
        ->where('jk.jabatan_kerja', 'like', '%PENGAWAS PEKERJAAN BANGUNAN GEDUNG%')
        ->where('jk.jabatan_kerja', 'like', '%LEVEL 5%')
        ->where('jd.kode', 'FR.IA.05')
        ->select('s.no', DB::raw('COUNT(*) as cnt'))
        ->groupBy('s.no')
        ->having('cnt', '>', 1)
        ->get();
    echo "Duplicate nos: " . count($dupes) . "\n";
    foreach ($dupes as $d) {
        echo "  no={$d->no}: {$d->cnt} records\n";
    }
}

echo "\n\n=== CHECK: * PREFIX IN JSON (Pattern B) ===\n";
$starCount = 0;
foreach ($json as $name => $data) {
    foreach (['ia05','ia06'] as $t) {
        foreach ($data[$t] ?? [] as $s) {
            $kk = $s['kode_kuk'] ?? '';
            if (preg_match('/^\*\d/', $kk) || preg_match('/[\s\n]\*\d/', $kk)) {
                $starCount++;
                if ($starCount <= 5) echo "  $name / $t / no={$s['no']}: kk={$kk}\n";
            }
        }
    }
}
echo "Total entries with * prefix in kode_kuk: $starCount\n";

echo "\n=== CHECK: TRAILING DOT IN DB KUK ===\n";
$trailingDot = DB::table('kuks')
    ->where('kode', 'like', '%.')
    ->select('id', 'kode', 'id_subunitkompetensi')
    ->get();
echo "KUK codes with trailing dot: " . count($trailingDot) . "\n";
if (count($trailingDot) > 0) {
    echo "Samples: ";
    foreach ($trailingDot->take(10) as $k) {
        echo "id={$k->id}:{$k->kode} ";
    }
    echo "\n";
}

echo "\n=== CHECK: COMMA IN JSON KUK ===\n";
$commaInKuk = 0;
foreach ($json as $name => $data) {
    foreach (['ia05','ia06'] as $t) {
        foreach ($data[$t] ?? [] as $s) {
            $kk = $s['kode_kuk'] ?? '';
            if (strpos($kk, ',') !== false) {
                $commaInKuk++;
                if ($commaInKuk <= 5) echo "  $name / $t / no={$s['no']}: kk={$kk}\n";
            }
        }
    }
}
echo "Total entries with comma in kode_kuk: $commaInKuk\n";

echo "\nDone.\n";
