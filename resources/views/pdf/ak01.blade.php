<!doctype html>
<html lang="id">
    <head>
        <meta charset="UTF-8" />
        <title>FR.AK.01 - PERSETUJUAN ASESMEN</title>
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
                <td class="judul noborder" style="font-weight: bold; font-size: 14px;">
                    FR.AK.01 - PERSETUJUAN ASESMEN<br />
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
                <td colspan="4">
                    Persetujuan Asesmen ini untuk menjamin bahwa Asesi telah diberi arahan
                    secara rinci tentang perencanaan dan proses asesmen
                </td>
            </tr>
            <tr>
                <td rowspan="2" style="width: 30%; vertical-align: top; font-weight: bold;">
                    Skema Sertifikasi<br />(KKNI/Okupasi/Klaster)
                </td>
                <td style="width: 10%; font-weight: bold;">Judul</td>
                <td style="width: 3%; text-align: center; font-weight: bold;">:</td>
                <td>{{ $skema_judul }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Nomor</td>
                <td style="text-align: center; font-weight: bold;">:</td>
                <td>{{ $skema_nomor }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">TUK</td>
                <td style="text-align: center; font-weight: bold;">:</td>
                <td colspan="2">{{ $tuk }}</td>
            </tr>
            @if(count($asesor_list) > 1)
                @foreach($asesor_list as $idx => $asesor)
                <tr>
                    <td style="font-weight: bold;">Nama Asesor {{ $idx + 1 }}</td>
                    <td style="text-align: center; font-weight: bold;">:</td>
                    <td colspan="2">{{ strtoupper($asesor->nama) }}@if($asesor->noreg) ({{ $asesor->noreg }})@endif</td>
                </tr>
                @endforeach
            @else
            <tr>
                <td style="font-weight: bold;">Nama Asesor</td>
                <td style="text-align: center; font-weight: bold;">:</td>
                <td colspan="2">{{ strtoupper($asesor_list[0]->nama ?? '') }}@if($asesor_list[0]->noreg ?? '') ({{ $asesor_list[0]->noreg }})@endif</td>
            </tr>
            @endif
            <tr>
                <td style="font-weight: bold;">Nama Asesi</td>
                <td style="text-align: center; font-weight: bold;">:</td>
                <td colspan="2">{{ strtoupper($nama_asesi) }}</td>
            </tr>
            <tr>
                <td style="vertical-align: top; font-weight: bold;">
                    Bukti yang akan dikumpulkan :
                </td>
                <td colspan="3">
                    <table width="100%" cellspacing="0" cellpadding="2">
                        @foreach($bukti_list as $idx => $bukti)
                        @if($idx % 2 == 0)
                        <tr>
                            <td style="border: none;">
                                <span class="checkbox @if(in_array($bukti->id, $bukti_dikumpulkan)) checked @endif"></span>
                                {{ $bukti->nama }}
                            </td>
                            @if(isset($bukti_list[$idx + 1]))
                            <td style="border: none;">
                                <span class="checkbox @if(in_array($bukti_list[$idx + 1]->id, $bukti_dikumpulkan)) checked @endif"></span>
                                {{ $bukti_list[$idx + 1]->nama }}
                            </td>
                            @else
                            <td style="border: none;"></td>
                            @endif
                        </tr>
                        @endif
                        @endforeach
                    </table>
                </td>
            </tr>
            <tr>
                <td rowspan="3" style="vertical-align: top; font-weight: bold;">
                    Pelaksanaan asesmen disepakati pada:
                </td>
                <td style="font-weight: bold;">Hari / Tanggal</td>
                <td style="text-align: center; font-weight: bold;">:</td>
                <td>{{ $hari_tanggal }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Waktu</td>
                <td style="text-align: center; font-weight: bold;">:</td>
                <td>{{ $waktu }} - Selesai</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">TUK</td>
                <td style="text-align: center; font-weight: bold;">:</td>
                <td>{{ $tuk }}</td>
            </tr>
            <tr>
                <td colspan="4">
                    <span style="font-weight: bold;">Asesor :</span><br /><br />
                    Menyatakan tidak akan membuka hasil pekerjaan yang saya peroleh karena
                    penugasan saya sebagai Asesor dalam pekerjaan Asesmen kepada siapapun
                    atau organisasi apapun selain kepada pihak yang berwenang sehubungan
                    dengan kewajiban saya sebagai Asesor yang ditugaskan oleh LSP.
                </td>
            </tr>
            <tr>
                <td colspan="4">
                    <span style="font-weight: bold;">Asesi :</span><br /><br />
                    Saya setuju mengikuti asesmen dengan pemahaman bahwa informasi yang
                    dikumpulkan hanya digunakan untuk pengembangan profesional dan hanya
                    dapat diakses oleh orang tertentu saja.
                </td>
            </tr>
            @if($barcodes['asesor2']->url ?? null)
            <tr>
                <td colspan="2" style="height: 70px; vertical-align: bottom; text-align: center;">
                    Tanda tangan Asesor 1 :<br />
                    @if($barcodes['asesor1']->url ?? null)
                    <img src="{{ $barcodes['asesor1']->url }}" style="height: 50px; width: 50px; object-fit: contain;" /><br />
                    <span style="font-size: 11px; font-weight: bold;">{{ strtoupper($barcodes['asesor1']->nama ?? '') }}</span><br />
                    <span style="font-size: 10px;">{{ $barcodes['asesor1']->tanggal ? \Carbon\Carbon::parse($barcodes['asesor1']->tanggal)->translatedFormat('d F Y') : '' }}</span>
                    @else
                    .............................................
                    @endif
                </td>
                <td colspan="2" style="height: 70px; vertical-align: bottom; text-align: center;">
                    Tanda tangan Asesor 2 :<br />
                    @if($barcodes['asesor2']->url ?? null)
                    <img src="{{ $barcodes['asesor2']->url }}" style="height: 50px; width: 50px; object-fit: contain;" /><br />
                    <span style="font-size: 11px; font-weight: bold;">{{ strtoupper($barcodes['asesor2']->nama ?? '') }}</span><br />
                    <span style="font-size: 10px;">{{ $barcodes['asesor2']->tanggal ? \Carbon\Carbon::parse($barcodes['asesor2']->tanggal)->translatedFormat('d F Y') : '' }}</span>
                    @else
                    .............................................
                    @endif
                </td>
            </tr>
            <tr>
                <td colspan="4" style="height: 70px; vertical-align: bottom; text-align: center;">
                    Tanda tangan Asesi :<br />
                    @if($barcodes['asesi']->url ?? null)
                    <img src="{{ $barcodes['asesi']->url }}" style="height: 50px; width: 50px; object-fit: contain;" /><br />
                    <span style="font-size: 11px; font-weight: bold;">{{ strtoupper($barcodes['asesi']->nama ?? '') }}</span><br />
                    <span style="font-size: 10px;">{{ $barcodes['asesi']->tanggal ? \Carbon\Carbon::parse($barcodes['asesi']->tanggal)->translatedFormat('d F Y') : '' }}</span>
                    @else
                    ..............................................
                    @endif
                </td>
            </tr>
            
        </table>
        <p style="font-size: 12px;">* Coret yang tidak perlu</p>
    </body>
</html>
