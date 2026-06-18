<?php

namespace App\Services;

use App\Models\KlasifikasiKualifikasi;
use App\Models\Pendidikan;
use App\Models\Personal;
use App\Models\Pelatihan;
use App\Models\Proyek;
use App\Models\SertifikatSuket;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class PermohonanSyncService
{
    public const SKK_TYPES = [
        'reguler' => '/',
        'balai'   => '-balai/',
        'fg'      => '-fg/',
    ];

    public const BASE_URL = 'https://siki.pu.go.id/siki-api/v1';

    /** @var int Consecutive SIKI API failures; reset to 0 on success */
    private int $consecutiveFailures = 0;

    /**
     * Sync a single permohonan by id_izin with fallback.
     * Tries reguler first, then balai, then fg.
     * Circuit breaker: after 3 consecutive failures, skip SIKI for subsequent calls.
     */
    public function syncWithFallback(string $id_izin): array
    {
        if ($this->existsInLocal($id_izin)) {
            return ['success' => true, 'type' => null, 'id_izin' => $id_izin, 'error' => null];
        }

        // Circuit breaker: if SIKI is down, don't keep trying
        if ($this->consecutiveFailures >= 3) {
            logger()->warning("[SIKI] Circuit breaker OPEN — skipping sync for id_izin={$id_izin}");
            return ['success' => false, 'type' => null, 'id_izin' => $id_izin, 'error' => 'SIKI circuit breaker open (consecutive failures)'];
        }

        $overallStart = microtime(true);

        foreach (self::SKK_TYPES as $type => $endpoint) {
            // Total timeout: 30 seconds across all attempts
            if (microtime(true) - $overallStart > 30) {
                logger()->warning("[SIKI] Global timeout 30s exceeded for id_izin={$id_izin}");
                break;
            }

            try {
                $this->syncSinglePermohonanData($id_izin, $type, $endpoint, self::BASE_URL);
                $this->consecutiveFailures = 0; // Reset circuit breaker on success
                return ['success' => true, 'type' => $type, 'id_izin' => $id_izin, 'error' => null];
            } catch (\Throwable $e) {
                logger()->warning("[SIKI] id_izin={$id_izin} type={$type} failed: " . $e->getMessage());
                continue;
            }
        }

        $this->consecutiveFailures++;
        logger()->warning("[SIKI] id_izin={$id_izin} ALL SOURCES FAILED (consecutive_failures={$this->consecutiveFailures})");
        return ['success' => false, 'type' => null, 'id_izin' => $id_izin, 'error' => 'Gagal sync dari semua sumber (reguler, balai, fg)'];
    }

    /**
     * Check if id_izin already exists in personals table.
     */
    public function existsInLocal(string $id_izin): bool
    {
        return Personal::where('id_izin', $id_izin)->exists();
    }

    /**
     * Sync single permohonan data from SIKI API into local database.
     */
    public function syncSinglePermohonanData(
        string $id_izin,
        string $type,
        string $endpoint,
        string $baseUrl
    ): void {
        $headers = [
            'Content-Type' => 'application/json',
            'token' => env('SIKI_TOKEN'),
        ];

        $detail = Http::withHeaders($headers)
            ->timeout(15)->connectTimeout(10)
            ->get("$baseUrl/permohonan-skk{$endpoint}{$id_izin}")
            ->json();

        if (($detail['status'] ?? null) === 'errors') {
            throw new \Exception($detail['message'] ?? 'Unknown error');
        }

        DB::transaction(function () use ($detail, $id_izin, $type) {

            // Personal
            foreach ($detail['personal'] as $p) {
                if (!is_array($p)) {
                    continue;
                }
                Personal::updateOrInsert(
                    ['id_izin' => $id_izin],
                    [
                        'id_pemohon' => $p['id'],
                        'type' => $type,
                        'updated' => now(),
                        'created' => $p['created'],
                        'creator' => $p['creator'],
                        'data_id' => $p['data_id'],
                        'nik' => $p['nik'],
                        'nama' => $p['nama'],
                        'tempat_lahir' => $p['tempat_lahir'],
                        'tanggal_lahir' => $p['tanggal_lahir'],
                        'email' => str_replace(' ', '', $p['email'] ?? ''),
                        'telepon' => $p['telepon'],
                        'npwp' => $p['npwp'],
                        'jenis_kelamin' => $p['jenis_kelamin'],
                        'alamat' => $p['alamat'],
                        'negara' => $p['negara'],
                        'propinsi' => $p['propinsi'],
                        'kabupaten' => $p['kabupaten'],
                        'kodepos' => $p['kodepos'],
                        'ktp' => $p['ktp'],
                        'status' => null,
                        'surat_pernyataan_kebenaran_data' => $p['surat_pernyataan_kebenaran_data'],
                        'file_npwp' => $p['file_npwp'],
                        'pas_foto' => $p['pas_foto'],
                    ]
                );
            }

            // Pendidikan
            collect($detail['pendidikan'] ?? [])
            ->filter(fn ($d) => is_array($d))
            ->each(fn ($d) =>
                Pendidikan::create([
                    'id_izin' => $id_izin,
                    'id_pendidikan' => $d['id'],
                    'updated' => now(),
                    'created' => $d['created'],
                    'creator' => $d['creator'],
                    'data_id' => $d['data_id'],
                    'nama_sekolah_perguruan_tinggi' => $d['nama_sekolah_perguruan_tinggi'],
                    'program_studi' => $d['program_studi'],
                    'no_ijazah' => $d['no_ijazah'],
                    'tahun_lulus' => $d['tahun_lulus'] ?? Carbon::now()->year,
                    'jenjang' => $d['jenjang'],
                    'alamat' => $d['alamat'],
                    'negara' => "ID",
                    'propinsi' => $d['propinsi'],
                    'kabupaten' => $d['kabupaten'],
                    'scan_ijazah_legalisir' => $d['scan_ijazah_legalisir'],
                    'scan_surat_keterangan' => $d['scan_surat_keterangan'],
                    'sertifikat_pemberian_kompetensi_tambahan' => $d['sertifikat_pemberian_kompetensi_tambahan'] ?? null,
                    'sertifikat_sibima' => $d['sertifikat_sibima'] ?? null,
                    'tanggal_lulus' => $this->formatDate($d['tanggal_lulus'] ?? null),
                ])
            );

            // Proyek
            collect($detail['proyek'] ?? [])
            ->filter(fn ($d) => is_array($d))
            ->each(fn ($d) =>
                Proyek::create([
                    'id_izin' => $id_izin,
                    'id_proyek' => $d['id'],
                    'updated' => now(),
                    'created' => $d['created'],
                    'creator' => $d['creator'],
                    'data_id' => $d['data_id'],
                    'nama_proyek' => $d['nama_proyek'],
                    'lokasi_proyek' => $d['lokasi_proyek'],
                    'tanggal_awal' => $this->formatDate($d['tanggal_awal'] ?? null),
                    'tanggal_akhir' => $this->formatDate($d['tanggal_akhir'] ?? null),
                    'jabatan' => $d['jabatan'],
                    'nilai_proyek' => $this->formatNumeric($d['nilai_proyek'] ?? null),
                    'surat_referensi' => $d['surat_referensi'],
                    'jenis_pengalaman' => $d['jenis_pengalaman'],
                    'pemberi_kerja' => $d['pemberi_kerja'],
                    'no_registrasi' => $d['no_registrasi'],
                ])
            );

            // Pelatihan
            collect($detail['pelatihan'] ?? [])
            ->filter(fn ($d) => is_array($d))
            ->each(fn ($d) =>
                Pelatihan::create([
                    'id_izin' => $id_izin,
                    'id_pelatihan' => $d['id'],
                    'updated' => now(),
                    'created' => $d['created'],
                    'creator' => $d['creator'],
                    'data_id' => $d['data_id'],
                    'nama_pelatihan' => $d['nama_pelatihan'],
                    'tanggal_awal' => $this->formatDate($d['tanggal_awal'] ?? null),
                    'tanggal_akhir' => $this->formatDate($d['tanggal_akhir'] ?? null),
                    'jumlah_jp' => $this->formatNumeric($d['jumlah_jp'] ?? null),
                    'jumlah_hari' => $this->formatNumeric($d['jumlah_hari'] ?? null),
                    'file_sertifikat' => $d['file_sertifikat'],
                ])
            );

            // Sertifikat
            collect($detail['sertifikat_surat_keterangan'] ?? [])
            ->filter(fn ($d) => is_array($d))
            ->each(fn ($d) =>
                SertifikatSuket::create([
                    'id_izin' => $id_izin,
                    'id_sertifikat_suket' => $d['id'],
                    'updated' => $d['updated'],
                    'created' => $d['created'],
                    'creator' => $d['creator'],
                    'nama_sertifikat_surket' => $d['nama_sertifikat_surket'],
                    'penerbit' => $d['penerbit'],
                    'tanggal_mulai' => $this->formatDate($d['tanggal_mulai'] ?? null),
                    'tanggal_selesai' => $this->formatDate($d['tanggal_selesai'] ?? null),
                    'file_sertifikat' => $d['file_sertifikat'],
                ])
            );

            // Klasifikasi
            collect($detail['klasifikasi_kualifikasi'] ?? [])
            ->filter(fn ($d) => is_array($d))
            ->each(fn ($d) =>
                KlasifikasiKualifikasi::create([
                    'id_izin' => $id_izin,
                    'updated' => $d['updated'],
                    'id_klasifikasi_kualifikasi' => $d['id'],
                    'created' => $d['created'],
                    'creator' => $d['creator'],
                    'data_id' => $d['data_id'],
                    'lsp' => $d['lsp'],
                    'subklasifikasi' => $d['subklasifikasi'],
                    'kualifikasi' => $d['kualifikasi'],
                    'jabatan_kerja' => $d['jabatan_kerja'],
                    'jenjang' => $d['jenjang'],
                    'asosiasi' => $d['asosiasi'],
                    'kta' => $d['no_registrasi_asosiasi'],
                    'tuk' => $d['tuk'],
                    'jenis_permohonan' => $d['jenis_permohonan'],
                    'berita_acara_vv' => $d['berita_acara_vv'],
                    'surat_permohonan' => $d['surat_permohonan'],
                    'surat_pengantar_pemohonan_asosiasi' => $d['surat_pengantar_pemohonan_asosiasi'],
                    'sertifikat_skk' => $d['sertifikat_skk'],
                    'self_asesmen_apl' => $d['self_asesmen_apl'],
                    'klasifikasi' => $d['klasifikasi'],
                    'metode_uji' => $d['metode_uji'] ?? null,
                ])
            );
        });
    }

    /**
     * Refetch single permohonan data from SIKI API and overwrite all local data.
     * Unlike syncSinglePermohonanData, this deletes existing child records first
     * so the data is fully refreshed from SIKI.
     */
    public function refetchSinglePermohonanData(
        string $id_izin,
        string $type,
        string $endpoint,
        string $baseUrl
    ): void {
        $headers = [
            'Content-Type' => 'application/json',
            'token' => env('SIKI_TOKEN'),
        ];

        $detail = Http::withHeaders($headers)
            ->timeout(15)->connectTimeout(10)
            ->get("$baseUrl/permohonan-skk{$endpoint}{$id_izin}")
            ->json();

        if (($detail['status'] ?? null) === 'errors') {
            throw new \Exception($detail['message'] ?? 'Unknown error');
        }

        DB::transaction(function () use ($detail, $id_izin, $type) {
            // Personal — updateOrInsert handles both insert and update
            foreach ($detail['personal'] as $p) {
                if (!is_array($p)) {
                    continue;
                }
                Personal::updateOrInsert(
                    ['id_izin' => $id_izin],
                    [
                        'id_pemohon' => $p['id'],
                        'type' => $type,
                        'updated' => now(),
                        'created' => $p['created'],
                        'creator' => $p['creator'],
                        'data_id' => $p['data_id'],
                        'nik' => $p['nik'],
                        'nama' => $p['nama'],
                        'tempat_lahir' => $p['tempat_lahir'],
                        'tanggal_lahir' => $p['tanggal_lahir'],
                        'email' => str_replace(' ', '', $p['email'] ?? ''),
                        'telepon' => $p['telepon'],
                        'npwp' => $p['npwp'],
                        'jenis_kelamin' => $p['jenis_kelamin'],
                        'alamat' => $p['alamat'],
                        'negara' => $p['negara'],
                        'propinsi' => $p['propinsi'],
                        'kabupaten' => $p['kabupaten'],
                        'kodepos' => $p['kodepos'],
                        'ktp' => $p['ktp'],
                        'status' => null,
                        'surat_pernyataan_kebenaran_data' => $p['surat_pernyataan_kebenaran_data'],
                        'file_npwp' => $p['file_npwp'],
                        'pas_foto' => $p['pas_foto'],
                    ]
                );
            }

            // Child tables — delete existing records, then re-insert fresh data

            // Pendidikan
            Pendidikan::where('id_izin', $id_izin)->delete();
            collect($detail['pendidikan'] ?? [])
                ->filter(fn ($d) => is_array($d))
                ->each(fn ($d) =>
                    Pendidikan::create([
                        'id_izin' => $id_izin,
                        'id_pendidikan' => $d['id'],
                        'updated' => now(),
                        'created' => $d['created'],
                        'creator' => $d['creator'],
                        'data_id' => $d['data_id'],
                        'nama_sekolah_perguruan_tinggi' => $d['nama_sekolah_perguruan_tinggi'],
                        'program_studi' => $d['program_studi'],
                        'no_ijazah' => $d['no_ijazah'],
                        'tahun_lulus' => $d['tahun_lulus'] ?? Carbon::now()->year,
                        'jenjang' => $d['jenjang'],
                        'alamat' => $d['alamat'],
                        'negara' => "ID",
                        'propinsi' => $d['propinsi'],
                        'kabupaten' => $d['kabupaten'],
                        'scan_ijazah_legalisir' => $d['scan_ijazah_legalisir'],
                        'scan_surat_keterangan' => $d['scan_surat_keterangan'],
                        'sertifikat_pemberian_kompetensi_tambahan' => $d['sertifikat_pemberian_kompetensi_tambahan'] ?? null,
                        'sertifikat_sibima' => $d['sertifikat_sibima'] ?? null,
                        'tanggal_lulus' => $this->formatDate($d['tanggal_lulus'] ?? null),
                    ])
                );

            // Proyek
            Proyek::where('id_izin', $id_izin)->delete();
            collect($detail['proyek'] ?? [])
                ->filter(fn ($d) => is_array($d))
                ->each(fn ($d) =>
                    Proyek::create([
                        'id_izin' => $id_izin,
                        'id_proyek' => $d['id'],
                        'updated' => now(),
                        'created' => $d['created'],
                        'creator' => $d['creator'],
                        'data_id' => $d['data_id'],
                        'nama_proyek' => $d['nama_proyek'],
                        'lokasi_proyek' => $d['lokasi_proyek'],
                        'tanggal_awal' => $this->formatDate($d['tanggal_awal'] ?? null),
                        'tanggal_akhir' => $this->formatDate($d['tanggal_akhir'] ?? null),
                        'jabatan' => $d['jabatan'],
                        'nilai_proyek' => $this->formatNumeric($d['nilai_proyek'] ?? null),
                        'surat_referensi' => $d['surat_referensi'],
                        'jenis_pengalaman' => $d['jenis_pengalaman'],
                        'pemberi_kerja' => $d['pemberi_kerja'],
                        'no_registrasi' => $d['no_registrasi'],
                    ])
                );

            // Pelatihan
            Pelatihan::where('id_izin', $id_izin)->delete();
            collect($detail['pelatihan'] ?? [])
                ->filter(fn ($d) => is_array($d))
                ->each(fn ($d) =>
                    Pelatihan::create([
                        'id_izin' => $id_izin,
                        'id_pelatihan' => $d['id'],
                        'updated' => now(),
                        'created' => $d['created'],
                        'creator' => $d['creator'],
                        'data_id' => $d['data_id'],
                        'nama_pelatihan' => $d['nama_pelatihan'],
                        'tanggal_awal' => $this->formatDate($d['tanggal_awal'] ?? null),
                        'tanggal_akhir' => $this->formatDate($d['tanggal_akhir'] ?? null),
                        'jumlah_jp' => $this->formatNumeric($d['jumlah_jp'] ?? null),
                        'jumlah_hari' => $this->formatNumeric($d['jumlah_hari'] ?? null),
                        'file_sertifikat' => $d['file_sertifikat'],
                    ])
                );

            // Sertifikat
            SertifikatSuket::where('id_izin', $id_izin)->delete();
            collect($detail['sertifikat_surat_keterangan'] ?? [])
                ->filter(fn ($d) => is_array($d))
                ->each(fn ($d) =>
                    SertifikatSuket::create([
                        'id_izin' => $id_izin,
                        'id_sertifikat_suket' => $d['id'],
                        'updated' => $d['updated'],
                        'created' => $d['created'],
                        'creator' => $d['creator'],
                        'nama_sertifikat_surket' => $d['nama_sertifikat_surket'],
                        'penerbit' => $d['penerbit'],
                        'tanggal_mulai' => $this->formatDate($d['tanggal_mulai'] ?? null),
                        'tanggal_selesai' => $this->formatDate($d['tanggal_selesai'] ?? null),
                        'file_sertifikat' => $d['file_sertifikat'],
                    ])
                );

            // Klasifikasi
            KlasifikasiKualifikasi::where('id_izin', $id_izin)->delete();
            collect($detail['klasifikasi_kualifikasi'] ?? [])
                ->filter(fn ($d) => is_array($d))
                ->each(fn ($d) =>
                    KlasifikasiKualifikasi::create([
                        'id_izin' => $id_izin,
                        'updated' => $d['updated'],
                        'id_klasifikasi_kualifikasi' => $d['id'],
                        'created' => $d['created'],
                        'creator' => $d['creator'],
                        'data_id' => $d['data_id'],
                        'lsp' => $d['lsp'],
                        'subklasifikasi' => $d['subklasifikasi'],
                        'kualifikasi' => $d['kualifikasi'],
                        'jabatan_kerja' => $d['jabatan_kerja'],
                        'jenjang' => $d['jenjang'],
                        'asosiasi' => $d['asosiasi'],
                        'kta' => $d['no_registrasi_asosiasi'],
                        'tuk' => $d['tuk'],
                        'jenis_permohonan' => $d['jenis_permohonan'],
                        'berita_acara_vv' => $d['berita_acara_vv'],
                        'surat_permohonan' => $d['surat_permohonan'],
                        'surat_pengantar_pemohonan_asosiasi' => $d['surat_pengantar_pemohonan_asosiasi'],
                        'sertifikat_skk' => $d['sertifikat_skk'],
                        'self_asesmen_apl' => $d['self_asesmen_apl'],
                        'klasifikasi' => $d['klasifikasi'],
                        'metode_uji' => $d['metode_uji'] ?? null,
                    ])
                );
        });
    }

    private function formatDate($date): ?string
    {
        if (empty($date) || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') {
            return null;
        }
        return $date;
    }

    private function formatNumeric($value): mixed
    {
        if (empty($value) || $value === '' || $value === null) {
            return null;
        }
        return is_numeric($value) ? $value : null;
    }
}
