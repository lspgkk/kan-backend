<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jabatan_config_a', function (Blueprint $table) {
            $table->unsignedBigInteger('manajer_id')->nullable()->after('validator_id');
            $table->string('manajer_img')->nullable()->after('validator_img');
            $table->string('manajer_url')->nullable()->after('manajer_img');
            $table->date('manajer_tgl')->nullable()->after('manajer_url');
        });
    }

    public function down(): void
    {
        Schema::table('jabatan_config_a', function (Blueprint $table) {
            $table->dropColumn(['manajer_id', 'manajer_img', 'manajer_url', 'manajer_tgl']);
        });
    }
};
