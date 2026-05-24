<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('bukti_asesmens', function (Blueprint $table) {
            $table->string('url_ia_08')->nullable()->after('url_ia_07');
            $table->string('url_ia_09')->nullable()->after('url_ia_08');
            $table->string('url_ia_10')->nullable()->after('url_ia_09');
        });
    }

    public function down()
    {
        Schema::table('bukti_asesmens', function (Blueprint $table) {
            $table->dropColumn(['url_ia_08', 'url_ia_09', 'url_ia_10']);
        });
    }
};