<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kan_ia4b_answers', function (Blueprint $table) {
            $table->unsignedBigInteger('id_izin')->nullable()->change();
        });

        Schema::table('kan_ia5_answers', function (Blueprint $table) {
            $table->unsignedBigInteger('id_izin')->nullable()->change();
        });

        Schema::table('kan_ia6_answers', function (Blueprint $table) {
            $table->unsignedBigInteger('id_izin')->nullable()->change();
        });

        Schema::table('kan_ia5_feedbacks', function (Blueprint $table) {
            $table->unsignedBigInteger('id_izin')->nullable()->change();
        });

        Schema::table('kan_ia6_feedbacks', function (Blueprint $table) {
            $table->unsignedBigInteger('id_izin')->nullable()->change();
        });

        Schema::table('kan_ak2_feedbacks', function (Blueprint $table) {
            $table->unsignedBigInteger('id_izin')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('kan_ia4b_answers', function (Blueprint $table) {
            $table->unsignedBigInteger('id_izin')->nullable(false)->change();
        });

        Schema::table('kan_ia5_answers', function (Blueprint $table) {
            $table->unsignedBigInteger('id_izin')->nullable(false)->change();
        });

        Schema::table('kan_ia6_answers', function (Blueprint $table) {
            $table->unsignedBigInteger('id_izin')->nullable(false)->change();
        });

        Schema::table('kan_ia5_feedbacks', function (Blueprint $table) {
            $table->unsignedBigInteger('id_izin')->nullable(false)->change();
        });

        Schema::table('kan_ia6_feedbacks', function (Blueprint $table) {
            $table->unsignedBigInteger('id_izin')->nullable(false)->change();
        });

        Schema::table('kan_ak2_feedbacks', function (Blueprint $table) {
            $table->unsignedBigInteger('id_izin')->nullable(false)->change();
        });
    }
};
