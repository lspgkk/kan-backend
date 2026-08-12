<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== LIST JABATAN KERJA DENGAN JAWABAN_ASESI KOSONG ===\n\n";

// Query: find all id_jabatan_kerja with empty jawaban in kan_ia5_answers
// via kan_praktisi_jabatan -> jabatan_kerjas
$results = DB::table('kan_ia5_answers as a')
    ->join('kan_praktisi_jabatan as kpj', 'a.kan_praktisi_jabatan_id', '=', 'kpj.id')
    ->join('jabatan_kerjas as jk', 'kpj.id_jabatan_kerja', '=', 'jk.id_jabatan_kerja')
    ->where('a.jawaban', '')
    ->orWhereNull('a.jawaban')
    ->select(
        'jk.id_jabatan_kerja',
        'jk.jabatan_kerja',
        DB::raw('COUNT(*) as empty_count'),
        DB::raw('SUM(a.skor) as correct_but_empty'),
        DB::raw('COUNT(*) - SUM(a.skor) as wrong_but_empty')
    )
    ->groupBy('jk.id_jabatan_kerja', 'jk.jabatan_kerja')
    ->orderBy('empty_count', 'desc')
    ->get();

if ($results->isEmpty()) {
    echo "✅ Tidak ada jawaban kosong.\n";
} else {
    echo str_pad("ID Jabatan Kerja", 14) . str_pad("Nama Jabatan Kerja", 70) . str_pad("Empty Count", 13) . str_pad("Correct(1)", 12) . "Wrong(0)\n";
    echo str_repeat("-", 130) . "\n";

    $totalEmpty = 0;
    $totalCorrect = 0;
    $totalWrong = 0;

    foreach ($results as $r) {
        $totalEmpty += $r->empty_count;
        $totalCorrect += $r->correct_but_empty;
        $totalWrong += $r->wrong_but_empty;
        echo str_pad($r->id_jabatan_kerja, 14)
            . str_pad(mb_substr($r->jabatan_kerja, 0, 68), 70)
            . str_pad($r->empty_count, 13)
            . str_pad($r->correct_but_empty, 12)
            . $r->wrong_but_empty . "\n";
    }

    echo str_repeat("-", 130) . "\n";
    echo str_pad("TOTAL: " . count($results) . " jabatan", 14)
        . str_pad("", 70)
        . str_pad($totalEmpty, 13)
        . str_pad($totalCorrect, 12)
        . $totalWrong . "\n";
}

echo "\n=== RINCIAN PER DOKUMEN ===\n\n";

// Also breakdown by dokumen type
$dokResults = DB::table('kan_ia5_answers as a')
    ->join('kan_praktisi_jabatan as kpj', 'a.kan_praktisi_jabatan_id', '=', 'kpj.id')
    ->join('jabatan_kerjas as jk', 'kpj.id_jabatan_kerja', '=', 'jk.id_jabatan_kerja')
    ->join('kan_soals as s', 'a.soal_id', '=', 's.id')
    ->where('a.jawaban', '')
    ->orWhereNull('a.jawaban')
    ->select(
        'jk.id_jabatan_kerja',
        'jk.jabatan_kerja',
        's.id_dokumen',
        DB::raw('COUNT(*) as cnt')
    )
    ->groupBy('jk.id_jabatan_kerja', 'jk.jabatan_kerja', 's.id_dokumen')
    ->orderBy('jk.id_jabatan_kerja')
    ->get();

$currentJk = '';
foreach ($dokResults as $dr) {
    if ($dr->id_jabatan_kerja !== $currentJk) {
        $currentJk = $dr->id_jabatan_kerja;
        echo "\n{$dr->id_jabatan_kerja} | {$dr->jabatan_kerja}\n";
    }
    echo "  └─ dokumen_id={$dr->id_dokumen}  empty_count={$dr->cnt}\n";
}

echo "\n=== ROOT CAUSE: SOAL DENGAN KUNCI NULL ===\n\n";

// Check how many soals have null kunci
$soalStats = DB::table('kan_soals as s')
    ->join('jabatan_dokumens as jd', 's.id_dokumen', '=', 'jd.id')
    ->where('s.tipe', 1)
    ->where(function($q) { $q->whereNull('s.jawaban')->orWhere('s.jawaban', ''); })
    ->select('jd.id_jabatan', 'jd.id as dok_id', DB::raw('COUNT(*) as cnt'))
    ->groupBy('jd.id_jabatan', 'jd.id')
    ->orderBy('cnt', 'desc')
    ->get();

$totalSoalNull = 0;
foreach ($soalStats as $ss) {
    $totalSoalNull += $ss->cnt;
    $jkName = DB::table('jabatan_kerjas')->where('id_jabatan_kerja', $ss->id_jabatan)->value('jabatan_kerja') ?? '???';
    echo "{$ss->id_jabatan} | {$jkName}\n";
    echo "  └─ dok_id={$ss->dok_id}  soal_tanpa_kunci={$ss->cnt}\n";
}
echo "\nTotal soal IA05 tanpa kunci: {$totalSoalNull}\n";
