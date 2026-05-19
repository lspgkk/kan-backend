<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asesi_jadwals', function (Blueprint $table) {
            $table->unsignedBigInteger('skema_id')->nullable()->after('id_izin');
        });
    }

    public function down(): void
    {
        Schema::table('asesi_jadwals', function (Blueprint $table) {
            $table->dropColumn('skema_id');
        });
    }
};
