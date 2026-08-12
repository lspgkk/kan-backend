<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

// Show soals without kunci
$noKunci = DB::table('kan_soals')
    ->where('id_dokumen', 8473)
    ->where('tipe', 1)
    ->where(function($q) { $q->whereNull('jawaban')->orWhere('jawaban', ''); })
    ->orderBy('no')
    ->get();

echo "Soal tanpa kunci: " . count($noKunci) . "\n\n";
foreach ($noKunci as $s) {
    echo "  No={$s->no} id={$s->id} | " . mb_substr($s->soal, 0, 80) . "...\n";
    echo "    kode_unit={$s->id_unitkompetensi}\n";
}

// Also show empty jawaban that remain
$remain = DB::table('kan_ia5_answers as a')
    ->join('kan_soals as s', 'a.soal_id', '=', 's.id')
    ->where('a.dokumen_id', 8473)
    ->where(function($q) { $q->where('a.jawaban', '')->orWhereNull('a.jawaban'); })
    ->select('s.no', DB::raw('COUNT(*) as cnt'))
    ->groupBy('s.no')
    ->orderBy('s.no')
    ->get();

echo "\nRemaining empty jawaban by soal no:\n";
foreach ($remain as $r) {
    echo "  Soal no={$r->no}: {$r->cnt} empty\n";
}
