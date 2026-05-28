<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE pengajuan_tuk MODIFY COLUMN tuk_type ENUM('mandiri', 'sewaktu', 'ajj') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE pengajuan_tuk MODIFY COLUMN tuk_type ENUM('mandiri', 'sewaktu') NOT NULL");
    }
};
