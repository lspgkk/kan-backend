<!doctype html>
<html lang="id">
    <head>
        <meta charset="UTF-8" />
        <title>FR.MAPA.01</title>
        <style>
            body {
                font-family:
                    "Open Sans",
                    Calibri,
                    Candara,
                    Segoe,
                    Segoe UI,
                    Optima,
                    Arial,
                    sans-serif;
                font-size: 11pt;
                margin-top: 65px;
            }
            .hd-dok {
                background-color: #c00000;
                color: #fff;
            }
            .hd-dok-b {
                background-color: #e79291;
                color: #000;
            }
            td {
                border: 0.2px solid black;
            }
            table {
                border: 0px solid black;
            }
            .fnt {
                font-family:
                    "Open Sans",
                    Calibri,
                    Candara,
                    Segoe,
                    Segoe UI,
                    Optima,
                    Arial,
                    sans-serif;
            }
            .noborder {
                border: 0 transparent !important;
            }
            .no-top-bottom {
                border-bottom-color: transparent !important;
                border-top-color: transparent !important;
            }
            .no-bottom {
                border-bottom-color: transparent !important;
            }
            .no-top {
                border-top-color: transparent !important;
            }
            .no-break {
                page-break-inside: avoid;
            }
            .headera,
            .footera {
                position: fixed;
            }
            .headera {
                top: 0px;
            }
            .tba td {
                border: 0 transparent !important;
            }

            .checkbox {
                width: 12px;
                height: 12px;
                border: 1px solid #000;
                display: inline-block;
                text-align: center;
                line-height: 11px;
                font-size: 10px;
            }

            .checked::after {
                content: "✔";
            }

            .radio {
                width: 12px;
                height: 12px;
                border: 1px solid #000;
                border-radius: 50%;
                display: inline-block;
                text-align: center;
                line-height: 11px;
                font-size: 10px;
            }

            .radio-checked::after {
                content: "•";
                font-size: 14px;
            }
            div.rotate-text {
-webkit-transform: rotate(90deg);
-moz-transform: rotate(90deg);
-o-transform: rotate(90deg);
-ms-transform: rotate(90deg);
transform: rotate(90deg);
}
td.rotate-text {
writing-mode: vertical-rl;
  text-orientation: mixed;
  transform : rotate(180deg);
  width: auto;      /* lebar = 1 "kolom" teks */
  height: 35vh;     /* tinggi ngikutin konten */
  word-break: break-all; /* atau break-word */
  white-space: normal;
}
        </style>
    </head>
    <body>
        <!-- Title -->
        <table width="100%" cellspacing="0" cellpadding="5" bgcolor="#fff" align="center">
            <tr>
                <td class="judul noborder">
                    FR. MAPA.01 MERENCANAKAN AKTIVITAS DAN PROSES ASESMEN<br />
                </td>
            </tr>
        </table>
        <br />

        <!-- Header: Skema Sertifikasi -->
        <table width="100%" cellpadding="5" cellspacing="0" align="center">
            <tr>
                <td style="width: 30%;" rowspan="2">Skema Sertifikasi Okupasi Nasional</td>
                <td style="width: 15%;">Judul</td>
                <td style="width: 3%;">:</td>
                <td style="width: 52%;">{{ $skema['judul'] }}</td>
            </tr>
            <tr>
                <td>Nomor</td>
                <td>:</td>
                <td>{{ $skema['nomor'] }}</td>
            </tr>
        </table>
        <br />

        <!-- ================= SECTION 1: Pendekatan Asesmen ================= -->
        <table width="100%" cellpadding="5" cellspacing="0" align="center" class="noborder">
            <tr>
                <td class="noborder">
                    <strong>1. Pendekatan Asesmen</strong><br />
                </td>
            </tr>
        </table>
        <table width="100%" cellpadding="5" cellspacing="0" align="center">
            <!-- Header Section 1 -->
            <tr>
                <td class="hd-dok" colspan="5" style="font-weight: bold;">1.1. Asesi</td>
            </tr>

            <!-- Asesi Options -->
            <tr>
                <td rowspan="21" style="width: 5%; vertical-align: top;">1.1.</td>
                <td rowspan="5" style="width: 20%; vertical-align: top;">Asesi</td>
                <td colspan="3">
                    <span class="checkbox {{ $asesi['hasil_pelatihan_telusur'] ? 'checked' : '' }}"></span>
                    Hasil pelatihan dan/atau pendidikan, dimana kurikulum dan fasilitas praktek mampu telusur terhadap standar kompetensi.
                </td>
            </tr>
            <tr>
                <td colspan="3">
                    <span class="checkbox {{ $asesi['hasil_pelatihan_tidak_kompetensi'] ? 'checked' : '' }}"></span>
                    Hasil pelatihan dan/atau pendidikan, dimana kurikulum belum berbasis kompetensi.
                </td>
            </tr>
            <tr>
                <td colspan="3">
                    <span class="checkbox {{ $asesi['pekerja_berpengalaman_telusur'] ? 'checked' : '' }}"></span>
                    Pekerja berpengalaman, dimana berasal dari industry/tempat kerja yang dalam operasionalnya mampu telusur dengan standar kompetensi.
                </td>
            </tr>
            <tr>
                <td colspan="3">
                    <span class="checkbox {{ $asesi['pekerja_berpengalaman_tidak_telusur'] ? 'checked' : '' }}"></span>
                    Pekerja berpengalaman, dimana berasal dari industry/tempat kerja yang dalam operasionalnya belum berbasis standar kompetensi.
                </td>
            </tr>
            <tr>
                <td colspan="3">
                    <span class="checkbox {{ $asesi['otodidak'] ? 'checked' : '' }}"></span>
                    Pelatihan/belajar mandiri atau otodidak.
                </td>
            </tr>

            <!-- Tujuan Asesmen -->
            <tr>
                <td rowspan="4" style="vertical-align: top;">Tujuan Asesmen</td>
                <td colspan="3">
                    <span class="checkbox {{ $tujuan['sertifikasi'] ? 'checked' : '' }}"></span>
                    Sertifikasi
                </td>
            </tr>
            <tr>
                <td colspan="3">
                    <span class="checkbox {{ $tujuan['pkt'] ? 'checked' : '' }}"></span>
                    Pengakuan Kompetensi Terkini (PKT)
                </td>
            </tr>
            <tr>
                <td colspan="3">
                    <span class="checkbox {{ $tujuan['rpl'] ? 'checked' : '' }}"></span>
                    Rekognisi pembelajaran lampau (RPL)
                </td>
            </tr>
            <tr>
                <td colspan="3">
                    <span class="checkbox {{ $tujuan['lainnya'] ? 'checked' : '' }}"></span>
                    Lainnya
                </td>
            </tr>

            <!-- Konteks Asesmen -->
            <tr>
                <td rowspan="8" style="vertical-align: top;">Konteks Asesmen:</td>
                <td style="width: 15%;">Lingkungan</td>
                <td style="width: 25%;">
                    <span class="checkbox {{ $konteks['tempat_kerja_nyata'] ? 'checked' : '' }}"></span>
                    Tempat kerja nyata
                </td>
                <td style="width: 25%;">
                    <span class="checkbox {{ $konteks['tempat_kerja_simulasi'] ? 'checked' : '' }}"></span>
                    Tempat kerja simulasi
                </td>
            </tr>
            <tr>
                <td>Peluang untuk mengumpulkan bukti dalam sejumlah situasi</td>
                <td>
                    <span class="checkbox {{ $konteks['tersedia'] ? 'checked' : '' }}"></span>
                    Tersedia
                </td>
                <td>
                    <span class="checkbox {{ $konteks['terbatas'] ? 'checked' : '' }}"></span>
                    Terbatas
                </td>
            </tr>
            <tr>
                <td rowspan="3">Hubungan antara standar kompetensi dan</td>
                <td colspan="2">
                    <span class="checkbox {{ $konteks['bukti_asesmen_rpl'] ? 'checked' : '' }}"></span>
                    Bukti untuk mendukung asesmen / RPL
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <span class="checkbox {{ $konteks['aktivitas_kerja'] ? 'checked' : '' }}"></span>
                    Aktivitas kerja di tempat kerja kandidat
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <span class="checkbox {{ $konteks['kegiatan_pembelajaran'] ? 'checked' : '' }}"></span>
                    Kegiatan Pembelajaran
                </td>
            </tr>
            <tr>
                <td rowspan="3">Siapa yang melakukan asesmen / RPL</td>
                <td colspan="2">
                    <span class="checkbox {{ $konteks['lsp_gatensi'] ? 'checked' : '' }}"></span>
                    LSP Gatensi Karya Konstruksi
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <span class="checkbox {{ $konteks['organisasi_pelatihan'] ? 'checked' : '' }}"></span>
                    Organisasi Pelatihan
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <span class="checkbox {{ $konteks['asesor_perusahaan'] ? 'checked' : '' }}"></span>
                    Asesor perusahaan
                </td>
            </tr>

            <!-- Orang yang relevan -->
            <tr>
                <td rowspan="4" style="vertical-align: top;">Orang yang relevan untuk dikonfirmasi</td>
                <td colspan="3">
                    <span class="checkbox {{ $orang_relevan['manajer_sertifikasi_lsp'] ? 'checked' : '' }}"></span>
                    Manajer sertifikasi LSP Gatensi Karya Konstruksi
                </td>
            </tr>
            <tr>
                <td colspan="3">
                    <span class="checkbox {{ $orang_relevan['master_assessor_lsp'] ? 'checked' : '' }}"></span>
                    Master Assessor / Master Trainer / Asesor Utama kompetensi
                </td>
            </tr>
            <tr>
                <td colspan="3">
                    <span class="checkbox {{ $orang_relevan['manajer_pelatihan_lsp'] ? 'checked' : '' }}"></span>
                    Manajer pelatihan Lembaga Training terakreditasi / Lembaga Training terdaftar
                </td>
            </tr>
            <tr>
                <td colspan="3">
                    <span class="checkbox {{ $orang_relevan['lainnya'] ? 'checked' : '' }}"></span>
                    Lainnya
                </td>
            </tr>

            <!-- Tolok Ukur Asesmen -->
            <tr>
                <td class="hd-dok" colspan="5" style="font-weight: bold;">1.2. Tolok ukur asesmen</td>
            </tr>
            <tr>
                <td rowspan="5" style="vertical-align: top;">1.2</td>
                <td rowspan="5" style="vertical-align: top;">Tolok ukur asesmen</td>
                <td colspan="3">
                    <span class="checkbox {{ $tolok_ukur['skkni'] ? 'checked' : '' }}"></span>
                    Standar Kompetensi
                </td>
            </tr>
            <tr>
                <td colspan="3">
                    <span class="checkbox {{ $tolok_ukur['kriteria_kurikulum'] ? 'checked' : '' }}"></span>
                    Kriteria asesmen dari kurikulum pelatihan
                </td>
            </tr>
            <tr>
                <td colspan="3">
                    <span class="checkbox {{ $tolok_ukur['spesifikasi_kinerja'] ? 'checked' : '' }}"></span>
                    Spesifikasi kinerja suatu perusahaan atau industri
                </td>
            </tr>
            <tr>
                <td colspan="3">
                    <span class="checkbox {{ $tolok_ukur['spesifikasi_produk'] ? 'checked' : '' }}"></span>
                    Spesifikasi Produk
                </td>
            </tr>
            <tr>
                <td colspan="3">
                    <span class="checkbox {{ $tolok_ukur['pedoman_khusus'] ? 'checked' : '' }}"></span>
                    Pedoman khusus
                </td>
            </tr>
        </table>
        <br />

        <!-- ================= SECTION 2: Mempersiapkan Rencana Asesmen ================= -->
        <table width="100%" cellpadding="5" cellspacing="0" align="center" class="noborder">
            <tr>
                <td class="noborder">
                    <strong>2. Mempersiapkan Rencana Asesmen</strong><br />
                </td>
            </tr>
        </table>

        @foreach ($kelompok_kerja as $kelompok)
        <!-- Kelompok Pekerjaan {{ $kelompok['urut'] }} -->
        <table width="100%" cellpadding="5" cellspacing="0" align="center">
            <tr>
                <td style="width: 20%;" rowspan="{{ count($kelompok['units']) + 1 }}">
                    <strong>Kelompok Pekerjaan {{ $kelompok['urut'] }}</strong>
                </td>
                <td style="width: 8%;" align="center"><strong>No.</strong></td>
                <td style="width: 22%;" align="center"><strong>Kode Unit</strong></td>
                <td style="width: 50%;" align="center"><strong>Judul Unit</strong></td>
            </tr>
            @foreach ($kelompok['units'] as $i => $unit)
            <tr>
                <td align="center">{{ $i + 1 }}.</td>
                <td align="center">{{ $unit['no_unit'] }}</td>
                <td>{{ $unit['kode_unit'] }}</td>
                <td>{{ $unit['nama_unit'] }}</td>
            </tr>
            @endforeach
            
        </table>
        <br />

        <!-- Tabel Metode Asesmen untuk Kelompok ini -->
        <table width="100%" cellpadding="5" cellspacing="0" align="center" style="font-size: 10pt;">
             <tr>
        <td rowspan="3" style="width: 15%;" align="center"><strong>Unit Kompetensi</strong></td>
        <td rowspan="3" style="width: 18%;" align="center">
            <strong>Bukti-Bukti</strong><br />
            <small>(Kinerja, Produk, Portofolio, dan/atau Pengetahuan) diidentifikasi berdasarkan Kriteria Unjuk Kerja dan Pendekatan Asesmen.</small>
        </td>
        <td colspan="3" align="center"><strong>Jenis Bukti</strong></td>
        <td colspan="5" align="center"><strong>Metode dan Perangkat Asesmen
CL (Ceklis Observasi), DIT (Daftar Instruksi 
Terstruktur), DPL (Daftar Pertanyaan Lisan), 
DPT (Daftar Pertanyaan Tertulis), VPK (Verifikasi 
Pihak Ketiga), CVP (Ceklis Verifikasi Portofolio), 
CRP (Ceklis Reviu Produk), PW (Pertanyaan 
Wawancara)
</strong></td>
    </tr>
            <tr>
                <td style="width: 5%;" align="center"><strong>L</strong></td>
                <td style="width: 5%;" align="center"><strong>T L</strong></td>
                <td style="width: 5%;" align="center"><strong>T</strong></td>
                <td style="width: 13%;" align="center" class="rotate-text">
                    <strong>Observasi langsung</strong><br />
                    <small>(kerja nyata/aktivitas waktu nyata di tempat kerja di lingkungan tempat kerja yang disimulasikan)</small></div>
                </td>
                <td style="width: 13%;" align="center" class="rotate-text">
                    <strong>Kegiatan Terstruktur</strong><br />
                    <small>(latihan simulasi dan bermain peran, proyek, presentasi, lembar kegiatan)</small>
                </td>
                <td style="width: 13%;" align="center" class="rotate-text">
                    <strong>Tanya Jawab</strong><br />
                    <small>(pertanyaan tertulis, wawancara, asesmen diri, tanya jawab lisan, angket, ujian lisan atau tertulis)</small>
                </td>
                <td style="width: 13%;" align="center" class="rotate-text">
                    <strong>Verifikasi Portfolio</strong><br />
                    <small>(sampel pekerjaan yang disusun oleh Asesi, produk dengan dokumentasi pendukung, bukti sejarah, jurnal atau buku catatan, informasi tentang pengalaman hidup)</small>
                </td>
                <td style="width: 10%;" align="center" class="rotate-text">
                    <strong>Reviu Produk</strong><br />
                    <small>Produk hasil proyek, contoh hasil kerja/produk</small>
                </td>
            </tr>
            @foreach ($kelompok['units'] as $i => $unit)
            <tr>
                <td align="center">L</td>
                <td align="center"></td>
                <td align="center">T</td>
                <td align="center"></td>
                <td align="center"><strong></strong></td>
                <td align="center"></td>
                <td align="center"></td>
                <td align="center"><strong></strong></td>
            </tr>
            @endforeach
        </table>
        <br />
        @endforeach

        <!-- ================= SECTION 3: Modifikasi dan Kontekstualisasi ================= -->
        <table width="100%" cellpadding="5" cellspacing="0" align="center" class="noborder">
            <tr>
                <td class="noborder">
                    <strong>3. Modifikasi dan Kontekstualisasi:</strong><br />
                </td>
            </tr>
        </table>
        <table width="100%" cellpadding="5" cellspacing="0" align="center">
            <tr>
                <td class="hd-dok" colspan="2" style="font-weight: bold;">Modifikasi dan Kontekstualisasi</td>
            </tr>
            @foreach ($modifikasi as $item)
            <tr>
                <td style="width: 50%;">{{ $item['prefix'] }} {{ $item['label'] }}</td>
                <td style="width: 50%;">
                    <span class="radio {{ $item['value'] ? 'radio-checked' : '' }}"></span> Ada
                    <span class="radio {{ !$item['value'] ? 'radio-checked' : '' }}"></span> Tidak ada
                    @if($item['value'])
                    <br /><br />
                    <strong>Jika ada, tuliskan:</strong><br />
                    {{ $item['alasan'] }}
                    @endif
                </td>
            </tr>
            @endforeach
        </table>
        <p style="font-size: 10pt;">*Pilih salah satu opsi</p>
        <br />

        <!-- ================= KONFIRMASI DENGAN ORANG YANG RELEVAN ================= -->
        <table width="100%" cellpadding="5" cellspacing="0" align="center" class="noborder">
            <tr>
                <td class="noborder">
                    <strong>Konfirmasi dengan orang yang relevan:</strong><br />
                </td>
            </tr>
        </table>
        <table width="100%" cellpadding="5" cellspacing="0" align="center">
            <tr>
                <td class="hd-dok" style="width: 40%;">Orang yang relevan</td>
                <td class="hd-dok" style="width: 30%;" align="center">Nama</td>
                <td class="hd-dok" style="width: 30%;">Tanda tangan</td>
            </tr>
            <tr>
                <td>
                    <span class="checkbox {{ $konfirmasi['manajer_sertifikasi'] ? 'checked' : '' }}"></span>
                    Manajer sertifikasi
                </td>
                <td></td>
                <td></td>
            </tr>
            <tr>
                <td>
                    <span class="checkbox {{ $konfirmasi['master_assessor'] ? 'checked' : '' }}"></span>
                    Master Assessor / Master Trainer / Asesor Utama kompetensi
                </td>
                <td></td>
                <td></td>
            </tr>
            <tr>
                <td>
                    <span class="checkbox {{ $konfirmasi['manajer_pelatihan'] ? 'checked' : '' }}"></span>
                    Manajer pelatihan Lembaga Training terakreditasi / Lembaga Training terdaftar
                </td>
                <td></td>
                <td></td>
            </tr>
            <tr>
                <td>
                    <span class="checkbox {{ $konfirmasi['lainnya'] ? 'checked' : '' }}"></span>
                    Lainnya:
                </td>
                <td></td>
                <td></td>
            </tr>
        </table>
        <br />

        <!-- ================= TANDA TANGAN ================= -->
        <table width="100%" cellpadding="5" cellspacing="0" align="center">
            <tr>
                <td class="hd-dok" style="width: 20%;">Status</td>
                <td class="hd-dok" style="width: 10%;" align="center">No</td>
                <td class="hd-dok" style="width: 25%;" align="center">Nama</td>
                <td class="hd-dok" style="width: 20%;">Nomor MET</td>
                <td class="hd-dok" style="width: 25%;">Tanda Tangan dan Tanggal</td>
            </tr>
            <tr>
                <td rowspan="2">Penyusun</td>
                <td align="center">1</td>
                <td>{{ $penyusun['nama'] }}</td>
                <td>{{ $penyusun['nomor_met'] }}</td>
                <td rowspan="2">
                    @if($barcodes['penyusun'])
                    <img src="{{ $barcodes['penyusun']->path }}" /><br />
                    {{ now()->translatedFormat('d F Y') }}
                    @endif
                </td>
            </tr>
            <tr>
                <td align="center">2</td>
                <td></td>
                <td></td>
            </tr>
            <tr>
                <td rowspan="2">Validator</td>
                <td align="center">1</td>
                <td>{{ $validator['nama'] }}</td>
                <td>{{ $validator['nomor_met'] }}</td>
                <td rowspan="2">
                    @if($barcodes['validator'])
                    <img src="{{ $barcodes['validator']->path }}" /><br />
                    {{ now()->translatedFormat('d F Y') }}
                    @endif
                </td>
            </tr>
            <tr>
                <td align="center">2</td>
                <td></td>
                <td></td>
            </tr>
        </table>
    </body>
</html>
