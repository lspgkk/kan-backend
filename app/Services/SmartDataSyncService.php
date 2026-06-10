<?php

namespace App\Services;

use App\Models\JabatanConfigA;
use App\Models\JabatanDokumen;
use App\Models\Referensi;
use App\Models\Soal;
use App\Models\SubunitKompetensi;
use App\Models\UnitKompetensi;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Sync master/reference data from the external "smart" database (webujian)
 * into the local application database.
 *
 * NOTE: The actual smart tables are in the `webujian` database on the same
 * host, NOT in `web2_db` as configured in DB_SMART_DATABASE. Override
 * the database name via the constructor if needed.
 *
 * For each table, rows whose identifier already exists locally are skipped;
 * only genuinely new rows are inserted.
 */
class SmartDataSyncService
{
    /**
     * Connection name configured in config/database.php for the smart DB.
     */
    protected string $smartConnection = 'smartgatensi';

    /**
     * Override database name. Set to null to use whatever is in .env.
     * Currently overridden to 'webujian' because the tables live there.
     */
    protected ?string $smartDatabase = 'webujian';

    /**
     * Define each sync target:
     *
     *  model   : fully-qualified model class name
     *  smart   : table name on the smart DB
     *  key     : column used to check existence (default 'id')
     *  columns : associative array mapping [smart_column => local_column]
     *            — columns omitted from the map are excluded from the insert
     *  filter  : optional callable to pre-process the smart row (array → array)
     */
    protected function definitions(): array
    {
        return [
            [
                'model'   => JabatanConfigA::class,
                'smart'   => 't_jabatan_confa',
                'key'     => 'id_jabatan',
                'columns' => [
                    'id_jabatan'      => 'id_jabatan',
                    'no_skema'        => 'no_skema',
                    'penyusun_id'     => 'penyusun_id',
                    'penyusun_tgl'    => 'penyusun_tgl',
                    'penyusun_img'    => 'penyusun_img',
                    'validator_id'    => 'validator_id',
                    'validator_tgl'   => 'validator_tgl',
                    'validator_img'   => 'validator_img',
                ],
            ],
            [
                'model'   => JabatanDokumen::class,
                'smart'   => 't_jabatan_dok',
                'key'     => 'id',
                'columns' => [
                    'id'            => 'id',
                    'kode'          => 'kode',
                    'nama_dokumen'  => 'nama_dokumen',
                    'id_jabatan'    => 'id_jabatan',
                    'tipe'          => 'tipe',
                    'inputas'       => 'inputas',
                    'inputfor'      => 'inputfor',
                    'dokas'         => 'dokas',
                    'file'          => 'file',
                    'id_tmp'        => 'id_tmp',
                    'sts_old'       => 'sts_old',
                    'no_skema_old'  => 'no_skema_old',
                    'is_replyfile'  => 'is_replyfile',
                    'urut'          => 'urut',
                    'isaktif'       => 'is_active',
                ],
            ],
            [
                'model'   => UnitKompetensi::class,
                'smart'   => 'm_unitkompetensi',
                'key'     => 'id',
                'columns' => [
                    'id'            => 'id',
                    'kode'          => 'kode',
                    'nama'          => 'nama',
                    'id_jabatan'    => 'id_jabatan',
                    'create_by'     => 'created_by',
                    'create_date'   => 'created_at',
                    'edit_by'       => 'updated_by',
                    'edit_date'     => 'updated_at',
                ],
            ],
            [
                'model'   => SubunitKompetensi::class,
                'smart'   => 'm_subunitkompetensi',
                'key'     => 'id',
                'columns' => [
                    'id'                 => 'id',
                    'kode'               => 'kode',
                    'nama'               => 'nama',
                    'id_unitkompetensi'  => 'id_unitkompetensi',
                    'create_by'          => 'created_by',
                    'create_date'        => 'created_at',
                    'edit_by'            => 'updated_by',
                    'edit_date'          => 'updated_at',
                ],
            ],
            [
                'model'   => Soal::class,
                'smart'   => 't_soal',
                'key'     => 'id',
                'columns' => [
                    'id'                    => 'id',
                    'id_dokumen'            => 'id_dokumen',
                    'urut'                  => 'urut',
                    'no'                    => 'no',
                    'jenis'                 => 'jenis',
                    'tipe'                  => 'tipe',
                    'isi_nonsoal'           => 'isi_nonsoal',
                    'id_unitkompetensi'     => 'id_unitkompetensi',
                    'id_subunitkompetensi'  => 'id_subunitkompetensi',
                    'id_kuk'                => 'id_kuk',
                    'soal'                  => 'soal',
                    'soal1'                 => 'soal1',
                    'soal2'                 => 'soal2',
                    'jawab_a'               => 'jawab_a',
                    'jawab_b'               => 'jawab_b',
                    'jawab_c'               => 'jawab_c',
                    'jawab_d'               => 'jawab_d',
                    'file_a'                => 'file_a',
                    'file_b'                => 'file_b',
                    'file_c'                => 'file_c',
                    'file_d'                => 'file_d',
                    'jawaban'               => 'jawaban',
                    'is_komentar'           => 'is_komentar',
                    'id_kelompok'           => 'id_kelompok',
                    'namasubunit'           => 'namasubunit',
                ],
            ],
            [
                'model'   => Referensi::class,
                'smart'   => 'm_referensi',
                'key'     => 'id',
                'columns' => [
                    'id'           => 'id',
                    'kategori'     => 'kategori',
                    'nama'         => 'nama',
                    'id_kelompok'  => 'id_kelompok',
                    'id_kat'       => 'id_kategori',
                    'id_subkat'    => 'id_subkategori',
                    'no'           => 'no',
                    'no_text'      => 'no_text',
                    'keterangan1'  => 'keterangan1',
                    'keterangan2'  => 'keterangan2',
                    'keterangan3'  => 'keterangan3',
                    'isdefault'    => 'isdefault',
                ],
            ],
        ];
    }

    // ─────────────────────────────────────────────
    //  PUBLIC API
    // ─────────────────────────────────────────────

    /**
     * Sync all configured tables.
     *
     * @return array{results: array, total_inserted: int, total_skipped: int}
     */
    public function syncAll(): array
    {
        $results = [];
        $totalInserted = 0;
        $totalSkipped = 0;

        foreach ($this->definitions() as $def) {
            /** @var \Illuminate\Database\Eloquent\Model $model */
            $model     = new $def['model'];
            $localTable = $model->getTable();
            $smartTable = $def['smart'];

            $this->log("Syncing {$localTable} ← {$smartTable} …");

            $result = $this->syncDefinition($def);
            $results[$localTable] = $result;
            $totalInserted += $result['inserted'];
            $totalSkipped += $result['skipped'];

            $this->log(
                "  {$localTable}: {$result['inserted']} inserted, {$result['skipped']} skipped"
            );
        }

        return [
            'results'        => $results,
            'total_inserted' => $totalInserted,
            'total_skipped'  => $totalSkipped,
        ];
    }

    /**
     * Sync a single table by its model class name.
     *
     * @return array{inserted: int, skipped: int}
     */
    public function syncTable(string $modelClass): array
    {
        foreach ($this->definitions() as $def) {
            if ($def['model'] === $modelClass) {
                return $this->syncDefinition($def);
            }
        }

        throw new \InvalidArgumentException("No definition for model {$modelClass}");
    }

    /**
     * Check whether the smart DB connection is reachable.
     */
    public function isConnectionAlive(): bool
    {
        try {
            DB::connection($this->smartConnection)->getPdo();
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Resolve the smart connection, optionally overriding the database name.
     */
    public function smartQuery(): \Illuminate\Database\Query\Builder
    {
        $conn = DB::connection($this->smartConnection);

        if ($this->smartDatabase) {
            $conn->statement("USE `{$this->smartDatabase}`");
        }

        // Return a fluent query builder that is already on this connection
        return $conn->table(DB::raw('DUAL')); // dummy; caller will ->table()
    }

    // ─────────────────────────────────────────────
    //  INTERNAL
    // ─────────────────────────────────────────────

    private function syncDefinition(array $def): array
    {
        $localTable = (new $def['model'])->getTable();
        $keyColumn  = $def['key'];
        $colMap     = $def['columns'];
        $hasTimestamps = (new $def['model'])->usesTimestamps();

        // ── 1. Get existing local key values (just IDs — small footprint) ──
        $localKeyValues = DB::table($localTable)
            ->pluck($keyColumn)
            ->toArray();

        $localKeySet = array_flip($localKeyValues);
        unset($localKeyValues);

        // ── 2. Stream rows from the smart DB via cursor ──
        $inserted = 0;
        $skipped  = 0;
        $chunk    = [];

        foreach ($this->smartCursor($def['smart']) as $row) {
            $row = (array) $row;
            $keyValue = $row[$def['key']] ?? null;

            if ($keyValue === null || $keyValue === '' || $keyValue === 0) {
                $skipped++;
                continue;
            }

            if (isset($localKeySet[$keyValue])) {
                $skipped++;
                continue;
            }

            // Map columns
            $mapped = [];
            foreach ($colMap as $smartCol => $localCol) {
                if (array_key_exists($smartCol, $row)) {
                    $mapped[$localCol] = $row[$smartCol];
                }
            }

            // Strip timestamps if model doesn't use them
            if (!$hasTimestamps) {
                unset($mapped['created_at'], $mapped['updated_at']);
            }

            $chunk[] = $mapped;

            // Flush to DB every 100 rows
            if (count($chunk) >= 100) {
                $inserted += $this->batchInsert($localTable, $keyColumn, $chunk);
                $chunk = [];
            }
        }

        // Flush remaining rows
        if (!empty($chunk)) {
            $inserted += $this->batchInsert($localTable, $keyColumn, $chunk);
        }

        return [
            'inserted' => $inserted,
            'skipped'  => $skipped,
        ];
    }

    /**
     * Batch-insert a chunk of mapped rows, falling back to row-by-row on failure.
     */
    private function batchInsert(string $table, string $keyColumn, array &$rows): int
    {
        $inserted = 0;
        $batches = array_chunk($rows, 100);

        foreach ($batches as $batch) {
            try {
                DB::table($table)->insert($batch);
                $inserted += count($batch);
            } catch (\Throwable $e) {
                foreach ($batch as $single) {
                    try {
                        DB::table($table)->insert([$single]);
                        $inserted++;
                    } catch (\Throwable $e2) {
                        $id = $single[$keyColumn] ?? '?';
                        $this->log(
                            "Failed to insert {$table} {$keyColumn}={$id}: {$e2->getMessage()}",
                            'warning'
                        );
                    }
                }
            }
        }

        return $inserted;
    }

    /**
     * Stream rows from a smart DB table using a cursor (memory-efficient).
     */
    private function smartCursor(string $table): \Illuminate\Support\LazyCollection
    {
        $conn = DB::connection($this->smartConnection);

        if ($this->smartDatabase) {
            $conn->statement("USE `{$this->smartDatabase}`");
        }

        return $conn->table($table)->cursor();
    }

    // ─────────────────────────────────────────────
    //  HELPERS
    // ─────────────────────────────────────────────

    private function log(string $message, string $level = 'info'): void
    {
        Log::$level("[SmartDataSync] {$message}");
        if (app()->runningInConsole()) {
            $this->output?->writeln($message);
        }
    }

    /**
     * Injected by the command so logs appear on the console too.
     */
    public ?\Symfony\Component\Console\Output\OutputInterface $output = null;
}
