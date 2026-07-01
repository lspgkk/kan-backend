<?php

namespace App\Http\Controllers;

use App\Models\AsesorBnsp;
use App\Models\JabatanConfigA;
use App\Models\JabatanKerja;
use App\Models\KanAsesorJabatan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class KanConfigController extends Controller
{
    /**
     * Bulk-submit KAN configuration: no_skema, penyusun, validator,
     * and asesor-to-jabatan_kerja mappings.
     *
     * If a noreg is not yet registered as a user (role_id=5), the endpoint
     * will attempt to fetch the asesor data from BNSP and auto-create the
     * user account.
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

            $noreg = trim($entry['penyusun']);
            $noregToFields[$noreg][] = "entries.{$idx}.penyusun";

            $noreg = trim($entry['validator']);
            $noregToFields[$noreg][] = "entries.{$idx}.validator";

            if (!empty($entry['asesor'])) {
                foreach ($entry['asesor'] as $key => $asesorNoreg) {
                    $noreg = trim($asesorNoreg);
                    $noregToFields[$noreg][] = "entries.{$idx}.asesor.{$key}";
                }
            }
        }

        $allUniqueNoregs = array_keys($noregToFields);

        // Query existing users with role asesor (5) + also check users with same noreg but different role
        $existingUsers = User::whereIn('noreg', $allUniqueNoregs)
            ->where('role_id', 5)
            ->pluck('id', 'noreg')
            ->toArray();

        // Also check users with the same noreg but possibly different role
        $existingNoregAnyRole = User::whereIn('noreg', $allUniqueNoregs)
            ->pluck('id', 'noreg')
            ->toArray();

        $missingNoregs = array_diff($allUniqueNoregs, array_keys($existingUsers));
        $newlyCreatedUsers = [];

        // ── Phase 1b: Auto-fetch & create users for missing noreg ──
        if (!empty($missingNoregs)) {
            $stillMissing = [];

            foreach ($missingNoregs as $noreg) {
                // Skip if user already exists with this noreg (but not role 5)
                if (isset($existingNoregAnyRole[$noreg])) {
                    $existingUsers[$noreg] = $existingNoregAnyRole[$noreg];
                    continue;
                }

                // 1. Fetch from BNSP — this also creates/updates the AsesorBnsp record
                $asesorBnsp = AsesorBnsp::fetchFromBnspByNoreg($noreg);

                if (!$asesorBnsp || !$asesorBnsp->nama) {
                    $stillMissing[] = $noreg;
                    continue;
                }

                // 2. Create user account
                $email = $this->generateEmail($noreg, $asesorBnsp->nama);

                $user = User::create([
                    'name'     => $asesorBnsp->nama,
                    'email'    => $email,
                    'phone'    => $asesorBnsp->no_hp ?? null,
                    'role_id'  => 5,
                    'noreg'    => $noreg,
                    'password' => Hash::make('123qweasd'),
                ]);

                $existingUsers[$noreg] = $user->id;
                $newlyCreatedUsers[] = [
                    'noreg' => $noreg,
                    'name'  => $asesorBnsp->nama,
                    'id'    => $user->id,
                ];
            }

            // If some noreg still cannot be resolved, return error
            if (!empty($stillMissing)) {
                $errors = [];
                foreach ($stillMissing as $noreg) {
                    $errors[] = [
                        'noreg'  => $noreg,
                        'fields' => $noregToFields[$noreg] ?? [],
                    ];
                }

                return response()->json([
                    'message' => 'Validasi gagal: MET/noreg tidak ditemukan di BNSP dan tidak terdaftar sebagai asesor.',
                    'errors'  => ['unregistered_noreg' => $errors],
                ], 422);
            }
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
            $entries, $existingUsers, $jabatanKerjaLookup,
            &$updatedConfigs, &$skippedNoMatch, &$skippedMissingConfig, &$asesorRecords
        ) {
            foreach ($entries as $entry) {
                $skemaName = trim($entry['skema']);

                $idJabatanKerja = $jabatanKerjaLookup[$skemaName] ?? null;
                if (!$idJabatanKerja) {
                    $skippedNoMatch[] = $skemaName;
                    continue;
                }

                $penyusunId  = $existingUsers[trim($entry['penyusun'])] ?? null;
                $validatorId = $existingUsers[trim($entry['validator'])] ?? null;

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

                if (!empty($entry['asesor'])) {
                    foreach ($entry['asesor'] as $asesorNoreg) {
                        $asesorUserId = $existingUsers[trim($asesorNoreg)] ?? null;
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
                'total_entries'            => count($entries),
                'updated_configs'          => $updatedConfigs,
                'asesor_mappings_upserted' => count($asesorRecords),
                'newly_created_users'      => $newlyCreatedUsers,
                'skipped_no_match'         => $skippedNoMatch,
                'skipped_missing_config'   => $skippedMissingConfig,
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

    /**
     * Generate a unique email for a new asesor user based on noreg.
     */
    private function generateEmail(string $noreg, string $nama): string
    {
        // Sanitize noreg: replace spaces/dots with nothing
        $sanitized = str_replace([' ', '.'], '', $noreg);
        $base = strtolower($sanitized) . '@asesor.gatensi.id';

        // Check uniqueness
        if (!User::where('email', $base)->exists()) {
            return $base;
        }

        // Fallback — append a suffix
        $suffix = 1;
        while (User::where('email', strtolower($sanitized) . '+' . $suffix . '@asesor.gatensi.id')->exists()) {
            $suffix++;
        }

        return strtolower($sanitized) . '+' . $suffix . '@asesor.gatensi.id';
    }
}
