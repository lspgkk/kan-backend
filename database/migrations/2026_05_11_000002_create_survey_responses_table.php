<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('survey_responses', function (Blueprint $table) {
            $table->id();
            $table->string('id_izin');
            $table->enum('kategori', ['LSP', 'TUK']);
            $table->text('saran')->nullable();
            $table->boolean('pernyataan')->nullable();
            $table->timestamps();

            $table->unique(['id_izin', 'kategori']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_responses');
    }
};
