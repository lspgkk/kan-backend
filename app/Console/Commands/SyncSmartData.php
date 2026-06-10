<?php

namespace App\Console\Commands;

use App\Services\SmartDataSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncSmartData extends Command
{
    protected $signature = 'smart:sync
        {--table= : Sync only a specific table (e.g. JabatanDokumen, Soal, Referensi)}
        {--dry-run : Show what would be inserted without actually inserting}
    ';

    protected $description = 'Sync master/reference data from the smart database (webujian) into the local database';

    protected SmartDataSyncService $syncService;

    public function __construct(SmartDataSyncService $syncService)
    {
        parent::__construct();
        $this->syncService = $syncService;
    }

    public function handle(): int
    {
        // Wire console output into the service so logs appear on terminal
        $this->syncService->output = $this->output;

        // Check smart DB connection first
        $this->line('Checking connection to smart database ...');
        if (!$this->syncService->isConnectionAlive()) {
            $this->error('Cannot connect to the smart database. Check DB_SMART_* env vars.');
            return Command::FAILURE;
        }
        $this->info('Connection OK');

        // Switch to webujian database where the actual tables live
        DB::connection('smartgatensi')->statement('USE webujian');

        if ($this->option('dry-run')) {
            return $this->handleDryRun();
        }

        return $this->handleSync();
    }

    private function handleDryRun(): int
    {
        $this->warn('DRY RUN — no data will be inserted');

        $tableOption = $this->option('table');
        $definitions = $this->getDefinitions();

        if ($tableOption) {
            $def = $this->findDefinition($tableOption);
            if (!$def) {
                $this->error("Unknown table: {$tableOption}. Available: " . implode(', ', array_keys($definitions)));
                return Command::FAILURE;
            }
            $definitions = [$tableOption => $def];
        }

        $rows = [];
        foreach ($definitions as $name => $def) {
            $localTable = (new $def['model'])->getTable();
            $smartTable = $def['smart'];
            $keyCol     = $def['key'];

            $localIds  = DB::table($localTable)->pluck($keyCol)->toArray();
            $remoteIds = DB::connection('smartgatensi')->table($smartTable)->pluck($keyCol)->toArray();

            // Normalise for comparison
            $localSet  = array_flip(array_map('strval', $localIds));
            $toSync = 0;
            foreach ($remoteIds as $rid) {
                if (!isset($localSet[(string) $rid])) {
                    $toSync++;
                }
            }

            $rows[] = [$name, count($localIds), count($remoteIds), $toSync];
        }

        $this->table(['Table', 'Local', 'Smart', 'To Sync'], $rows);
        return Command::SUCCESS;
    }

    private function handleSync(): int
    {
        $tableOption = $this->option('table');

        if ($tableOption) {
            $def = $this->findDefinition($tableOption);
            if (!$def) {
                $this->error("Unknown table: {$tableOption}. Available: " . implode(', ', array_keys($this->getDefinitions())));
                return Command::FAILURE;
            }

            $this->line("Syncing {$tableOption} ...");
            $result = $this->syncService->syncTable($def['model']);
            $this->line("  Inserted: {$result['inserted']}, Skipped: {$result['skipped']}");
        } else {
            $this->line('Syncing all tables ...');
            $result = $this->syncService->syncAll();

            $this->newLine();
            $this->info('Sync complete');
            $this->table(
                ['Table', 'Inserted', 'Skipped'],
                collect($result['results'])->map(fn ($r, $t) => [$t, $r['inserted'], $r['skipped']])->values()->all()
            );
            $this->line("Total inserted: {$result['total_inserted']}, Total skipped: {$result['total_skipped']}");
        }

        return Command::SUCCESS;
    }

    private function getDefinitions(): array
    {
        return [
            'JabatanConfigA'   => ['model' => \App\Models\JabatanConfigA::class,  'smart' => 't_jabatan_confa',   'key' => 'id_jabatan'],
            'JabatanDokumen'   => ['model' => \App\Models\JabatanDokumen::class,  'smart' => 't_jabatan_dok',    'key' => 'id'],
            'UnitKompetensi'   => ['model' => \App\Models\UnitKompetensi::class,  'smart' => 'm_unitkompetensi', 'key' => 'id'],
            'SubunitKompetensi'=> ['model' => \App\Models\SubunitKompetensi::class,'smart' => 'm_subunitkompetensi','key' => 'id'],
            'Soal'             => ['model' => \App\Models\Soal::class,            'smart' => 't_soal',           'key' => 'id'],
            'Referensi'        => ['model' => \App\Models\Referensi::class,       'smart' => 'm_referensi',      'key' => 'id'],
        ];
    }

    private function findDefinition(string $name): ?array
    {
        return $this->getDefinitions()[$name] ?? $this->getDefinitions()[ucfirst($name)] ?? null;
    }
}
