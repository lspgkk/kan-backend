<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Fix kan_penyusun_url and kan_validator_url:
     * Replace /ttd/ with /penyusun-validator/ for all KAN penyusun & validator URLs.
     */
    public function up(): void
    {
        // Fix kan_penyusun_url
        DB::statement("
            UPDATE jabatan_config_a
            SET kan_penyusun_url = REPLACE(kan_penyusun_url, '/ttd/', '/penyusun-validator/')
            WHERE kan_penyusun_url LIKE '%/ttd/%'
        ");

        // Fix kan_validator_url
        DB::statement("
            UPDATE jabatan_config_a
            SET kan_validator_url = REPLACE(kan_validator_url, '/ttd/', '/penyusun-validator/')
            WHERE kan_validator_url LIKE '%/ttd/%'
        ");
    }

    public function down(): void
    {
        DB::statement("
            UPDATE jabatan_config_a
            SET kan_penyusun_url = REPLACE(kan_penyusun_url, '/penyusun-validator/', '/ttd/')
            WHERE kan_penyusun_url LIKE '%/penyusun-validator/%'
        ");

        DB::statement("
            UPDATE jabatan_config_a
            SET kan_validator_url = REPLACE(kan_validator_url, '/penyusun-validator/', '/ttd/')
            WHERE kan_validator_url LIKE '%/penyusun-validator/%'
        ");
    }
};
