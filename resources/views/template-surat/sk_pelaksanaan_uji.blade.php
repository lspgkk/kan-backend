<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>SK Pelaksanaan Uji</title>

<style>

body{
    font-family: Arial, sans-serif;
    background:#f3f3f3;
}

.page{
    width:800px;
    height:1132px;
    margin:40px auto;
    background:#fff;
    padding:40px 65px 45px 65px;
    font-size:14px;
    line-height:1.5;
    box-sizing:border-box;
    overflow:hidden;
}

.page.first{
    background:#fff url('{{ asset('images/kop-surat-1.png') }}') no-repeat top center;
    background-size:100% auto;
    padding:150px 65px 45px 65px;
}

.center{
    text-align:center;
    font-weight:bold;
}

.spacer{
    height:14px;
}

.highlight{
    font-weight: bold;
}

.main-table{
    width:100%;
    border-collapse:collapse;
}

.main-table td{
    vertical-align:top;
}

.label{
    width:130px;
}

.colon{
    width:10px;
}

.content{
    text-align:justify;
}

.letter-list{
    padding-left:0;
    text-align:left;
}

.letter-list div{
    margin-bottom:4px;
    padding-left:22px;
    text-indent:-15px;
}

.decision{
    text-align:center;
    font-weight:bold;
    letter-spacing:6px;
    margin:26px 0 18px 0;
}

.detail-table{
    border-collapse:collapse;
    margin-top:6px;
}

.detail-table td{
    padding:2px 6px 2px 0;
    vertical-align:top;
}

.footer{
    margin-top:45px;
    display:flex;
    justify-content:flex-end;
}

.signature{
    text-align:left;
    margin-top:35px;
    font-weight:bold;
}

.signature .sign-space{
    display:inline-block;
    width:100%;
    height:80px;
}

/* Lampiran page */
.lampiran-title{
    text-align:center;
    font-weight:bold;
    margin-top:20px;
}

.table{
    width:100%;
    border-collapse:collapse;
    margin:10px 0 14px 0;
}

.table th,
.table td{
    border:1px solid black;
    padding:6px 8px;
}

.table th{
    background:#d9e1e7;
    text-align:center;
}

.page-break{
    page-break-before:always;
    margin-top:40px;
}

</style>
</head>

<body>

<div class="page first">

<div class="center">
SURAT KEPUTUSAN DIREKTUR<br>
LEMBAGA SERTIFIKASI PROFESI GATENSI KARYA KONSTRUKSI
</div>

<div class="center">
Nomor :
<span class="highlight">{{ $nomor_sk ?? 'XXX/LSP GKK B.003-B/ROMAN/TAHUN' }}</span>
</div>

<div class="spacer"></div>

<div class="center">TENTANG</div>
<div class="spacer"></div>
<div class="center">
PELAKSANAAN UJI KOMPETENSI
</div>
<div class="center">LEMBAGA SERTIFIKASI PROFESI GATENSI KARYA KONSTRUKSI</div>

<hr style="border:none;border-top:2px solid black;margin:10px 0">

<table class="main-table">

<tr>
<td class="label">Menimbang</td>
<td class="colon">:</td>
<td class="content">

<div class="letter-list">
<div>a. bahwa dalam rangka menjamin mutu pelaksanaan sertifikasi kompetensi, perlu dilakukan penetapan jadwal pelaksanaan uji kompetensi sesuai dengan ketentuan yang berlaku;</div>

<div>b. bahwa berdasarkan permintaan pelaksanaan uji kompetensi, perlu ditetapkan jadwal pelaksanaan uji kompetensi bagi asesi yang akan mengikuti uji kompetensi;</div>

<div>c. bahwa penetapan pelaksanaan uji kompetensi sebagaimana dimaksud pada huruf a dan huruf b perlu ditetapkan melalui Surat Keputusan Direktur Lembaga Sertifikasi Profesi Gatensi Karya Konstruksi;</div>
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

<div>g. Surat Keputusan Direktur Nomor <span class="highlight">067/LSP GKK C.003-A/VIII/2025</span>.</div>

</div>

</td>
</tr>


<tr>
<td class="label"><br/>Memperhatikan</td>
<td class="colon"><br/>:</td>
<td class="content">
<br/>
Jadwal pelaksanaan uji kompetensi yang akan dilaksanakan pada <span class="highlight">{{ $tanggal_uji ?? 'HARI, TANGGAL' }}</span>
</td>
</tr>

</table>

<div class="decision">MEMUTUSKAN</div>


<table class="main-table">

<tr>
<td class="label">Menetapkan</td>
<td class="colon">:</td>
<td class="content">

SURAT KEPUTUSAN DIREKTUR LEMBAGA SERTIFIKASI PROFESI
GATENSI KARYA KONSTRUKSI TENTANG PELAKSANAAN
UJI KOMPETENSI

</td>
</tr>

<tr>
<td class="label"><br/>Kesatu</td>
<td class="colon"><br/>:</td>
<td class="content">
<br/>
Menjadwalkan pelaksanaan uji kompetensi bagi asesi sebagaimana tercantum dalam lampiran yang merupakan bagian tidak terpisahkan dari surat keputusan ini.
</td>
</tr>

<tr>
<td class="label"><br/>Dua</td>
<td class="colon"><br/>:</td>
<td class="content"><br/>
Keputusan ini berlaku sejak tanggal ditetapkan.
</td>
</tr>

</table>


<div class="footer">

<div class="signature">
<span style="font-weight:normal">Ditetapkan di : Jakarta<br>
Tanggal : {{ now()->isoFormat('D MMMM Y') }}</span><br>
LSP GATENSI KARYA KONSTRUKSI<br>
<div class="sign-space"></div>
RADINAL EFENDY, S.T.<br>
Direktur
</div>

</div>

<div class="page-break"></div>

<!-- LAMPIRAN -->
<div class="lampiran-title">
LAMPIRAN SURAT KEPUTUSAN<br>
NOMOR: <span class="highlight">{{ $nomor_sk ?? 'XXX/LSP GKK B.003-B/ROMAN/TAHUN' }}</span>
</div>

<br/>

<table class="table">

<tr>
<th width="60">No</th>
<th>Nama</th>
<th>Skema Sertifikasi</th>
</tr>

@foreach($peserta ?? [] as $index => $asesi)
<tr>
<td>{{ $index + 1 }}.</td>
<td><span class="highlight">{{ $asesi['nama'] }}</span></td>
<td><span class="highlight">{{ $asesi['skema'] }}</span></td>
</tr>
@endforeach

</table>

<br/><br/><br/>

<div class="footer">

<div class="signature">
DIREKTUR LSP GATENSI KARYA KONSTRUKSI<br>
<div class="sign-space"></div>
RADINAL EFENDY, S.T.
</div>

</div>

</div>

</body>
</html>