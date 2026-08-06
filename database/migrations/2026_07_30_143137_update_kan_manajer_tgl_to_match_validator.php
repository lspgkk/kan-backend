<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Set kan_manajer_tgl = kan_validator_tgl for all rows
     * where kan_manajer_tgl currently matches kan_penyusun_tgl.
     */
    public function up(): void
    {
        DB::statement("
            UPDATE jabatan_config_a
            SET kan_manajer_tgl = kan_validator_tgl
            WHERE kan_manajer_tgl IS NOT NULL
              AND kan_manajer_tgl = kan_penyusun_tgl
        ");
    }

    /**
     * Reverse the migrations.
     * Rollback: set kan_manajer_tgl back to kan_penyusun_tgl
     * for rows that were updated (only those where kan_validator_tgl matches).
     */
    public function down(): void
    {
        DB::statement("
            UPDATE jabatan_config_a
            SET kan_manajer_tgl = kan_penyusun_tgl
            WHERE kan_manajer_tgl IS NOT NULL
              AND kan_manajer_tgl = kan_validator_tgl
        ");
    }
};
