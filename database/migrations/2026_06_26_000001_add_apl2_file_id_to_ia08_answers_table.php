<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('ia08_answers', function (Blueprint $table) {
            // Make soal_id nullable — answers are now per file, not per soal
            $table->integer('soal_id')->nullable()->change();

            // Add apl2_file_id for file-based answers
            $table->unsignedBigInteger('apl2_file_id')->nullable()->after('soal_id');
            $table->foreign('apl2_file_id')
                ->references('id')->on('apl2_files')
                ->onDelete('cascade');
            $table->index('apl2_file_id');
        });
    }

    public function down()
    {
        Schema::table('ia08_answers', function (Blueprint $table) {
            $table->dropForeign(['apl2_file_id']);
            $table->dropIndex(['apl2_file_id']);
            $table->dropColumn('apl2_file_id');

            $table->integer('soal_id')->nullable(false)->change();
        });
    }
};
