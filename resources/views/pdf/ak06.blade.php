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
                margin-top: 0;
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

            .checkbox {
                width: 12px;
                height: 12px;
                border: 1px solid #000;
                display: inline-block;
                text-align: center;
                line-height: 11px;
                font-size: 15px;
                font-family: "DejaVu Sans", sans-serif;
            }

            .checked::after {
                content: "✔";
            }
        </style>
    </head>
    <body>
        <table width=100% align=center class="noborder headera" cellpadding=5 cellspacing=0>
            <tr>
                <td class=noborder><img src="data:image/png;base64,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"/>
                </td>
                <td align=right class=noborder>
                    <img src="data:image/png;base64,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"/>
                </td>
            </tr>
        </table><br>
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
                <td rowspan="2" style="width: 30%; vertical-align: top;">Skema Sertifikasi<br />(<s>KKNI</s>/Okupasi/<s>Klaster</s>)</td>
                <td style="width: 12%;">Judul</td>
                <td>{{ $dokumenHeader['jabatan_kerja'] }}</td>
            </tr>
            <tr>
                <td>Nomor</td>
                <td>{{ $dokumenHeader['nomor_skema'] }}</td>
            </tr>
            <tr>
                <td>TUK</td>
                <td colspan="2">{{ $dokumenHeader['tuk'] }}</td>
            </tr>
            @if($dokumenHeader['asesor_2'])
                <tr>
                    <td>Nama Asesor 1</td>
                    <td colspan="2">{{ strtoupper($dokumenHeader['asesor_1']) }}@if($dokumenHeader['noreg_asesor_1']) ({{ $dokumenHeader['noreg_asesor_1'] }})@endif</td>
                </tr>
                <tr>
                    <td>Nama Asesor 2</td>
                    <td colspan="2">{{ strtoupper($dokumenHeader['asesor_2']) }}@if($dokumenHeader['noreg_asesor_2']) ({{ $dokumenHeader['noreg_asesor_2'] }})@endif</td>
                </tr>
            @else
            <tr>
                <td>Nama Asesor</td>
                <td colspan="2">{{ strtoupper($dokumenHeader['asesor_1']) }}@if($dokumenHeader['noreg_asesor_1']) ({{ $dokumenHeader['noreg_asesor_1'] }})@endif</td>
            </tr>
            @endif
            <tr>
                <td>Tanggal</td>
                <td colspan="2">{{ \Carbon\Carbon::parse($dokumenHeader['tanggal_uji'])->locale('id')->translatedFormat('d F Y')}}</td>
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
            @foreach($aspek as $item)
            <tr>
                <td>
                    @if($item['nama'] == 'Prosedur asesmen')
                    Prosedur asesmen:<br />
                    @endif
                    • {{ $item['nama'] }}
                </td>
                <td style="text-align: center;">
                    <span class="checkbox @if($item['validitas']) checked @endif"></span>
                </td>
                <td style="text-align: center;">
                    <span class="checkbox @if($item['reliabel']) checked @endif"></span>
                </td>
                <td style="text-align: center;">
                    @if($item['nama'] != 'Keputusan asesmen' && $item['nama'] != 'Umpan balik asesmen')
                    <span class="checkbox @if($item['fleksibel']) checked @endif"></span>
                    @else
                    &nbsp;
                    @endif
                </td>
                <td style="text-align: center;">
                    <span class="checkbox @if($item['adil']) checked @endif"></span>
                </td>
            </tr>
            @endforeach
            <tr>
                <td colspan="5">
                    <b>Rekomendasi untuk peningkatan</b><br />
                    {{ $feedback['rekomendasi1'] }}
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
                <td style="text-align: center;">{!! $dimensi_kompetensi['task_skills'] !!}</td>
                <td style="text-align: center;">{!! $dimensi_kompetensi['task_management_skills'] !!}</td>
                <td style="text-align: center;">{!! $dimensi_kompetensi['contingency_management_skills'] !!}</td>
                <td style="text-align: center;">{!! $dimensi_kompetensi['job_role_environment_skills'] !!}</td>
                <td style="text-align: center;">{!! $dimensi_kompetensi['transfer_skills'] !!}</td>
            </tr>
            <tr>
                <td colspan="6">
                    <b>Rekomendasi untuk peningkatan:</b><br />
                    {{ $feedback['rekomendasi2'] }}
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
            @if ($dokumenHeader['asesor_2'])
            <tr>
                <td style="height: 100px; vertical-align: top;">
                    {{ strtoupper($dokumenHeader['asesor_1']) }}
                </td>
                <td style="vertical-align: top; text-align: center;">
                    @if($barcodes['asesor1']['url'] ?? null)
                    <div>
                        <img src="{{ $barcodes['asesor1']['url'] }}" style="width: 80px; height: 80px;" />
                        <div style="font-size: 11px; margin-top: 4px;">
                            {{ $barcodes['asesor1']['tanggal'] ? \Carbon\Carbon::parse($barcodes['asesor1']['tanggal'])->locale('id')->translatedFormat('d F Y') : '' }}
                        </div>
                    </div>
                    @else
                    <div style="min-height: 80px;"></div>
                    @endif
                </td>
                <td>{{ $feedback['catatan_asesor1'] ?? '' }}</td>
            </tr>
            <tr>
                <td style="height: 100px; vertical-align: top;">
                    {{ strtoupper($dokumenHeader['asesor_2']) }}
                </td>
                <td style="vertical-align: top; text-align: center;">
                    @if($barcodes['asesor2']['url'] ?? null)
                    <div>
                        <img src="{{ $barcodes['asesor2']['url'] }}" style="width: 80px; height: 80px;" />
                        <div style="font-size: 11px; margin-top: 4px;">
                            {{ $barcodes['asesor2']['tanggal'] ? \Carbon\Carbon::parse($barcodes['asesor2']['tanggal'])->locale('id')->translatedFormat('d F Y') : '' }}
                        </div>
                    </div>
                    @else
                    <div style="min-height: 80px;"></div>
                    @endif
                </td>
                <td>{{ $feedback['catatan_asesor2'] ?? '' }}</td>
            </tr>
            @else
            <tr>
                <td style="height: 100px; vertical-align: top;">
                    {{ strtoupper($dokumenHeader['asesor_1']) }}
                </td>
                <td style="vertical-align: top; text-align: center;">
                    @if($barcodes['asesor1']['url'] ?? null)
                    <div>
                        <img src="{{ $barcodes['asesor1']['url'] }}" style="width: 80px; height: 80px;" />
                        <div style="font-size: 11px; margin-top: 4px;">
                            {{ $barcodes['asesor1']['tanggal'] ? \Carbon\Carbon::parse($barcodes['asesor1']['tanggal'])->locale('id')->translatedFormat('d F Y') : '' }}
                        </div>
                    </div>
                    @else
                    <div style="min-height: 80px;"></div>
                    @endif
                </td>
                <td>{{ $feedback['catatan_asesor1'] ?? '' }}</td>
            </tr>
            @endif
        </table>
    </body>
</html>
