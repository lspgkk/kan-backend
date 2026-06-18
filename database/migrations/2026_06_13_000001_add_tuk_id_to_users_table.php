<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('tuk_id')->nullable()->after('role_id');
            $table->foreign('tuk_id')->references('id')->on('mytuk')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['tuk_id']);
            $table->dropColumn('tuk_id');
        });
    }
};
