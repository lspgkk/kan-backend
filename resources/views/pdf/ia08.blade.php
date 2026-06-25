<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ceklis Verifikasi Portofolio</title>
<style>
@page {
    size: A4;
    margin: 15mm;
}

body{
    font-family: 'DejaVu Sans', Arial, sans-serif;
    font-size:12px;
    margin:0;
    color:#000;
}

.title{
    font-weight:bold;
    font-size:14px;
    margin-bottom:10px;
}

table{
    width:100%;
    border-collapse:collapse;
    margin:0;
}

table + table{
    margin-top:10px;
}

td, th{
    border:1px solid #000;
    padding:6px;
    font-size:12px;
    vertical-align:top;
}

.red{
    background:#c00000;
    color:#000;
    font-weight:bold;
}

.center{
    text-align:center;
}

.small{
    font-size:11px;
}

.section{
    margin-top:10px;
}

.checkbox{
    width:15px;
    height:15px;
    border:1px solid #000;
    display:inline-block;
}

.checked::after {
    content: "✔";
}

a {
    color: #003d73;
    text-decoration: underline;
    word-break: break-all;
}
</style>
</head>
<body>

<div class="title">FR.IA.08. CEKLIS VERIFIKASI PORTOFOLIO</div>

<table>
<tr>
<td>Skema Sertifikasi<br><span class="small">(KKNI/Okupasi/Klaster)</span></td>
<td>:</td>
<td>{{ $dokumenHeader['jabatan_kerja'] ?? '' }}</td>
</tr>
<tr>
<td>Judul</td><td>:</td>
<td>{{ $dokumenHeader['nomor_skema'] ?? '' }}</td>
</tr>
<tr><td>TUK</td><td>:</td><td>{{ $dokumenHeader['tuk'] ?? '' }}</td></tr>
<tr><td>Nama Asesor 1</td><td>:</td><td>{{ strtoupper($dokumenHeader['asesor_1'] ?? '') }}</td></tr>
<tr><td>Nama Asesor 2</td><td>:</td><td>{{ strtoupper($dokumenHeader['asesor_2'] ?? '') }}</td></tr>
<tr><td>Nama Asesi</td><td>:</td><td>{{ strtoupper($dokumenHeader['nama_asesi'] ?? '') }}</td></tr>
<tr><td>Tanggal</td><td>:</td><td>{{ isset($dokumenHeader['tanggal_uji']) ? \Carbon\Carbon::parse($dokumenHeader['tanggal_uji'])->locale('id')->translatedFormat('d F Y') : '' }}</td></tr>
</table>

<div class="small">*Coret yang tidak perlu</div>

<div class="section red">PANDUAN BAGI ASESOR</div>
<table>
<tr><td>
<ul>
<li>Verifikasi portofolio dapat dilakukan untuk keseluruhan unit kompetensi dalam skema sertifikasi atau dilakukan untuk masing-masing kelompok pekerjaan dalam satu skema sertifikasi.</li>
<li>Isilah bukti portofolio sesuai ketentuan bukti berkualitas dan relevan dengan standar kompetensi kerja.</li>
<li>Lakukan verifikasi portofolio berdasarkan aturan bukti.</li>
<li>Berikan hasil verifikasi portofolio dengan memberi centang (√).</li>
<li>Jika belum memenuhi aturan bukti maka lanjutkan wawancara.</li>
</ul>
</td></tr>
</table>

@if(isset($soal) && isset($soal[1]))
@php
    // Build mapping: filetype → soal item (from $soal[1]) for answer lookups
    $filetypeSoalMap = [];
    $filetypeLabels = [
        'referensi_kerja' => 'referensi kerja',
        'laporan_pekerjaan' => 'laporan pekerjaan',
        'dokumentasi_pekerjaan' => 'dokumentasi pekerjaan',
    ];
    foreach ($soal[1] as $item) {
        $soalText = trim(strtolower($item->soal ?? ''));
        foreach ($filetypeLabels as $ft => $label) {
            if ($soalText === $label) {
                $filetypeSoalMap[$ft] = $item;
                break;
            }
        }
    }
    $baseUrl = 'https://moon.lspgatensi.id/files/new_gatensi/';
@endphp
<table class="section">
<tr class="red center">
<td rowspan="3" style="text-align: center;">Dokumen Portofolio</td>
<td colspan="8">Aturan Bukti</td>
</tr>
<tr class="red center">
<td colspan="2">Valid</td>
<td colspan="2">Asli</td>
<td colspan="2">Terkini</td>
<td colspan="2">Memadai</td>
</tr>
<tr class="red center small">
<td>Ya</td><td>Tidak</td>
<td>Ya</td><td>Tidak</td>
<td>Ya</td><td>Tidak</td>
<td>Ya</td><td>Tidak</td>
</tr>
@foreach($files as $file)
@php
    $soalItem = $filetypeSoalMap[$file->filetype] ?? null;
    // Handle both full URL and relative path
    $fileUrl = $file->path;
    if ($fileUrl && !str_starts_with($fileUrl, 'http://') && !str_starts_with($fileUrl, 'https://')) {
        $fileUrl = $baseUrl . ltrim($fileUrl, '/');
    }
@endphp
<tr>
<td>@if($fileUrl)<a href="{{ $fileUrl }}" target="_blank" style="color:#003d73;text-decoration:underline;">{{ $file->original_name }}</a>@else{{ $file->original_name }}@endif</td>
<td class="center">@if($soalItem && isset($answers[$soalItem->id]['valid']) && $answers[$soalItem->id]['valid'] === true)<span class="checkbox checked"></span>@endif</td>
<td class="center">@if($soalItem && isset($answers[$soalItem->id]['valid']) && $answers[$soalItem->id]['valid'] === false)<span class="checkbox checked"></span>@endif</td>
<td class="center">@if($soalItem && isset($answers[$soalItem->id]['asli']) && $answers[$soalItem->id]['asli'] === true)<span class="checkbox checked"></span>@endif</td>
<td class="center">@if($soalItem && isset($answers[$soalItem->id]['asli']) && $answers[$soalItem->id]['asli'] === false)<span class="checkbox checked"></span>@endif</td>
<td class="center">@if($soalItem && isset($answers[$soalItem->id]['terkini']) && $answers[$soalItem->id]['terkini'] === true)<span class="checkbox checked"></span>@endif</td>
<td class="center">@if($soalItem && isset($answers[$soalItem->id]['terkini']) && $answers[$soalItem->id]['terkini'] === false)<span class="checkbox checked"></span>@endif</td>
<td class="center">@if($soalItem && isset($answers[$soalItem->id]['memadai']) && $answers[$soalItem->id]['memadai'] === true)<span class="checkbox checked"></span>@endif</td>
<td class="center">@if($soalItem && isset($answers[$soalItem->id]['memadai']) && $answers[$soalItem->id]['memadai'] === false)<span class="checkbox checked"></span>@endif</td>
</tr>
@endforeach
</table>
@endif

@if(isset($soal) && isset($soal[2]))
<table class="section">
<tr class="red center">
<td>Cek List</td>
<td>No. Unit Kompetensi</td>
<td>No. Elemen</td>
<td>Materi/Substansi Wawancara</td>
</tr>
@foreach($soal[2] as $item)
<tr>
<td class="center">@if(isset($unitAnswers[$item->id]) && $unitAnswers[$item->id])<span class="checkbox checked"></span>@endif</td>
<td>{{ $item->unit->kode ?? '' }}</td>
<td class="center">{{ $item->kuk->kode ?? '' }}</td>
<td>{{ $item->subunit->nama ?? '' }}</td>
</tr>
@endforeach
</table>
@endif

<table class="section">
<tr><td><b>Bukti tambahan diperlukan pada unit / elemen kompetensi</b></td></tr>
<tr><td style="height:80px">{{ $buktiTambahan ?? '' }}</td></tr>
</table>

<table>
<tr>
<td class="red">Rekomendasi Asesor</td>
<td>
<div>@if(isset($isKompeten) && $isKompeten === true)<span class="checkbox checked"></span> @endif Asesi telah memenuhi pencapaian seluruh kriteria
unjuk kerja, direkomendasikan KOMPETEN </div>
<div>@if(isset($isKompeten) && $isKompeten === false)<span class="checkbox checked"></span> @endif Asesi belum memenuhi pencapaian seluruh kriteria
unjuk kerja, direkomendasikan uji demonstrasi pada: <br/>
Unit         : {{ $rekomendasiUnit ?? '' }}<br/>
Elemen   : {{ $rekomendasiElemen ?? '' }}<br/>
KUK        : {{ $rekomendasiKuk ?? '' }}</div>
</td>
</tr>
</table>

<table class="section">
<tr><td colspan="3"><b>Asesi</b></td></tr>
<tr><td>Nama</td><td>:</td><td>{{ strtoupper($dokumenHeader['nama_asesi'] ?? '') }}</td></tr>
<tr><td>Tanda tangan dan tanggal</td><td>:</td><td><img src="{{ $barcodes['asesi']['url'] ?? '' }}" style="height:50px; width:50px; object-fit:contain;" /></td></tr>
</table>

<table class="section">
<tr><td colspan="3"><b>Asesor 1</b></td></tr>
<tr><td>Nama</td><td>:</td><td>{{ strtoupper($dokumenHeader['asesor_1'] ?? '') }}</td></tr>
<tr><td>No. Reg</td><td>:</td><td>{{ $dokumenHeader['noreg_asesor_1'] ?? '' }}</td></tr>
<tr><td>Tanda tangan dan tanggal</td><td>:</td><td><img src="{{ $barcodes['asesor1']['url'] ?? '' }}" style="height:50px; width:50px; object-fit:contain;" /></td></tr>
</table>

<table class="section">
<tr><td colspan="3"><b>Asesor 2</b></td></tr>
<tr><td>Nama</td><td>:</td><td>{{ strtoupper($dokumenHeader['asesor_2'] ?? '') }}</td></tr>
<tr><td>No. Reg</td><td>:</td><td>{{ $dokumenHeader['noreg_asesor_2'] ?? '' }}</td></tr>
<tr><td>Tanda tangan dan tanggal</td><td>:</td><td><img src="{{ $barcodes['asesor2']['url'] ?? '' }}" style="height:50px; width:50px; object-fit:contain;" /></td></tr>
</table>

</body>
</html>