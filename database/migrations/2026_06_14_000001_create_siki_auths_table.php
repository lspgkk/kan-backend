<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('siki_auths', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable()->comment('Label untuk membedakan koneksi');
            $table->string('base_url')->default('https://siki.pu.go.id/siki-api/v1');
            $table->string('token');
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('siki_auths');
    }
};
