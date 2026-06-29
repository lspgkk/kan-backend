<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('jadwals', function (Blueprint $table) {
            $table->string('pengajuan_tuk_ref', 50)->nullable()->after('link_video');
            $table->index('pengajuan_tuk_ref');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('jadwals', function (Blueprint $table) {
            $table->dropIndex(['pengajuan_tuk_ref']);
            $table->dropColumn('pengajuan_tuk_ref');
        });
    }
};
