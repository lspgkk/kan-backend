<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== VERIFY: AHLI UTAMA GEOTEKNIK IA05 ===\n\n";

$soals = DB::table('kan_soals as s')
    ->join('jabatan_dokumens as jd', 's.id_dokumen', '=', 'jd.id')
    ->join('jabatan_kerjas as jk', 'jd.id_jabatan', '=', 'jk.id_jabatan_kerja')
    ->join('kuks', 's.id_kuk', '=', 'kuks.id')
    ->where('jk.jabatan_kerja', 'like', '%UTAMA GEOTEKNIK%')
    ->where('jd.kode', 'FR.IA.05')
    ->select('s.no', 'kuks.kode')
    ->orderBy('s.no')
    ->get();

$uniqueKuk = $soals->pluck('kode')->unique()->sort()->values();
echo "Total soal: " . $soals->count() . "\n";
echo "Unique KUK: " . $uniqueKuk->count() . "\n";
echo "KUK values: " . $uniqueKuk->implode(', ') . "\n\n";

echo "First 15:\n";
foreach ($soals->take(15) as $s) {
    echo sprintf("  no=%3d → kuk=%s\n", $s->no, $s->kode);
}

echo "\nLast 10:\n";
foreach ($soals->take(-10) as $s) {
    echo sprintf("  no=%3d → kuk=%s\n", $s->no, $s->kode);
}

// Verify 4 "KUK 1.1 semua" items from MIGRASI DATA
echo "\n=== VERIFY: 4 'KUK 1.1' ITEMS FROM MIGRASI DATA ===\n";
$kuk11Items = [
    ['jabatan' => '%UTAMA GEOTEKNIK%', 'id' => 38, 'desc' => 'AHLI UTAMA GEOTEKNIK'],
    ['jabatan' => '%TEKNIK TEROWONGAN%', 'id' => 50, 'desc' => 'TEKNIK TEROWONGAN'],
    ['jabatan' => '%BANGUNAN PENGAMAN PANTAI%LEVEL 4%', 'id' => 165, 'desc' => 'BANGUNAN PENGAMAN PANTAI (LEVEL 4)'],
    ['jabatan' => '%OPERATOR UTAMA SURVEI TERESTRIS%', 'id' => 166, 'desc' => 'OPERATOR UTAMA SURVEI TERESTRIS'],
];

foreach ($kuk11Items as $item) {
    // Need to handle BANGUNAN PENGAMAN PANTAI LEVEL 4 differently
    if ($item['id'] === 165) {
        $soals2 = DB::table('kan_soals as s')
            ->join('jabatan_dokumens as jd', 's.id_dokumen', '=', 'jd.id')
            ->join('jabatan_kerjas as jk', 'jd.id_jabatan', '=', 'jk.id_jabatan_kerja')
            ->join('kuks', 's.id_kuk', '=', 'kuks.id')
            ->where('jk.jabatan_kerja', 'like', '%BANGUNAN PENGAMAN PANTAI%')
            ->where('jk.jabatan_kerja', 'like', '%LEVEL 4%')
            ->where('jd.kode', 'FR.IA.05')
            ->select('kuks.kode')
            ->distinct()
            ->get();
    } else {
        $soals2 = DB::table('kan_soals as s')
            ->join('jabatan_dokumens as jd', 's.id_dokumen', '=', 'jd.id')
            ->join('jabatan_kerjas as jk', 'jd.id_jabatan', '=', 'jk.id_jabatan_kerja')
            ->join('kuks', 's.id_kuk', '=', 'kuks.id')
            ->where('jk.jabatan_kerja', 'like', $item['jabatan'])
            ->where('jd.kode', 'FR.IA.05')
            ->select('kuks.kode')
            ->distinct()
            ->get();
    }
    $kukVals = $soals2->pluck('kode')->unique()->sort()->values();
    echo "  {$item['desc']}:\n";
    echo "    Unique KUK: " . $kukVals->count() . " → " . $kukVals->implode(', ') . "\n";
}

// Post-fix summary
echo "\n=== POST-FIX SUMMARY ===\n";
$nullKuk = DB::table('kan_soals')->whereNull('id_kuk')->count();
$total = DB::table('kan_soals')->count();
echo "Total kan_soals: $total\n";
echo "NULL id_kuk remaining: $nullKuk\n";

foreach (['FR.IA.05', 'FR.IA.06', 'FR.IA.04.B'] as $dk) {
    $cnt = DB::table('kan_soals as s')
        ->join('kuks', 's.id_kuk', '=', 'kuks.id')
        ->join('jabatan_dokumens as jd', 's.id_dokumen', '=', 'jd.id')
        ->where('jd.kode', $dk)
        ->select('kuks.kode')
        ->distinct()
        ->count();
    $totalDk = DB::table('kan_soals as s')
        ->join('jabatan_dokumens as jd', 's.id_dokumen', '=', 'jd.id')
        ->where('jd.kode', $dk)
        ->count();
    $nullDk = DB::table('kan_soals as s')
        ->join('jabatan_dokumens as jd', 's.id_dokumen', '=', 'jd.id')
        ->where('jd.kode', $dk)
        ->whereNull('s.id_kuk')
        ->count();
    echo "$dk: $totalDk total, $cnt unique KUK codes, $nullDk NULL\n";
}

echo "\nDone.\n";
