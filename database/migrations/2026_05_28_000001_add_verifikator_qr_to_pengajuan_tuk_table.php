<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pengajuan_tuk', function (Blueprint $table) {
            $table->text('verifikator_qr')->nullable()->after('ketua_tuk_qr');
        });
    }

    public function down(): void
    {
        Schema::table('pengajuan_tuk', function (Blueprint $table) {
            $table->dropColumn('verifikator_qr');
        });
    }
};
