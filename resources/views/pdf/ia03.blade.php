    <!DOCTYPE html>
    <html lang="id">
    <head>
    <meta charset="UTF-8">
    <title>FR.IA.03 - Pertanyaan Untuk Mendukung Observasi</title>

    <style>
    @page {
        size: A4;
        margin: 15mm;
    }

    body{
        font-family: Arial, Helvetica, sans-serif;
        background:#fff;
        margin:20px;
        font-size:12px;
        color:#000;
    }

    h3{
        margin:0 0 10px 0;
        font-size:14px;
        color:#2a5db0;
    }

    .box{
        margin-bottom:12px;
        padding:0;
    }

    table{
        width:100%;
        border-collapse:collapse;
        margin-bottom:15px;
    }

    table + table{
        margin-top:-1px;
    }

    td, th{
        border:1px solid #000;
        padding:6px;
        vertical-align:top;
    }

    .no-border td{
        border:none;
    }

    .gray{
        background:#fff;
    }

    .red{
        background:#c40000;
        color:#fff;
        font-weight:bold;
        text-align:center;
    }

    .section-title{
        background:#c40000;
        color:#fff;
        padding:6px;
        font-weight:bold;
    }

    .small{
        font-size:11px;
    }

    .center{
        text-align:center;
    }

    .right{
        text-align:right;
    }

    .boxi{
        border:2px solid #000;
        margin-bottom:12px;
        padding:0;
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

    .footer{
        font-size:11px;
        margin-top:6px;
    }

    .barcode-img{
        max-width: 80px;
        max-height: 40px;
    }
    </style>
    </head>
    <body>

    <h3>{{ $kelompok_kerja['kode'] }}. &nbsp; {{ $kelompok_kerja['nama_dokumen'] }}</h3>

    <!-- HEADER IDENTITAS -->
    @if(isset($dokumenHeader))
    <div class="box">
    <table>
    <tr>
    <td width="25%" class="gray">Skema Sertifikasi<br>(KKNI/Okupasi/Klaster)</td>
    <td width="2%">:</td>
    <td>{{ isset($dokumenHeader['jabatan_kerja']) && is_string($dokumenHeader['jabatan_kerja']) ? $dokumenHeader['jabatan_kerja'] : '-' }}</td>
    </tr>

    <tr>
    <td class="gray">Judul</td>
    <td>:</td>
    <td>{{ isset($dokumenHeader['nomor_skema']) && is_string($dokumenHeader['nomor_skema']) ? $dokumenHeader['nomor_skema'] : '-' }}</td>
    </tr>

    <tr>
    <td class="gray">TUK</td>
    <td>:</td>
    <td>{{ isset($dokumenHeader['tuk']) && is_string($dokumenHeader['tuk']) ? $dokumenHeader['tuk'] : '-' }}</td>
    </tr>

    <tr>
    <td class="gray">Nama Asesor</td>
    <td>:</td>
    <td>{{ isset($dokumenHeader['asesor_1']) && is_string($dokumenHeader['asesor_1']) ? $dokumenHeader['asesor_1'] : '-' }}</td>
    </tr>

    <tr>
    <td class="gray">Nama Asesi</td>
    <td>:</td>
    <td>{{ isset($dokumenHeader['nama_asesi']) && is_string($dokumenHeader['nama_asesi']) ? $dokumenHeader['nama_asesi'] : '-' }}</td>
    </tr>

    <tr>
    <td class="gray">Tanggal</td>
    <td>:</td>
    <td>{{ isset($dokumenHeader['tanggal_uji']) && is_string($dokumenHeader['tanggal_uji']) ? $dokumenHeader['tanggal_uji'] : '-' }}</td>
    </tr>
    </table>
    </div>
    @endif

    <!-- PANDUAN -->
    <div class="boxi">
    <div class="section-title">PANDUAN BAGI ASESOR</div>
    <div style="padding:10px;">
    <ul>
    <li>Formulir ini diisi oleh asesor kompetensi sebelum, pada saat atau setelah melakukan asesmen metode observasi demonstrasi.</li>
    <li>Pertanyaan dibuat dengan tujuan untuk menggali, dapat berisi pertanyaan yang berkaitan dengan dimensi kompetensi.</li>
    <li>Jika pertanyaan disampaikan sebelum asesmen melakukan praktik demonstrasi, maka pertanyaan dibuat berkaitan dengan aspek K3L, SOP, penggunaan peralatan.</li>
    <li>Jika setelah asesmen dilakukan praktik, maka pertanyaan pendukung observasi dapat dilakukan secara lisan.</li>
    <li>Tanggapan asesi ditulis pada kolom tanggapan.</li>
    </ul>
    </div>
    </div>

    @foreach($kelompok_kerja['kelompok_kerja'] as $kelompok)
    <!-- KELOMPOK {{ $kelompok['urut'] }} -->
    <div class="box">
    <table>
    <tr>
    <th class="red" width="18%">Kelompok Pekerjaan {{ $kelompok['urut'] }}</th>
    <th class="red" width="5%">No</th>
    <th class="red" width="20%">Kode Unit</th>
    <th class="red">Judul Unit</th>
    </tr>

    @php
        $totalUnits = count($kelompok['units']);
    @endphp

    @foreach($kelompok['units'] as $index => $unit)
    <tr>
    @if($index == 0)
        <td rowspan="{{ $totalUnits }}"></td>
    @endif
    <td class="center">{{ $index + 1 }}</td>
    <td>{{ $unit['kode_unit'] ?? '' }}</td>
    <td>{{ $unit['nama_unit'] ?? '' }}</td>
    </tr>
    @endforeach
    </table>

    @if(count($kelompok['soal']) > 0)
    <table>
    <tr>
    <th class="red">Pertanyaan</th>
    <th class="red" width="10%">Ya</th>
    <th class="red" width="10%">Tidak</th>
    </tr>

    @foreach($kelompok['soal'] as $soal)
    <tr>
    <td>
    {{ $soal['no'] ?? '' }}. {{ $soal['soal'] ?? '' }}
    </td>
    <td class="center">
        @if(isset($soal['pencapaian']) && $soal['pencapaian'] === true)
            <div class="checkbox checked"></div>
        @else
            <div class="checkbox"></div>
        @endif
    </td>
    <td class="center">
        @if(isset($soal['pencapaian']) && $soal['pencapaian'] === false)
            <div class="checkbox checked"></div>
        @else
            <div class="checkbox"></div>
        @endif
    </td>
    </tr>

    @if(isset($soal['tanggapan']) && !empty($soal['tanggapan']) && is_string($soal['tanggapan']))
    <tr>
    <td colspan="3"><b>Tanggapan:</b> {{ $soal['tanggapan'] }}</td>
    </tr>
    @endif
    @endforeach
    </table>
    @endif
    </div>
    @endforeach

    <!-- UMPAN BALIK -->
    <div class="box">
    <table>
    <tr>
    <td height="80"><b>Umpan balik untuk asesi:</b></td>
    </tr>
    @if(isset($umpan_balik) && !empty($umpan_balik) && is_string($umpan_balik))
    <tr>
    <td>{{ $umpan_balik }}</td>
    </tr>
    @endif
    </table>
    </div>

    <!-- TANDA TANGAN -->
    @if(isset($dokumenHeader))
    <div class="box">
    <table>
        <tr><td style="font-weight: bold;" colspan="3">Asesi :</td></tr>
    <tr>
    <td width="30%">Nama Asesi</td>
    <td width="2%">:</td>
    <td>{{ isset($dokumenHeader['nama_asesi']) && is_string($dokumenHeader['nama_asesi']) ? $dokumenHeader['nama_asesi'] : '-' }}</td>
    </tr>

    <tr>
    <td>Tanda Tangan dan Tanggal</td>
    <td>:</td>
    <td>
        @if(isset($barcodes['asesi']) && !empty($barcodes['asesi']) && is_string($barcodes['asesi']))
            <img src="{{ $barcodes['asesi'] }}" class="barcode-img" alt="Barcode Asesi">
        @else
            {{ isset($dokumenHeader['tanggal_uji']) && is_string($dokumenHeader['tanggal_uji']) ? $dokumenHeader['tanggal_uji'] : '-' }}
        @endif
    </td>
    </tr>

    <tr><td style="font-weight: bold;" colspan="3">Asesor :</td></tr>
    <tr>
    <td>Nama Asesor</td>
    <td>:</td>
    <td>{{ isset($dokumenHeader['asesor_1']) && is_string($dokumenHeader['asesor_1']) ? $dokumenHeader['asesor_1'] : '-' }}</td>
    </tr>

    <tr>
    <td>No. Reg</td>
    <td>:</td>
    <td>{{ isset($dokumenHeader['noreg_asesor_1']) && is_string($dokumenHeader['noreg_asesor_1']) ? $dokumenHeader['noreg_asesor_1'] : '-' }}</td>
    </tr>

    <tr>
    <td>Tanda Tangan dan Tanggal</td>
    <td>:</td>
    <td>
        @if(isset($barcodes['asesor1']) && !empty($barcodes['asesor1']) && is_string($barcodes['asesor1']))
            <img src="{{ $barcodes['asesor1'] }}" class="barcode-img" alt="Barcode Asesor 1">
        @else
            {{ isset($dokumenHeader['tanggal_uji']) && is_string($dokumenHeader['tanggal_uji']) ? $dokumenHeader['tanggal_uji'] : '-' }}
        @endif
    </td>
    </tr>

    @if(isset($dokumenHeader['asesor_2']) && !empty($dokumenHeader['asesor_2']))
    <tr><td style="font-weight: bold;" colspan="3">Asesor 2 :</td></tr>
    <tr>
    <td>Nama Asesor</td>
    <td>:</td>
    <td>{{ isset($dokumenHeader['asesor_2']) && is_string($dokumenHeader['asesor_2']) ? $dokumenHeader['asesor_2'] : '-' }}</td>
    </tr>

    <tr>
    <td>No. Reg</td>
    <td>:</td>
    <td>{{ isset($dokumenHeader['noreg_asesor_2']) && is_string($dokumenHeader['noreg_asesor_2']) ? $dokumenHeader['noreg_asesor_2'] : '-' }}</td>
    </tr>

    <tr>
    <td>Tanda Tangan dan Tanggal</td>
    <td>:</td>
    <td>
        @if(isset($barcodes['asesor2']) && !empty($barcodes['asesor2']) && is_string($barcodes['asesor2']))
            <img src="{{ $barcodes['asesor2'] }}" class="barcode-img" alt="Barcode Asesor 2">
        @else
            {{ isset($dokumenHeader['tanggal_uji']) && is_string($dokumenHeader['tanggal_uji']) ? $dokumenHeader['tanggal_uji'] : '-' }}
        @endif
    </td>
    </tr>
    @endif
    </table>

    </div>
    @endif

    <div class="footer">
    Diadaptasi dari template Departemen Pendidikan dan Pelatikan Australia.
    </div>
    </body>
    </html>
