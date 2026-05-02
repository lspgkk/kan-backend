<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('ia10_answers', function (Blueprint $table) {
            $table->id();
            $table->string('id_izin');
            $table->integer('referensi_id')->nullable();
            $table->boolean('answer')->nullable();
            $table->text('essay_answer')->nullable();
            $table->timestamps();

            $table->foreign('referensi_id')->references('id')->on('referensis')->onDelete('cascade');
            $table->index('id_izin');
        });

        Schema::create('ia10_form_data', function (Blueprint $table) {
            $table->id();
            $table->string('id_izin');
            $table->string('nama_pengawas')->nullable();
            $table->string('tempat_kerja')->nullable();
            $table->string('alamat')->nullable();
            $table->string('telepon')->nullable();
            $table->timestamps();

            $table->index('id_izin');
        });
    }

    public function down()
    {
        Schema::dropIfExists('ia10_answers');
        Schema::dropIfExists('ia10_form_data');
    }
};