<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kan_ia6_feedbacks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_izin');
            $table->text('umpan_balik')->nullable();
            $table->text('unit_elemen_kuk')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kan_ia6_feedbacks');
    }
};
