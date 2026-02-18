<!doctype html>
<html lang="id">
    <head>
        <meta charset="UTF-8" />
        <title>FR.IA.04.B - LEMBAR PERIKSA KEGIATAN TERSTRUKTUR</title>
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
                background-color: #c40000;
                color: #fff;
            }
            td {
                border: 0.2px solid black;
            }
            table {
                border: 0px solid black;
            }
            .noborder {
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
        </style>
    </head>
    <body>
        <table
            width="100%"
            cellspacing="0"
            cellpadding="5"
            bgcolor="#fff"
            align="center"
        >
            <tr>
                <td class="judul noborder" style="font-weight: bold; font-size: 16px;">
                    FR.IA.04.B {{ $nama_dokumen ?? 'LEMBAR PERIKSA KEGIATAN TERSTRUKTUR' }}
                </td>
            </tr>
        </table>
        <br />
        <!-- IDENTITAS Table -->
        <table
            width="100%"
            cellpadding="5"
            bgcolor="#fff"
            cellspacing="0"
            align="center"
            style="border: 1px solid #000;"
        >
            <tr>
                <td rowspan="2" style="width: 30%; vertical-align: top;">Skema Sertifikasi (KKNI/Okupasi/Klaster)</td>
                <td style="width: 12%;">Judul</td>
                <td>{{ strtoupper($jabatan_kerja) }}</td>
            </tr>
            <tr>
                <td>Nomor</td>
                <td>{{ strtoupper($nomor_skema) }}</td>
            </tr>
            <tr>
                <td>TUK</td>
                <td>:</td>
                <td>{{ strtoupper($tuk) }}</td>
            </tr>
            @if(count($asesor_list) > 1)
                @foreach($asesor_list as $idx => $asesor)
                <tr>
                    <td>Nama Asesor {{ $idx + 1 }}</td>
                    <td>:</td>
                    <td>{{ strtoupper($asesor->nama) }}</td>
                </tr>
                @endforeach
            @else
            <tr>
                <td>Nama Asesor</td>
                <td>:</td>
                <td>{{ strtoupper($asesor_list[0]->nama ?? '') }}</td>
            </tr>
            @endif
            <tr>
                <td>Nama Asesi</td>
                <td>:</td>
                <td>{{ strtoupper($nama_asesi) }}</td>
            </tr>
            <tr>
                <td>Tanggal</td>
                <td>:</td>
                <td>{{ $tanggal }}</td>
            </tr>
        </table>
        <p style="font-size: 12px;">*Coret yang tidak perlu</p>

        <!-- PANDUAN BAGI ASESOR -->
        <table
            width="100%"
            cellpadding="5"
            bgcolor="#fff"
            cellspacing="0"
            align="center"
            style="border: 1px solid #000;"
        >
            <tr class="hd-dok">
                <td style="font-weight: bold;">PANDUAN BAGI ASESOR</td>
            </tr>
            <tr>
                <td>
                    <ul style="margin: 4px 0 4px 18px; padding-left: 18px; list-style-type: disc;">
                        <li style="margin-bottom: 4px;">Lakukan penilaian pencapaian hasil proyek singkat atau kegiatan terstruktur lainnya melalui presentasi.</li>
                        <li style="margin-bottom: 4px;">Penilaian dapat dilakukan untuk keseluruhan unit kompetensi atau per kelompok pekerjaan.</li>
                        <li style="margin-bottom: 4px;">Pertanyaan disampaikan oleh asesor saat asesi melakukan presentasi.</li>
                        <li style="margin-bottom: 4px;">Pertanyaan untuk pemenuhan pencapaian 5 dimensi kompetensi.</li>
                        <li style="margin-bottom: 4px;">Isilah kolom lingkup penyajian proyek sesuai sektor/sub-sektor/profesi.</li>
                        <li style="margin-bottom: 0;">Berikan keputusan pencapaian berdasarkan kesimpulan jawaban asesi.</li>
                    </ul>
                </td>
            </tr>
        </table>
        <br />

        <!-- MAIN ASSESSMENT Table -->
        <table
            width="100%"
            cellpadding="5"
            bgcolor="#fff"
            cellspacing="0"
            align="center"
            style="border: 1px solid #000;"
        >
            <tr class="hd-dok" style="font-weight: bold; text-align: center;">
                <td rowspan="2" style="width: 5%;">No</td>
                <td colspan="3" style="width: 14%;">Aspek Penialaian</td>
                <td colspan="2" style="width: 14%;">Pencapaian</td>
            </tr>
            <tr class="hd-dok" style="font-weight: bold; text-align: center;">
                <td rowspan="1" style="width: 30%;">Lingkup Penyajian proyek atau kegiatan terstruktur lainnya</td>
                <td rowspan="1" style="width: 25%;">Daftar Pertanyaan</td>
                <td rowspan="1" style="width: 25%;">Kesesuaian dengan standar kompetensi kerja</td>
                <td style="width: 7%;">Ya</td>
                <td style="width: 7%;">Tdk</td>
            </tr>

            @foreach($soal_list as $soal)
            <tr>
                <td style="text-align: center; vertical-align: top;">{{ $soal->no }}</td>
                <td style="vertical-align: top;">{{ $soal->soal }}</td>
                <td style="vertical-align: top;">
                    <div>{{ $soal->soal1 }}</div>
                    <p style="margin: 8px 0 4px 0; font-size: 12px; font-weight: bold;">Jawaban asesi:</p>
                    <div style="min-height: 50px; border: 1px solid #ccc; padding: 6px; font-size: 12px; background: #f9f9f9;">
                        {{ $soal->jawaban ?? '-' }}
                    </div>
                </td>
                <td style="vertical-align: top;">{{ $soal->soal2 }}</td>
                <td style="text-align: center; vertical-align: middle;">
                    <span class="checkbox @if($soal->pencapaian === true) checked @endif"></span>
                </td>
                <td style="text-align: center; vertical-align: middle;">
                    <span class="checkbox @if($soal->pencapaian === false) checked @endif"></span>
                </td>
            </tr>
            @endforeach
        </table>
        <br />

        <!-- REKOMENDASI Table -->
        <table
            width="100%"
            cellpadding="5"
            bgcolor="#fff"
            cellspacing="0"
            align="center"
            style="border: 1px solid #000;"
        >
            <tr>
                <td style="width: 30%; font-weight: bold; vertical-align: top;">
                    {{ $rekomendasi_soal ?? 'Rekomendasi Asesor:' }}
                </td>
                <td>
                    Asesi telah memenuhi/belum memenuhi pencapaian seluruh kriteria unjuk kerja, direkomendasikan:<br /><br />
                    <span class="checkbox @if($rekomendasi === 'kompeten') checked @endif"></span> Kompeten<br /><br />
                    <span class="checkbox @if($rekomendasi === 'belum_kompeten') checked @endif"></span> Belum Kompeten
                </td>
            </tr>
        </table>
        <br />

        <!-- ASESI SIGNATURE Table -->
        <table
            width="100%"
            cellpadding="5"
            bgcolor="#fff"
            cellspacing="0"
            align="center"
            style="border: 1px solid #000;"
        >
            <tr style="font-weight: bold;">
                <td colspan="3">Asesi :</td>
            </tr>
            <tr>
                <td style="width: 30%;">Nama</td>
                <td style="width: 5%;">:</td>
                <td>{{ strtoupper($nama_asesi) }}</td>
            </tr>
            <tr>
                <td>Tanda tangan/ Tanggal</td>
                <td>:</td>
                <td style="height: 70px; vertical-align: middle; text-align: center;">
                    @if($barcodes['asesi']->url ?? null)
                    <img src="{{ $barcodes['asesi']->url }}" style="height: 50px; width: 50px; object-fit: contain;" /><br />
                    <span style="font-size: 11px;">
                        {{ $barcodes['asesi']->tanggal ? \Carbon\Carbon::parse($barcodes['asesi']->tanggal)->translatedFormat('d F Y') : '' }}
                    </span>
                    @endif
                </td>
            </tr>
        </table>
        <!-- @foreach($asesor_list as $idx => $asesor)
        @php $asesor_key = 'asesor' . ($idx + 1); @endphp -->
        <table
            width="100%"
            cellpadding="5"
            bgcolor="#fff"
            cellspacing="0"
            align="center"
            style="border: 1px solid #000; border-top: none;"
        >
            <tr style="font-weight: bold;">
                <td colspan="3">Asesor {{ count($asesor_list) > 1 ? ($idx + 1) : '' }} :</td>
            </tr>
            <tr>
                <td style="width: 30%;">Nama</td>
                <td style="width: 5%;">:</td>
                <td>{{ strtoupper($asesor->nama) }}</td>
            </tr>
            <tr>
                <td>Tanda tangan/ Tanggal</td>
                <td>:</td>
                <td style="height: 70px; vertical-align: middle; text-align: center;">
                    @if($barcodes[$asesor_key]->url ?? null)
                    <img src="{{ $barcodes[$asesor_key]->url }}" style="height: 50px; width: 50px; object-fit: contain;" /><br />
                    <span style="font-size: 11px;">
                        {{ $barcodes[$asesor_key]->tanggal ? \Carbon\Carbon::parse($barcodes[$asesor_key]->tanggal)->translatedFormat('d F Y') : '' }}
                    </span>
                    @endif
                </td>
            </tr>
        </table>
        @endforeach
    </body>
</html>
