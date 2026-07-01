<?php

namespace App\Http\Controllers;

use App\Models\JabatanConfigA;
use App\Models\JabatanKerja;
use App\Models\KanAsesorJabatan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KanConfigController extends Controller
{
    /**
     * Bulk-submit KAN configuration: no_skema, penyusun, validator,
     * and asesor-to-jabatan_kerja mappings.
     */
    public function submit(Request $request)
    {
        $validated = $request->validate([
            'entries'              => 'required|array|min:1',
            'entries.*.no_skema'   => 'required|string',
            'entries.*.skema'      => 'required|string',
            'entries.*.penyusun'   => 'required|string',
            'entries.*.validator'  => 'required|string',
            'entries.*.tgl_penyusun'  => 'nullable|string',
            'entries.*.tgl_validator' => 'nullable|string',
            'entries.*.asesor'     => 'nullable|array',
        ]);

        $entries = $validated['entries'];

        // ── Phase 1: Collect & validate all noreg values ──
        $noregToFields = []; // noreg => [field paths]
        $allSkemaNames = [];

        foreach ($entries as $idx => $entry) {
            $allSkemaNames[] = trim($entry['skema']);

            // penyusun
            $noreg = trim($entry['penyusun']);
            $noregToFields[$noreg][] = "entries.{$idx}.penyusun";

            // validator
            $noreg = trim($entry['validator']);
            $noregToFields[$noreg][] = "entries.{$idx}.validator";

            // asesor entries
            if (!empty($entry['asesor'])) {
                foreach ($entry['asesor'] as $key => $asesorNoreg) {
                    $noreg = trim($asesorNoreg);
                    $noregToFields[$noreg][] = "entries.{$idx}.asesor.{$key}";
                }
            }
        }

        $allUniqueNoregs = array_keys($noregToFields);

        // Query registered users with role asesor (5)
        $userLookup = User::whereIn('noreg', $allUniqueNoregs)
            ->where('role_id', 5)
            ->pluck('id', 'noreg')
            ->toArray();

        $registeredNoregs = array_keys($userLookup);
        $missingNoregs = array_diff($allUniqueNoregs, $registeredNoregs);

        if (!empty($missingNoregs)) {
            $errors = [];
            foreach ($missingNoregs as $noreg) {
                $errors[] = [
                    'noreg'  => $noreg,
                    'fields' => $noregToFields[$noreg] ?? [],
                ];
            }

            return response()->json([
                'message' => 'Validasi gagal: MET/noreg tidak terdaftar sebagai asesor (role_id=5).',
                'errors'  => ['unregistered_noreg' => $errors],
            ], 422);
        }

        // ── Phase 2: Build skema name → JabatanKerja lookup ──
        $allSkemaNames = array_unique($allSkemaNames);
        $jabatanKerjaLookup = JabatanKerja::whereIn('jabatan_kerja', $allSkemaNames)
            ->pluck('id_jabatan_kerja', 'jabatan_kerja')
            ->toArray();

        // ── Phase 3: Process in transaction ──
        $updatedConfigs = 0;
        $skippedNoMatch = [];
        $skippedMissingConfig = [];
        $asesorRecords = [];

        DB::transaction(function () use (
            $entries, $userLookup, $jabatanKerjaLookup,
            &$updatedConfigs, &$skippedNoMatch, &$skippedMissingConfig, &$asesorRecords
        ) {
            foreach ($entries as $entry) {
                $skemaName = trim($entry['skema']);

                // Match skema name to JabatanKerja
                $idJabatanKerja = $jabatanKerjaLookup[$skemaName] ?? null;
                if (!$idJabatanKerja) {
                    $skippedNoMatch[] = $skemaName;
                    continue;
                }

                $penyusunId  = $userLookup[trim($entry['penyusun'])] ?? null;
                $validatorId = $userLookup[trim($entry['validator'])] ?? null;

                // Update JabatanConfigA where id_jabatan = id_jabatan_kerja
                $updated = JabatanConfigA::where('id_jabatan', $idJabatanKerja)->update([
                    'kan_no_skema'      => $entry['no_skema'],
                    'kan_penyusun_id'   => $penyusunId,
                    'kan_penyusun_tgl'  => $this->parseDate($entry['tgl_penyusun'] ?? null),
                    'kan_validator_id'  => $validatorId,
                    'kan_validator_tgl' => $this->parseDate($entry['tgl_validator'] ?? null),
                ]);

                if ($updated === 0) {
                    $skippedMissingConfig[] = $entry['no_skema'];
                } else {
                    $updatedConfigs++;
                }

                // Collect asesor mappings for batch upsert
                if (!empty($entry['asesor'])) {
                    foreach ($entry['asesor'] as $asesorNoreg) {
                        $asesorUserId = $userLookup[trim($asesorNoreg)] ?? null;
                        if ($asesorUserId) {
                            $asesorRecords[] = [
                                'user_id'          => $asesorUserId,
                                'id_jabatan_kerja' => $idJabatanKerja,
                                'updated_at'       => now(),
                                'created_at'       => now(),
                            ];
                        }
                    }
                }
            }

            // Batch upsert asesor mappings
            if (!empty($asesorRecords)) {
                KanAsesorJabatan::upsert(
                    $asesorRecords,
                    ['user_id', 'id_jabatan_kerja'],
                    ['updated_at']
                );
            }
        });

        return response()->json([
            'message' => 'Konfigurasi KAN berhasil disimpan.',
            'data'    => [
                'total_entries'           => count($entries),
                'updated_configs'         => $updatedConfigs,
                'asesor_mappings_upserted'=> count($asesorRecords),
                'skipped_no_match'        => $skippedNoMatch,
                'skipped_missing_config'  => $skippedMissingConfig,
            ],
        ]);
    }

    // ── Helpers ──

    private function parseDate(mixed $value): ?string
    {
        if (empty($value)) {
            return null;
        }
        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}
