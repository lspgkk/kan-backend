<!doctype html>
<html lang="id">
    <head>
        <meta charset="UTF-8" />
        <title>FR.AK.02 - FRAGEMEN ANTARA ASESOR (KAN)</title>
        <style>
            @font-face {
                font-family: "Open Sans";
                font-style: normal;
                font-weight: normal;
                src: url("{{ media_url() }}fonts/cJZKeOuBrn4kERxqtaUH3aCWcynf_cDxXwCLxiixG1c) format('truetype');
            }
            body {
                font-family: "Open Sans", Calibri, Candara, Segoe, Segoe UI, Optima, Arial, sans-serif;
                font-size: 11pt;
                margin-top: 65px;
            }
            .hd-dok { background-color: #c40000; color: #fff; }
            td { border: 0.2px solid black; }
            table { border: 0px solid black; border-collapse: collapse; }
            .noborder { border: 0 transparent !important; }
            .checkbox {
                width: 12px; height: 12px;
                border: 1px solid #000;
                display: inline-block;
                text-align: center;
                line-height: 11px;
                font-size: 10px;
            }
            .checked::after { content: "✔"; font-family: "DejaVu Sans", sans-serif; }
            .text-center { text-align: center; }
            .text-bold { font-weight: bold; }
            .uppercase { text-transform: uppercase; }
            .ttd-height { height: 70px; }
            .ttd-barcode { text-align: center; vertical-align: middle; }
            .ttd-barcode img { height: 50px; width: 50px; object-fit: contain; }
            .lampiran { font-size: 12px; }
            .nilai-table th, .nilai-table td { padding: 6px 8px; }
            .rekom-table td { padding: 8px 10px; }
        </style>
    </head>
    <body>
        <table width="100%" cellspacing="0" cellpadding="5" bgcolor="#fff" align="center">
            <tr>
                <td class="judul noborder" style="font-weight: bold; font-size: 16px; letter-spacing: 1px;">
                    FR.AK.02 &nbsp;&nbsp; FRAGEMEN ANTARA ASESOR<br />
                </td>
            </tr>
        </table>
        <br />

        <!-- IDENTITAS -->
        <table width="100%" cellpadding="5" bgcolor="#fff" cellspacing="0" align="center" style="border: 2px solid #000;">
            <tr>
                <td rowspan="2" style="width: 30%;">Skema Sertifikasi (KKNI/Okupasi/Klaster)</td>
                <td style="width: 12%;">Judul</td>
                <td style="width: 3%; text-align: end;">:</td>
                <td style="text-transform: uppercase;">{{ $jabatan_kerja }}</td>
            </tr>
            <tr>
                <td>Nomor</td>
                <td style="text-align: end;">:</td>
                <td style="text-transform: uppercase;">{{ $nomor_skema }}</td>
            </tr>
            <tr>
                <td>TUK</td>
                <td style="text-align: end;">:</td>
                <td colspan="2" style="text-transform: uppercase;">{{ $tuk }}</td>
            </tr>
            @if(count($asesor_list) > 1)
                @foreach($asesor_list as $idx => $asesor)
                <tr>
                    <td>Nama Asesor {{ $idx + 1 }}</td>
                    <td style="text-align: end;">:</td>
                    <td colspan="2">{{ strtoupper($asesor->nama) }}@if($asesor->noreg) ({{ $asesor->noreg }})@endif</td>
                </tr>
                @endforeach
            @else
            <tr>
                <td>Nama Asesor</td>
                <td style="text-align: end;">:</td>
                <td colspan="2">{{ strtoupper($asesor_list[0]->nama ?? '') }}@if($asesor_list[0]->noreg ?? '') ({{ $asesor_list[0]->noreg }})@endif</td>
            </tr>
            @endif
            <tr>
                <td>Nama Asesi</td>
                <td style="text-align: end;">:</td>
                <td colspan="2" style="text-transform: uppercase;">{{ $nama_asesi }}</td>
            </tr>
            <tr>
                <td rowspan="2">Tanggal Asesmen</td>
                <td style="text-align: right;">Mulai :</td>
                <td colspan="2">{{ $tanggal_mulai }}</td>
            </tr>
            <tr>
                <td style="text-align: right;">Selesai :</td>
                <td colspan="2">{{ $tanggal_selesai }}</td>
            </tr>
        </table>
        <br />

        <p style="font-size: 13px;">
            Beri tanda centang (√) di kolom yang sesuai untuk mencerminkan bukti yang diperoleh untuk menentukan Kompetensi asesi untuk setiap Unit Kompetensi.
        </p>

        <!-- MATRIKS KOMPETENSI -->
        <table width="100%" cellpadding="5" bgcolor="#fff" align="center" border="1" cellspacing="0">
            <tr style="font-weight: bold; text-align: center;">
                <th style="width: 30%;">Unit kompetensi</th>
                <th style="width: 15%;">Observasi demonstrasi</th>
                <th>Portofolio</th>
                <th>Pernyataan Pihak Ketiga<br/>Pertanyaan Wawancara</th>
                <th>Pertanyaan lisan</th>
                <th>Pertanyaan tertulis</th>
                <th>Proyek kerja</th>
                <th>Lainnya</th>
            </tr>

            @foreach($unit_kompetensi as $unit)
            <tr>
                <td>{{ $unit->kode }}<br />{{ $unit->nama }}</td>
                <td style="text-align: center; font-size: 18px;">
                    <span class="checkbox @if($unit->evidence->observasi) checked @endif"></span>
                </td>
                <td style="text-align: center; font-size: 18px;">
                    <span class="checkbox @if($unit->evidence->portofolio) checked @endif"></span>
                </td>
                <td style="text-align: center; font-size: 18px;">
                    <span class="checkbox @if($unit->evidence->pertanyaan_wawancara) checked @endif"></span>
                </td>
                <td style="text-align: center; font-size: 18px;">
                    <span class="checkbox @if($unit->evidence->pertanyaan_lisan) checked @endif"></span>
                </td>
                <td style="text-align: center; font-size: 18px;">
                    <span class="checkbox @if($unit->evidence->pertanyaan_tertulis) checked @endif"></span>
                </td>
                <td style="text-align: center; font-size: 18px;">
                    <span class="checkbox @if($unit->evidence->proyek_kerja) checked @endif"></span>
                </td>
                <td style="text-align: center; font-size: 18px;">
                    <span class="checkbox @if($unit->evidence->lainnya ?? false) checked @endif"></span>
                </td>
            </tr>
            @endforeach

            <!-- REKAPITULASI PENILAIAN HASIL UJI -->
            <tr>
                <td colspan="8" style="padding: 0;">
                    <div style="padding: 10px 14px; font-size: 13px; line-height: 1.4;">
                        <div style="text-align: center; font-weight: bold; margin-bottom: 18px;">
                            Rekapitulasi Penilaian Hasil Uji
                        </div>

                        <table width="100%" cellspacing="0" cellpadding="0" style="border: 0;">
                            <tr>
                                <td class="noborder" style="width: 24px; vertical-align: top;">A.</td>
                                <td class="noborder">
                                    Panduan Penilaian:
                                    <ol style="margin-top: 0; margin-bottom: 8px; padding-left: 22px;">
                                        <li>Tuliskan Total Skor dari setiap masing-masing instrumen asesmen (FR. IA 04B, FR. IA 05 dan FR. IA 06).</li>
                                        <li>Rumus Skor:</li>
                                    </ol>

                                    <table width="100%" cellspacing="0" cellpadding="3" style="border: 0; margin-left: 18px;">
                                        <tr>
                                            <td class="noborder" style="width: 18px; vertical-align: top;">•</td>
                                            <td class="noborder" style="width: 210px;">Skor Pertanyaan DIT</td>
                                            <td class="noborder" style="width: 15px;">=</td>
                                            <td class="noborder">
                                                <span style="display: inline-block; text-align: center; vertical-align: middle; font-style: italic;">
                                                    <span style="display: block; border-bottom: 1px solid #000; padding: 0 8px;">Total Skor</span>
                                                    <span style="display: block;">10</span>
                                                </span>
                                                <span style="font-style: italic;"> x bobot nilai</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="noborder" style="width: 18px; vertical-align: top;">•</td>
                                            <td class="noborder">Skor Pertanyaan Pilihan Ganda</td>
                                            <td class="noborder">=</td>
                                            <td class="noborder">
                                                <span style="display: inline-block; text-align: center; vertical-align: middle; font-style: italic;">
                                                    <span style="display: block; border-bottom: 1px solid #000; padding: 0 8px;">Total Skor</span>
                                                    <span style="display: block;">20</span>
                                                </span>
                                                <span style="font-style: italic;"> x bobot nilai</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="noborder" style="width: 18px; vertical-align: top;">•</td>
                                            <td class="noborder">Skor Pertanyaan Esai</td>
                                            <td class="noborder">=</td>
                                            <td class="noborder">
                                                <span style="display: inline-block; text-align: center; vertical-align: middle; font-style: italic;">
                                                    <span style="display: block; border-bottom: 1px solid #000; padding: 0 8px;">Total Skor</span>
                                                    <span style="display: block;">10</span>
                                                </span>
                                                <span style="font-style: italic;"> x bobot nilai</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="noborder" style="width: 18px; vertical-align: top;">•</td>
                                            <td class="noborder" colspan="3">
                                                Total Skor Nilai = Skor FR. IA 04B + Skor FR. IA 05 + Skor FR. IA 06
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>

                            <tr>
                                <td class="noborder" style="height: 18px;"></td>
                                <td class="noborder"></td>
                            </tr>

                            <tr>
                                <td class="noborder" style="width: 24px; vertical-align: top;">B.</td>
                                <td class="noborder">
                                    Syarat Total Skor Nilai Kompeten/Lulus yaitu:
                                    <ol style="margin-top: 0; margin-bottom: 0; padding-left: 22px;">
                                        <li>
                                            Total Skor Nilai &lt; {{ $threshold_passing }} dinyatakan
                                            <b>"Tidak Lulus/ Tidak direkomendasikan Kompeten"</b>
                                        </li>
                                        <li>
                                            Total Skor Nilai &ge; {{ $threshold_passing }} dapat direkomendasi
                                            <b>"Lulus/ Direkomendasikan Kompeten"</b>
                                        </li>
                                    </ol>
                                </td>
                            </tr>
                        </table>
                    </div>
                </td>
            </tr>

            <!-- NILAI TABLE -->
            <tr class="nilai-table">
                <th style="width: 36%;">Form</th>
                <th style="width: 17%;" colspan="2">Jumlah Soal</th>
                <th style="width: 16%;" colspan="2">Total Skor</th>
                <th style="width: 16%;">Bobot Nilai %</th>
                <th style="width: 15%;" colspan="2">Nilai Skor</th>
            </tr>

            <tr class="nilai-table">
                <td>
                    1. FR IA 04B<br />
                    Pertanyaan DIT
                </td>
                <td class="text-center" colspan="2">10</td>
                <td class="text-center" colspan="2">{{ $total_skor_dit ?? '' }}</td>
                <td class="text-center">50</td>
                <td class="text-center" colspan="2">{{ $nilai_skor_dit ?? '' }}</td>
            </tr>

            <tr class="nilai-table">
                <td>
                    2. FR IA 05<br />
                    Pertanyaan Pilihan Ganda
                </td>
                <td class="text-center" colspan="2">20</td>
                <td class="text-center" colspan="2">{{ $total_skor_pilihan_ganda ?? '' }}</td>
                <td class="text-center">30</td>
                <td class="text-center" colspan="2">{{ $nilai_skor_pilihan_ganda ?? '' }}</td>
            </tr>

            <tr class="nilai-table">
                <td>
                    3. FR IA 06<br />
                    Pertanyaan Esai
                </td>
                <td class="text-center" colspan="2">10</td>
                <td class="text-center" colspan="2">{{ $total_skor_esai ?? '' }}</td>
                <td class="text-center">20</td>
                <td class="text-center" colspan="2">{{ $nilai_skor_esai ?? '' }}</td>
            </tr>

            <tr class="nilai-table">
                <td colspan="6" class="text-center text-bold" style="height: 45px;">
                    Skor Nilai Akhir
                </td>
                <td class="text-center text-bold" colspan="2">
                    {{ $skor_nilai_akhir ?? '' }}
                </td>
            </tr>
        </table>

        <br />

        <!-- REKOMENDASI, TINDAK LANJUT, KOMENTAR -->
        <table class="rekom-table" width="100%" cellspacing="0" cellpadding="0" align="center" style="border: 1.4px solid #000;">
            <tr>
                <td style="width: 30%; font-weight: bold;">
                    Rekomendasi hasil<br />
                    asesmen
                </td>
                <td>
                    <br/>
                    &nbsp;&nbsp;<span class="checkbox @if($isTidakLulus) checked @endif"></span>
                    Tidak Lulus/ Tidak direkomendasikan Kompeten (Nilai total &lt; {{ $threshold_passing }})
                    <br /><br />
                    &nbsp;&nbsp;<span class="checkbox @if($isLulus) checked @endif"></span>
                    Lulus/ Direkomendasikan Kompeten (Nilai total &ge; {{ $threshold_passing }})
                    <br/><br/>
                </td>
            </tr>

            <tr>
                <td style="width: 30%; font-weight: bold;">
                    Tindak lanjut yang<br />
                    dibutuhkan
                    <br /><br />
                    <span style="font-weight: normal;">
                        (Masukkan pekerjaan tambahan dan asesmen yang diperlukan untuk mencapai kompetensi)
                    </span>
                </td>
                <td style="height: 80px; vertical-align: top;">
                    {{ $tindak_lanjut }}
                </td>
            </tr>

            <tr>
                <td style="width: 30%; font-weight: bold;">
                    Komentar/ Observasi<br />
                    oleh asesor
                </td>
                <td style="height: 90px; vertical-align: top;">
                    {{ $komentar }}
                </td>
            </tr>
        </table>

        <br />

        <!-- TANDA TANGAN -->
        <table class="ttd-table" width="100%" cellspacing="0" cellpadding="0" align="center" style="border: 1.4px solid #000;">
            <tr>
                <td colspan="3" class="text-bold">Asesi :</td>
            </tr>
            <tr>
                <td style="width: 30%;">Nama</td>
                <td style="width: 3%; text-align: center;">:</td>
                <td class="uppercase">{{ $nama_asesi }}</td>
            </tr>
            <tr>
                <td class="ttd-height">
                    Tanda tangan<br />
                    dan Tanggal
                </td>
                <td class="text-center">:</td>
                <td class="ttd-height ttd-barcode">
                    @if($barcodes['asesi']->url ?? null)
                        <img src="{{ $barcodes['asesi']->url }}" />
                        <br />
                        <span style="font-size: 10px;">
                            {{ $barcodes['asesi']->tanggal ? \Carbon\Carbon::parse($barcodes['asesi']->tanggal)->translatedFormat('d F Y') : '' }}
                        </span>
                    @endif
                </td>
            </tr>

            @foreach($asesor_list as $idx => $asesor)
                @php $asesor_key = 'asesor' . ($idx + 1); @endphp
                <tr>
                    <td colspan="3" class="text-bold">
                        Asesor {{ $idx + 1 }} :
                    </td>
                </tr>
                <tr>
                    <td>Nama</td>
                    <td class="text-center">:</td>
                    <td class="uppercase">{{ $asesor->nama }}</td>
                </tr>
                <tr>
                    <td>No. Reg</td>
                    <td class="text-center">:</td>
                    <td>{{ $asesor->noreg ?? '' }}</td>
                </tr>
                <tr>
                    <td class="ttd-height">
                        Tanda tangan<br />
                        dan Tanggal
                    </td>
                    <td class="text-center">:</td>
                    <td class="ttd-height ttd-barcode">
                        @if($barcodes[$asesor_key]->url ?? null)
                            <img src="{{ $barcodes[$asesor_key]->url }}" />
                            <br />
                            <span style="font-size: 10px;">
                                {{ $barcodes[$asesor_key]->tanggal ? \Carbon\Carbon::parse($barcodes[$asesor_key]->tanggal)->translatedFormat('d F Y') : '' }}
                            </span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </table>

        <br />

        <!-- LAMPIRAN DOKUMEN -->
        <div class="lampiran">
            <b>LAMPIRAN DOKUMEN:</b><br />
            1. Dokumen APL 01 peserta<br />
            2. Dokumen APL 02 peserta<br />
            3. Bukti-bukti berkualitas peserta<br />
            4. Tinjauan proses asesmen
        </div>
    </body>
</html>
