<?php

namespace App\Console\Commands;

use App\Http\Controllers\KanAsesmenController;
use App\Models\KanPraktisiJabatan;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class GenerateKanMuk extends Command
{
    protected $signature = 'kan:generate-muk
                            {kpj_id? : Optional specific kan_praktisi_jabatan ID for sample output}
                            {--all : Generate for ALL kan_praktisi_jabatan records}';
    protected $description = 'Generate MUK KAN PDF documents (AK02, IA04B, IA05, IA06, MAPA01)';

    private const DOC_TYPES = [
        ['method' => 'KanIa04b', 'blade' => 'kan_ia04b', 'dir' => 'kan_ia04b', 'code' => 'KAN-IA-04B'],
        ['method' => 'KanIa05',  'blade' => 'kan_ia05',  'dir' => 'kan_ia05',  'code' => 'KAN-IA-05'],
        ['method' => 'KanIa06',  'blade' => 'kan_ia06',  'dir' => 'kan_ia06',  'code' => 'KAN-IA-06'],
        ['method' => 'KanAk02',  'blade' => 'kan_ak02',  'dir' => 'kan_ak02',  'code' => 'KAN-AK-02'],
        ['method' => 'KanMapa',  'blade' => 'kan_mapa01', 'dir' => 'kan_mapa',  'code' => 'KAN-MAPA-01'],
        ['method' => 'KanMapa2', 'blade' => 'kan_mapa02', 'dir' => 'kan_mapa2', 'code' => 'KAN-MAPA-02'],
    ];

    private KanAsesmenController $kan;

    public function __construct()
    {
        parent::__construct();
        $this->kan = app(KanAsesmenController::class);
    }

    public function handle(): int
    {
        $kpjId = $this->argument('kpj_id');
        $all = $this->option('all');

        if (!$kpjId && !$all) {
            $this->error('Specify a kpj_id for sample, or --all for all records.');
            return 1;
        }

        $query = KanPraktisiJabatan::orderBy('id');
        if ($kpjId) {
            $query->where('id', $kpjId);
        }

        $records = $query->get();
        $total = $records->count();

        if ($total === 0) {
            $this->warn('No records found.');
            return 0;
        }

        $this->info("Processing {$total} records...");
        $bar = $this->output->createProgressBar($total * count(self::DOC_TYPES));
        $bar->start();

        $stats = ['generated' => 0, 'skipped' => 0, 'errors' => []];

        foreach ($records as $record) {
            foreach (self::DOC_TYPES as $doc) {
                try {
                    $result = $this->generateDoc($record->id, $doc);
                    if ($result) {
                        $stats['generated']++;
                    } else {
                        $stats['skipped']++;
                    }
                } catch (\Throwable $e) {
                    $stats['errors'][] = "KPJ#{$record->id} {$doc['method']}: {$e->getMessage()}";
                    $this->warn("\n  ⚠ KPJ#{$record->id} {$doc['method']}: {$e->getMessage()}");
                }
                $bar->advance();
            }
        }

        $bar->finish();
        $this->newLine(2);

        $this->info('═══ HASIL GENERATE MUK KAN ═══');
        $this->info("PDF dibuat:  {$stats['generated']}");
        $this->info("PDF diskip:  {$stats['skipped']}");
        $this->info("Errors:      " . count($stats['errors']));

        if (!empty($stats['errors'])) {
            $this->warn('Detail errors:');
            foreach (array_slice($stats['errors'], 0, 10) as $err) {
                $this->line("  - {$err}");
            }
        }

        return 0;
    }

    private function generateDoc(int $kpjId, array $doc): bool
    {
        $disk = 'str_moon';
        $filename = $doc['code'] . '-PRAKTISI-' . $kpjId . '.pdf';
        $path = "dok_asesmen/{$doc['dir']}/{$filename}";

        // Build data
        $viewData = $this->buildData($kpjId, $doc['method']);

        if ($viewData === null) {
            return false;
        }

        // Generate PDF
        $pdf = Pdf::loadView("pdf.{$doc['blade']}", $viewData)->setPaper('A4', 'portrait');
        Storage::disk($disk)->put($path, $pdf->output());

        $url = 'https://moon.lspgatensi.id/files/new_gatensi/' . $path;
        $this->line("  ✓ {$doc['code']}: {$url}", null, 'vv');

        return true;
    }

    private function buildData(int $kpjId, string $method): ?array
    {
        // Build header (with PDF-mode barcodes: base64)
        $header = $this->kan->buildKanDokumenHeaderByPraktisiForPdf($kpjId);

        // Build document-specific data
        $docData = match ($method) {
            'KanIa04b' => $this->kan->buildKanIa04bDataByPraktisiForPdf($kpjId),
            'KanIa05'  => $this->kan->buildKanIa05DataByPraktisiForPdf($kpjId),
            'KanIa06'  => $this->kan->buildKanIa06DataByPraktisiForPdf($kpjId),
            'KanAk02'  => $this->kan->buildKanAk02DataByPraktisiForPdf($kpjId),
            'KanMapa'  => $this->kan->buildKanMapaDataByPraktisiForPdf($kpjId),
            'KanMapa2' => $this->kan->buildKanMapa2DataByPraktisiForPdf($kpjId),
            default    => null,
        };

        if ($docData === null || $header === null) {
            return null;
        }

        // MAPA01/MAPA02 use nested header format
        if (in_array($method, ['KanMapa', 'KanMapa2'])) {
            $viewData = array_merge($docData, ['dokumenHeader' => $header]);
            // Sanitize: ensure all MAPA template keys exist
            $viewData['skkni'] = $viewData['skkni'] ?? '';
            $viewData['kelompok_kerja'] = $viewData['kelompok_kerja'] ?? ['kelompok_kerja' => []];
            $viewData['referensi_form'] = $viewData['referensi_form'] ?? [];
            return $viewData;
        }

        // IA04B/IA05/IA06/AK02: merge header + doc data
        return array_merge($docData, $header);
    }
}
