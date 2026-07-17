<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kan_praktisi_jabatan', function (Blueprint $table) {
            $table->string('url_ia04b')->nullable()->after('id_user');
            $table->string('url_ia05')->nullable()->after('url_ia04b');
            $table->string('url_ia06')->nullable()->after('url_ia05');
            $table->string('url_ak02')->nullable()->after('url_ia06');
            $table->string('url_mapa01')->nullable()->after('url_ak02');
            $table->string('url_mapa02')->nullable()->after('url_mapa01');
        });
    }

    public function down(): void
    {
        Schema::table('kan_praktisi_jabatan', function (Blueprint $table) {
            $table->dropColumn([
                'url_ia04b', 'url_ia05', 'url_ia06',
                'url_ak02', 'url_mapa01', 'url_mapa02',
            ]);
        });
    }
};
