<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>FR.IA.01 - Ceklis Observasi</title>

<style>
@page {
    size: A4;
    margin: 15mm;
}

body{
    font-family: 'DejaVu Sans', Arial, Helvetica, sans-serif;
    font-size:12px;
    margin:20px;
    color:#000;
}

h3{
    font-size:14px;
    margin-bottom:10px;
}

.box{
    margin-bottom:12px;
    padding:0;
}

.boxi{
    border:2px solid #000;
    margin-bottom:12px;
    padding:0;
}

table{
    width:100%;
    border-collapse:collapse;
    margin:0;
}

table + table{
    margin-top:-1px;
}

td, th{
    border:1px solid #000;
    padding:6px;
    vertical-align:top;
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

.center{
    text-align:center;
}

.small{
    font-size:11px;
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

.signature-box{
    height:60px;
}

.barcode-img{
    max-width: 80px;
    max-height: 40px;
}
</style>
</head>
<body>

<h3><b>{{ $kelompok_kerja['kode'] }}.</b> &nbsp; {{ $kelompok_kerja['nama_dokumen'] }}</h3>

<!-- IDENTITAS -->
@if(isset($dokumenHeader))
<div class="box">
<table>
<tr>
<td width="25%" class="gray">Skema Sertifikasi<br>(KKNI/Okupasi/Klaster)</td>
<td width="2%">:</td>
<td>{{ isset($dokumenHeader['jabatan_kerja']) && is_string($dokumenHeader['jabatan_kerja']) ? $dokumenHeader['jabatan_kerja'] : '-' }}</td>
</tr>
<tr>
<td class="gray">Nomor</td>
<td>:</td>
<td>{{ isset($dokumenHeader['nomor_skema']) && is_string($dokumenHeader['nomor_skema']) ? $dokumenHeader['nomor_skema'] : '-' }}</td>
</tr>
<tr>
<td class="gray">TUK</td>
<td>:</td>
<td>{{ isset($dokumenHeader['tuk']) && is_string($dokumenHeader['tuk']) ? $dokumenHeader['tuk'] : '-' }}</td>
</tr>
@if(isset($dokumenHeader['asesor_2']) && !empty($dokumenHeader['asesor_2']))
<tr>
<td class="gray">Nama Asesor 1</td>
<td>:</td>
<td>{{ isset($dokumenHeader['asesor_1']) && is_string($dokumenHeader['asesor_1']) ? strtoupper($dokumenHeader['asesor_1']) : '-' }}</td>
</tr>
<tr>
<td class="gray">Nama Asesor 2</td>
<td>:</td>
<td>{{ isset($dokumenHeader['asesor_2']) && is_string($dokumenHeader['asesor_2']) ? strtoupper($dokumenHeader['asesor_2']) : '-' }}</td>
</tr>
@else
<tr>
<td class="gray">Nama Asesor</td>
<td>:</td>
<td>{{ isset($dokumenHeader['asesor_1']) && is_string($dokumenHeader['asesor_1']) ? strtoupper($dokumenHeader['asesor_1']) : '-' }}</td>
</tr>
@endif
<tr>
<td class="gray">Nama Asesi</td>
<td>:</td>
<td>{{ isset($dokumenHeader['nama_asesi']) && is_string($dokumenHeader['nama_asesi']) ? strtoupper($dokumenHeader['nama_asesi']) : '-' }}</td>
</tr>
<tr>
<td class="gray">Tanggal</td>
<td>:</td>
<td>{{ isset($dokumenHeader['tanggal_uji']) && is_string($dokumenHeader['tanggal_uji']) ? \Carbon\Carbon::parse($dokumenHeader['tanggal_uji'])->locale('id')->translatedFormat('d F Y') : '-' }}</td>
</tr>
</table>
</div>
@endif

<!-- PANDUAN -->
<div class="boxi">
<div class="section-title">PANDUAN BAGI ASESOR</div>
<div style="padding:10px;">
<ol>
<li>Lengkapi nama unit kompetensi, elemen, dan kriteria unjuk kerja sesuai kolom dalam tabel.</li>
<li>Isi standar industri atau tempat kerja.</li>
<li>Beri tanda centang (✓) pada kolom "YA" jika asesi kompeten, dan "Tidak" jika sebaliknya.</li>
<li>Penilaian lanjut diisi bila hasil belum dapat disimpulkan.</li>
<li>Isi kolom KUK sesuai SKKNI.</li>
</ol>
</div>
</div>

@foreach($kelompok_kerja['kelompok_kerja'] as $kelompok)
<!-- KELOMPOK PEKERJAAN {{ $kelompok['urut'] }} -->
<div class="box">
<table>
<tr>
<th class="red" width="20%">Kelompok Pekerjaan {{ $kelompok['urut'] }}</th>
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
</div>

@foreach($unit['subunits'] as $index => $subunit)
<!-- UNIT KOMPETENSI -->
<div class="box">
<table>
<tr>
<td rowspan="2" width="25%">Unit Kompetensi {{ $index + 1 }}</td>
<td class="gray" width="20%">Kode</td>
<td width="2%">:</td>
<td colspan="2">{{ $unit['kode_unit'] }}</td>
</tr>
<tr>
<td class="gray">Judul Unit</td>
<td>:</td>
<td colspan="2">{{ $unit['nama_unit'] }}</td>
</tr>
</table>
</div>

<!-- TABEL OBSERVASI -->
<div class="box">
<table>
<tr>
<th class="red" width="5%" rowspan="2">No</th>
<th class="red" width="20%" rowspan="2">Kriteria Unjuk Kerja</th>
<th class="red" width="15%" rowspan="2">Standar Industri / Tempat Kerja</th>
<th class="red" colspan="2">Pencapaian</th>
<th class="red" width="15%" rowspan="2">Penilaian Lanjut</th>
</tr>

<tr>
<th class="red" width="7%">Ya</th>
<th class="red" width="7%">Tidak</th>
</tr>

@foreach($subunit['soal'] as $soal)
<tr>
<td class="center">{{ $soal['no'] ?? '' }}</td>
<td>{{ $soal['kuk']['nama'] ?? '' }}</td>
<td class="center">SKKNI</td>
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
<td>{{ isset($soal['penilaian_lanjut']) && is_string($soal['penilaian_lanjut']) ? $soal['penilaian_lanjut'] : '' }}</td>
</tr>
@endforeach

</table>
</div>
@endforeach

<!-- UMPAN BALIK -->
<div class="box">
<table>
<tr>
<td height="70"><b>Umpan Balik untuk asesi:</b></td>
</tr>
@if(isset($kelompok['umpan_balik']) && !empty($kelompok['umpan_balik']) && is_string($kelompok['umpan_balik']))
<tr>
<td>{{ $kelompok['umpan_balik'] }}</td>
</tr>
@endif
</table>
</div>
@endforeach

<!-- REKOMENDASI & TTD -->
@if(isset($dokumenHeader))
<div class="box">
<table>
<tr>
<td class="gray" colspan="2"><b>Rekomendasi:</b></td>
<td class="gray" colspan="2"><b>Asesi</b></td>
</tr>
<tr>
<td colspan="2" rowspan="8">
<div class="checkbox"></div>
Asesi telah memenuhi pencapaian seluruh kriteria unjuk kerja, direkomendasikan <b>KOMPETEN</b>.
<br/>
<br/>
<div class="checkbox"></div>
Asesi belum memenuhi pencapaian seluruh kriteria unjuk kerja, direkomendasikan <b>BELUM KOMPETEN</b>. pada:
<br/>
<span style="margin-left: 18px;">Kelompok Pekerjaan: …</span>
<br/>
<span style="margin-left: 18px;">Unit: …..</span>
<br/>
<span style="margin-left: 18px;">Elemen: …..</span>
<br/>
<span style="margin-left: 18px;">KUK: …….</span>

</td>
<td width="15%">Nama</td>
<td width="35%">: {{ isset($dokumenHeader['nama_asesi']) && is_string($dokumenHeader['nama_asesi']) ? strtoupper($dokumenHeader['nama_asesi']) : '-' }}</td>
</tr>
<tr>

<td width="15%">Tanda tangan / Tanggal</td>
<td width="35%">:
    @if(isset($barcodes['asesi']['url']) && !empty($barcodes['asesi']['url']))
        <img src="{{ $barcodes['asesi']['url'] }}" class="barcode-img" alt="Barcode Asesi">
    @else
        {{ isset($dokumenHeader['tanggal_uji']) && is_string($dokumenHeader['tanggal_uji']) ? \Carbon\Carbon::parse($dokumenHeader['tanggal_uji'])->locale('id')->translatedFormat('d F Y') : '-' }}
    @endif
    <div class="signature-box"></div>
</td>
</tr>
<tr>
<td class="gray" colspan="2"><b>Asesor</b></td>
</tr>
<tr>
<td width="15%">Nama</td>
<td width="35%">: {{ isset($dokumenHeader['asesor_1']) && is_string($dokumenHeader['asesor_1']) ? strtoupper($dokumenHeader['asesor_1']) : '-' }}</td>
</tr>
<tr>
<td width="15%">No. Reg</td>
<td width="35%">: {{ isset($dokumenHeader['noreg_asesor_1']) && is_string($dokumenHeader['noreg_asesor_1']) ? $dokumenHeader['noreg_asesor_1'] : '-' }}</td>
</tr>
<tr>
<td width="15%">Tanda tangan / Tanggal</td>
<td width="35%">:
    @if(isset($barcodes['asesor1']['url']) && !empty($barcodes['asesor1']['url']))
        <img src="{{ $barcodes['asesor1']['url'] }}" class="barcode-img" alt="Barcode Asesor 1">
    @else
        {{ isset($dokumenHeader['tanggal_uji']) && is_string($dokumenHeader['tanggal_uji']) ? \Carbon\Carbon::parse($dokumenHeader['tanggal_uji'])->locale('id')->translatedFormat('d F Y') : '-' }}
    @endif
    <div class="signature-box"></div>
</td>
</tr>
@if(isset($dokumenHeader['asesor_2']) && !empty($dokumenHeader['asesor_2']))
<tr>
<td width="15%" class="gray" colspan="2"><b>Asesor 2</b></td>
</tr>
<tr>
<td width="15%">Nama</td>
<td width="35%">: {{ isset($dokumenHeader['asesor_2']) && is_string($dokumenHeader['asesor_2']) ? strtoupper($dokumenHeader['asesor_2']) : '-' }}</td>
</tr>
<tr>
<td width="15%">No. Reg</td>
<td width="35%">: {{ isset($dokumenHeader['noreg_asesor_2']) && is_string($dokumenHeader['noreg_asesor_2']) ? $dokumenHeader['noreg_asesor_2'] : '-' }}</td>
</tr>
<tr>
<td width="15%">Tanda tangan / Tanggal</td>
<td width="35%">:
    @if(isset($barcodes['asesor2']['url']) && !empty($barcodes['asesor2']['url']))
        <img src="{{ $barcodes['asesor2']['url'] }}" class="barcode-img" alt="Barcode Asesor 2">
    @else
        {{ isset($dokumenHeader['tanggal_uji']) && is_string($dokumenHeader['tanggal_uji']) ? \Carbon\Carbon::parse($dokumenHeader['tanggal_uji'])->locale('id')->translatedFormat('d F Y') : '-' }}
    @endif
    <div class="signature-box"></div>
</td>
</tr>
@endif
</table>
</div>
@endif

</body>
</html>
