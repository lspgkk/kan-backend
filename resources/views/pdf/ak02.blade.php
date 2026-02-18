<!doctype html>
<html lang="id">
    <head>
        <meta charset="UTF-8" />
        <title>FR.AK.02 - FRAGEMEN ANTARA ASESOR</title>
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
                    FR.AK.02 &nbsp;&nbsp; FRAGEMEN ANTARA ASESOR<br />
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
            style="border: 2px solid #000;"
        >
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
        <!-- MATRIKS KOMPETENSI Table -->
        <table
            width="100%"
            cellpadding="5"
            bgcolor="#fff"
            cellspacing="0"
            align="center"
            style="border: 2px solid #000;"
        >
            <tr class="hd-dok" style="font-weight: bold; text-align: center;">
                <th style="width: 30%;">Unit kompetensi</th>
                <th>Observasi demonstrasi</th>
                <th>Portofolio</th>
                <th>Pertanyaan wawancara</th>
                <th>Pertanyaan lisan</th>
                <th>Pertanyaan tertulis</th>
                <th>Proyek kerja</th>
            </tr>
            @foreach($unit_kompetensi as $unit)
            <tr>
                <td>
                    {{ $unit->kode }}<br />
                    {{ $unit->nama }}
                </td>
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
            </tr>
            @endforeach
            <tr>
                <td style="font-weight: bold;">Rekomendasi hasil asesmen</td>
                <td colspan="6" style="text-align: center;">
                    <span class="checkbox @if($is_kompeten === true) checked @endif"></span> Kompeten
                    &nbsp;&nbsp;&nbsp;&nbsp;
                    <span class="checkbox @if($is_kompeten === false) checked @endif"></span> Belum kompeten
                </td>
            </tr>
            <tr>
                <td>
                    <b>Tindak lanjut yang dibutuhkan</b><br />
                    <span style="font-size: 13px;">(Masukkan pekerjaan tambahan dan asesmen yang diperlukan untuk mencapai kompetensi)</span>
                </td>
                <td colspan="6">{{ $tindak_lanjut }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">Komentar / Observasi oleh asesor</td>
                <td colspan="6">{{ $komentar }}</td>
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
            style="border: 2px solid #000;"
        >
            <tr>
                <td colspan="3" style="font-weight: bold;">Asesi :</td>
            </tr>
            <tr>
                <td style="width: 30%;">Nama</td>
                <td style="width: 3%;">:</td>
                <td style="text-transform: uppercase;">{{ $nama_asesi }}</td>
            </tr>
            <tr>
                <td>Tanda tangan dan tanggal</td>
                <td>:</td>
                <td style="height: 60px; text-align: center; vertical-align: middle;">
                    @if($barcodes['asesi']->url ?? null)
                    <img src="{{ $barcodes['asesi']->url }}" style="height: 50px; width: 50px; object-fit: contain;" /><br />
                    <span style="font-size: 11px;">{{ $barcodes['asesi']->tanggal ? \Carbon\Carbon::parse($barcodes['asesi']->tanggal)->translatedFormat('d F Y') : '' }}</span>
                    @endif
                </td>
            </tr>
            @foreach($asesor_list as $idx => $asesor)
            @php $asesor_key = 'asesor' . ($idx + 1); @endphp
            <tr>
                <td colspan="3" style="font-weight: bold;">@if($idx > 0) Asesor @endif</td>
            </tr>
            <tr>
                <td>Nama Asesor @if(count($asesor_list) > 1) {{ $idx + 1 }} @endif</td>
                <td>:</td>
                <td style="text-transform: uppercase;">{{ $asesor->nama }}</td>
            </tr>
            <tr>
                <td>No. Reg @if(count($asesor_list) > 1) {{ $idx + 1 }} @endif</td>
                <td>:</td>
                <td>{{ $asesor->noreg ?? '' }}</td>
            </tr>
            <tr>
                <td>Tanda tangan dan tanggal</td>
                <td>:</td>
                <td style="height: 60px; text-align: center; vertical-align: middle;">
                    @if($barcodes[$asesor_key]->url ?? null)
                    <img src="{{ $barcodes[$asesor_key]->url }}" style="height: 50px; width: 50px; object-fit: contain;" /><br />
                    <span style="font-size: 11px;">{{ $barcodes[$asesor_key]->tanggal ? \Carbon\Carbon::parse($barcodes[$asesor_key]->tanggal)->translatedFormat('d F Y') : '' }}</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </table>
        <br />
        <!-- LAMPIRAN DOKUMEN -->
        <div style="font-size: 13px;">
            <b>LAMPIRAN DOKUMEN:</b><br />
            1. Dokumen APL 01 peserta<br />
            2. Dokumen APL 02 peserta<br />
            3. Bukti-bukti berkualitas peserta<br />
            4. Tinjauan proses asesmen
        </div>
    </body>
</html>
