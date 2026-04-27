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
        Schema::create('ak6_dimensi_kompetensi', function (Blueprint $table) {
            $table->id();
            $table->string('id_izin');
            $table->string('task_skills')->nullable();
            $table->string('task_management_skills')->nullable();
            $table->string('contingency_management_skills')->nullable();
            $table->string('job_role_environment_skills')->nullable();
            $table->string('transfer_skills')->nullable();
            $table->timestamps();

            $table->index('id_izin');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ak6_dimensi_kompetensi');
    }
};
