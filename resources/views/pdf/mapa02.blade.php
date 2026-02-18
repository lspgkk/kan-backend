<!doctype html>
<html lang="id">
    <head>
        <meta charset="UTF-8" />
        <title>FR.MAPA.02</title>
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

            .keterangan-box {
                background-color: #ffffff;
                border: 1px solid #6f6f6f;
                padding: 12px;
                margin-bottom: 16px;
                font-size: 14px;
            }
            table {
    border-collapse: collapse;
}

table > tbody > tr:first-child > td:first-child {
    border-top-left-radius: 0;
}

/* Border luar bold */
table {
    border: 2px solid black !important;
}

/* Border dalam tetap tipis */
td {
    border: 0.2px solid black;
}
        </style>
    </head>
    <body>
        <!-- Title -->
                    FR. MAPA.02 - FORMULIR MAPA 02<br />
        <br />

        <!-- Header: Skema Sertifikasi -->
        <table width="100%" cellpadding="5" cellspacing="0" align="center">
            <tr>
                <td style="width: 35%;" rowspan="2">
                    <strong>Skema Sertifikasi</strong><br />
                    (KKNI/Okupasi/Klaster)
                </td>
                <td style="width: 15%;"><strong>Judul</strong></td>
                <td style="width: 3%;" align="center"><strong>:</strong></td>
                <td style="width: 47%;"><strong>{{($skema['judul']) }}</strong></td>
            </tr>
            <tr>
                <td><strong>Nomor</strong></td>
                <td align="center"><strong>:</strong></td>
                <td><strong>{{($skema['nomor']) }}</strong></td>
            </tr>
        </table>
        <br />

        @foreach ($kelompok_kerja as $kelompok)
        <!-- Kelompok Pekerjaan {{ $kelompok['urut'] }} -->
        <table width="100%" cellpadding="5" cellspacing="0" align="center">
            <tr>
                <td style="width: 25%;" rowspan="2" valign="top">
                    {{ $kelompok['nama'] }}
                </td>
                <td style="width: 5%;" align="center"><strong>No.</strong></td>
                <td style="width: 20%;" align="center"><strong>Kode Unit</strong></td>
                <td style="width: 50%;" align="center"><strong>Judul Unit</strong></td>
            </tr>
            @foreach ($kelompok['units'] as $i => $unit)
            <tr>
                <td align="center">{{ $i + 1 }}.</td>
                <td>{{ $unit['kode_unit'] }}</td>
                <td>{{ $unit['nama_unit'] }}</td>
            </tr>
            @endforeach
        </table>
        <br />

        <!-- Tabel Instrumen Asesmen -->
        <table width="100%" cellpadding="5" cellspacing="0" align="center" style="font-size: 11pt;">
            <tr>
                <td class="hd-dok" style="width: 5%;" rowspan="2" align="center"><strong>No.</strong></td>
                <td class="hd-dok" rowspan="2"><strong>Instrumen Asesmen</strong></td>
                <td class="hd-dok" colspan="5" align="center"><strong>Potensi Asesi **</strong></td>
            </tr>
            <tr>
                <td class="hd-dok" style="width: 8%;" align="center"><strong>1</strong></td>
                <td class="hd-dok" style="width: 8%;" align="center"><strong>2</strong></td>
                <td class="hd-dok" style="width: 8%;" align="center"><strong>3</strong></td>
                <td class="hd-dok" style="width: 8%;" align="center"><strong>4</strong></td>
                <td class="hd-dok" style="width: 8%;" align="center"><strong>5</strong></td>
            </tr>
            @foreach ($instrumen_asesmen as $i => $item)
            <tr>
                <td align="center">{{ $i + 1 }}.</td>
                <td>{{ $item['nama'] }}</td>
                <td align="center">
                    <span class="checkbox {{ $item['potensi'] == 1 ? 'checked' : '' }}"></span>
                </td>
                <td align="center">
                    <span class="checkbox {{ $item['potensi'] == 2 ? 'checked' : '' }}"></span>
                </td>
                <td align="center">
                    <span class="checkbox {{ $item['potensi'] == 3 ? 'checked' : '' }}"></span>
                </td>
                <td align="center">
                    <span class="checkbox {{ $item['potensi'] == 4 ? 'checked' : '' }}"></span>
                </td>
                <td align="center">
                    <span class="checkbox {{ $item['potensi'] == 5 ? 'checked' : '' }}"></span>
                </td>
            </tr>
            @endforeach
        </table>
        <br />
        @endforeach

        <!-- Keterangan -->
            {!! $keterangan !!}

    </body>
</html>
