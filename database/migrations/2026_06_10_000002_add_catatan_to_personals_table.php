<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personals', function (Blueprint $table) {
            if (!Schema::hasColumn('personals', 'catatan')) {
                $table->text('catatan')->nullable()->after('password_plain')
                    ->comment('Catatan/alasan saat update status (lengkap, tidak lengkap, tolak)');
            }
        });
    }

    public function down(): void
    {
        Schema::table('personals', function (Blueprint $table) {
            if (Schema::hasColumn('personals', 'catatan')) {
                $table->dropColumn('catatan');
            }
        });
    }
};
