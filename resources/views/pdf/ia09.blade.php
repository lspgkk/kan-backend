<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>FR.IA.09 - Pertanyaan Wawancara</title>
<style>
@page {
    size: A4;
    margin: 15mm;
}

body {
    font-family: 'DejaVu Sans', Arial, sans-serif;
    font-size: 12px;
    margin: 0;
    color: #000;
}

.title {
    font-weight: bold;
    font-size: 14px;
    margin-bottom: 10px;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin: 0;
}

table + table {
    margin-top: 10px;
}

td, th {
    border: 1px solid #000;
    padding: 5px;
    vertical-align: top;
    font-size: 12px;
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
            <td>{{ strtoupper($dokumenHeader['asesor_1'] ?? '') }}</td>
        </tr>
        <tr>
            <td>Nama Asesor 2</td>
            <td>:</td>
            <td>{{ strtoupper($dokumenHeader['asesor_2'] ?? '') }}</td>
        </tr>
        <tr>
            <td>Nama Asesi</td>
            <td>:</td>
            <td>{{ strtoupper($dokumenHeader['nama_asesi'] ?? '') }}</td>
        </tr>
        <tr>
            <td>Tanggal</td>
            <td>:</td>
            <td>{{ isset($dokumenHeader['tanggal_uji']) ? \Carbon\Carbon::parse($dokumenHeader['tanggal_uji'])->locale('id')->translatedFormat('d F Y') : '' }}</td>
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
            <td class="center">@if(isset($answers[$item->id]['is_kompeten']) && $answers[$item->id]['is_kompeten'] === true)<span class="checkbox checked"></span>@endif</td>
            <td class="center">@if(isset($answers[$item->id]['is_kompeten']) && $answers[$item->id]['is_kompeten'] === false)<span class="checkbox checked"></span>@endif</td>
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
            <td>{{ strtoupper($dokumenHeader['nama_asesi'] ?? '') }}</td>
        </tr>
        <tr>
            <td>Tanda tangan dan Tanggal</td>
            <td>:</td>
            <td height="60px"><img src="{{ $barcodes['asesi']['url'] ?? '' }}" style="height:50px; width:50px; object-fit:contain;" /></td>
        </tr>

        <tr>
            <td colspan="3"><b>Asesor 1 :</b></td>
        </tr>
        <tr>
            <td>Nama</td>
            <td>:</td>
            <td>{{ strtoupper($dokumenHeader['asesor_1'] ?? '') }}</td>
        </tr>
        <tr>
            <td>No. Reg</td>
            <td>:</td>
            <td>{{ $dokumenHeader['noreg_asesor_1'] ?? '' }}</td>
        </tr>
        <tr>
            <td>Tanda tangan dan Tanggal</td>
            <td>:</td>
            <td height="60px"><img src="{{ $barcodes['asesor1']['url'] ?? '' }}" style="height:50px; width:50px; object-fit:contain;" /></td>
        </tr>

        <tr>
            <td colspan="3"><b>Asesor 2 :</b></td>
        </tr>
        <tr>
            <td>Nama</td>
            <td>:</td>
            <td>{{ strtoupper($dokumenHeader['asesor_2'] ?? '') }}</td>
        </tr>
        <tr>
            <td>No. Reg</td>
            <td>:</td>
            <td>{{ $dokumenHeader['noreg_asesor_2'] ?? '' }}</td>
        </tr>
        <tr>
            <td>Tanda tangan dan Tanggal</td>
            <td>:</td>
            <td height="60px"><img src="{{ $barcodes['asesor2']['url'] ?? '' }}" style="height:50px; width:50px; object-fit:contain;" /></td>
        </tr>
    </table>

</body>
</html>