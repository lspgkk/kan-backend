<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asesi_jadwals', function (Blueprint $table) {
            $table->uuid('wa_uuid')->nullable()->unique()->after('skema_id');
        });
    }

    public function down(): void
    {
        Schema::table('asesi_jadwals', function (Blueprint $table) {
            $table->dropColumn('wa_uuid');
        });
    }
};
