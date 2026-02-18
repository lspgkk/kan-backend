<!doctype html>
<html lang="id">
    <head>
        <meta charset="UTF-8" />
        <title>FR.IA.04.A - PENJELASAN SINGKAT PROYEK TERKAIT PEKERJAAN</title>
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
                <td class="judul noborder" style="font-weight: bold; font-size: 16px; letter-spacing: 1px;">
                    {{ $kode_dokumen }} {{ $nama_dokumen ?? 'FR.IA.04. PENJELASAN SINGKAT PROYEK TERKAIT PEKERJAAN KEGIATAN TERSTRUKTUR LAINNYA' }}
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
                    <ul style="margin: 5px 0 5px 18px; padding-left: 18px; list-style-type: disc;">
                        <li style="margin-bottom: 6px;">Tentukan proyek singkat atau kegiatan terstruktur lainnya yang harus dipersiapkan dan dipresentasikan oleh asesi.</li>
                        <li style="margin-bottom: 6px;">Proyek singkat atau kegiatan terstruktur lainnya dibuat untuk keseluruhan unit kompetensi dalam Skema Sertifikasi atau untuk masing-masing kelompok pekerjaan.</li>
                        <li style="margin-bottom: 0;">Kumpulkan hasil proyek singkat atau kegiatan terstruktur lainnya sesuai dengan hasil keluaran yang telah ditetapkan.</li>
                    </ul>
                </td>
            </tr>
        </table>
        <br />

        <!-- KELOMPOK PEKERJAAN Table -->
        <table
            width="100%"
            cellpadding="5"
            bgcolor="#fff"
            cellspacing="0"
            align="center"
            style="border: 1px solid #000;"
        >
            <tr class="hd-dok" style="font-weight: bold; text-align: center;">
                <td rowspan="2" style="width: 20%; vertical-align: middle;">
                    {{ $kelompok_kerja_nama }} || count($unit_kompetensi) + 1
                </td>
                <td style="width: 8%;">No.</td>
                <td style="width: 25%;">Kode Unit</td>
                <td>Judul Unit</td>
            </tr>
            @foreach($unit_kompetensi as $idx => $unit)
            <tr>
                <td style="text-align: center;">{{ $idx + 1 }}</td>
                <td>{{ $unit->kode_unit }}</td>
                <td>{{ $unit->nama_unit }}</td>
            </tr>
            @endforeach
        </table>
        <br />

        <!-- SOAL Sections -->
        @foreach($soal_list as $soal)
        <table
            width="100%"
            cellpadding="5"
            bgcolor="#fff"
            cellspacing="0"
            align="center"
            style="border: 1px solid #000;"
        >
            <tr>
                <td style="width: 28%; font-weight: bold; background: #fff;">
                    {{ $soal->soal }}
                </td>
                <td>
                    @if($soal->is_komentar === "2")
                        {{ $soal->jawaban }}
                    @elseif($soal->jenis === '1')
                        {!! nl2br(e($soal->jawaban)) !!}
                    @else
                        &nbsp;
                    @endif
                </td>
            </tr>
        </table>
        @endforeach

        <!-- TANDA TANGAN Table -->
        <table
            width="100%"
            cellpadding="5"
            bgcolor="#fff"
            cellspacing="0"
            align="center"
            style="border: 1px solid #000;"
        >
            <tr style="text-align: center; font-weight: bold;">
                <td>Tanda Tangan Asesi</td>
                <td colspan="{{ count($asesor_list) }}">Tanda Tangan Asesor</td>
            </tr>
            <tr>
                <td style="height: 100px; vertical-align: middle; text-align: center;">
                    @if($barcodes['asesi']->url ?? null)
                    <div style="font-size: 12px; font-weight: bold; margin-bottom: 4px;">
                        {{ strtoupper($barcodes['asesi']->nama ?? '') }}
                    </div>
                    <img src="{{ $barcodes['asesi']->url }}" style="height: 50px; width: 50px; object-fit: contain;" /><br />
                    <span style="font-size: 11px;">
                        {{ $barcodes['asesi']->tanggal ? \Carbon\Carbon::parse($barcodes['asesi']->tanggal)->translatedFormat('d F Y') : '' }}
                    </span>
                    @endif
                </td>
                @foreach($asesor_list as $idx => $asesor)
                @php $asesor_key = 'asesor' . ($idx + 1); @endphp
                <td style="height: 100px; vertical-align: middle; text-align: center;">
                    @if($barcodes[$asesor_key]->url ?? null)
                    <div style="font-size: 12px; font-weight: bold; margin-bottom: 4px;">
                        {{ strtoupper($barcodes[$asesor_key]->nama ?? '') }}
                    </div>
                    <img src="{{ $barcodes[$asesor_key]->url }}" style="height: 50px; width: 50px; object-fit: contain;" /><br />
                    <span style="font-size: 11px;">
                        {{ $barcodes[$asesor_key]->tanggal ? \Carbon\Carbon::parse($barcodes[$asesor_key]->tanggal)->translatedFormat('d F Y') : '' }}
                    </span>
                    @endif
                </td>
                @endforeach
            </tr>
        </table>
        <br />

        <!-- STATUS Table -->
        <table
            width="100%"
            cellpadding="5"
            bgcolor="#fff"
            cellspacing="0"
            align="center"
            style="border: 1px solid #000;"
        >
            <tr style="text-align: center; font-weight: bold;">
                <td style="width: 15%;">STATUS</td>
                <td style="width: 8%;">NO</td>
                <td>NAMA</td>
                <td style="width: 20%;">NOMOR MET</td>
                <td style="width: 20%;">TANDA TANGAN DAN TANGGAL</td>
            </tr>
            <tr style="background: #fff; font-weight: bold;">
                <td rowspan="2" style="text-align: center;">PENYUSUN</td>
                <td style="text-align: center;">1</td>
                <td></td>
                <td></td>
                <td></td>
            </tr>
            <tr style="background: #fff;">
                <td style="text-align: center;">2</td>
                <td></td>
                <td></td>
                <td></td>
            </tr>
            <tr style="background: #fff; font-weight: bold;">
                <td rowspan="2" style="text-align: center;">VALIDATOR</td>
                <td style="text-align: center;">1</td>
                <td></td>
                <td></td>
                <td></td>
            </tr>
            <tr style="background: #fff;">
                <td style="text-align: center;">2</td>
                <td></td>
                <td></td>
                <td></td>
            </tr>
        </table>
    </body>
</html>
