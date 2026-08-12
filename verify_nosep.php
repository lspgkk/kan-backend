<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$checks = [
    ['name' => 'ANALIS STRUKTUR BANGUNAN RISHA', 'range' => '1-10', 'keyword' => '%ANALIS STRUKTUR BANGUNAN RISHA%'],
    ['name' => 'TEKNISI GEOTEKNIK (LEVEL 5)', 'range' => '1-2', 'keyword' => '%TEKNISI GEOTEKNIK%LEVEL 5%'],
    ['name' => 'PELAKSANA LAPANGAN PEKERJAAN BANGUNAN PENGAMAN PANTAI (LEVEL 5)', 'range' => '1-39, 53-65', 'keyword' => '%PELAKSANA LAPANGAN PEKERJAAN BANGUNAN PENGAMAN PANTAI%LEVEL 5%'],
    ['name' => 'SPESIALIS SIG', 'range' => 'all', 'keyword' => '%SPESIALIS SIG%'],
];

echo "=== POST-FIX VERIFICATION ===\n\n";

foreach ($checks as $check) {
    if ($check['name'] === 'PELAKSANA LAPANGAN PEKERJAAN BANGUNAN PENGAMAN PANTAI (LEVEL 5)') {
        $soals = DB::table('kan_soals as s')
            ->join('jabatan_dokumens as jd', 's.id_dokumen', '=', 'jd.id')
            ->join('jabatan_kerjas as jk', 'jd.id_jabatan', '=', 'jk.id_jabatan_kerja')
            ->join('kuks', 's.id_kuk', '=', 'kuks.id')
            ->where('jk.jabatan_kerja', 'like', '%PELAKSANA LAPANGAN PEKERJAAN BANGUNAN PENGAMAN PANTAI%')
            ->where('jk.jabatan_kerja', 'like', '%LEVEL 5%')
            ->where('jd.kode', 'FR.IA.05')
            ->select('s.no', 'kuks.kode')
            ->orderBy('s.no')
            ->get();
    } elseif ($check['name'] === 'TEKNISI GEOTEKNIK (LEVEL 5)') {
        $soals = DB::table('kan_soals as s')
            ->join('jabatan_dokumens as jd', 's.id_dokumen', '=', 'jd.id')
            ->join('jabatan_kerjas as jk', 'jd.id_jabatan', '=', 'jk.id_jabatan_kerja')
            ->join('kuks', 's.id_kuk', '=', 'kuks.id')
            ->where('jk.jabatan_kerja', 'like', '%TEKNISI GEOTEKNIK%')
            ->where('jk.jabatan_kerja', 'like', '%LEVEL 5%')
            ->where('jd.kode', 'FR.IA.05')
            ->select('s.no', 'kuks.kode')
            ->orderBy('s.no')
            ->get();
    } else {
        $soals = DB::table('kan_soals as s')
            ->join('jabatan_dokumens as jd', 's.id_dokumen', '=', 'jd.id')
            ->join('jabatan_kerjas as jk', 'jd.id_jabatan', '=', 'jk.id_jabatan_kerja')
            ->join('kuks', 's.id_kuk', '=', 'kuks.id')
            ->where('jk.jabatan_kerja', 'like', $check['keyword'])
            ->where('jd.kode', 'FR.IA.05')
            ->select('s.no', 'kuks.kode')
            ->orderBy('s.no')
            ->get();
    }

    $uniqueKuk = $soals->pluck('kode')->unique()->sort()->values();
    echo "{$check['name']}:\n";
    echo "  Total: {$soals->count()} soal, Unique KUK: {$uniqueKuk->count()}\n";
    echo "  KUK: {$uniqueKuk->implode(', ')}\n";

    // Check if ALL are "1.1" (would indicate NOT fixed)
    $all11 = $uniqueKuk->count() === 1 && $uniqueKuk[0] === '1.1';
    $status = $all11 ? '❌ STILL ALL 1.1!' : '✅ FIXED';
    echo "  Status: $status\n";

    // Show first 10
    echo "  First 10: ";
    $items = [];
    foreach ($soals->take(10) as $s) { $items[] = "{$s->no}→{$s->kode}"; }
    echo implode(', ', $items) . "\n\n";
}

echo "Done.\n";
