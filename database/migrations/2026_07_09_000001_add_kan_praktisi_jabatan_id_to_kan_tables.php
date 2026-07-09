<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Answer tables
        Schema::table('kan_ia4b_answers', function (Blueprint $table) {
            $table->unsignedBigInteger('kan_praktisi_jabatan_id')->nullable()->after('id_izin');
            $table->index('kan_praktisi_jabatan_id', 'kpj_ia4b_idx');
        });

        Schema::table('kan_ia5_answers', function (Blueprint $table) {
            $table->unsignedBigInteger('kan_praktisi_jabatan_id')->nullable()->after('id_izin');
            $table->index('kan_praktisi_jabatan_id', 'kpj_ia5_idx');
        });

        Schema::table('kan_ia6_answers', function (Blueprint $table) {
            $table->unsignedBigInteger('kan_praktisi_jabatan_id')->nullable()->after('id_izin');
            $table->index('kan_praktisi_jabatan_id', 'kpj_ia6_idx');
        });

        // Feedback tables
        Schema::table('kan_ia5_feedbacks', function (Blueprint $table) {
            $table->unsignedBigInteger('kan_praktisi_jabatan_id')->nullable()->after('id_izin');
            $table->index('kan_praktisi_jabatan_id', 'kpj_ia5f_idx');
        });

        Schema::table('kan_ia6_feedbacks', function (Blueprint $table) {
            $table->unsignedBigInteger('kan_praktisi_jabatan_id')->nullable()->after('id_izin');
            $table->index('kan_praktisi_jabatan_id', 'kpj_ia6f_idx');
        });

        Schema::table('kan_ak2_feedbacks', function (Blueprint $table) {
            $table->unsignedBigInteger('kan_praktisi_jabatan_id')->nullable()->after('id_izin');
            $table->index('kan_praktisi_jabatan_id', 'kpj_ak2f_idx');
        });

        // Barcodes
        Schema::table('barcodes', function (Blueprint $table) {
            $table->unsignedBigInteger('kan_praktisi_jabatan_id')->nullable()->after('id_izin');
            $table->index('kan_praktisi_jabatan_id', 'kpj_barcode_idx');
        });
    }

    public function down(): void
    {
        Schema::table('kan_ia4b_answers', function (Blueprint $table) {
            $table->dropIndex('kpj_ia4b_idx');
            $table->dropColumn('kan_praktisi_jabatan_id');
        });

        Schema::table('kan_ia5_answers', function (Blueprint $table) {
            $table->dropIndex('kpj_ia5_idx');
            $table->dropColumn('kan_praktisi_jabatan_id');
        });

        Schema::table('kan_ia6_answers', function (Blueprint $table) {
            $table->dropIndex('kpj_ia6_idx');
            $table->dropColumn('kan_praktisi_jabatan_id');
        });

        Schema::table('kan_ia5_feedbacks', function (Blueprint $table) {
            $table->dropIndex('kpj_ia5f_idx');
            $table->dropColumn('kan_praktisi_jabatan_id');
        });

        Schema::table('kan_ia6_feedbacks', function (Blueprint $table) {
            $table->dropIndex('kpj_ia6f_idx');
            $table->dropColumn('kan_praktisi_jabatan_id');
        });

        Schema::table('kan_ak2_feedbacks', function (Blueprint $table) {
            $table->dropIndex('kpj_ak2f_idx');
            $table->dropColumn('kan_praktisi_jabatan_id');
        });

        Schema::table('barcodes', function (Blueprint $table) {
            $table->dropIndex('kpj_barcode_idx');
            $table->dropColumn('kan_praktisi_jabatan_id');
        });
    }
};
