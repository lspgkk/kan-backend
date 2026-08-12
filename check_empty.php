<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$totalSoal = DB::table('kan_soals')->where('tipe', 1)->count();
$nullKunci = DB::table('kan_soals')->where('tipe', 1)
    ->where(function($q) { $q->whereNull('jawaban')->orWhere('jawaban', ''); })
    ->count();

echo "Total IA05 soals: $totalSoal\n";
echo "With null/empty kunci: $nullKunci\n";

$emptyJawaban = DB::table('kan_ia5_answers')
    ->where(function($q) { $q->where('jawaban', '')->orWhereNull('jawaban'); })
    ->count();
echo "IA05 answers with empty jawaban_asesi: $emptyJawaban\n";

// Show a few examples
$examples = DB::table('kan_ia5_answers as a')
    ->join('kan_soals as s', 'a.soal_id', '=', 's.id')
    ->where(function($q) { $q->where('a.jawaban', '')->orWhereNull('a.jawaban'); })
    ->select('a.id', 'a.skor', 'a.jawaban', 's.jawaban as kunci', 's.soal')
    ->limit(10)
    ->get();

echo "\nExamples of empty jawaban answers:\n";
foreach ($examples as $ex) {
    echo "  ID={$ex->id} skor={$ex->skor} jawaban='{$ex->jawaban}' kunci_soal='{$ex->kunci}' soal=" . mb_substr($ex->soal, 0, 60) . "...\n";
}
