<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class UpdateInvoiceTemplates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invoice:update-templates';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update invoice Excel templates to add ID Izin column';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $templates = [
            'invoice_reguler' => resource_path('views/template-invoice/invoice_reguler.xlsx'),
            'invoice_sistem' => resource_path('views/template-invoice/invoice_sistem.xlsx')
        ];

        foreach ($templates as $name => $path) {
            $this->info("Processing template: {$name}");

            if (!file_exists($path)) {
                $this->error("Template not found: {$path}");
                continue;
            }

            try {
                // Backup original file
                $backupPath = $path . '.backup_' . date('YmdHis');
                copy($path, $backupPath);
                $this->info("Backup created: {$backupPath}");

                // Load spreadsheet
                $spreadsheet = IOFactory::load($path);
                $worksheet = $spreadsheet->getActiveSheet();

                // Insert new column D (shift columns D-H to E-I)
                $worksheet->insertNewColumnBefore('D', 1);

                // Update header row (row 21)
                $this->updateHeaderRow($worksheet);

                // Update format row (row 22)
                $this->updateFormatRow($worksheet);

                // Shift all data columns from row 23 onwards
                $this->shiftDataColumns($worksheet);

                // Update formulas and references
                $this->updateFormulas($worksheet);

                // Save updated template
                $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
                $writer->save($path);

                $this->info("✅ Template updated successfully: {$name}");

            } catch (\Exception $e) {
                $this->error("❌ Failed to update {$name}: " . $e->getMessage());

                // Restore from backup if update failed
                if (file_exists($backupPath)) {
                    copy($backupPath, $path);
                    $this->info("Restored from backup");
                }
            }
        }

        $this->newLine();
        $this->info('🎉 Invoice template update completed!');
        $this->warn('Please test the templates by creating a new invoice.');

        return Command::SUCCESS;
    }

    /**
     * Update header row to add ID IZIN column
     */
    private function updateHeaderRow($worksheet)
    {
        // Create header style
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '003D73']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
        ];

        // Update headers
        $worksheet->setCellValue('B21', 'NO');
        $worksheet->setCellValue('C21', 'NAMA');
        $worksheet->setCellValue('D21', 'ID IZIN'); // New column
        $worksheet->setCellValue('E21', 'JABATAN KERJA');
        $worksheet->setCellValue('F21', 'JENJANG');
        $worksheet->setCellValue('G21', 'HARGA');

        // Apply header style
        $worksheet->getStyle('B21:G21')->applyFromArray($headerStyle);
    }

    /**
     * Update format row (row 22) for proper formatting
     */
    private function updateFormatRow($worksheet)
    {
        // Copy formatting from adjacent columns
        $formatStyle = $worksheet->getStyle('C22');
        $worksheet->duplicateStyle($formatStyle, 'D22');

        // Set column width for ID IZIN
        $worksheet->getColumnDimension('D')->setWidth(15);
    }

    /**
     * Shift data columns and update cell references
     */
    private function shiftDataColumns($worksheet)
    {
        // This is handled automatically by insertNewColumnBefore()
        // but we need to ensure all formatting is preserved
        $highestRow = $worksheet->getHighestRow();

        // Copy formatting for the new column D from column C
        for ($row = 23; $row <= $highestRow; $row++) {
            if ($worksheet->getCell('C' . $row)->getValue() !== null) {
                $formatStyle = $worksheet->getStyle('C' . $row);
                $worksheet->duplicateStyle($formatStyle, 'D' . $row);
            }
        }
    }

    /**
     * Update formulas and cell references
     */
    private function updateFormulas($worksheet)
    {
        $highestRow = $worksheet->getHighestRow();

        // Find and update SUM formulas
        for ($row = 23; $row <= $highestRow; $row++) {
            $cell = $worksheet->getCell('H' . $row); // Old G column is now H

            if ($cell->getValue() && strpos($cell->getValue(), 'SUM') !== false) {
                $formula = $cell->getValue();
                // Update formula from SUM(Gxx:Gyy) to SUM(Hxx:Hyy)
                $formula = str_replace('G', 'H', $formula);
                $cell->setValue($formula);
            }
        }
    }
}