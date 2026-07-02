<?php

namespace App\Http\Controllers;

use App\Models\AsesorBnsp;
use App\Models\Barcode;
use App\Models\JabatanConfigA;
use App\Models\JabatanKerja;
use App\Models\KanPraktisiJabatan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Ramsey\Uuid\Uuid;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class KanConfigController extends Controller
{
    /**
     * Bulk-submit KAN configuration: no_skema, penyusun, validator,
     * praktisi (asesor), and generate QR codes.
     *
     * - Penyusun (role_id=14) & Validator (role_id=15): lookup by noreg,
     *   fallback to BNSP fetch if not registered.
     * - Praktisi / Asesor (role_id=16): lookup by name, create if not exists.
     */
    public function submit(Request $request)
    {
        // Accept both "entries" and "data" as the input key
        $inputKey = $request->has('entries') ? 'entries' : 'data';

        $validated = $request->validate([
            $inputKey                => 'required|array|min:1',
            $inputKey . '.*.no_skema'     => 'required|string',
            $inputKey . '.*.skema'        => 'required|string',
            $inputKey . '.*.penyusun'     => 'required|string',
            $inputKey . '.*.validator'    => 'required|string',
            $inputKey . '.*.tgl_penyusun'  => 'nullable|string',
            $inputKey . '.*.tgl_validator' => 'nullable|string',
            $inputKey . '.*.asesor'       => 'nullable|array',
        ]);

        $entries = $validated[$inputKey];

        // ── Phase 1: Resolve all users ──
        $allSkemaNames = [];
        $noregPenyusun  = [];
        $noregValidator = [];
        $praktisiNames  = [];

        foreach ($entries as $idx => $entry) {
            $allSkemaNames[] = trim($entry['skema']);
            $noregPenyusun[]  = trim($entry['penyusun']);
            $noregValidator[] = trim($entry['validator']);

            if (!empty($entry['asesor'])) {
                foreach ($entry['asesor'] as $nama) {
                    $name = trim($nama);
                    if (!empty($name)) {
                        $praktisiNames[] = $name;
                    }
                }
            }
        }

        // ── Phase 1a: Penyusun (role_id=14) by noreg ──
        $penyusunUsers = $this->resolveUsersByNoreg(
            array_unique($noregPenyusun),
            14,
            $noregPenyusun
        );

        // ── Phase 1b: Validator (role_id=15) by noreg ──
        $validatorUsers = $this->resolveUsersByNoreg(
            array_unique($noregValidator),
            15,
            $noregValidator
        );

        // ── Phase 1c: Praktisi (role_id=16) by name ──
        $praktisiUsers = $this->resolvePraktisiUsers(array_unique($praktisiNames));

        // Collect newly created users for response
        $newlyCreatedUsers = array_merge(
            $penyusunUsers['created'] ?? [],
            $validatorUsers['created'] ?? [],
            $praktisiUsers['created'] ?? []
        );

        // ── Phase 2: Build skema name → JabatanKerja lookup ──
        // Force case-insensitive matching with LOWER() on both sides
        $allSkemaNames = array_unique($allSkemaNames);
        $lowerNames = array_map('strtolower', $allSkemaNames);

        $jabatanKerjaRows = JabatanKerja::select('id_jabatan_kerja', 'jabatan_kerja')
            ->whereRaw('LOWER(jabatan_kerja) IN (' . implode(',', array_fill(0, count($lowerNames), '?')) . ')', $lowerNames)
            ->orderBy('id_jabatan_kerja', 'desc')
            ->get();

        $jabatanKerjaLookup = [];
        foreach ($jabatanKerjaRows as $row) {
            $key = strtolower($row->jabatan_kerja);
            if (!isset($jabatanKerjaLookup[$key])) {
                $jabatanKerjaLookup[$key] = $row->id_jabatan_kerja;
            }
        }

        // ── Phase 3: Process in transaction ──
        $updatedConfigs = 0;
        $skippedNoMatch = [];
        $skippedMissingConfig = [];
        $praktisiRecords = [];
        $qrGenerated = 0;

        DB::transaction(function () use (
            $entries, $penyusunUsers, $validatorUsers, $praktisiUsers,
            $jabatanKerjaLookup, &$updatedConfigs, &$skippedNoMatch,
            &$skippedMissingConfig, &$praktisiRecords, &$qrGenerated
        ) {
            foreach ($entries as $entry) {
                $skemaName = trim($entry['skema']);

                $idJabatanKerja = $jabatanKerjaLookup[strtolower($skemaName)] ?? null;
                if (!$idJabatanKerja) {
                    $skippedNoMatch[] = $skemaName;
                    continue;
                }

                $penyusunNoreg  = trim($entry['penyusun']);
                $validatorNoreg = trim($entry['validator']);
                $penyusunId     = $penyusunUsers['map'][$penyusunNoreg] ?? null;
                $validatorId    = $validatorUsers['map'][$validatorNoreg] ?? null;

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

                // ── Generate QR for penyusun & validator ──
                if ($penyusunId || $validatorId) {
                    $this->generateQrForJabatanConfig(
                        $idJabatanKerja,
                        $penyusunId,
                        $validatorId,
                        $entry['tgl_penyusun'] ?? null
                    );
                    $qrGenerated++;
                }

                // ── Praktisi / Asesor mappings ──
                if (!empty($entry['asesor'])) {
                    foreach ($entry['asesor'] as $nama) {
                        $name = trim($nama);
                        if (empty($name)) continue;

                        $praktisiId = $praktisiUsers['map'][$name] ?? null;
                        if ($praktisiId) {
                            $praktisiRecords[] = [
                                'id_jabatan_kerja' => $idJabatanKerja,
                                'id_user'          => $praktisiId,
                                'updated_at'       => now(),
                                'created_at'       => now(),
                            ];
                        }
                    }
                }
            }

            if (!empty($praktisiRecords)) {
                KanPraktisiJabatan::upsert(
                    $praktisiRecords,
                    ['id_jabatan_kerja', 'id_user'],
                    ['updated_at']
                );
            }
        });

        // Generate Excel export
        $excelUrl = $this->exportExcel($newlyCreatedUsers);

        return response()->json([
            'message' => 'Konfigurasi KAN berhasil disimpan.',
            'data'    => [
                'total_entries'              => count($entries),
                'updated_configs'            => $updatedConfigs,
                'qr_generated'               => $qrGenerated,
                'praktisi_mappings_upserted' => count($praktisiRecords),
                'newly_created_users'        => $newlyCreatedUsers,
                'skipped_no_match'           => $skippedNoMatch,
                'skipped_missing_config'     => $skippedMissingConfig,
                'excel_url'                  => $excelUrl,
            ],
        ]);
    }

    /**
     * Download Excel list of KAN users (role 14, 15, 16) separated by sheet.
     */
    public function downloadUsers()
    {
        $roles = [
            14 => 'Penyusun',
            15 => 'Validator',
            16 => 'Praktisi',
        ];

        $spreadsheet = new Spreadsheet();
        $sheetIndex = 0;

        foreach ($roles as $roleId => $roleName) {
            if ($sheetIndex > 0) {
                $spreadsheet->createSheet();
            }
            $spreadsheet->setActiveSheetIndex($sheetIndex);

            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle($roleName);

            $sheet->setCellValue('A1', 'Nama');
            $sheet->setCellValue('B1', 'Email');
            $sheet->getStyle('A1:B1')->getFont()->setBold(true);

            $users = User::where('role_id', $roleId)
                ->where('is_deleted', false)
                ->select('name', 'email')
                ->orderBy('name')
                ->get();

            $row = 2;
            foreach ($users as $user) {
                $sheet->setCellValue("A{$row}", $user->name);
                $sheet->setCellValue("B{$row}", $user->email);
                $row++;
            }

            $sheet->getColumnDimension('A')->setAutoSize(true);
            $sheet->getColumnDimension('B')->setAutoSize(true);

            $sheetIndex++;
        }

        $filename = 'kan_users_' . date('Ymd_His') . '.xlsx';
        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $content = ob_get_clean();

        return response($content, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    // ──────────────────────────────────────────────
    //  USER RESOLUTION HELPERS
    // ──────────────────────────────────────────────

    /**
     * Resolve users by noreg for a specific role_id.
     * Falls back to AsesorBnsp → BNSP API → create user if not found.
     */
    private function resolveUsersByNoreg(array $noregs, int $roleId, array $allNoregs): array
    {
        $map     = []; // noreg => user_id
        $created = [];

        // Find existing users with this role
        $existing = User::whereIn('noreg', $noregs)
            ->where('role_id', $roleId)
            ->pluck('id', 'noreg')
            ->toArray();

        $map = $existing;

        // Find any user with same noreg (different role) as fallback
        $anyRole = User::whereIn('noreg', $noregs)
            ->whereNotIn('noreg', array_keys($existing))
            ->pluck('id', 'noreg')
            ->toArray();

        foreach ($anyRole as $noreg => $userId) {
            $map[$noreg] = $userId;
        }

        // Resolve missing noreg
        $missing = array_diff($noregs, array_keys($map));

        foreach ($missing as $noreg) {
            // Try local AsesorBnsp first
            $asesorBnsp = AsesorBnsp::where('noreg', $noreg)->first();

            // If not found locally, fetch from BNSP API
            if (!$asesorBnsp || !$asesorBnsp->nama) {
                $asesorBnsp = AsesorBnsp::fetchFromBnspByNoreg($noreg);
            }

            if (!$asesorBnsp || !$asesorBnsp->nama) {
                continue; // truly unresolvable — skip
            }

            $email = $this->generateEmailFromName($asesorBnsp->nama);

            $user = User::create([
                'name'     => $asesorBnsp->nama,
                'email'    => $email,
                'phone'    => $asesorBnsp->no_hp ?? '0',
                'role_id'  => $roleId,
                'noreg'    => $noreg,
                'password' => Hash::make('123qweasd'),
            ]);

            $map[$noreg] = $user->id;
            $created[]   = [
                'noreg'   => $noreg,
                'name'    => $asesorBnsp->nama,
                'role_id' => $roleId,
                'id'      => $user->id,
            ];
        }

        return ['map' => $map, 'created' => $created];
    }

    /**
     * Resolve praktisi users (role_id=16) by name.
     * Create if not found.
     */
    private function resolvePraktisiUsers(array $names): array
    {
        $map     = []; // name => user_id
        $created = [];

        $existing = User::whereIn('name', $names)
            ->where('role_id', 16)
            ->pluck('id', 'name')
            ->toArray();

        $map = $existing;

        $missing = array_diff($names, array_keys($map));

        foreach ($missing as $name) {
            if (empty($name)) continue;

            $email = $this->generateEmailFromName($name);

            $user = User::create([
                'name'     => $name,
                'email'    => $email,
                'phone'    => '0',
                'role_id'  => 16,
                'password' => Hash::make('123qweasd'),
            ]);

            $map[$name] = $user->id;
            $created[]  = [
                'name'    => $name,
                'role_id' => 16,
                'id'      => $user->id,
            ];
        }

        return ['map' => $map, 'created' => $created];
    }

    // ──────────────────────────────────────────────
    //  QR GENERATION
    // ──────────────────────────────────────────────

    /**
     * Generate QR barcodes for penyusun & validator
     * and update JabatanConfigA with image URLs.
     */
    private function generateQrForJabatanConfig(
        string $idJabatan,
        ?int $penyusunId,
        ?int $validatorId,
        ?string $tglPenyusun
    ): void {
        $updateData = [];

        // Generate QR for penyusun
        if ($penyusunId) {
            $uuidPenyusun = (string) Uuid::uuid4();
            $urlTtd = "https://barcode.lspgatensi.id/penyusun-validator/{$uuidPenyusun}";
            $filename = 'qr_penyusun_' . Str::uuid() . '.png';

            $qrImage = QrCode::format('png')->size(300)->margin(1)->generate($urlTtd);
            $path = "barcodes/{$filename}";
            Storage::disk('str_moon')->put($path, $qrImage);

            $urlImage = "https://moon.lspgatensi.id/files/new_gatensi/{$path}";

            $updateData['kan_penyusun_img'] = $urlImage;
            $updateData['kan_penyusun_url'] = $urlTtd;

            // Save to Barcode table
            Barcode::updateOrCreate(
                [
                    'id_user'       => $penyusunId,
                    'id_izin'       => 'PENYUSUN_KAN_' . $idJabatan,
                    'jenis_dokumen' => 'PENYUSUN_KAN',
                ],
                [
                    'url_image'  => $urlImage,
                    'url_ttd'    => $urlTtd,
                    'id_role'    => 14,
                    'created_at' => $this->parseDate($tglPenyusun) ?? now(),
                ]
            );
        }

        // Generate QR for validator
        if ($validatorId) {
            $uuidValidator = (string) Uuid::uuid4();
            $urlTtd = "https://barcode.lspgatensi.id/penyusun-validator/{$uuidValidator}";
            $filename = 'qr_validator_' . Str::uuid() . '.png';

            $qrImage = QrCode::format('png')->size(300)->margin(1)->generate($urlTtd);
            $path = "barcodes/{$filename}";
            Storage::disk('str_moon')->put($path, $qrImage);

            $urlImage = "https://moon.lspgatensi.id/files/new_gatensi/{$path}";

            $updateData['kan_validator_img'] = $urlImage;
            $updateData['kan_validator_url'] = $urlTtd;

            // Save to Barcode table
            Barcode::updateOrCreate(
                [
                    'id_user'       => $validatorId,
                    'id_izin'       => 'VALIDATOR_KAN_' . $idJabatan,
                    'jenis_dokumen' => 'VALIDATOR_KAN',
                ],
                [
                    'url_image' => $urlImage,
                    'url_ttd'   => $urlTtd,
                    'id_role'   => 15,
                ]
            );
        }

        if (!empty($updateData)) {
            JabatanConfigA::where('id_jabatan', $idJabatan)->update($updateData);
        }
    }

    // ──────────────────────────────────────────────
    //  UTILITY HELPERS
    // ──────────────────────────────────────────────

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
     * Export newly created users to Excel with each role in a separate sheet.
     * Returns the public URL of the generated file.
     */
    private function exportExcel(array $newlyCreatedUsers): ?string
    {
        if (empty($newlyCreatedUsers)) {
            return null;
        }

        // Group by role_id
        $byRole = [];
        foreach ($newlyCreatedUsers as $user) {
            $roleId = $user['role_id'];
            if (!isset($byRole[$roleId])) {
                $byRole[$roleId] = [];
            }
            $byRole[$roleId][] = $user;
        }

        $roleNames = [
            14 => 'Penyusun',
            15 => 'Validator',
            16 => 'Praktisi',
        ];

        $spreadsheet = new Spreadsheet();

        $sheetIndex = 0;
        foreach ($byRole as $roleId => $users) {
            if ($sheetIndex > 0) {
                $spreadsheet->createSheet();
            }
            $spreadsheet->setActiveSheetIndex($sheetIndex);

            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle($roleNames[$roleId] ?? "Role-{$roleId}");

            // Headers
            $sheet->setCellValue('A1', 'Nama');
            $sheet->setCellValue('B1', 'Email');
            if (in_array($roleId, [14, 15])) {
                $sheet->setCellValue('C1', 'Noreg');
            }

            // Data rows
            $row = 2;
            foreach ($users as $user) {
                $sheet->setCellValue("A{$row}", $user['name'] ?? '');
                $sheet->setCellValue("B{$row}", $user['email'] ?? '');
                if (in_array($roleId, [14, 15])) {
                    $sheet->setCellValue("C{$row}", $user['noreg'] ?? '');
                }
                $row++;
            }

            // Auto-size columns
            foreach (range('A', 'C') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            $sheetIndex++;
        }

        // Save to storage
        $filename = 'kan_users_' . date('Ymd_His') . '.xlsx';
        $path = "exports/{$filename}";

        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $content = ob_get_clean();

        Storage::disk('str_moon')->put($path, $content);

        return "https://moon.lspgatensi.id/files/new_gatensi/{$path}";
    }

    /**
     * Generate a unique email from a person's name.
     * Format: {sanitized_name}@lspgatensi.id
     */
    private function generateEmailFromName(string $name): string
    {
        // Sanitize: lowercase, remove spaces and special chars
        $sanitized = preg_replace('/[^a-z0-9]/', '', strtolower($name));
        $base = $sanitized . '@lspgatensi.id';

        if (!User::where('email', $base)->exists()) {
            return $base;
        }

        // Fallback — append a number
        $suffix = 1;
        while (User::where('email', $sanitized . '+' . $suffix . '@lspgatensi.id')->exists()) {
            $suffix++;
        }

        return $sanitized . '+' . $suffix . '@lspgatensi.id';
    }
}
