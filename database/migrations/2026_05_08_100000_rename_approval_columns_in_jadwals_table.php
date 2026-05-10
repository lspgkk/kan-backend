<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jadwals', function (Blueprint $table) {
            // Rename sk_hasil_asesmen_direktur_approved to sk_pelaksanaan_uji_direktur_approved
            $table->renameColumn('sk_hasil_asesmen_direktur_approved', 'sk_pelaksanaan_uji_direktur_approved');
        });

        // Drop ba_komtek_direktur_approved column if it exists
        if (Schema::hasColumn('jadwals', 'ba_komtek_direktur_approved')) {
            Schema::table('jadwals', function (Blueprint $table) {
                $table->dropColumn('ba_komtek_direktur_approved');
            });
        }
    }

    public function down(): void
    {
        Schema::table('jadwals', function (Blueprint $table) {
            $table->renameColumn('sk_pelaksanaan_uji_direktur_approved', 'sk_hasil_asesmen_direktur_approved');
        });

        if (!Schema::hasColumn('jadwals', 'ba_komtek_direktur_approved')) {
            Schema::table('jadwals', function (Blueprint $table) {
                $table->boolean('ba_komtek_direktur_approved')->default(false);
            });
        }
    }
};
