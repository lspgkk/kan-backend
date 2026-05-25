<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PengajuanTuk extends Model
{
    protected $table = 'pengajuan_tuk';

    protected $fillable = [
        'ref_number',
        'tuk_type',
        'status',
        'data_tuk',
        'asesor_list',
        'data_sewaktu',
        'dokumen_permohonan',
        'lampiran',
        'submitted_by',
        'approved_by_admin',
        'approved_at_admin',
        'rejected_by_admin',
        'rejection_reason',
        'rejected_at',
        'direktur_id',
        'direktur_qr',
        'approved_at_direktur',
        'ketua_tuk_id',
        'ketua_tuk_qr',
        'confirmed_at_tuk',
        'verifikator_id',
        'checklist_fasilitas',
        'checklist_peralatan',
        'spesifikasi_tuk',
        'rekomendasi',
        'verified_at',
        'validator_id',
        'hasil_validasi',
        'validated_at',
        'no_sk',
        'sk_file',
        'sk_at',
        'doc_peminjaman',
        'doc_surat_tugas',
        'doc_checklist',
        'doc_berita_acara',
        'doc_hasil_verifikasi',
        'doc_lampiran_sk',
        'no_surat_peminjaman',
        'no_surat_tugas',
        'no_berita_acara',
    ];

    protected $casts = [
        'data_tuk' => 'array',
        'asesor_list' => 'array',
        'data_sewaktu' => 'array',
        'checklist_fasilitas' => 'array',
        'checklist_peralatan' => 'array',
        'spesifikasi_tuk' => 'array',
        'approved_at_admin' => 'datetime',
        'rejected_at' => 'datetime',
        'approved_at_direktur' => 'datetime',
        'confirmed_at_tuk' => 'datetime',
        'verified_at' => 'datetime',
        'validated_at' => 'datetime',
        'sk_at' => 'datetime',
    ];

    public function submitter()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function direktur()
    {
        return $this->belongsTo(User::class, 'direktur_id');
    }

    public function ketuaTuk()
    {
        return $this->belongsTo(User::class, 'ketua_tuk_id');
    }

    public function verifikator()
    {
        return $this->belongsTo(User::class, 'verifikator_id');
    }

    public function validator()
    {
        return $this->belongsTo(User::class, 'validator_id');
    }
}
