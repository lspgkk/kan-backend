<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('metode_pembayarans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('kode')->unique();
            $table->string('tipe');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metode_pembayarans');
    }
};
