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
        Schema::create('sk_penetapans', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('jadwal_id');
            $table->string('no_surat')->nullable();
            $table->text('link')->nullable();
            $table->boolean('has_ttd_direktur')->default(false);
            $table->bigInteger('inputby_id')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::dropIfExists('sk_penetapans');
    }
};
