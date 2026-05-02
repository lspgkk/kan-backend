<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('ia09_answers', function (Blueprint $table) {
            $table->id();
            $table->string('id_izin');
            $table->integer('soal_id');
            $table->text('kesimpulan')->nullable();
            $table->boolean('is_kompeten')->nullable();
            $table->timestamps();

            $table->foreign('soal_id')->references('id')->on('soals')->onDelete('cascade');
            $table->index('id_izin');
        });
    }

    public function down()
    {
        Schema::dropIfExists('ia09_answers');
    }
};