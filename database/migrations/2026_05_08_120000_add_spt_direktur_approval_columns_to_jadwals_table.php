<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jadwals', function (Blueprint $table) {
            if (!Schema::hasColumn('jadwals', 'spt_asesor_direktur_approved')) {
                $table->boolean('spt_asesor_direktur_approved')->default(false);
            }
            if (!Schema::hasColumn('jadwals', 'spt_komtek_direktur_approved')) {
                $table->boolean('spt_komtek_direktur_approved')->default(false);
            }
        });
    }

    public function down(): void
    {
        Schema::table('jadwals', function (Blueprint $table) {
            $table->dropColumn(['spt_asesor_direktur_approved', 'spt_komtek_direktur_approved']);
        });
    }
};
