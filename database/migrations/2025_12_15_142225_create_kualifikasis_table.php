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
        Schema::create('kualifikasis', function (Blueprint $table) {
            $table->id(); // bigint unsigned, auto increment, primary key

            $table->unsignedBigInteger('kualifikasi_id');
            $table->string('kualifikasi', 100)->nullable();
            $table->string('kualifikasi_en', 100)->nullable();

            $table->timestamps(); // created_at & updated_at (nullable by default)
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kualifikasis');
    }
};
