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
        Schema::create('harga_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('tipe');
            $table->string('jenjang');
            $table->integer('tahap1')->nullable();
            $table->integer('tahap2')->nullable();
            $table->timestamp();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('harga_invoices');
    }
};
