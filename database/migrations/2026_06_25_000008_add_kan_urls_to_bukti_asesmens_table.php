<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bukti_asesmens', function (Blueprint $table) {
            $table->string('url_kan_ia_04b', 500)->nullable()->after('url_ia_10');
            $table->string('url_kan_ia_05', 500)->nullable()->after('url_kan_ia_04b');
            $table->string('url_kan_ia_06', 500)->nullable()->after('url_kan_ia_05');
            $table->string('url_kan_ak_02', 500)->nullable()->after('url_kan_ia_06');
        });
    }

    public function down(): void
    {
        Schema::table('bukti_asesmens', function (Blueprint $table) {
            $table->dropColumn([
                'url_kan_ia_04b',
                'url_kan_ia_05',
                'url_kan_ia_06',
                'url_kan_ak_02',
            ]);
        });
    }
};
