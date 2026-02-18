<!doctype html>
<html lang="id">
    <head>
        <meta charset="UTF-8" />
        <title>FR.AK.06 - MENINJAU PROSES ASESMEN</title>
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
                background-color: #cc0000;
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
                    FR.AK.06. MENINJAU PROSES ASESMEN<br />
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
                <td rowspan="2" style="width: 30%; vertical-align: top;">Skema Sertifikasi<br />(KKNI/Okupasi/Klaster)</td>
                <td style="width: 12%;">Judul</td>
                <td>{{ $jabatan_kerja }}</td>
            </tr>
            <tr>
                <td>Nomor</td>
                <td>{{ $nomor_skema }}</td>
            </tr>
            <tr>
                <td>TUK</td>
                <td colspan="2">{{ $tuk }}</td>
            </tr>
            @if(count($asesor_list) > 1)
                @foreach($asesor_list as $idx => $asesor)
                <tr>
                    <td>Nama Asesor {{ $idx + 1 }}</td>
                    <td colspan="2">{{ strtoupper($asesor->nama) }}@if($asesor->noreg) ({{ $asesor->noreg }})@endif</td>
                </tr>
                @endforeach
            @else
            <tr>
                <td>Nama Asesor</td>
                <td colspan="2">{{ strtoupper($asesor_list[0]->nama ?? '') }}@if($asesor_list[0]->noreg ?? '') ({{ $asesor_list[0]->noreg }})@endif</td>
            </tr>
            @endif
            <tr>
                <td>Tanggal</td>
                <td colspan="2">{{ $tanggal }}</td>
            </tr>
        </table>
        <br />
        <!-- PENJELASAN Table -->
        <table
            width="100%"
            cellpadding="5"
            bgcolor="#fff"
            cellspacing="0"
            align="center"
            style="border: 1px solid #000;"
        >
            <tr class="hd-dok">
                <th style="font-weight: bold; text-align: left;">Penjelasan:</th>
            </tr>
            <tr>
                <td>
                    1. Peninjauan dapat dilakukan oleh lead asesor atau asesor yang melaksanakan asesmen.<br />
                    2. Peninjauan dapat dilakukan secara terpadu dalam skema sertifikasi dan / atau peserta kelompok yang homogen.<br />
                    3. Isilah pemenuhan dimensi kompetensi dengan menulis kode rekaman formulir yang membuktikan terpenuhinya dimensi kompetensi.
                </td>
            </tr>
        </table>
        <br />
        <!-- KONSEP ASESMEN Table -->
        <table
            width="100%"
            cellpadding="5"
            bgcolor="#fff"
            cellspacing="0"
            align="center"
            style="border: 1px solid #000;"
        >
            <tr class="hd-dok" style="font-weight: bold; text-align: center;">
                <th rowspan="2">Aspek yang ditinjau</th>
                <th colspan="4">Kesesuaian dengan prinsip asesmen</th>
            </tr>
            <tr class="hd-dok" style="font-weight: bold; text-align: center; font-style: italic;">
                <th>Validitas</th>
                <th>Reliabel</th>
                <th>Fleksibel</th>
                <th>Adil</th>
            </tr>
            @foreach($aspek_items as $aspek)
            <tr>
                <td>
                    @if($aspek->nama == 'Prosedur asesmen')
                    Prosedur asesmen:<br />
                    @endif
                    • {{ $aspek->nama }}
                </td>
                <td style="text-align: center;">
                    <span class="checkbox @if($aspek->validitas) checked @endif"></span>
                </td>
                <td style="text-align: center;">
                    @if($aspek->nama != 'Keputusan asesmen' && $aspek->nama != 'Umpan balik asesmen')
                    <span class="checkbox @if($aspek->reliabel) checked @endif"></span>
                    @else
                    &nbsp;
                    @endif
                </td>
                <td style="text-align: center;">
                    @if($aspek->nama != 'Keputusan asesmen' && $aspek->nama != 'Umpan balik asesmen')
                    <span class="checkbox @if($aspek->fleksibel) checked @endif"></span>
                    @else
                    &nbsp;
                    @endif
                </td>
                <td style="text-align: center;">
                    <span class="checkbox @if($aspek->adil) checked @endif"></span>
                </td>
            </tr>
            @endforeach
            <tr>
                <td colspan="5">
                    <b>Rekomendasi untuk peningkatan</b><br />
                    {{ $rekomendasi_prinsip }}
                </td>
            </tr>
        </table>
        <br />
        <!-- DIMENSI KOMPETENSI Table -->
        <table
            width="100%"
            cellpadding="5"
            bgcolor="#fff"
            cellspacing="0"
            align="center"
            style="border: 1px solid #000;"
        >
            <tr class="hd-dok" style="font-weight: bold; text-align: center;">
                <th rowspan="2">Aspek yang ditinjau</th>
                <th colspan="5">Pemenuhan dimensi kompetensi</th>
            </tr>
            <tr class="hd-dok" style="font-weight: bold; text-align: center; font-style: italic;">
                <th>Task Skills</th>
                <th>Task Management Skills</th>
                <th>Contingency Management Skills</th>
                <th>Job Role/Environment Skills</th>
                <th>Transfer Skills</th>
            </tr>
            <tr>
                <td>
                    <b>Konsistensi keputusan asesmen</b><br />
                    Bukti dari berbagai asesmen diperiksa untuk konsistensi dimensi kompetensi
                </td>
                <td style="text-align: center;">L/DIT<br/> T/DPT</td>
                <td style="text-align: center;">L/DIT<br/> T/DPT</td>
                <td style="text-align: center;">L/DIT<br/> T/DPT</td>
                <td style="text-align: center;">L/DIT<br/> T/DPT</td>
                <td style="text-align: center;">L/DIT<br/> T/DPT</td>
            </tr>
            <tr>
                <td colspan="6">
                    <b>Rekomendasi untuk peningkatan:</b><br />
                    {{ $rekomendasi_dimensi }}
                </td>
            </tr>
        </table>
        <br />
        <!-- TANDA TANGAN Table -->
        <table
            width="100%"
            cellpadding="5"
            bgcolor="#fff"
            cellspacing="0"
            align="center"
            style="border: 1px solid #000;"
        >
            <tr>
                <td style="width: 33%;">Nama Lead Asesor/Asesor</td>
                <td style="width: 33%;">Tanggal Tanda Tangan</td>
                <td style="width: 34%;">Komentar</td>
            </tr>
            @foreach($asesor_list as $idx => $asesor)
            @php $asesor_key = 'asesor' . ($idx + 1); @endphp
            <tr>
                <td style="height: 100px; vertical-align: top;">
                    {{ strtoupper($asesor->nama) }}
                </td>
                <td style="vertical-align: top; text-align: center;">
                    @if($barcodes[$asesor_key]->url ?? null)
                    <div>
                        <img src="{{ $barcodes[$asesor_key]->url }}" style="width: 80px; height: 80px;" />
                        <div style="font-size: 11px; margin-top: 4px;">
                            {{ $barcodes[$asesor_key]->tanggal ? \Carbon\Carbon::parse($barcodes[$asesor_key]->tanggal)->translatedFormat('d F Y') : '' }}
                        </div>
                    </div>
                    @else
                    <div style="min-height: 80px;"></div>
                    @endif
                </td>
                <td>{{ $asesor->komentar ?? '' }}</td>
            </tr>
            @endforeach
        </table>
    </body>
</html>
