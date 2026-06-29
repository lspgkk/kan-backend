<?php

namespace App\Services;

use App\Models\JabatanKerja;
use App\Models\KlasifikasiKualifikasi;
use App\Models\PengajuanTuk;
use App\Models\Personal;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class DokumenTUKService
{
    private const BULAN_ROMawi = [
        1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV',
        5 => 'V', 6 => 'VI', 7 => 'VII', 8 => 'VIII',
        9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII',
    ];

    private const DIREKTUR_DEFAULT = 'Radinal Efendy, S.T.';

    private const FORMAT_SURAT_PEMINJAMAN = '1/LSP GKK B.006-C/{romawi}/{tahun}';
    private const FORMAT_SURAT_TUGAS      = '1/GKK B.007-E/{romawi}/{tahun}';
    private const FORMAT_BERITA_ACARA     = '1/GKK B.008-E/{romawi}/{tahun}';
    private const FORMAT_SK               = '1/LSP GKK C.003-G/{romawi}/{tahun}';

    /**
     * Generate combined verification PDF (all pages except SK/Lampiran).
     * Stores a single combined PDF on str_moon.
     */
    public function generateDokumenVerifikasi(PengajuanTuk $pengajuan): array
    {
        Carbon::setLocale('id');
        $now = Carbon::now();

        $viewData = $this->buildViewData($pengajuan, $now);
        $viewData['showSk'] = false;

        $isAjj = $pengajuan->tuk_type === 'ajj';
        $template = $isAjj ? 'template-surat.verifikasi-tuk-ajj' : 'template-surat.verifikasi-tuk-nonajj';

        $path = "pengajuan-tuk/{$pengajuan->id}/verifikasi-tuk-{$pengajuan->ref_number}.pdf";
        $this->renderToPdf($template, $viewData, $path);

        $pengajuan->update([
            'doc_peminjaman'       => $path,
            'doc_surat_tugas'      => $path,
            'doc_checklist'        => $path,
            'doc_berita_acara'     => $path,
            'doc_hasil_verifikasi' => $path,
            'no_surat_peminjaman'  => $viewData['noSuratPeminjaman'],
            'no_surat_tugas'       => $viewData['noSuratTugas'],
            'no_berita_acara'      => $viewData['noBeritaAcara'],
        ]);

        return ['doc_verifikasi' => $path];
    }

    /**
     * Generate combined PDF WITH SK and Lampiran pages.
     */
    public function generateDokumenSK(PengajuanTuk $pengajuan): array
    {
        Carbon::setLocale('id');
        $now = Carbon::now();
        $romawi = $this->bulanRomawi($now->month);
        $tahun = $now->year;

        $noSk = $pengajuan->no_sk
            ?? $this->generateNomor(PengajuanTuk::class, 'no_sk', self::FORMAT_SK, $romawi, $tahun);

        $viewData = $this->buildViewData($pengajuan, $now);
        $viewData['showSk']   = true;
        $viewData['noSk']     = $noSk;
        $viewData['tanggalKeputusan'] = $now->isoFormat('D MMMM Y');

        $isAjj = $pengajuan->tuk_type === 'ajj';
        $template = $isAjj ? 'template-surat.verifikasi-tuk-ajj' : 'template-surat.verifikasi-tuk-nonajj';

        // Render full PDF (verifikasi + SK + lampiran) — overwrites main verifikasi file
        $mainPath = "pengajuan-tuk/{$pengajuan->id}/verifikasi-tuk-{$pengajuan->ref_number}.pdf";
        $this->renderToPdf($template, $viewData, $mainPath);

        // Also save as standalone SK file
        $skPath = "pengajuan-tuk/{$pengajuan->id}/verifikasi-tuk-sk-{$pengajuan->ref_number}.pdf";
        $this->renderToPdf($template, $viewData, $skPath);

        $pengajuan->update([
            'no_sk'              => $noSk,
            'sk_file'            => $skPath,
            'doc_lampiran_sk'    => $skPath,
            'doc_peminjaman'     => $mainPath,
            'doc_surat_tugas'    => $mainPath,
            'doc_checklist'      => $mainPath,
            'doc_berita_acara'   => $mainPath,
            'doc_hasil_verifikasi' => $mainPath,
            'sk_at'              => $now,
        ]);

        return ['sk_file' => $skPath, 'doc_lampiran_sk' => $skPath];
    }

    // ─── Private ────────────────────────────────────────────────────

    private function buildViewData(PengajuanTuk $pengajuan, Carbon $now): array
    {
        $dataTuk     = $pengajuan->data_tuk ?? [];
        $dataSewaktu = $pengajuan->data_sewaktu ?? [];
        $asesorList  = $pengajuan->asesor_list ?? [];

        $romawi   = $this->bulanRomawi($now->month);
        $tahun    = $now->year;
        $tglSurat = $now->isoFormat('D MMMM Y');
        $tglVerif = isset($dataTuk['tanggal_verifikasi'])
            ? Carbon::parse($dataTuk['tanggal_verifikasi'])->isoFormat('D MMMM Y')
            : $now->isoFormat('D MMMM Y');
        $direkturName = $this->getDirekturName();
        $verifikatorName = $this->getRelatedUserName($pengajuan->verifikator);

        // Surat numbers
        $noPeminjaman = $pengajuan->no_surat_peminjaman
            ?? $this->generateNomor(PengajuanTuk::class, 'no_surat_peminjaman', self::FORMAT_SURAT_PEMINJAMAN, $romawi, $tahun);
        $noSuratTugas = $pengajuan->no_surat_tugas
            ?? $this->generateNomor(PengajuanTuk::class, 'no_surat_tugas', self::FORMAT_SURAT_TUGAS, $romawi, $tahun);
        $noBa = $pengajuan->no_berita_acara
            ?? $this->generateNomor(PengajuanTuk::class, 'no_berita_acara', self::FORMAT_BERITA_ACARA, $romawi, $tahun);

        // Schedule
        $examDate = isset($dataSewaktu['plannedExamDate'])
            ? Carbon::parse($dataSewaktu['plannedExamDate']) : $now;
        $hariTanggal    = $examDate->isoFormat('dddd, D MMMM Y');
        $hariTanggalUji = $hariTanggal;
        $waktu = '07:30 - Selesai';

        // Skema / Jabker
        $skemaList = array_values(array_unique(array_filter(array_column($asesorList, 'skemaSkk'))));
        $skemaStr  = !empty($skemaList) ? implode(', ', $skemaList) : '-';
        $jenjang = $this->resolveJenjang($asesorList);

        // AJJ: resolve skema from KlasifikasiKualifikasi via id_izin
        if ($pengajuan->tuk_type === 'ajj' && ($skemaStr === '-' || empty($skemaStr))) {
            $ajjIdIzin = $dataSewaktu['id_izin'] ?? null;
            if (!$ajjIdIzin) {
                $personal = Personal::where('email', $dataTuk['emailTuk'] ?? '')->first();
                $ajjIdIzin = $personal?->id_izin;
            }
            if ($ajjIdIzin) {
                $kk = KlasifikasiKualifikasi::where('id_izin', $ajjIdIzin)->first();
                if ($kk && $kk->jabatan_kerja) {
                    $jk = JabatanKerja::where('id_jabatan_kerja', $kk->jabatan_kerja)->first();
                    if ($jk) {
                        $skemaStr = $jk->jabatan_kerja;
                        $skemaList = [$jk->jabatan_kerja];
                        $jenjang = $jk->jenjang_id ?? $jenjang;
                    }
                }
            }
        }

        // Checklist
        $checklistPeralatan = $pengajuan->checklist_peralatan ?? [];
        $checklistItems = [];
        foreach ($checklistPeralatan as $i => $item) {
            $checklistItems[] = [
                'no'          => (string)($i + 1),
                'nama'        => $item['nama'] ?? '-',
                'spesifikasi' => $item['spesifikasi'] ?? '-',
                'jumlah'      => (string)($item['jumlah'] ?? 1),
                'tersedia'    => !empty($item['tersedia']),
                'layak'       => !empty($item['layak']),
            ];
        }
        if (empty($checklistItems)) {
            $checklistItems[] = ['no' => '1', 'nama' => '', 'spesifikasi' => '', 'jumlah' => '', 'tersedia' => null, 'layak' => null];
        }

        // Asesor text (all)
        $asesorText = $this->buildAsesorText($asesorList);

        // Group by metode for Berita Acara + Form Checklist pages
        $baseMetodes = ['Observasi', 'Portofolio'];
        $metodeGroups = [];
        foreach ($baseMetodes as $baseMetode) {
            $groupItems = [];
            foreach ($asesorList as $item) {
                $met = $item['metode'] ?? 'Observasi';
                if (str_contains($met, $baseMetode)) {
                    $groupItems[] = $item;
                }
            }
            if (!empty($groupItems)) {
                $groupSkemaNames = array_values(array_unique(array_filter(array_column($groupItems, 'skemaSkk'))));
                $metodeGroups[] = [
                    'metode_uji'    => $baseMetode,
                    'skema_list'    => $groupItems,
                    'skema_str'     => implode(', ', $groupSkemaNames),
                    'asesor_text'   => $this->buildAsesorText($groupItems),
                    'checklist'     => $checklistItems,
                ];
            }
        }

        // Rekomendasi
        $rekomendasi = $pengajuan->rekomendasi;
        $isLayak = $rekomendasi === 'layak';
        $verifikatorNoreg = $pengajuan->verifikator?->noreg ?? '';

        return [
            // Page 1
            'tanggalSurat'      => $tglSurat,
            'noSuratPeminjaman' => $noPeminjaman,
            'namaKetuaTUK'      => $dataTuk['namaKetua'] ?? '-',
            'hariTanggal'       => $hariTanggal,
            'waktu'             => $waktu,
            'tempat'            => $dataTuk['alamat'] ?? '-',
            'namaDirektur'      => $direkturName,
            // Page 2
            'noSuratTugas'      => $noSuratTugas,
            'namaVerifikator'   => $verifikatorName,
            'verifikatorNoreg'  => $verifikatorNoreg,
            'namaTUK'           => $dataTuk['namaTuk'] ?? '-',
            'alamatTUK'         => $dataTuk['alamat'] ?? '-',
            'skemaSertifikasi'      => $skemaStr,
            'skemaSertifikasiList'  => $skemaList,
            'tanggalVerifikasi'     => $tglVerif,
            // Page 3-4 (per metode group)
            'metodeGroups'      => $metodeGroups,
            'jenjang'           => $jenjang,
            'noBeritaAcara'     => $noBa,
            'hariTanggalVerifikasi' => $hariTanggal,
            'metodeVerifikasi'  => $dataTuk['metode_verifikasi'] ?? 'Daring',
            'hariTanggalUji'    => $hariTanggalUji,
            // Page 3-4 combined fallbacks
            'metodeUji'         => implode(', ', array_unique(array_column($metodeGroups, 'metode_uji'))),
            'namaAsesor'        => $asesorText ?: '-',
            // Asesor list (raw names for AJJ template)
            'asesorList'        => array_column($asesorList, 'skemaSkk'),
            // Page 5
            'rekomendasi'       => $rekomendasi,
            'hasilVerifikasiLayak'   => $isLayak ? '✓' : '',
            'hasilVerifikasiTidakLayak' => !$isLayak ? '✓' : '',
            // SK (defaults, overridden in generateDokumenSK)
            'noSk'              => '',
            'namaAdminTUK'      => $dataTuk['namaAdmin'] ?? '',
            'statusTUK'         => $isLayak ? 'Layak' : 'Tidak Layak',
            'tanggalKeputusan'  => $tglVerif,
            'showSk'            => false,
            'qrDirektur'        => $pengajuan->direktur_qr ?: '',
            'qrKetuaTuk'        => $pengajuan->ketua_tuk_qr ?: '',
            'qrVerifikator'     => $pengajuan->verifikator_qr ?? '',
            // AJJ-specific fields
            'qrDirekturSk'      => $pengajuan->direktur_qr ?? '',
            'ruanganUjiImages'  => $dataSewaktu['ruangan_uji_images'] ?? [],
            'laptopImages'      => $dataSewaktu['laptop_images'] ?? [],
            'jaringanImages'    => $dataSewaktu['kecepatan_jaringan_images'] ?? [],
            'kondisiRuangan'    => $dataSewaktu['kondisi_ruangan'] ?? null,
            'keteranganRuangan' => $dataSewaktu['keterangan_ruangan'] ?? null,
            'kondisiLaptop'     => $dataSewaktu['kondisi_laptop'] ?? null,
            'keteranganLaptop'  => $dataSewaktu['keterangan_laptop'] ?? null,
            'kondisiJaringan'   => $dataSewaktu['kondisi_jaringan'] ?? null,
            'keteranganJaringan' => $dataSewaktu['keterangan_jaringan'] ?? null,
            'jumlahPeserta'     => 0,
            'namaAsesi'         => $dataTuk['namaKetua'] ?? '',
        ];
    }

    private function renderToPdf(string $view, array $data, string $storagePath): void
    {
        $pdf = Pdf::loadView($view, $data);
        Storage::disk('str_moon')->put($storagePath, $pdf->output());
    }

    private function generateNomor(string $modelClass, string $field, string $pattern, string $romawi, int $tahun): string
    {
        $last = $modelClass::whereNotNull($field)->latest('id')->first();
        if ($last && $last->{$field}) {
            $parts = explode('/', $last->{$field});
            $counter = (count($parts) >= 4 && (int)($parts[3] ?? 0) === $tahun) ? ((int)$parts[0] + 1) : 1;
        } else {
            $counter = 1;
        }
        $patternParts = explode('/', $pattern);
        $patternParts[0] = (string)$counter;
        $patternParts[2] = $romawi;
        $patternParts[3] = (string)$tahun;
        return implode('/', $patternParts);
    }

    private function getDirekturName(): string
    {
        return User::where('role_id', 6)->where('is_deleted', false)->value('name') ?? self::DIREKTUR_DEFAULT;
    }

    private function getRelatedUserName(?User $user): string
    {
        return $user?->name ?? '';
    }

    private function bulanRomawi(int $month): string
    {
        return self::BULAN_ROMawi[$month] ?? 'I';
    }

    private function buildAsesorText(array $items): string
    {
        $text = '';
        foreach ($items as $i => $a) {
            $met = $a['metode'] ?? '';
            $nama = $a['skemaSkk'] ?? '';
            $text .= ($i > 0 ? "\n" : '') . ($i + 1) . '. ' . $nama . ' (' . $met . ')';
        }
        return $text ?: '-';
    }

    private function resolveJenjang(array $asesorList): string
    {
        // Check jenjang from admin input first
        $values = array_unique(array_filter(array_column($asesorList, 'jenjang')));
        if (!empty($values)) return implode(', ', $values);
        // Fallback to masaBerlakuSka from landing page
        $values = array_unique(array_filter(array_column($asesorList, 'masaBerlakuSka')));
        return !empty($values) ? implode(', ', $values) : '-';
    }
}
