<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Permohonan Blanko</title>

<style>

@page { margin: 0cm 0cm; size: A4; }

body{
    margin-top: 3cm;
    margin-left: 2cm;
    margin-right: 2cm;
    margin-bottom: 2cm;
    font-family: Arial, sans-serif;
    font-size: 14px;
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
    text-align: center;
}

.right{
    text-align: right;
}

.bold{
    font-weight: bold;
}

.blue{
    color: blue;
}

.title{
    font-size: 22px;
    color: blue;
    text-align: center;
    font-weight: bold;
    padding: 5px;
}

.address{
    color: black;
    text-align: center;
    font-size: 12px;
}

.divider{
    height: 5px;
    border-bottom: 4px double blue;
}

.content-table{
    width: 100%;
    border-collapse: collapse;
    margin-top: 8px;
}

.content-table td{
    padding: 2px 5px;
    vertical-align: top;
}

.content{
    text-align: justify;
    margin-top: 12px;
    line-height: 1.5;
}

.signature-table{
    width: 50%;
    float: right;
    text-align: center;
    margin-top: 30px;
}

.signature-table td{
    padding: 2px 0;
}

.no-break{
    page-break-inside: avoid;
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

<div class="right" style="margin-bottom: 18px;">
    Jakarta, {{ \Carbon\Carbon::parse($tanggal_surat)->setTimezone('Asia/Jakarta')->isoFormat('dddd, D MMMM YYYY') }}
</div>

<table class="content-table">
    <tr>
        <td style="width: 120px;">Nomor</td>
        <td style="width: 15px;">:</td>
        <td>{{ $no_surat }}</td>
    </tr>
    <tr>
        <td>Perihal</td>
        <td>:</td>
        <td>Permohonan Blanko Sertifikat Kompetensi</td>
    </tr>
</table>

<div class="content">
    Kepada Yth.<br/>
    Ketua Badan Nasional Sertifikasi Profesi (BNSP)<br/>
    di -<br/>
    Jakarta
</div>

<div class="content">
    Bersama ini kami melaporkan bahwa Lembaga Sertifikasi Profesi Gatensi Karya Konstruksi telah melaksanakan uji kompetensi dengan rincian (terlampir) sebagai berikut :
</div>

<table class="content-table">
    <tr>
        <td style="width: 60%;">1. Kompeten Sumber anggaran biaya Mandiri</td>
        <td style="width: 15px;">:</td>
        <td>{{ $kompeten }}</td>
    </tr>
    <tr>
        <td>2. Belum Kompeten</td>
        <td>:</td>
        <td>{{ $belum_kompeten }}</td>
    </tr>
    <tr>
        <td style="text-align: right; font-weight: bold;">Jumlah</td>
        <td style="font-weight: bold;">:</td>
        <td style="font-weight: bold;">{{ $kompeten + $belum_kompeten }}</td>
    </tr>
</table>

<div class="content">
    Sehubungan dengan hal tersebut di atas, kami membutuhkan blanko sertifikat sesuai jumlah peserta yang kompeten sebanyak {{ $kompeten }} lembar. Blanko sertifikat kompetensi yang diberikan BNSP akan kami pertanggungjawabkan sesuai dengan keperuntukannya.
</div>

<div class="content">
    Demikian permohonan kami, atas perhatiannya diucapkan terima kasih.
</div>

<table class="signature-table">
    <tr>
        <td>Hormat kami,</td>
    </tr>
    <tr>
        <td class="bold">Lembaga Sertifikasi Profesi Gatensi Karya Konstruksi</td>
    </tr>
    <tr>
        <td style="padding: 15px 0px;">{!! $svg !!}</td>
    </tr>
    <tr>
        <td class="bold">Radinal Efendy, ST</td>
    </tr>
    <tr>
        <td>Ketua LSP</td>
    </tr>
</table>

</body>
</html>
