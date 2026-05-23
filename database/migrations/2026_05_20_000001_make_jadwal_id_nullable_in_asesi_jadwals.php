<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asesi_jadwals', function (Blueprint $table) {
            $table->dropForeign('asesi_jadwals_jadwal_id_foreign');
            $table->dropUnique('asesi_jadwals_jadwal_id_id_izin_unique');
        });

        Schema::table('asesi_jadwals', function (Blueprint $table) {
            $table->integer('jadwal_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('asesi_jadwals', function (Blueprint $table) {
            $table->integer('jadwal_id')->nullable(false)->change();
        });

        Schema::table('asesi_jadwals', function (Blueprint $table) {
            $table->foreign('jadwal_id')
                ->references('jadwal_id')
                ->on('jadwals')
                ->cascadeOnDelete();

            $table->unique(['jadwal_id', 'id_izin']);
        });
    }
};
