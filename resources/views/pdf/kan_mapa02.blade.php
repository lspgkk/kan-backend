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
                margin-top: 0;
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
                position: static; /* was fixed, changed for DomPDF performance */
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
                font-size: 15px;
                font-family: "DejaVu Sans", sans-serif;
                margin-right: 4px;
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
                margin-right: 4px;
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
    page-break-inside: avoid;
}

tr {
    page-break-inside: avoid;
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
        <table width=100% align=center class="noborder headera" cellpadding=5 cellspacing=0>
            <tr>
                <td class=noborder><img src="{{ public_path("img/kan/logo-left.png") }}"/>
                </td>
                <td align=right class=noborder>
                    <img src="{{ public_path("img/kan/logo-right.png") }}"/>
                </td>
            </tr>
        </table><br>
        <!-- Title -->
                    FR. MAPA.02 - FORMULIR MAPA 02<br />
        <br />

        <!-- Header: Skema Sertifikasi -->
        <table width="100%" cellpadding="5" cellspacing="0" align="center">
            <tr>
                <td style="width: 35%;" rowspan="2">
                    <strong>Skema Sertifikasi</strong><br />
                    (<s>KKNI</s>/Okupasi/<s>Klaster</s>)
                </td>
                <td style="width: 15%;"><strong>Judul</strong></td>
                <td style="width: 3%;" align="center"><strong>:</strong></td>
                <td style="width: 47%;"><strong>{{ $dokumenHeader['jabatan_kerja'] }}</strong></td>
            </tr>
            <tr>
                <td><strong>Nomor</strong></td>
                <td align="center"><strong>:</strong></td>
                <td><strong>{{ $dokumenHeader['nomor_skema'] }}</strong></td>
            </tr>
        </table>
        <br />

        @foreach ($kelompok_kerja['kelompok_kerja'] as $kelompok)
        <table width="100%" cellpadding="5" cellspacing="0" align="center" class="no-break">
            <tr>
                <td style="width:25%;" class="noborder"></td>
                <td style="width:5%;" align="center"><strong>No.</strong></td>
                <td style="width:20%;" align="center"><strong>Kode Unit</strong></td>
                <td style="width:50%;" align="center"><strong>Judul Unit</strong></td>
            </tr>

            @foreach ($kelompok['units'] as $i => $unit)
                @php
                    $total = count($kelompok['units']);

                    // middle index (works for odd & even counts)
                    $middleIndex = intdiv($total - 1, 2);

                    // border handling
                    if ($i === 0 && $total > 1) {
                        $borderClass = 'no-bottom';
                    } elseif ($i === $total - 1 && $total > 1) {
                        $borderClass = 'no-top';
                    } elseif ($total > 1) {
                        $borderClass = 'no-top-bottom';
                    } else {
                        $borderClass = '';
                    }
                @endphp

                <tr>
                    <td valign="top" class="{{ $borderClass }}">
                        @if ($i === $middleIndex)
                            {{ $kelompok['nama'] }}
                        @endif
                    </td>

                    <td align="center">{{ $i + 1 }}.</td>
                    <td>{{ $unit['kode_unit'] }}</td>
                    <td>{{ $unit['nama_unit'] }}</td>
                </tr>
            @endforeach
        </table>
        <br />

        <div style="page-break-inside: avoid;">
        <!-- Tabel Instrumen Asesmen -->
        <table width="100%" cellpadding="5" cellspacing="0" align="center" style="font-size: 11pt;">
            <tr>
                <td class="hd-dok" style="width: 5%; border-bottom-color: #c00000;" align="center"><strong>No.</strong></td>
                <td class="hd-dok" style="border-bottom-color: #c00000;"><strong>Instrumen Asesmen</strong></td>
                <td class="hd-dok" colspan="5" align="center"><strong>Potensi Asesi **</strong></td>
            </tr>
            <tr>
                <td class="hd-dok" style="border-top-color: #c00000;"></td>
                <td class="hd-dok" style="border-top-color: #c00000;"></td>
                <td class="hd-dok" style="width: 8%;" align="center"><strong>1</strong></td>
                <td class="hd-dok" style="width: 8%;" align="center"><strong>2</strong></td>
                <td class="hd-dok" style="width: 8%;" align="center"><strong>3</strong></td>
                <td class="hd-dok" style="width: 8%;" align="center"><strong>4</strong></td>
                <td class="hd-dok" style="width: 8%;" align="center"><strong>5</strong></td>
            </tr>
            @foreach ($referensi_form[1]['referensis'] as $i => $item)
            @php $checkedCol = ($item['isdefault'] !== null) ? ($item['potensi_asesi_index'] ?? null) : null; @endphp
            <tr>
                <td align="center">{{ $i + 1 }}.</td>
                <td>{{ $item['nama'] }}</td>
                <td align="center">
                    <span class="checkbox {{ $checkedCol == 1 ? 'checked' : '' }}"></span>
                </td>
                <td align="center">
                    <span class="checkbox {{ $checkedCol == 2 ? 'checked' : '' }}"></span>
                </td>
                <td align="center">
                    <span class="checkbox {{ $checkedCol == 3 ? 'checked' : '' }}"></span>
                </td>
                <td align="center">
                    <span class="checkbox {{ $checkedCol == 4 ? 'checked' : '' }}"></span>
                </td>
                <td align="center">
                    <span class="checkbox {{ $checkedCol == 5 ? 'checked' : '' }}"></span>
                </td>
            </tr>
            @endforeach
        </table>
        </div>
        <br />
        @endforeach

        {!! $referensi_form[0]['referensis'][0]['nama'] !!}
        <br>
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
                <td>Penyusun</td>
                <td align="center">1</td>
                <td>{{ $dokumenHeader['nama_penyusun'] ?? '' }}</td>
                <td>{{ $dokumenHeader['noreg_penyusun'] ?? '' }}</td>
                <td>
                    @if($dokumenHeader['barcode_penyusun'] ?? null)
                    <img src="{{ $dokumenHeader['barcode_penyusun'] }}" width="80" height="80" /><br />
                    @endif
                    {{ $dokumenHeader['tanggal_penyusun'] ? \Carbon\Carbon::parse($dokumenHeader['tanggal_penyusun'])->locale('id')->translatedFormat('d F Y') : '' }}
                </td>
            </tr>
            <tr>
                <td>Validator</td>
                <td align="center">1</td>
                <td>{{ $dokumenHeader['nama_validator'] ?? '' }}</td>
                <td>{{ $dokumenHeader['noreg_validator'] ?? '' }}</td>
                <td>
                    @if($dokumenHeader['barcode_validator'] ?? null)
                    <img src="{{ $dokumenHeader['barcode_validator'] }}" width="80" height="80" /><br />
                    @endif
                    {{ $dokumenHeader['tanggal_validator'] ? \Carbon\Carbon::parse($dokumenHeader['tanggal_validator'])->locale('id')->translatedFormat('d F Y') : '' }}
                </td>
            </tr>
        </table>

    </body>
</html>
