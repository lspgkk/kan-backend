<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pengajuan_tuk', function (Blueprint $table) {
            // Admin LSP approval
            $table->string('approved_by_admin')->nullable();
            $table->timestamp('approved_at_admin')->nullable();
            $table->string('rejected_by_admin')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('rejected_at')->nullable();

            // Direktur TTD
            $table->unsignedBigInteger('direktur_id')->nullable();
            $table->text('direktur_qr')->nullable();
            $table->timestamp('approved_at_direktur')->nullable();

            // Ketua TUK confirm
            $table->unsignedBigInteger('ketua_tuk_id')->nullable();
            $table->text('ketua_tuk_qr')->nullable();
            $table->timestamp('confirmed_at_tuk')->nullable();

            // Verifikator
            $table->unsignedBigInteger('verifikator_id')->nullable();
            $table->json('checklist_fasilitas')->nullable();
            $table->json('checklist_peralatan')->nullable();
            $table->json('spesifikasi_tuk')->nullable();
            $table->string('rekomendasi')->nullable();
            $table->timestamp('verified_at')->nullable();

            // Validator
            $table->unsignedBigInteger('validator_id')->nullable();
            $table->string('hasil_validasi')->nullable();
            $table->timestamp('validated_at')->nullable();

            // SK
            $table->string('no_sk')->nullable();
            $table->string('sk_file')->nullable();
            $table->timestamp('sk_at')->nullable();

            // Generated documents
            $table->string('doc_peminjaman')->nullable();
            $table->string('doc_surat_tugas')->nullable();
            $table->string('doc_checklist')->nullable();
            $table->string('doc_berita_acara')->nullable();
            $table->string('doc_hasil_verifikasi')->nullable();
            $table->string('doc_lampiran_sk')->nullable();

            // Surat numbers
            $table->string('no_surat_peminjaman')->nullable();
            $table->string('no_surat_tugas')->nullable();
            $table->string('no_berita_acara')->nullable();
        });

        // Update status enum
        DB::statement("ALTER TABLE pengajuan_tuk MODIFY COLUMN status ENUM('diajukan','disetujui_admin','ditolak','disetujui_direktur','dikonfirmasi_tuk','diverifikasi','divalidasi','selesai') NOT NULL DEFAULT 'diajukan'");
    }

    public function down(): void
    {
        Schema::table('pengajuan_tuk', function (Blueprint $table) {
            $table->dropColumn([
                'approved_by_admin', 'approved_at_admin',
                'rejected_by_admin', 'rejection_reason', 'rejected_at',
                'direktur_id', 'direktur_qr', 'approved_at_direktur',
                'ketua_tuk_id', 'ketua_tuk_qr', 'confirmed_at_tuk',
                'verifikator_id', 'checklist_fasilitas', 'checklist_peralatan',
                'spesifikasi_tuk', 'rekomendasi', 'verified_at',
                'validator_id', 'hasil_validasi', 'validated_at',
                'no_sk', 'sk_file', 'sk_at',
                'doc_peminjaman', 'doc_surat_tugas', 'doc_checklist',
                'doc_berita_acara', 'doc_hasil_verifikasi', 'doc_lampiran_sk',
                'no_surat_peminjaman', 'no_surat_tugas', 'no_berita_acara',
            ]);
        });

        DB::statement("ALTER TABLE pengajuan_tuk MODIFY COLUMN status ENUM('diajukan','diverifikasi','ditolak') NOT NULL DEFAULT 'diajukan'");
    }
};
