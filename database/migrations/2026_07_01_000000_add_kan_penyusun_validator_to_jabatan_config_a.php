<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jabatan_config_a', function (Blueprint $table) {
            // KAN penyusun (compiler)
            $table->unsignedBigInteger('kan_penyusun_id')->nullable()->after('manajer_tgl');
            $table->date('kan_penyusun_tgl')->nullable()->after('kan_penyusun_id');
            $table->string('kan_penyusun_img')->nullable()->after('kan_penyusun_tgl');
            $table->string('kan_penyusun_url')->nullable()->after('kan_penyusun_img');

            // KAN validator
            $table->unsignedBigInteger('kan_validator_id')->nullable()->after('kan_penyusun_url');
            $table->date('kan_validator_tgl')->nullable()->after('kan_validator_id');
            $table->string('kan_validator_img')->nullable()->after('kan_validator_tgl');
            $table->string('kan_validator_url')->nullable()->after('kan_validator_img');
        });
    }

    public function down(): void
    {
        Schema::table('jabatan_config_a', function (Blueprint $table) {
            $table->dropColumn([
                'kan_penyusun_id',
                'kan_penyusun_tgl',
                'kan_penyusun_img',
                'kan_penyusun_url',
                'kan_validator_id',
                'kan_validator_tgl',
                'kan_validator_img',
                'kan_validator_url',
            ]);
        });
    }
};
