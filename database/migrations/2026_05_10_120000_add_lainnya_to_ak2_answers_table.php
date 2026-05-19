<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ak2_answers', function (Blueprint $table) {
            $table->boolean('lainnya')->nullable()->after('proyek_kerja');
        });
    }

    public function down(): void
    {
        Schema::table('ak2_answers', function (Blueprint $table) {
            $table->dropColumn('lainnya');
        });
    }
};
