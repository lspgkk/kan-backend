<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;

echo "=== VERIFIKASI 3 ISSUE ===\n\n";

// ─── Issue 1: SI101018 no=18 — unit F.42HLK00.002.1 ───
echo "─── [1] SI101018 no=18, unit F.42HLK00.002.1 ───\n";
$jk1 = DB::table('jabatan_kerjas')->where('id_jabatan_kerja', 'SI101018')->first();
echo "Jabatan: " . ($jk1->jabatan_kerja ?? 'NOT FOUND') . "\n";

$soal1 = DB::table('kan_soals as s')
    ->join('jabatan_dokumens as jd', 's.id_dokumen', '=', 'jd.id')
    ->where('jd.id_jabatan', 'SI101018')
    ->where('s.no', 18)
    ->first(['s.id', 's.no', 's.id_unitkompetensi', 's.id_dokumen', 'jd.kode']);
if ($soal1) {
    $unit1 = DB::table('unit_kompetensis')->find($soal1->id_unitkompetensi);
    echo "Soal: id={$soal1->id}, no={$soal1->no}, dok={$soal1->kode}\n";
    echo "id_unitkompetensi: " . ($soal1->id_unitkompetensi ?? 'NULL') . "\n";
    echo "Unit in DB: " . ($unit1 ? "id={$unit1->id}, kode={$unit1->kode}" : "NOT FOUND (NULL)") . "\n";

    // Check if F.42HLK00.002.1 exists
    $checkUnit = DB::table('unit_kompetensis')->where('kode', 'F.42HLK00.002.1')->first();
    echo "F.42HLK00.002.1 exists in unit_kompetensis: " . ($checkUnit ? "YES id={$checkUnit->id}" : "NO") . "\n";

    // Check similar codes
    $similar = DB::table('unit_kompetensis')->where('kode', 'like', '%42HLK%')->get();
    echo "Units with 42HLK: " . count($similar) . "\n";
    foreach ($similar as $u) echo "  id={$u->id}, kode={$u->kode}\n";
} else {
    echo "Soal no=18 NOT FOUND for SI101018\n";
}

// ─── Issue 2: SI011002 no=42 — unit F.41BPC00.005.2 ───
echo "\n─── [2] SI011002 no=42, unit F.41BPC00.005.2 ───\n";
$jk2 = DB::table('jabatan_kerjas')->where('id_jabatan_kerja', 'SI011002')->first();
echo "Jabatan: " . ($jk2->jabatan_kerja ?? 'NOT FOUND') . "\n";

$soal2 = DB::table('kan_soals as s')
    ->join('jabatan_dokumens as jd', 's.id_dokumen', '=', 'jd.id')
    ->where('jd.id_jabatan', 'SI011002')
    ->where('s.no', 42)
    ->first(['s.id', 's.no', 's.id_unitkompetensi', 's.id_dokumen', 'jd.kode']);
if ($soal2) {
    $unit2 = DB::table('unit_kompetensis')->find($soal2->id_unitkompetensi);
    echo "Soal: id={$soal2->id}, no={$soal2->no}, dok={$soal2->kode}\n";
    echo "id_unitkompetensi: " . ($soal2->id_unitkompetensi ?? 'NULL') . "\n";
    echo "Unit in DB: " . ($unit2 ? "id={$unit2->id}, kode={$unit2->kode}" : "NOT FOUND (NULL)") . "\n";

    // Check F.41BPC00.005.2
    $checkUnit2 = DB::table('unit_kompetensis')->where('kode', 'F.41BPC00.005.2')->first();
    echo "F.41BPC00.005.2 exists: " . ($checkUnit2 ? "YES id={$checkUnit2->id}" : "NO") . "\n";

    // Check similar
    $similar2 = DB::table('unit_kompetensis')->where('kode', 'like', '%41BPC00.005%')->get();
    echo "Units with 41BPC00.005: " . count($similar2) . "\n";
    foreach ($similar2 as $u) echo "  id={$u->id}, kode={$u->kode}\n";
} else {
    echo "Soal no=42 NOT FOUND for SI011002\n";
}

// ─── Issue 3: SI092009 no=69 — kuk 1.5 tidak ada ───
echo "\n─── [3] SI092009 no=69, kuk 1.5 ───\n";
$jk3 = DB::table('jabatan_kerjas')->where('id_jabatan_kerja', 'SI092009')->first();
echo "Jabatan: " . ($jk3->jabatan_kerja ?? 'NOT FOUND') . "\n";

$soal3 = DB::table('kan_soals as s')
    ->join('jabatan_dokumens as jd', 's.id_dokumen', '=', 'jd.id')
    ->where('jd.id_jabatan', 'SI092009')
    ->where('s.no', 69)
    ->first(['s.id', 's.no', 's.id_unitkompetensi', 's.id_subunitkompetensi', 's.id_kuk', 's.id_dokumen', 'jd.kode']);
if ($soal3) {
    echo "Soal: id={$soal3->id}, no={$soal3->no}, dok={$soal3->kode}\n";
    echo "id_unitkompetensi: " . ($soal3->id_unitkompetensi ?? 'NULL') . "\n";
    echo "id_subunitkompetensi: " . ($soal3->id_subunitkompetensi ?? 'NULL') . "\n";
    echo "id_kuk: " . ($soal3->id_kuk ?? 'NULL') . "\n";

    // Check current KUK
    $kuk3 = DB::table('kuks')->find($soal3->id_kuk);
    echo "Current KUK: " . ($kuk3 ? "id={$kuk3->id}, kode={$kuk3->kode}" : "NOT FOUND (NULL)") . "\n";

    // What subunit is assigned?
    $subunit3 = DB::table('subunit_kompetensis')->find($soal3->id_subunitkompetensi);
    if ($subunit3) {
        echo "Current subunit: id={$subunit3->id}, kode={$subunit3->kode}, unit_id={$subunit3->id_unitkompetensi}\n";

        // What KUKs exist under this subunit?
        $kuksInSub = DB::table('kuks')->where('id_subunitkompetensi', $subunit3->id)->get();
        echo "KUKs available in this subunit:\n";
        foreach ($kuksInSub as $k) echo "  id={$k->id}, kode={$k->kode}\n";
    }

    // Check: does kuk kode=1.5 exist ANYWHERE in the DB?
    $kuk15 = DB::table('kuks')->where('kode', '1.5')->get();
    echo "KUK with kode='1.5' anywhere in DB:\n";
    foreach ($kuk15 as $k) {
        $subKuk = DB::table('subunit_kompetensis')->find($k->id_subunitkompetensi);
        $unitKuk = $subKuk ? DB::table('unit_kompetensis')->find($subKuk->id_unitkompetensi) : null;
        echo "  kuk id={$k->id}, subunit=" . ($subKuk->kode ?? '?') . ", unit=" . ($unitKuk->kode ?? '?') . "\n";
    }
} else {
    echo "Soal no=69 NOT FOUND for SI092009\n";
}

// ─── DEEP DIVE: Check JSON source for these specific soals ───
echo "\n─── DEEP DIVE: JSON source ───\n";
$json = json_decode(file_get_contents('c:/Users/Rafie Pradipta/Downloads/ALL_SOAL_MERGED.json'), true);

// Find the skema keys for SI101018 and SI092009
foreach ($json as $skemaKey => $skema) {
    $norm = trim(preg_replace('/^\d+\.\s*/', '', $skemaKey));
    $normLower = strtolower($norm);

    // SI101018 = Ahli Utama Hidrolika
    if (stripos($normLower, 'hidrolika') !== false) {
        echo "\nFound HIDROLIKA skema: \"{$skemaKey}\"\n";
        // Check ia04b soal no=18
        foreach (($skema['ia04b'] ?? []) as $q) {
            if ($q['no'] == 18) {
                echo "  IA04B no=18:\n";
                echo "    kode_unit: " . ($q['kode_unit'] ?? 'N/A') . "\n";
                echo "    kode_kuk: " . ($q['kode_kuk'] ?? 'N/A') . "\n";
            }
        }
        foreach (($skema['ia05'] ?? []) as $q) {
            if ($q['no'] == 18) {
                echo "  IA05 no=18:\n";
                echo "    kode_unit: " . ($q['kode_unit'] ?? 'N/A') . "\n";
                echo "    kode_kuk: " . ($q['kode_kuk'] ?? 'N/A') . "\n";
            }
        }
    }

    // SI092009 = Pelaksana Lapangan Pekerjaan Bangunan Pengaman Pantai (Level 4)
    if (stripos($normLower, 'pengaman pantai') !== false && stripos($normLower, 'level 4') !== false) {
        echo "\nFound PENGAMAN PANTAI Lv.4 skema: \"{$skemaKey}\"\n";
        // Check ia05 soal no=69
        foreach (($skema['ia05'] ?? []) as $q) {
            if ($q['no'] == 69) {
                echo "  IA05 no=69:\n";
                echo "    kode_unit: " . ($q['kode_unit'] ?? 'N/A') . "\n";
                echo "    kode_kuk: " . ($q['kode_kuk'] ?? 'N/A') . "\n";
            }
        }
    }
}

// Also check: what unit is currently used by soal no=69 of SI092009?
echo "\n─── SOAL 69 UNIT CHECK ───\n";
$s69 = DB::table('kan_soals')->find(19152);
if ($s69) {
    $u69 = DB::table('unit_kompetensis')->find($s69->id_unitkompetensi);
    $su69 = DB::table('subunit_kompetensis')->find($s69->id_subunitkompetensi);
    echo "soal_id=19152, id_unitkompetensi={$s69->id_unitkompetensi} ({$u69->kode})\n";
    echo "subunit: " . ($su69 ? "id={$su69->id}, kode={$su69->kode}" : "NULL") . "\n";

    // What OTHER subunits exist for this unit that might have kuk 1.5?
    $allSubs = DB::table('subunit_kompetensis')->where('id_unitkompetensi', $s69->id_unitkompetensi)->get();
    echo "All subunits for unit {$u69->kode}:\n";
    foreach ($allSubs as $as) {
        $kukCount = DB::table('kuks')->where('id_subunitkompetensi', $as->id)->count();
        $has15 = DB::table('kuks')->where('id_subunitkompetensi', $as->id)->where('kode', '1.5')->exists();
        echo "  subunit id={$as->id}, kode={$as->kode}: {$kukCount} KUKs" . ($has15 ? " [HAS 1.5!]" : "") . "\n";
        if ($has15) {
            $allKuks = DB::table('kuks')->where('id_subunitkompetensi', $as->id)->get();
            foreach ($allKuks as $ak) echo "    kuk id={$ak->id}, kode={$ak->kode}\n";
        }
    }
}

// Check SI101018 soal 18 unit more carefully
echo "\n─── SOAL 18 (SI101018) DEEP CHECK ───\n";
$s18 = DB::table('kan_soals')->find(7448);
if ($s18) {
    $u18 = DB::table('unit_kompetensis')->find($s18->id_unitkompetensi);
    echo "Current unit: id={$u18->id}, kode={$u18->kode}\n";
    echo "Expected unit: F.42HLK00.002.1 → id=1287 (exists in DB)\n";

    // Why was this mapping chosen? Let's check what units exist with same code but different id
    $sameCode = DB::table('unit_kompetensis')->where('kode', 'F.42HLK00.002.1')->get();
    echo "All units with kode=F.42HLK00.002.1:\n";
    foreach ($sameCode as $sc) {
        $subCount = DB::table('subunit_kompetensis')->where('id_unitkompetensi', $sc->id)->count();
        echo "  id={$sc->id}, kode={$sc->kode}, subunit_count={$subCount}\n";
    }
}

echo "\nDone.\n";
