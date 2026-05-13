<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('survey_pertanyaans', function (Blueprint $table) {
            $table->id();
            $table->enum('kategori', ['LSP', 'TUK']);
            $table->tinyInteger('nomor');
            $table->string('aspek');
            $table->text('pertanyaan');
            $table->timestamps();

            $table->unique(['kategori', 'nomor']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_pertanyaans');
    }
};
