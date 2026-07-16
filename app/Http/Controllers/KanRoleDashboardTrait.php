<?php

namespace App\Http\Controllers;

use App\Models\Barcode;
use App\Models\JabatanConfigA;
use App\Models\JabatanKerja;
use App\Models\KanPraktisiJabatan;
use App\Models\KlasifikasiKualifikasi;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

trait KanRoleDashboardTrait
{
    public function jabker(): JsonResponse
    {
        $jabkers = JabatanKerja::select([
                'jabatan_kerjas.id_jabatan_kerja',
                'jabatan_kerjas.jabatan_kerja',
                'jabatan_kerjas.jenjang_id',
            ])
            ->withCount(['jabatanConfigA as jumlah_skema' => function ($q) {
                $q->whereNotNull('kan_no_skema');
            }])
            ->where('lsp_id_klasifikasi', 'SI')
            ->where('versi', 2)
            ->get();

        // Batch load praktisi progress per jabker
        $jabkerIds = $jabkers->pluck('id_jabatan_kerja');
        $praktisiCounts = KanPraktisiJabatan::select('id_jabatan_kerja', DB::raw('count(*) as total'))
            ->whereIn('id_jabatan_kerja', $jabkerIds)
            ->groupBy('id_jabatan_kerja')
            ->pluck('total', 'id_jabatan_kerja');

        // Count completed praktisi (IA06 signed) per jabker
        $completedCounts = KanPraktisiJabatan::select('kan_praktisi_jabatans.id_jabatan_kerja', DB::raw('count(distinct kan_praktisi_jabatans.id) as selesai'))
            ->join('barcodes', 'barcodes.kan_praktisi_jabatan_id', '=', 'kan_praktisi_jabatans.id')
            ->whereIn('kan_praktisi_jabatans.id_jabatan_kerja', $jabkerIds)
            ->where('barcodes.jenis_dokumen', 'IA06')
            ->whereNotNull('barcodes.url_image')
            ->groupBy('kan_praktisi_jabatans.id_jabatan_kerja')
            ->pluck('selesai', 'id_jabatan_kerja');

        $result = $jabkers->map(function ($jabker) use ($praktisiCounts, $completedCounts) {
            $total   = (int) ($praktisiCounts[$jabker->id_jabatan_kerja] ?? 0);
            $selesai = (int) ($completedCounts[$jabker->id_jabatan_kerja] ?? 0);

            $hasActive = KlasifikasiKualifikasi::where('jabatan_kerja', $jabker->id_jabatan_kerja)
                ->whereHas('asesiJadwal')
                ->exists();

            return [
                'id_jabatan_kerja' => $jabker->id_jabatan_kerja,
                'jabatan_kerja'    => $jabker->jabatan_kerja,
                'jumlah_skema'     => (int) $jabker->jumlah_skema,
                'status'           => $hasActive ? 'aktif' : '-',
                'total_praktisi'   => $total,
                'praktisi_selesai' => $selesai,
            ];
        });

        return response()->json(['message' => 'OK', 'data' => $result]);
    }

    public function skema(): JsonResponse
    {
        $skemas = JabatanConfigA::with([
                'jabatan:id_jabatan_kerja,jabatan_kerja',
            ])
            ->whereNotNull('kan_no_skema')
            ->select(['id_jabatan', 'kan_no_skema', 'kan_penyusun_id', 'kan_validator_id'])
            ->get();

        // Batch load praktisi progress per id_jabatan
        $jabatanIds = $skemas->pluck('id_jabatan');
        $praktisiCounts = KanPraktisiJabatan::select('id_jabatan_kerja', DB::raw('count(*) as total'))
            ->whereIn('id_jabatan_kerja', $jabatanIds)
            ->groupBy('id_jabatan_kerja')
            ->pluck('total', 'id_jabatan_kerja');

        $completedCounts = KanPraktisiJabatan::select('kan_praktisi_jabatans.id_jabatan_kerja', DB::raw('count(distinct kan_praktisi_jabatans.id) as selesai'))
            ->join('barcodes', 'barcodes.kan_praktisi_jabatan_id', '=', 'kan_praktisi_jabatans.id')
            ->whereIn('kan_praktisi_jabatans.id_jabatan_kerja', $jabatanIds)
            ->where('barcodes.jenis_dokumen', 'IA06')
            ->whereNotNull('barcodes.url_image')
            ->groupBy('kan_praktisi_jabatans.id_jabatan_kerja')
            ->pluck('selesai', 'id_jabatan_kerja');

        $result = $skemas->map(function ($config) use ($praktisiCounts, $completedCounts) {
            $total   = (int) ($praktisiCounts[$config->id_jabatan] ?? 0);
            $selesai = (int) ($completedCounts[$config->id_jabatan] ?? 0);

            return [
                'id'              => $config->id_jabatan,
                'id_jabatan'      => $config->id_jabatan,
                'nama'            => $config->jabatan?->jabatan_kerja,
                'jabatan_kerja'   => $config->jabatan?->jabatan_kerja,
                'kan_no_skema'    => $config->kan_no_skema,
                'penyusun_id'     => $config->kan_penyusun_id,
                'validator_id'    => $config->kan_validator_id,
                'total_praktisi'  => $total,
                'praktisi_selesai'=> $selesai,
            ];
        });

        return response()->json(['message' => 'OK', 'data' => $result]);
    }
}
