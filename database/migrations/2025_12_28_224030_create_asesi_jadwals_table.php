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
        Schema::create('asesi_jadwals', function (Blueprint $table) {
            $table->id();

            // FK to jadwals.id
            $table->integer('jadwal_id');

            // FK to personals.id_izin
            $table->string('id_izin', 255);

            $table->timestamps();

            $table->foreign('jadwal_id')
                ->references('jadwal_id')
                ->on('jadwals')
                ->cascadeOnDelete();

            $table->foreign('id_izin')
                ->references('id_izin')
                ->on('personals')
                ->cascadeOnDelete();

            // prevent duplicate asesi in same jadwal
            $table->unique(['jadwal_id', 'id_izin']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asesi_jadwals');
    }
};
