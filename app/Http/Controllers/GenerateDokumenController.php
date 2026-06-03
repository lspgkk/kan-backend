<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateDokumenJob;
use App\Services\GenerateDokumenService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class GenerateDokumenController extends Controller
{
    protected GenerateDokumenService $generateService;

    public function __construct(GenerateDokumenService $generateService)
    {
        $this->generateService = $generateService;
    }

    /**
     * Show the web form for template download and import.
     */
    public function showForm()
    {
        return view('generate-dokumen');
    }

    /**
     * Handle import from web form — dispatch async jobs, redirect to progress page.
     */
    public function importFromWeb(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx|max:5120',
        ]);

        try {
            $batchId = $this->dispatchBatch($request->file('file'));
            return redirect()->route('generate-dokumen.progress', ['batchId' => $batchId]);
        } catch (\Throwable $e) {
            return back()->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    /**
     * API: upload Excel, dispatch jobs, return batch ID.
     */
    public function importAndGenerate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:xlsx|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $batchId = $this->dispatchBatch($request->file('file'));
            return response()->json([
                'message' => 'Batch import started',
                'batch_id' => $batchId,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Gagal memproses file: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Parse Excel and dispatch one job per row.
     */
    private function dispatchBatch($file): string
    {
        // Parse Excel
        $inputFileType = \PhpOffice\PhpSpreadsheet\IOFactory::identify($file->getPathname());
        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($inputFileType);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($file->getPathname());
        $rows = $spreadsheet->getActiveSheet()->toArray();

        if (count($rows) < 2) {
            throw new \Exception('File Excel kosong. Minimal harus ada 1 baris data.');
        }

        // Remove header
        array_shift($rows);

        $batchId = (string) Str::uuid();
        $validRows = [];

        foreach ($rows as $index => $row) {
            if (empty(array_filter($row))) continue;

            $idIzin = trim($row[0] ?? '');
            $noregAsesor1 = trim($row[1] ?? '');
            $noregAsesor2 = trim($row[2] ?? '');
            $tanggalUji = trim($row[3] ?? '');
            $jadwalId = trim($row[4] ?? '');

            if (empty($idIzin)) continue;
            if (empty($noregAsesor1) || empty($noregAsesor2)) {
                Cache::put("batch:{$batchId}:result:{$idIzin}", [
                    'id_izin' => $idIzin, 'status' => 'error',
                    'error' => 'Noreg asesor wajib diisi', 'urls' => [],
                ], 86400);
                continue;
            }
            // If jadwal_id is provided, tanggal_uji from Excel is not needed
            // (will be fetched from BNSP API instead)
            if (empty($jadwalId) && empty($tanggalUji)) {
                Cache::put("batch:{$batchId}:result:{$idIzin}", [
                    'id_izin' => $idIzin, 'status' => 'error',
                    'error' => 'Tanggal uji atau Jadwal ID wajib diisi', 'urls' => [],
                ], 86400);
                continue;
            }

            $validRows[] = [
                'id_izin' => $idIzin,
                'noreg_asesor_1' => $noregAsesor1,
                'noreg_asesor_2' => $noregAsesor2,
                'tanggal_uji' => $tanggalUji,
                'jadwal_id' => $jadwalId,
            ];
        }

        $total = count($validRows);

        // Initialize batch meta with id_izin list for result retrieval
        Cache::put("batch:{$batchId}:meta", [
            'total' => $total,
            'processed' => 0,
            'success' => 0,
            'failed' => 0,
            'status' => 'processing',
            'started_at' => now()->toIsoString(),
            'completed_at' => null,
            'id_izins' => array_column($validRows, 'id_izin'),
        ], 86400);

        // Dispatch one job per row — throttle to avoid flooding the queue
        foreach ($validRows as $row) {
            GenerateDokumenJob::dispatch(
                $row['id_izin'],
                $row['noreg_asesor_1'],
                $row['noreg_asesor_2'],
                $row['tanggal_uji'],
                $batchId,
                $row['jadwal_id'] ?: null
            )->onQueue('generate-dokumen');
        }

        return $batchId;
    }

    /**
     * Get batch progress by counting JSON result files.
     */
    public function getProgress(string $batchId)
    {
        $meta = Cache::get("batch:{$batchId}:meta");

        if (!$meta) {
            return response()->json(['message' => 'Batch not found'], 404);
        }

        // Try Cache first, then JSON files
        $results = [];
        $id_izins = $meta['id_izins'] ?? [];
        foreach ($id_izins as $id) {
            $r = Cache::get("batch:{$batchId}:result:{$id}");
            if ($r) $results[] = $r;
        }

        // Fallback to JSON files
        if (count($results) === 0) {
            $dir = storage_path("app/batches/{$batchId}");
            if (is_dir($dir)) {
                $files = glob("{$dir}/*.json");
                foreach ($files as $file) {
                    $data = json_decode(file_get_contents($file), true);
                    if ($data) $results[] = $data;
                }
            }
        }

        $processed = count($results);
        $success = count(array_filter($results, fn($r) => ($r['status'] ?? '') === 'success'));
        $failed = $processed - $success;

        $status = $meta['status'];
        $completedAt = $meta['completed_at'] ?? null;

        if ($status === 'processing' && $processed >= $meta['total']) {
            $status = 'completed';
            $completedAt = now()->toIsoString();
            $meta['status'] = 'completed';
            $meta['completed_at'] = $completedAt;
            Cache::put("batch:{$batchId}:meta", $meta, 86400);
        }

        return response()->json([
            'total'     => $meta['total'],
            'processed' => $processed,
            'success'   => $success,
            'failed'    => $failed,
            'status'    => $status,
            'started_at' => $meta['started_at'],
            'completed_at' => $completedAt,
        ]);
    }

    /**
     * Download result Excel for a completed batch.
     */
    public function downloadResult(string $batchId)
    {
        $meta = Cache::get("batch:{$batchId}:meta");

        if (!$meta || $meta['status'] !== 'completed') {
            return response()->json(['message' => 'Batch belum selesai atau tidak ditemukan'], 404);
        }

        // Collect all results — try Cache first, then JSON files
        $results = [];
        $id_izins = $meta['id_izins'] ?? [];
        foreach ($id_izins as $id) {
            $r = Cache::get("batch:{$batchId}:result:{$id}");
            if ($r) $results[] = $r;
        }

        // Fallback to JSON files
        if (empty($results)) {
            $dir = storage_path("app/batches/{$batchId}");
            if (is_dir($dir)) {
                $files = glob("{$dir}/*.json");
                foreach ($files as $file) {
                    $data = json_decode(file_get_contents($file), true);
                    if ($data) $results[] = $data;
                }
            }
        }

        return $this->generateResultExcel($results);
    }

    /**
     * Show progress page (web view).
     */
    public function showProgress(string $batchId)
    {
        $meta = Cache::get("batch:{$batchId}:meta");

        if (!$meta) {
            return redirect()->route('generate-dokumen.form')
                ->with('error', 'Batch tidak ditemukan');
        }

        return view('generate-dokumen-progress', [
            'batchId' => $batchId,
            'meta' => $meta,
        ]);
    }

    /**
     * Download Excel template for bulk import.
     */
    public function downloadTemplate()
    {
        $spreadsheet = new Spreadsheet();

        // ─── Sheet 1: Data Import ───
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Data Import');

        // Headers
        $headers = ['ID Izin', 'Noreg Asesor 1', 'Noreg Asesor 2', 'Tanggal Uji', 'Jadwal ID'];
        $colLetters = ['A', 'B', 'C', 'D', 'E'];

        foreach ($headers as $i => $label) {
            $col = $colLetters[$i];
            $sheet->setCellValue("{$col}1", $label);
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Header styling
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D60000']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ];
        $sheet->getStyle('A1:E1')->applyFromArray($headerStyle);

        // Example row
        $sheet->setCellValue('A2', 'I-202401-000123');
        $sheet->setCellValue('B2', '12345678');
        $sheet->setCellValue('C2', '87654321');
        $sheet->setCellValue('D2', Carbon::now()->format('Y-m-d'));
        $sheet->setCellValue('E2', '353435');

        $exampleStyle = [
            'font' => ['color' => ['rgb' => '999999'], 'italic' => true],
        ];
        $sheet->getStyle('A2:E2')->applyFromArray($exampleStyle);

        // ─── Sheet 2: Petunjuk ───
        $instructions = $spreadsheet->createSheet();
        $instructions->setTitle('Petunjuk Pengisian');
        $instructions->setCellValue('A1', 'PETUNJUK PENGISIAN DATA IMPORT');
        $instructions->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $texts = [
            '',
            'Kolom A - ID Izin:',
            '  Masukkan ID Izin permohonan (contoh: I-202401-000123). Wajib diisi.',
            '',
            'Kolom B - Noreg Asesor 1:',
            '  Masukkan nomor registrasi asesor 1. Wajib diisi.',
            '',
            'Kolom C - Noreg Asesor 2:',
            '  Masukkan nomor registrasi asesor 2. Wajib diisi.',
            '',
            'Kolom D - Tanggal Uji:',
            '  Masukkan tanggal uji dengan format YYYY-MM-DD (contoh: 2024-06-15).',
            '  Opsional jika Kolom E (Jadwal ID) diisi (tanggal akan diambil dari API BNSP).',
            '',
            'Kolom E - Jadwal ID:',
            '  Masukkan ID jadwal dari BNSP (contoh: 353435).',
            '  Jika diisi, tanggal uji (Kolom D) akan diabaikan dan menggunakan tanggal_mulai dari API BNSP.',
            '  Opsional, kosongkan jika menggunakan Tanggal Uji manual.',
            '',
            '- Baris pertama (header) tidak boleh dihapus.',
            '- Pastikan ID Izin terdaftar di SIKI atau sudah tersimpan di database.',
            '- Semua asesi akan dinyatakan KOMPETEN secara otomatis.',
            '- Metode asesmen yang digunakan: OBSERVASI.',
            '- Proses generate dokumen cukup lama karena akan sync data dan generate PDF.',
        ];

        foreach ($texts as $i => $text) {
            $instructions->setCellValue('A' . ($i + 2), $text);
        }
        $instructions->getColumnDimension('A')->setWidth(80);

        // ─── Output ───
        $writer = new Xlsx($spreadsheet);
        $filename = 'template-import-praasesmen.xlsx';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function generateResultExcel(array $results)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Hasil Import');

        // Define all possible URL columns
        $urlLabels = [
            'url_apl_01' => 'URL APL-01',
            'url_apl_02' => 'URL APL-02',
            'url_mapa01' => 'URL MAPA-01',
            'url_mapa02' => 'URL MAPA-02',
            'url_ak07' => 'URL AK-07',
            'url_ak04' => 'URL AK-04',
            'url_ak01' => 'URL AK-01',
            'url_ia_01' => 'URL IA-01 / IA-04.A',
            'url_ia_02' => 'URL IA-02 / IA-04.B',
            'url_ia_03' => 'URL IA-03 / IA-05',
            'url_ia_05' => 'URL IA-05',
            'url_ia_04a' => 'URL IA-04.A',
            'url_ia_04b' => 'URL IA-04.B',
            'url_ak02' => 'URL AK-02',
            'url_ak03' => 'URL AK-03',
            'url_ak05' => 'URL AK-05',
            'url_ak06' => 'URL AK-06',
        ];

        // Headers
        $headers = ['ID Izin', 'Status Import', 'Pesan Error'];
        $colKeys = ['id_izin', 'status', 'error'];
        foreach ($urlLabels as $key => $label) {
            $headers[] = $label;
            $colKeys[] = $key;
        }

        $colLetters = range('A', 'Z');
        foreach ($headers as $i => $label) {
            $col = $colLetters[$i] ?? 'A' . $i;
            $sheet->setCellValue("{$col}1", $label);
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Header styling
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D60000']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
        ];
        $endCol = $colLetters[count($headers) - 1] ?? 'Z';
        $sheet->getStyle("A1:{$endCol}1")->applyFromArray($headerStyle);

        // Data rows
        foreach ($results as $rowIdx => $result) {
            $dataRow = $rowIdx + 2;

            $idIzin = $result['id_izin'] ?? '';
            $status = $result['status'] ?? 'error';
            $error = $result['error'] ?? '';

            $sheet->setCellValue("A{$dataRow}", $idIzin);
            $sheet->setCellValue("B{$dataRow}", $status);
            $sheet->setCellValue("C{$dataRow}", $error);

            // URL columns
            $urls = $result['urls'] ?? [];
            $colIdx = 3; // D column
            foreach ($urlLabels as $key => $label) {
                $col = $colLetters[$colIdx] ?? 'A' . $colIdx;
                $urlValue = $urls[$key] ?? '';
                if ($urlValue) {
                    $sheet->setCellValue("{$col}{$dataRow}", $urlValue);
                    $sheet->getCell("{$col}{$dataRow}")->getHyperlink()->setUrl($urlValue);
                }
                $colIdx++;
            }

            // Color status cell
            $statusColor = match ($status) {
                'success' => 'C6EFCE',
                'sync_failed', 'no_klasifikasi', 'asesor_not_found' => 'FFC7CE',
                default => 'FFEB9C',
            };
            $sheet->getStyle("B{$dataRow}")->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB($statusColor);
        }

        // ─── Output ───
        $writer = new Xlsx($spreadsheet);
        $filename = 'hasil-generate-dokumen-' . Carbon::now()->format('Ymd_His') . '.xlsx';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
