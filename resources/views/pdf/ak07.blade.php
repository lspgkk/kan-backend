<!doctype html>
<html lang="id">
    <head>
        <meta charset="UTF-8" />
        <title>FR.AK.07</title>
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
        </style>
    </head>
    <body>
        <!-- Title -->
        <table width="100%" cellspacing="0" cellpadding="5" bgcolor="#fff" align="center">
            <tr>
                <td class="judul noborder">
                    FR.AK.07 - FORMULIR PENYESUAIAN ASESMEN<br />
                </td>
            </tr>
        </table>
        <br />

        <!-- Identitas Table -->
        <table width="100%" cellpadding="5" cellspacing="0" align="center">
            <tr>
                <td style="width: 35%;" rowspan="2">
                    <strong>Skema Sertifikasi</strong><br />
                    <span style="font-size: 10px;">(KKNI/Okupasi/Klaster)</span>
                </td>
                <td style="width: 15%;"><strong>Judul</strong></td>
                <td style="width: 3%;" align="center"><strong>:</strong></td>
                <td>{{($skema['judul']) }}</td>
            </tr>
            <tr>
                <td><strong>Nomor</strong></td>
                <td align="center"><strong>:</strong></td>
                <td>{{($skema['nomor']) }}</td>
            </tr>
            <tr>
                <td><strong>TUK</strong></td>
                <td align="center"><strong>:</strong></td>
                <td colspan="2">{{($tuk) ?: 'Sewaktu/Tempat Kerja/Mandiri*' }}</td>
            </tr>
            @if(count($asesorList) > 1)
                @foreach($asesorList as $idx => $asesor)
                <tr>
                    <td><strong>Nama Asesor {{ $idx + 1 }}</strong></td>
                    <td align="center"><strong>:</strong></td>
                    <td colspan="2">{{($asesor['nama']) }}{{ $asesor['noreg'] ? ' (' . $asesor['noreg'] . ')' : '' }}</td>
                </tr>
                @endforeach
            @else
                @if(count($asesorList) > 0)
                <tr>
                    <td><strong>Nama Asesor</strong></td>
                    <td align="center"><strong>:</strong></td>
                    <td colspan="2">{{($asesorList[0]['nama']) }}{{ $asesorList[0]['noreg'] ? ' (' . $asesorList[0]['noreg'] . ')' : '' }}</td>
                </tr>
                @endif
            @endif
            <tr>
                <td><strong>Nama Asesi</strong></td>
                <td align="center"><strong>:</strong></td>
                <td colspan="2">{{($namaAsesi) }}</td>
            </tr>
            <tr>
                <td><strong>Tanggal</strong></td>
                <td align="center"><strong>:</strong></td>
                <td colspan="2">{{ date('d/m/Y') }}</td>
            </tr>
        </table>
        <p style="font-size: 10px; color: #666;">*Coret yang tidak perlu</p>
        <br />

        <!-- Panduan -->
        <table width="100%" cellpadding="5" cellspacing="0" align="center">
            <tr>
                <td style="background-color: #f0f0f0; font-weight: bold;">PANDUAN Asesmen Mandiri</td>
            </tr>
            <tr>
                <td style="line-height: 1.6; text-align: justify;">
                    <table width="100%" cellspacing="0" cellpadding="0" style="border: none;">
                        <tr>
                            <td style="border: none; width: 25px; vertical-align: top;">1.</td>
                            <td style="border: none;">Formulir ini digunakan pada saat pelaksanaan pra asesmen, jika ada asesi yang mempunyai keterbatasan sesuai karakteristik yang dimilikinya sehinga diperlukan penyesuaian yang wajar dan beralasan dan atau ada penyesuaian rencana asesemen, jika tidak sesuai dengan acuan pembanding, potensi asesi dan konteks asesi.</td>
                        </tr>
                        <tr>
                            <td style="border: none; width: 25px; vertical-align: top;">2.</td>
                            <td style="border: none;">Formulir ini terdiri dari dua bagian yaitu bagian A, jika Asesi mempunyai keterbatasan sesuai karakteristik yang dimilikinya dan bagian B penyesuaian rencana asesmen jika tidak sesuai dengan acuan pembanding, potensi asesi dan konteks asesi.</td>
                        </tr>
                        <tr>
                            <td style="border: none; width: 25px; vertical-align: top;">3.</td>
                            <td style="border: none;">Coretlah pada tanda * yang tidak sesuai.</td>
                        </tr>
                        <tr>
                            <td style="border: none; width: 25px; vertical-align: top;">4.</td>
                            <td style="border: none;">Berilah tanda ✓ Ya atau Tidak pada tanda ** sesuai pilihan.</td>
                        </tr>
                        <tr>
                            <td style="border: none; width: 25px; vertical-align: top;">5.</td>
                            <td style="border: none;">Berilah tanda pada kotak pada kolom potensi asesi.</td>
                        </tr>
                        <tr>
                            <td style="border: none; width: 25px; vertical-align: top;">6.</td>
                            <td style="border: none;">Berilah tanda ✓ Ya atau Tidak pada tanda ** sesuai pilihan, jika jawaban Ya selanjutnya pada kolom keterangan berilah tanda ✓ di kotak yang tersedia, pilihan boleh lebih dari satu pilihan disesuaikan kebutuhan.</td>
                        </tr>
                        <tr>
                            <td style="border: none; width: 25px; vertical-align: top;">7.</td>
                            <td style="border: none;">Formulir ini juga digunakan untuk bagian B, jika rekaman rencana asesmen tervalidasi tidak sesuai dengan acuan pembanding, potensi asesi dan konteks asesi.</td>
                        </tr>
                        <tr>
                            <td style="border: none; width: 25px; vertical-align: top;">8.</td>
                            <td style="border: none;">Berilah tanda ✓ Ya atau Tidak pada tanda ** sesuai pilihan dan jika jawaban Ya selanjutnya pada kolom keterangan dengan *** tuliskan penyesuaian yang diperlukan.</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
        <br />

        <!-- Potensi Asesi -->
        <table width="100%" cellpadding="5" cellspacing="0" align="center">
            <tr>
                <td style="width: 20%; font-weight: bold; vertical-align: top;">Potensi Asesi</td>
                <td>
                    @foreach($potensiAsesi as $item)
                    <div style="margin-bottom: 4px;">
                        <span class="checkbox {{ $item['checked'] ? 'checked' : '' }}"></span>
                        {{ $item['nama'] }}
                    </div>
                    @endforeach
                </td>
            </tr>
        </table>
        <br />

        <!-- Bagian A - Mengidentifikasi Persyaratan Modifikasi -->
        <table width="100%" cellpadding="5" cellspacing="0" align="center">
            <tr>
                <td class="hd-dok" style="width: 5%; text-align: center;"><strong>No</strong></td>
                <td class="hd-dok" style="width: 35%;"><strong>Mengidentifikasi Persyaratan Modifikasi dan Kontekstualisasi</strong></td>
                <td class="hd-dok" style="width: 60px; text-align: center;"><strong>Ya</strong></td>
                <td class="hd-dok" style="width: 60px; text-align: center;"><strong>Tidak</strong></td>
                <td class="hd-dok"><strong>Keterangan</strong></td>
            </tr>
            @foreach($modifikasi as $kategori)
                @php
                    $refs = $kategori['referensis'];
                    while (count($refs) < [count]) {
                        $refs[] = ['nama' => '', 'jawaban' => null];
                    }
                    $refCount = count($refs);
                @endphp
                @foreach($refs as $idx => $ref)
                <tr>
                    @if($idx == 0)
                    <td rowspan="{{ $refCount }}" style="text-align: center; vertical-align: top;">{{ $kategori['urut'] }}</td>
                    <td rowspan="{{ $refCount }}" style="vertical-align: top;">{{ $kategori['nama'] }}</td>
                    @endif
                    <td style="text-align: center;">
                        <span class="checkbox {{ !empty($ref['nama']) && ($ref['jawaban'] ?? false) ? 'checked' : '' }}"></span>
                    </td>
                    <td style="text-align: center;">
                        <span class="checkbox {{ !empty($ref['nama']) && !($ref['jawaban'] ?? false) ? 'checked' : '' }}"></span>
                    </td>
                    <td>{{ $ref['nama'] ?? '' }}</td>
                </tr>
                @endforeach
            @endforeach
        </table>
        <br />

        <!-- Rekaman Rencana Asesmen -->
        <table width="100%" cellpadding="5" cellspacing="0" align="center">
            <p>Bagian B Penyesuaian rencana asesmen sesuai acuan pembanding, potensi asesi dan konteks asesi</p>
            <tr>
                <td class="hd-dok" style="width: 5%; text-align: center;"><strong>No</strong></td>
                <td class="hd-dok" style="width: 55%;"><strong>Rekaman Rencana Asesmen</strong></td>
                <td class="hd-dok" style="width: 60px; text-align: center;"><strong>Ya</strong></td>
                <td class="hd-dok" style="width: 60px; text-align: center;"><strong>Tidak</strong></td>
                <td class="hd-dok" style="width: 20%;"><strong>Keterangan</strong></td>
            </tr>
            @foreach($rencanaAsesmen as $idx => $item)
            <tr>
                <td style="text-align: center; vertical-align: top;">{{ $idx + 1 }}</td>
                <td style="vertical-align: top;">{{ $item['nama'] }}</td>
                <td style="text-align: center;">
                    <span class="checkbox {{ $item['jawaban'] ? 'checked' : '' }}"></span>
                </td>
                <td style="text-align: center;">
                    <span class="checkbox {{ !$item['jawaban'] ? 'checked' : '' }}"></span>
                </td>
                <td>{{ $item['keterangan'] ?? '' }}</td>
            </tr>
            @endforeach
        </table>
        <br />

        <!-- Hasil Penyesuaian -->
        <table width="100%" cellpadding="5" cellspacing="0" align="center">
            <tr>
                <td colspan="3" style="font-weight: bold;">Hasil Penyesuaian yang wajar dan beralasan disepakati menggunakan:</td>
            </tr>
            @foreach($hasilPenyesuaian as $idx => $item)
            <tr>
                <td style="width: 5%; text-align: center; vertical-align: top;">{{ $idx + 1 }})</td>
                <td style="width: 45%; vertical-align: top;">{{ $item['nama'] }}</td>
                <td style="width: 50%;">: {{ $item['jawaban'] ?? '' }}</td>
            </tr>
            @endforeach
        </table>
        <br />

        <!-- Tanda Tangan -->
        <table width="100%" cellpadding="5" cellspacing="0" align="center">
            @if(count($asesorList) > 1)
                @foreach($asesorList as $idx => $asesor)
                <tr>
                    <td style="width: 50%; height: 100px; vertical-align: top;">
                        <div>Nama Asesor {{ $idx + 1 }} : {{($asesor['nama']) }}</div>
                        @if($asesor['noreg'])
                        <div>No. Reg : {{ $asesor['noreg'] }}</div>
                        @endif
                    </td>
                    <td style="width: 50%; vertical-align: middle; text-align: center;">
                        <div style="text-align: left; margin-bottom: 4px;">Tanggal dan Tanda Tangan Asesor :</div>
                        @if(isset($barcodes['asesor'][$asesor['id']]))
                        <img src="{{ $barcodes['asesor'][$asesor['id']]['url'] }}" style="height: 50px; width: 50px;" /><br />
                        <span style="font-size: 10px;">{{ date('d F Y', strtotime($barcodes['asesor'][$asesor['id']]['tanggal'])) }}</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            @elseif(count($asesorList) > 0)
                <tr>
                    <td style="width: 50%; height: 100px; vertical-align: top;">
                        <div>Nama Asesor : {{($asesorList[0]['nama']) }}</div>
                        @if($asesorList[0]['noreg'])
                        <div>No. Reg : {{ $asesorList[0]['noreg'] }}</div>
                        @endif
                    </td>
                    <td style="width: 50%; vertical-align: middle; text-align: center;">
                        <div style="text-align: left; margin-bottom: 4px;">Tanggal dan Tanda Tangan Asesor :</div>
                        @if(isset($barcodes['asesor'][$asesorList[0]['id']]))
                        <img src="{{ $barcodes['asesor'][$asesorList[0]['id']]['url'] }}" style="height: 50px; width: 50px;" /><br />
                        <span style="font-size: 10px;">{{ date('d F Y', strtotime($barcodes['asesor'][$asesorList[0]['id']]['tanggal'])) }}</span>
                        @endif
                    </td>
                </tr>
            @endif
            <tr>
                <td style="height: 100px; vertical-align: top;">
                    <div>Nama Asesi :</div>
                    <div>{{($namaAsesi) }}</div>
                </td>
                <td style="vertical-align: middle; text-align: center;">
                    <div style="text-align: left; margin-bottom: 4px;">Tanggal dan Tanda Tangan Asesi :</div>
                    @if(isset($barcodes['asesi']))
                    <img src="{{ $barcodes['asesi']['url'] }}" style="height: 50px; width: 50px;" /><br />
                    <span style="font-size: 10px;">{{ date('d F Y', strtotime($barcodes['asesi']['tanggal'])) }}</span>
                    @endif
                </td>
            </tr>
        </table>
    </body>
</html>
