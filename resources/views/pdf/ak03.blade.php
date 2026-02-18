<!doctype html>
<html lang="id">
    <head>
        <meta charset="UTF-8" />
        <title>FR.AK.03 - UMPAN BALIK ASESI</title>
        <style>
            @font-face {
                font-family: "Open Sans";
                font-style: normal;
                font-weight: normal;
                src: url(".media_url()."fonts/cJZKeOuBrn4kERxqtaUH3aCWcynf_cDxXwCLxiixG1c)
                    format("truetype");
            }
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
                background-color: #d10000;
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
                <td class="judul noborder" style="font-weight: bold; font-size: 18px;">
                    FR.AK.03 UMPAN BALIK ASESI<br />
                </td>
            </tr>
        </table>
        <br />
        <p style="font-size: 13px;">
            Umpan balik dari Asesi (diisi oleh Asesi setelah pengambilan keputusan) :
        </p>
        <!-- UM PAN BALIK Table -->
        <table
            width="100%"
            cellpadding="5"
            bgcolor="#fff"
            cellspacing="0"
            align="center"
            style="border: 2px solid #000;"
        >
            <tr class="hd-dok" style="font-weight: bold; text-align: center;">
                <th rowspan="2" style="width: 55%;">KOMPONEN</th>
                <th colspan="2">Hasil</th>
                <th rowspan="2" style="width: 25%;">Catatan/Komentar Asesi</th>
            </tr>
            <tr class="hd-dok" style="font-weight: bold; text-align: center;">
                <th style="width: 50px;">Ya</th>
                <th style="width: 50px;">Tidak</th>
            </tr>
            @foreach($feedback_items as $item)
            <tr>
                <td>{{ $item->pertanyaan }}</td>
                <td style="text-align: center;">
                    <span class="checkbox @if($item->ya) checked @endif"></span>
                </td>
                <td style="text-align: center;">
                    <span class="checkbox @if($item->tidak) checked @endif"></span>
                </td>
                <td>{{ $item->catatan }}</td>
            </tr>
            @endforeach
            <tr>
                <td style="width: 20%; font-weight: bold;">Catatan :</td>
                <td colspan="3">{{ $catatan_umum }}</td>
            </tr>
        </table>
    </body>
</html>
