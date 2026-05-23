<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('apl1_answers', function (Blueprint $table) {
            $table->boolean('is_diterima')->nullable()->after('email_kantor');
        });
    }

    public function down(): void
    {
        Schema::table('apl1_answers', function (Blueprint $table) {
            $table->dropColumn('is_diterima');
        });
    }
};
