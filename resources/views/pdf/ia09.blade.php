<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>FR.IA.09 - Pertanyaan Wawancara</title>
<style>
    body {
        font-family: Arial, sans-serif;
        font-size: 12px;
    }
    .container {
        width: 900px;
        margin: auto;
    }
    .title {
        font-weight: bold;
        margin-bottom: 5px;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 10px;
    }
    td, th {
        border: 1px solid #000;
        padding: 5px;
        vertical-align: top;
    }
    .no-border td {
        border: none;
    }
    .header-red {
        background: #c00000;
        color: #fff;
        font-weight: bold;
        text-align: center;
    }
    .left {
        text-align: left;
    }
    .center {
        text-align: center;
    }
    .small {
        font-size: 11px;
    }
</style>
</head>

<body>
<div class="container">

    <div class="title">FR.IA.09 &nbsp;&nbsp; PERTANYAAN WAWANCARA</div>

    <!-- Header -->
    <table>
        <tr>
            <td width="30%">Skema Sertifikasi<br><span class="small">(KKNI/Okupasi/Klaster)</span></td>
            <td width="5%">:</td>
            <td>{{ $dokumenHeader['jabatan_kerja'] ?? '' }}</td>
        </tr>
        <tr>
            <td>Judul</td>
            <td>:</td>
            <td>{{ $dokumenHeader['nomor_skema'] ?? '' }}</td>
        </tr>
        <tr>
            <td>Nomor</td>
            <td>:</td>
            <td>SKEMA-01/LSP-GKK/2025</td>
        </tr>
        <tr>
            <td>TUK</td>
            <td>:</td>
            <td>{{ $dokumenHeader['tuk'] ?? '' }}</td>
        </tr>
        <tr>
            <td>Nama Asesor 1</td>
            <td>:</td>
            <td>{{ $dokumenHeader['asesor_1'] ?? '' }}</td>
        </tr>
        <tr>
            <td>Nama Asesor 2</td>
            <td>:</td>
            <td>{{ $dokumenHeader['asesor_2'] ?? '' }}</td>
        </tr>
        <tr>
            <td>Nama Asesi</td>
            <td>:</td>
            <td>{{ $dokumenHeader['nama_asesi'] ?? '' }}</td>
        </tr>
        <tr>
            <td>Tanggal</td>
            <td>:</td>
            <td>{{ $dokumenHeader['tanggal_uji'] ?? '' }}</td>
        </tr>
    </table>

    <div class="small">*Coret yang tidak perlu</div>

    <!-- Panduan -->
    <table>
        <tr>
            <td class="header-red left">PANDUAN BAGI ASESOR</td>
        </tr>
        <tr>
            <td>
                <ul>
                    <li>Pertanyaan wawancara dapat dilakukan untuk keseluruhan unit kompetensi atau kelompok pekerjaan.</li>
                    <li>Isilah bukti portofolio sesuai dengan bukti pada FR.IA.08.</li>
                    <li>Ajukan pertanyaan verifikasi portofolio untuk semua unit kompetensi.</li>
                    <li>Ajukan pertanyaan kepada asesi sebagai tindak lanjut verifikasi portofolio.</li>
                    <li>Jika hasil verifikasi belum memadai, ajukan pertanyaan tambahan.</li>
                    <li>Tuliskan pencapaian dengan mencentang (√) "Ya" atau "Tidak".</li>
                </ul>
            </td>
        </tr>
    </table>

    <!-- Bukti -->
    @if(isset($soal) && isset($soal[1]))
    <table>
        <tr class="header-red">
            <td width="5%">No.</td>
            <td>Bukti – Bukti Kompetensi</td>
        </tr>
        @foreach($soal[1] as $item)
        <tr>
            <td class="center">{{ $item->no ?? '' }}</td>
            <td>{{ $item->soal ?? '' }}</td>
        </tr>
        @endforeach
    </table>
    @endif

    <!-- Pertanyaan -->
    @if(isset($soal) && isset($soal[2]))
    <table>
        <tr class="header-red">
            <td width="5%">No.</td>
            <td width="40%">Daftar Pertanyaan Wawancara</td>
            <td width="35%">Kesimpulan Jawaban Asesi</td>
            <td width="10%">K</td>
            <td width="10%">BK</td>
        </tr>
        @foreach($soal[2] as $item)
        <tr>
            <td class="center">{{ $loop->index }}.</td>
            <td>{{ $item->soal ?? '' }}</td>
            <td>{{ $answers[$item->id]['kesimpulan'] ?? '' }}</td>
            <td class="center">@if(isset($answers[$item->id]['is_kompeten']) && $answers[$item->id]['is_kompeten'] === true)✓@endif</td>
            <td class="center">@if(isset($answers[$item->id]['is_kompeten']) && $answers[$item->id]['is_kompeten'] === false)✓@endif</td>
        </tr>
        @endforeach
    </table>
    @endif

    <!-- Tanda tangan -->
    <table>
        <tr>
            <td colspan="3"><b>Asesi :</b></td>
        </tr>
        <tr>
            <td width="20%">Nama</td>
            <td width="5%">:</td>
            <td>{{ $dokumenHeader['nama_asesi'] ?? '' }}</td>
        </tr>
        <tr>
            <td>Tanda tangan dan Tanggal</td>
            <td>:</td>
            <td height="60px">{!! $barcodes['asesi']['url'] ?? '' !!}</td>
        </tr>

        <tr>
            <td colspan="3"><b>Asesor 1 :</b></td>
        </tr>
        <tr>
            <td>Nama</td>
            <td>:</td>
            <td>{{ $dokumenHeader['asesor_1'] ?? '' }}</td>
        </tr>
        <tr>
            <td>No. Reg</td>
            <td>:</td>
            <td>{{ $dokumenHeader['noreg_asesor_1'] ?? '' }}</td>
        </tr>
        <tr>
            <td>Tanda tangan dan Tanggal</td>
            <td>:</td>
            <td height="60px">{!! $barcodes['asesor1']['url'] ?? '' !!}</td>
        </tr>

        <tr>
            <td colspan="3"><b>Asesor 2 :</b></td>
        </tr>
        <tr>
            <td>Nama</td>
            <td>:</td>
            <td>{{ $dokumenHeader['asesor_2'] ?? '' }}</td>
        </tr>
        <tr>
            <td>No. Reg</td>
            <td>:</td>
            <td>{{ $dokumenHeader['noreg_asesor_2'] ?? '' }}</td>
        </tr>
        <tr>
            <td>Tanda tangan dan Tanggal</td>
            <td>:</td>
            <td height="60px">{!! $barcodes['asesor2']['url'] ?? '' !!}</td>
        </tr>
    </table>

</div>
</body>
</html>