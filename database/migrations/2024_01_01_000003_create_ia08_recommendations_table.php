<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('ia08_recommendations', function (Blueprint $table) {
            $table->id();
            $table->string('id_izin');
            $table->boolean('is_kompeten')->nullable();
            $table->string('bukti_tambahan')->nullable();
            $table->string('rekomendasi_unit')->nullable();
            $table->string('rekomendasi_elemen')->nullable();
            $table->string('rekomendasi_kuk')->nullable();
            $table->timestamps();

            $table->index('id_izin');
        });
    }

    public function down()
    {
        Schema::dropIfExists('ia08_recommendations');
    }
};