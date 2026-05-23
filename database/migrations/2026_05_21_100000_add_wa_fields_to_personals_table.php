<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personals', function (Blueprint $table) {
            $table->date('rencana_tanggal_uji')->nullable()->after('pas_foto');
            $table->uuid('wa_uuid')->nullable()->unique()->after('rencana_tanggal_uji');
            $table->string('account', 20)->nullable()->after('wa_uuid');
            $table->string('password_plain', 20)->nullable()->after('account');
        });
    }

    public function down(): void
    {
        Schema::table('personals', function (Blueprint $table) {
            $table->dropColumn(['rencana_tanggal_uji', 'wa_uuid', 'account', 'password_plain']);
        });
    }
};
