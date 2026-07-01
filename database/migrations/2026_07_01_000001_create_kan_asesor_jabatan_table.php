<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kan_asesor_jabatan', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('id_jabatan_kerja', 10);
            $table->timestamps();

            $table->unique(['user_id', 'id_jabatan_kerja']);

            // Foreign keys omitted — jabatan_kerjas may use MyISAM which doesn't support FKs.
            // Referential integrity is enforced at the application layer.
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kan_asesor_jabatan');
    }
};
