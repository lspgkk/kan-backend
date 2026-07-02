<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jabatan_config_a', function (Blueprint $table) {
            // KAN manager mutu
            $table->unsignedBigInteger('kan_manajer_id')->nullable()->after('kan_validator_url');
            $table->date('kan_manajer_tgl')->nullable()->after('kan_manajer_id');
            $table->string('kan_manajer_img')->nullable()->after('kan_manajer_tgl');
            $table->string('kan_manajer_url')->nullable()->after('kan_manajer_img');
        });
    }

    public function down(): void
    {
        Schema::table('jabatan_config_a', function (Blueprint $table) {
            $table->dropColumn([
                'kan_manajer_id',
                'kan_manajer_tgl',
                'kan_manajer_img',
                'kan_manajer_url',
            ]);
        });
    }
};
