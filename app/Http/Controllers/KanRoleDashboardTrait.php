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
    /**
     * Scope dashboard by the authenticated role: penyusun (14) only sees
     * skema where they are kan_penyusun_id, validator (15) only sees
     * skema where they are kan_validator_id. Other roles see everything.
     */
    private function kanRoleScope(): ?array
    {
        $user = auth()->user();
        if (!$user) {
            return null;
        }

        return match ((int) $user->role_id) {
            14 => ['column' => 'kan_penyusun_id', 'user_id' => (int) $user->id],
            15 => ['column' => 'kan_validator_id', 'user_id' => (int) $user->id],
            default => null,
        };
    }

    public function jabker(): JsonResponse
    {
        $scope = $this->kanRoleScope();

        $jabkers = JabatanKerja::select([
                'jabatan_kerjas.id_jabatan_kerja',
                'jabatan_kerjas.jabatan_kerja',
                'jabatan_kerjas.jenjang_id',
            ])
            ->addSelect([
                'jumlah_skema' => JabatanConfigA::selectRaw('count(*)')
                    ->whereColumn('id_jabatan', 'jabatan_kerjas.id_jabatan_kerja')
                    ->whereNotNull('kan_no_skema')
                    ->when($scope, fn ($q) => $q->where($scope['column'], $scope['user_id'])),
            ])
            ->where('lsp_id_klasifikasi', 'SI')
            ->where('versi', 2)
            ->when($scope, function ($q) use ($scope) {
                $q->whereExists(function ($sub) use ($scope) {
                    $sub->select(DB::raw(1))
                        ->from('jabatan_config_a')
                        ->whereColumn('jabatan_config_a.id_jabatan', 'jabatan_kerjas.id_jabatan_kerja')
                        ->where('jabatan_config_a.' . $scope['column'], $scope['user_id'])
                        ->whereNotNull('jabatan_config_a.kan_no_skema');
                });
            })
            ->get();

        $jabkerIds = $jabkers->pluck('id_jabatan_kerja');

        // Batch praktisi count per jabker
        $praktisiCounts = KanPraktisiJabatan::select('id_jabatan_kerja', DB::raw('count(*) as total'))
            ->whereIn('id_jabatan_kerja', $jabkerIds)
            ->groupBy('id_jabatan_kerja')
            ->pluck('total', 'id_jabatan_kerja');

        // Batch completed count (IA06 signed) per jabker
        $completedCounts = KanPraktisiJabatan::select('kan_praktisi_jabatan.id_jabatan_kerja', DB::raw('count(distinct kan_praktisi_jabatan.id) as selesai'))
            ->join('barcodes', function ($join) {
                $join->on('barcodes.kan_praktisi_jabatan_id', '=', 'kan_praktisi_jabatan.id')
                    ->where('barcodes.jenis_dokumen', 'IA06')
                    ->whereNotNull('barcodes.url_image');
            })
            ->whereIn('kan_praktisi_jabatan.id_jabatan_kerja', $jabkerIds)
            ->groupBy('kan_praktisi_jabatan.id_jabatan_kerja')
            ->pluck('selesai', 'id_jabatan_kerja');

        // Batch check active status (has asesi with jadwal)
        $activeJabkers = KlasifikasiKualifikasi::select('jabatan_kerja')
            ->whereIn('jabatan_kerja', $jabkerIds)
            ->whereHas('asesiJadwal')
            ->distinct()
            ->pluck('jabatan_kerja')
            ->toArray();

        $result = $jabkers->map(function ($jabker) use ($praktisiCounts, $completedCounts, $activeJabkers) {
            $total   = (int) ($praktisiCounts[$jabker->id_jabatan_kerja] ?? 0);
            $selesai = (int) ($completedCounts[$jabker->id_jabatan_kerja] ?? 0);

            return [
                'id_jabatan_kerja' => $jabker->id_jabatan_kerja,
                'jabatan_kerja'    => $jabker->jabatan_kerja,
                'jumlah_skema'     => (int) $jabker->jumlah_skema,
                'status'           => in_array($jabker->id_jabatan_kerja, $activeJabkers) ? 'aktif' : '-',
                'total_praktisi'   => $total,
                'praktisi_selesai' => $selesai,
            ];
        });

        return response()->json(['message' => 'OK', 'data' => $result]);
    }

    public function skema(): JsonResponse
    {
        $scope = $this->kanRoleScope();

        $skemas = JabatanConfigA::with([
                'jabatan:id_jabatan_kerja,jabatan_kerja',
            ])
            ->whereNotNull('kan_no_skema')
            ->when($scope, fn ($q) => $q->where($scope['column'], $scope['user_id']))
            ->select(['id_jabatan', 'kan_no_skema', 'kan_penyusun_id', 'kan_validator_id'])
            ->get();

        $jabatanIds = $skemas->pluck('id_jabatan');

        // Batch praktisi count per id_jabatan
        $praktisiCounts = KanPraktisiJabatan::select('id_jabatan_kerja', DB::raw('count(*) as total'))
            ->whereIn('id_jabatan_kerja', $jabatanIds)
            ->groupBy('id_jabatan_kerja')
            ->pluck('total', 'id_jabatan_kerja');

        // Batch completed count (IA06 signed) per id_jabatan
        $completedCounts = KanPraktisiJabatan::select('kan_praktisi_jabatan.id_jabatan_kerja', DB::raw('count(distinct kan_praktisi_jabatan.id) as selesai'))
            ->join('barcodes', function ($join) {
                $join->on('barcodes.kan_praktisi_jabatan_id', '=', 'kan_praktisi_jabatan.id')
                    ->where('barcodes.jenis_dokumen', 'IA06')
                    ->whereNotNull('barcodes.url_image');
            })
            ->whereIn('kan_praktisi_jabatan.id_jabatan_kerja', $jabatanIds)
            ->groupBy('kan_praktisi_jabatan.id_jabatan_kerja')
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
