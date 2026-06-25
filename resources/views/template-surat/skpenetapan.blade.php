<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>SK Penetapan Hasil Sertifikasi Kompetensi</title>

<style>

@page { margin: 0cm 0cm; size: A4; }

body{
    margin-top: 3cm;
    margin-left: 2cm;
    margin-right: 2cm;
    margin-bottom: 2cm;
    font-family: Arial, sans-serif;
    font-size: 13px;
    line-height: 1.3;
    background: #fff;
}

header{
    position: fixed;
    top: 0cm;
    left: 0cm;
    right: 0cm;
    height: 3cm;
}

header img{
    width: 100%;
    height: 100%;
}

footer{
    position: fixed;
    bottom: 0cm;
    left: 0cm;
    right: 0cm;
    height: 2cm;
}

footer img{
    width: 100%;
    height: 100%;
}

.center{
    text-align:center;
    font-weight:bold;
}

.underline{
    text-decoration:underline;
}

.highlight{
    font-weight: bold;
}

.spacer{
    height: 10px;
}

.paragraph{
    text-align:justify;
    margin-bottom: 8px;
}

.table{
    width:100%;
    border-collapse:collapse;
    margin: 10px 0 14px 0;
}

.table th,
.table td{
    border:1px solid black;
    padding: 6px 8px;
    vertical-align: top;
}

.table th{
    background:#d9e1e7;
    text-align:center;
}

.table td{
    text-align:left;
}

.table td.center{
    text-align:center;
}

.main-table{
    width:100%;
    border-collapse:collapse;
}

.main-table td{
    vertical-align:top;
    padding: 2px 0;
    border: none;
}

.label{
    width: 130px;
    border: none;
}

.colon{
    width: 10px;
    border: none;
}

.content{
    text-align:justify;
    border: none;
}

.letter-list{
    padding-left: 0;
    text-align:left;
}

.letter-list div{
    margin-bottom: 4px;
    padding-left: 22px;
    text-indent: -15px;
}

.decision{
    text-align:center;
    font-weight:bold;
    letter-spacing: 6px;
    margin: 26px 0 18px 0;
}

.signature{
    text-align:left;
    margin-top: 35px;
}

.signature .sign-space{
    display:inline-block;
    width: 100%;
    height: 90px;
}

.footer{
    margin-top: 14px;
}

.page-break{
    page-break-before: always;
    margin-top: 20px;
}

.lampiran-title{
    text-align:center;
    font-weight:bold;
    margin-top: 20px;
    margin-bottom: 10px;
}

.checkbox {
    display: inline-block;
    width: 14px;
    height: 14px;
    border: 1.5px solid #333;
    position: relative;
}

.checkbox.checked::after {
    content: "✔";
    font-size: 12px;
    font-family: "DejaVu Sans", sans-serif;
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    line-height: 1;
}

</style>
</head>

<body>

<header>
    <img src="./images/untuk-template/kop_satu.png" width="100%" />
</header>

<footer>
    <img src="./images/untuk-template/kop_dua.png" width="100%" />
</footer>

<div class="center underline">
SURAT KEPUTUSAN DIREKTUR<br>
LEMBAGA SERTIFIKASI PROFESI GATENSI KARYA KONSTRUKSI
</div>

<div class="center">
Nomor :
<span class="highlight">{{ $nomor ?? 'XXX/LSP GKK B.003-B/ROMAN/TAHUN' }}</span>
</div>

<div class="spacer"></div>

<div class="center">TENTANG</div>
<div class="spacer"></div>
<div class="center">
PENETAPAN HASIL SERTIFIKASI KOMPETENSI
</div>
<div class="center">LEMBAGA SERTIFIKASI PROFESI GATENSI KARYA KONSTRUKSI</div>

<hr style="border:none;border-top:2px solid black;margin: 10px 0">

<table class="main-table">

<tr>
<td class="label">Menimbang</td>
<td class="colon">:</td>
<td class="content">

<div class="letter-list">
<div>a. bahwa hasil sertifikasi Kompetensi harus ditetapkan oleh Lembaga Sertifikasi Profesi;</div>

<div>b. bahwa berdasarkan berita acara rapat pleno komite teknis nomor <span class="highlight">{{ $no_ba_komtek ?? '-' }}</span>;</div>

<div>c. bahwa berdasarkan poin a dan b maka perlu ditetapkan hasil sertifikasi kompetensi Lembaga Sertifikasi Profesi Gatensi Karya Konstruksi.</div>
</div>

</td>
</tr>

<tr>
<td class="label"><br/>Mengingat</td>
<td class="colon"><br/>:</td>
<td class="content">

<div class="letter-list">
<br/>
<div>a. Undang-Undang Republik Indonesia Nomor 13 Tahun 2003 Tentang Ketenagakerjaan;</div>

<div>b. Peraturan Pemerintah Pengganti Undang-Undang Nomor 2 Tahun 2022 Tentang Cipta Kerja;</div>

<div>c. Peraturan Pemerintah Nomor 10 Tahun 2018 tentang Badan Nasional Sertifikasi Profesi;</div>

<div>d. Peraturan Badan Nasional Sertifikasi Profesi Nomor: 1/BNSP/III/2014 tentang pedoman penilaian kesesuaian persyaratan umum Lembaga Sertifikasi Profesi;</div>

<div>e. Peraturan Badan Nasional Sertifikasi Profesi Nomor: 2/BNSP/III/2014 tentang pedoman pembentukan Lembaga Sertifikasi Profesi;</div>

<div>f. Peraturan Badan Nasional Sertifikasi Profesi Nomor: 09/BNSP.301/XI/2013 tentang pedoman pelaksanaan asesmen kompetensi;</div>

<div>g. Surat Keputusan Direktur Lembaga Sertifikasi Profesi Gatensi Karya Konstruksi Nomor: <span class="highlight">{{ $no_sk_direktur ?? '-' }}</span> Tentang Penetapan Komite Teknis Atas Hasil Uji Kompetensi Lembaga Sertifikasi Profesi Gatensi Karya Konstruksi.</div>

</div>

</td>
</tr>

<tr>
<td class="label"><br/>Memperhatikan</td>
<td class="colon"><br/>:</td>
<td class="content">
<br/>
Hasil berita acara pleno komite teknis pada tanggal <span class="highlight">{{ $tanggal_ba_komtek ?? $tanggal ?? now()->isoFormat('D MMMM Y') }}</span> dengan nomor <span class="highlight">{{ $no_ba_komtek ?? '-' }}</span>

</td>
</tr>

</table>

<div class="page-break"></div>

<div class="decision">MEMUTUSKAN</div>

<table class="main-table">

<tr>
<td class="label">Menetapkan</td>
<td class="colon">:</td>
<td class="content">

SURAT KEPUTUSAN DIREKTUR LEMBAGA SERTIFIKASI PROFESI
GATENSI KARYA KONSTRUKSI TENTANG PENETAPAN HASIL
SERTIFIKASI KOMPETENSI LEMBAGA SERTIFIKASI PROFESI
GATENSI KARYA KONSTRUKSI

</td>
</tr>

<tr>

<td class="label"><br/>Kesatu</td>
<td class="colon"><br/>:</td>
<td class="content">
<br/>
Hasil sertifikasi kompetensi Lembaga Sertifikasi Profesi Gatensi
Karya Konstruksi yang dilaksanakan pada tanggal
<span class="highlight">{{ $tanggal_uji ?? $tanggal ?? now()->isoFormat('D MMMM Y') }}</span>
sebagaimana terlampir.
</td>
</tr>

<tr>
<td class="label"><br/>Kedua</td>
<td class="colon"><br/>:</td>
<td class="content"><br/>
Keputusan ini berlaku sejak tanggal ditetapkan.
</td>
</tr>

</table>

<div class="footer">

<div class="signature">
<span style="font-weight:normal">Ditetapkan di : Jakarta<br>
Tanggal :
<span class="highlight">{{ $tanggal ?? now()->isoFormat('D MMMM Y') }}</span></span><br>
LSP GATENSI KARYA KONSTRUKSI<br>
<div class="sign-space">
@if(!empty($qr)){!! $qr !!}@endif
</div>
{{ $direktur_name ?? 'Direktur' }}<br>
Direktur
</div>

</div>

<div class="page-break"></div>

<!-- LAMPIRAN -->
<div class="lampiran-title">
LAMPIRAN SURAT KEPUTUSAN NOMOR : <span class="highlight">{{ $nomor ?? '-' }}</span><br>
TENTANG PENETAPAN HASIL SERTIFIKASI KOMPETENSI<br>
LEMBAGA SERTIFIKASI PROFESI GATENSI KARYA KONSTRUKSI
</div>

<br/>
<div style="text-align:center;font-weight:bold;margin-bottom:10px;">
HASIL SERTIFIKASI KOMPETENSI:
</div>

<table class="table">

<tr>
<th width="40">No</th>
<th>Nama</th>
<th>Skema Sertifikasi</th>
<th width="50">Hasil</th>
</tr>

@foreach($peserta ?? [] as $index => $p)
<tr>
<td>{{ $index + 1 }}.</td>
<td><span class="highlight">{{ $p['nama'] }}</span></td>
<td><span class="highlight">{{ $p['skema'] }}</span></td>
<td style="text-align:center;">
  @if(isset($p['kompeten']))
    @if($p['kompeten'] === 'K' || $p['kompeten'] === true) K
    @elseif($p['kompeten'] === 'BK' || $p['kompeten'] === false) BK
    @endif
  @endif
</td>
</tr>
@endforeach

</table>

<br/><br/><br/>

<div class="footer">

<div class="signature">
DIREKTUR LSP GATENSI KARYA KONSTRUKSI<br>
<div class="sign-space">
@if(!empty($qr)){!! $qr !!}@endif
</div>
{{ $direktur_name ?? 'Direktur' }}
</div>

</div>

</body>
</html>
