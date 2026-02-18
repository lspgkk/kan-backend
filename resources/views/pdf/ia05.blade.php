<!doctype html>
<html lang="id">
    <head>
        <meta charset="UTF-8" />
        <title>FR.IA.05 - LEMBAR JAWABAN PERTANYAAN TERTULIS PILIHAN GANDA</title>
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
            .hd-dok-b {
                background-color: #d58a94;
                color: #000;
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

            .radio {
                width: 12px;
                height: 12px;
                border: 1px solid #000;
                border-radius: 50%;
                display: inline-block;
                text-align: center;
                line-height: 10px;
                font-size: 10px;
            }

            .radio.checked::after {
                content: "•";
                font-size: 14px;
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
                    FR.IA.05 LEMBAR JAWABAN PERTANYAAN TERTULIS PILIHAN GANDA
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
                <td style="width: 3%; text-align: center;">:</td>
                <td style="text-transform: uppercase;">{{ $jabatan_kerja }}</td>
            </tr>
            <tr>
                <td>Nomor</td>
                <td style="text-align: center;">:</td>
                <td style="text-transform: uppercase;">{{ $nomor_skema }}</td>
            </tr>
            <tr>
                <td>TUK</td>
                <td style="text-align: center;">:</td>
                <td colspan="2" style="text-transform: uppercase;">{{ $tuk }}</td>
            </tr>
            @if(count($asesor_list) > 1)
                @foreach($asesor_list as $idx => $asesor)
                <tr>
                    <td>Nama Asesor {{ $idx + 1 }}</td>
                    <td style="text-align: center;">:</td>
                    <td colspan="2">{{ strtoupper($asesor->nama) }}</td>
                </tr>
                @endforeach
            @else
            <tr>
                <td>Nama Asesor</td>
                <td style="text-align: center;">:</td>
                <td colspan="2">{{ strtoupper($asesor_list[0]->nama ?? '') }}</td>
            </tr>
            @endif
            <tr>
                <td>Nama Asesi</td>
                <td style="text-align: center;">:</td>
                <td colspan="2" style="text-transform: uppercase;">{{ $nama_asesi }}</td>
            </tr>
            <tr>
                <td>Tanggal</td>
                <td style="text-align: center;">:</td>
                <td colspan="2">{{ $tanggal }}</td>
            </tr>
            <tr>
                <td>Waktu</td>
                <td style="text-align: center;">:</td>
                <td colspan="2">{{ $waktu ?? '60 menit' }}</td>
            </tr>
        </table>
        <p style="font-size: 12px;">*Coret yang tidak perlu</p>

        <!-- SOAL Table -->
        <table
            width="100%"
            cellpadding="5"
            bgcolor="#fff"
            cellspacing="0"
            align="center"
            style="border: 2px solid #000;"
        >
            @foreach($soal_list as $soal)
            <tr class="hd-dok-b">
                <td style="width: 100px; text-align: center; font-weight: bold;">
                    {{ $soal->unit_kode }}<br />{{ $soal->kuk_kode }}
                </td>
                <td colspan="2" style="font-weight: bold;">
                    SOAL, Pilih Jawaban semua pertanyaan berikut (A / B / C / D) :
                </td>
            </tr>
            <tr>
                <td style="background: #d58a94; text-align: center; font-weight: bold;">{{ $soal->unit_kode }}<br />{{ $soal->kuk_kode }}</td>
                <td style="width: 40px; text-align: center;">{{ $soal->no }}.</td>
                <td>{{ $soal->soal }}</td>
            </tr>
            <tr>
                <td></td>
                <td></td>
                <td>
                    <span class="radio @if($soal->jawaban_asesi === 'A') checked @endif"></span>
                    a. {{ $soal->jawab_a }}
                </td>
            </tr>
            <tr>
                <td></td>
                <td></td>
                <td>
                    <span class="radio @if($soal->jawaban_asesi === 'B') checked @endif"></span>
                    b. {{ $soal->jawab_b }}
                </td>
            </tr>
            <tr>
                <td></td>
                <td></td>
                <td>
                    <span class="radio @if($soal->jawaban_asesi === 'C') checked @endif"></span>
                    c. {{ $soal->jawab_c }}
                </td>
            </tr>
            <tr>
                <td></td>
                <td></td>
                <td>
                    <span class="radio @if($soal->jawaban_asesi === 'D') checked @endif"></span>
                    d. {{ $soal->jawab_d }}
                </td>
            </tr>
            @endforeach
        </table>
        <br />

        <table
            width="100%"
            cellpadding="5"
            bgcolor="#fff"
            cellspacing="0"
            align="center"
            style="border: 1px solid #000;"
        >
            <tr>
                <td style="width: 30%; vertical-align: top;">Umpan Balik: <br/>{{ $umpan_balik ?? '-' }}</td>
                    
            </tr>
        </table>
        <br />

        <h2 style="font-size: 14px; font-weight: bold;">PENYUSUN DAN VALIDATOR</h2>
        <table
            width="100%"
            cellpadding="5"
            bgcolor="#fff"
            cellspacing="0"
            align="center"
            style="border: 1px solid #000;"
        >
            <tr style="background: #f0f0f0; font-weight: bold; text-align: center;">
                <td style="width: 15%;">Status</td>
                <td style="width: 8%;">No</td>
                <td>Nama</td>
                <td style="width: 20%;">Nomor MET</td>
                <td style="width: 20%;">Tanda Tangan Dan Tanggal</td>
            </tr>
            <tr style="font-weight: bold;">
                <td rowspan="2">Penyusun</td>
                <td style="text-align: center;">1</td>
                <td></td>
                <td></td>
                <td style="height: 50px;"></td>
            </tr>
            <tr>
                <td style="text-align: center;">2</td>
                <td></td>
                <td></td>
                <td style="height: 50px;"></td>
            </tr>
            <tr style="font-weight: bold;">
                <td rowspan="2">Validator</td>
                <td style="text-align: center;">1</td>
                <td></td>
                <td></td>
                <td style="height: 50px;"></td>
            </tr>
            <tr>
                <td style="text-align: center;">2</td>
                <td></td>
                <td></td>
                <td style="height: 50px;"></td>
            </tr>
        </table>

        <!-- LEMBAR JAWABAN (ASESOR VIEW) -->
        <h2 style="font-size: 14px; font-weight: bold;">FR.05.C. LEMBAR JAWABAN PERTANYAAN TERTULIS PILIHAN GANDA</h2>
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
                <td style="width: 3%; text-align: center;">:</td>
                <td style="text-transform: uppercase;">{{ $jabatan_kerja }}</td>
            </tr>
            <tr>
                <td>Nomor</td>
                <td style="text-align: center;">:</td>
                <td style="text-transform: uppercase;">{{ $nomor_skema }}</td>
            </tr>
            <tr>
                <td>TUK</td>
                <td style="text-align: center;">:</td>
                <td colspan="2" style="text-transform: uppercase;">{{ $tuk }}</td>
            </tr>
            @if(count($asesor_list) > 1)
                @foreach($asesor_list as $idx => $asesor)
                <tr>
                    <td>Nama Asesor {{ $idx + 1 }}</td>
                    <td style="text-align: center;">:</td>
                    <td colspan="2">{{ strtoupper($asesor->nama) }}</td>
                </tr>
                @endforeach
            @else
            <tr>
                <td>Nama Asesor</td>
                <td style="text-align: center;">:</td>
                <td colspan="2">{{ strtoupper($asesor_list[0]->nama ?? '') }}</td>
            </tr>
            @endif
            <tr>
                <td>Nama Asesi</td>
                <td style="text-align: center;">:</td>
                <td colspan="2" style="text-transform: uppercase;">{{ $nama_asesi }}</td>
            </tr>
            <tr>
                <td>Tanggal</td>
                <td style="text-align: center;">:</td>
                <td colspan="2">{{ $tanggal }}</td>
            </tr>
            <tr>
                <td>Waktu</td>
                <td style="text-align: center;">:</td>
                <td colspan="2">{{ $waktu ?? '60 menit' }}</td>
            </tr>
        </table>
        <p style="font-size: 12px;">*Coret yang tidak perlu</p>
        <table
            width="100%"
            cellpadding="5"
            bgcolor="#fff"
            cellspacing="0"
            align="center"
            style="border: 1px solid #000;"
        >
            <tr class="hd-dok" style="font-weight: bold; text-align: center;">
                <td colspan="2">Lembar Jawaban</td>
                <td colspan="2">Rekomendasi</td>
            </tr>
            <tr class="hd-dok-b" style="font-weight: bold; text-align: center;">
                <td style="width: 10%;">No.</td>
                <td style="width: 40%;">Jawaban</td>
                <td style="width: 25%;">K</td>
                <td style="width: 25%;">BK</td>
            </tr>

            @foreach($soal_list as $soal)
            <tr>
                <td style="text-align: center;">{{ $soal->no }}</td>
                <td>
                    @if($soal->jawaban_asesi)
                        {{ $soal->jawaban_asesi }} -
                        @if($soal->jawaban_asesi === 'A') {{ $soal->jawab_a }}
                        @elseif($soal->jawaban_asesi === 'B') {{ $soal->jawab_b }}
                        @elseif($soal->jawaban_asesi === 'C') {{ $soal->jawab_c }}
                        @elseif($soal->jawaban_asesi === 'D') {{ $soal->jawab_d }}
                        @endif
                    @else
                        <span style="color: #999; font-style: italic;">Belum dijawab</span>
                    @endif
                </td>
                <td style="text-align: center;">
                    <span class="checkbox @if($soal->jawaban_asesi === $soal->kunci_jawaban) checked @endif"></span>
                </td>
                <td style="text-align: center;">
                    <span class="checkbox @if($soal->jawaban_asesi && $soal->jawaban_asesi !== $soal->kunci_jawaban) checked @endif"></span>
                </td>
            </tr>
            @endforeach
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
