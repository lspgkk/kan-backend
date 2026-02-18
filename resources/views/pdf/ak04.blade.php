<!doctype html>
<html lang="id">
    <head>
        <meta charset="UTF-8" />
        <title>FR.AK.04 - BANDING ASESMEN</title>
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
                background-color: #d60000;
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
                <td class="judul noborder" style="font-weight: bold; font-size: 14px; text-transform: uppercase;">
                    FR.AK.04 BANDING ASESMEN<br />
                </td>
            </tr>
        </table>
        <br />
        <table
            width="100%"
            cellpadding="5"
            bgcolor="#fff"
            cellspacing="0"
            align="center"
        >
            <tr>
                <td style="width: 25%;">Nama Asesi</td>
                <td colspan="3">: {{ strtoupper($nama_asesi) }}</td>
            </tr>
            @if(count($asesor_list) > 1)
                @foreach($asesor_list as $idx => $asesor)
                <tr>
                    <td>Nama Asesor {{ $idx + 1 }}</td>
                    <td colspan="3">: {{ strtoupper($asesor->nama) }}</td>
                </tr>
                @endforeach
            @else
            <tr>
                <td>Nama Asesor</td>
                <td colspan="3">: {{ strtoupper($asesor_list[0]->nama ?? '') }}</td>
            </tr>
            @endif
            <tr>
                <td>Tanggal Asesmen</td>
                <td colspan="3">: {{ $tanggal_asesmen }}</td>
            </tr>
        </table>
        <br />
        <table
            width="100%"
            cellpadding="5"
            bgcolor="#fff"
            cellspacing="0"
            align="center"
        >
            <tr>
                <td colspan="2" style="font-weight: bold;">
                    Jawablah dengan Ya atau Tidak pertanyaan-pertanyaan berikut ini :
                </td>
                <td style="width: 10%; font-weight: bold; text-align: center;">YA</td>
                <td style="width: 10%; font-weight: bold; text-align: center;">TIDAK</td>
            </tr>
            @foreach($pertanyaan_list as $pertanyaan)
            <tr>
                <td colspan="2">{{ $pertanyaan->nama }}</td>
                <td style="text-align: center;">
                    <span class="checkbox @if($pertanyaan->jawaban === true) checked @endif"></span>
                </td>
                <td style="text-align: center;">
                    <span class="checkbox @if($pertanyaan->jawaban === false) checked @endif"></span>
                </td>
            </tr>
            @endforeach
            <tr>
                <td colspan="4">
                    Banding ini diajukan atas Keputusan Asesmen yang dibuat terhadap Skema Sertifikasi (KKNI/Okupasi/Klaster) berikut :
                    <br /><br />
                    Skema Sertifikasi : {{ strtoupper($skema_judul) }}<br />
                    No. Skema Sertifikasi : {{ strtoupper($skema_nomor) }}
                </td>
            </tr>
            <tr>
                <td colspan="4" style="vertical-align: top;">
                    <div style="margin-bottom: 8px;">Banding ini diajukan atas alasan sebagai berikut :</div>
                    <div style="min-height: 70px; border: 1px solid #ccc; padding: 8px;">
                        {{ $alasan_banding }}
                    </div>
                </td>
            </tr>
            <tr>
                <td colspan="4">
                    Anda mempunyai hak mengajukan banding jika Anda menilai proses asesmen tidak sesuai SOP dan tidak memenuhi Prinsip Asesmen.
                </td>
            </tr>
            <tr>
                <td colspan="4" style="height: 80px;">
                    Tanda tangan Asesi : {{ strtoupper($nama_asesi) }}<br /><br />
                    Tanggal : {{ $tanggal_asesmen }}
                </td>
            </tr>
        </table>
    </body>
</html>
