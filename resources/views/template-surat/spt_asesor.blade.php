<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Surat Perintah Tugas Asesor</title>

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
    line-height:1.1;
    box-sizing:border-box;
    overflow:hidden;
}

.page.first{
    background:#fff url('{{ asset('images/kop-surat-1.png') }}') no-repeat top center;
    background-size:100% auto;
    padding:150px 65px 45px 65px;
}

/* HEADER */

.center{
    text-align:center;
    font-weight:bold;
}

.number{
    text-align:center;
    margin-top:4px;
    margin-bottom:28px;
}

.highlight{
    font-weight: bold;
}

/* PARAGRAPH */

.paragraph{
    text-align:justify;
    margin-bottom:12px;
}

/* DETAIL GRID (MASTER GRID SAMA SEMUA DOKUMEN) */

.detail{
    width:100%;
    border-collapse:collapse;
    margin-top:6px;
}

.detail td{
    padding:10px 0;
    vertical-align:top;
}

.label{
    width:165px;
}

.colon{
    width:12px;
}

/* FOOTER */

.footer{
    margin-top:55px;
    display:flex;
    justify-content:flex-end;
}

.signature{
    text-align:left;
    margin-top:40px;
    font-weight:bold;
}

.signature .sign-space{
    display:inline-block;
    width:100%;
    height:80px;
}

.underline{
    text-decoration:underline;
}

</style>
</head>

<body>

<div class="page first">

<div class="center underline">
SURAT PERINTAH TUGAS
</div>

<div class="number center">
Nomor :
<span class="highlight">{{ $no_surat ?? 'XXX/LSP GKK B.012-D/ROMAN/TAHUN' }}</span>
</div>


<div class="paragraph">
Yang bertandatangan di bawah ini Direktur LSP Gatensi Karya Konstruksi,
dengan ini memberi tugas kepada Asesor utama:
</div>


<table class="detail">

<tr>
<td class="label">Nama</td>
<td class="colon">:</td>
<td><span class="highlight">{{ $nama_asesor ?? '' }}</span></td>
</tr>

<tr>
<td class="label">Nomor Registrasi</td>
<td class="colon">:</td>
<td><span class="highlight">{{ $nama_met ?? '' }}</span></td>
</tr>

<tr>
<td class="label">Nomor MET</td>
<td class="colon">:</td>
<td><span class="highlight">{{ $nama_met ?? '' }}</span></td>
</tr>

</table>

<br/>
<div class="paragraph">
dan kepada Asesor peninjau:
</div>


@if($nama_asesor2 ?? false)
<table class="detail">

<tr>
<td class="label">Nama</td>
<td class="colon">:</td>
<td><span class="highlight">{{ $nama_asesor2 }}</span></td>
</tr>

<tr>
<td class="label">Nomor Registrasi</td>
<td class="colon">:</td>
<td><span class="highlight">{{ $nama_met2 ?? '' }}</span></td>
</tr>

<tr>
<td class="label">Nomor MET</td>
<td class="colon">:</td>
<td><span class="highlight">{{ $nama_met2 ?? '' }}</span></td>
</tr>

</table>
@endif

<br/>
<div class="paragraph">
Untuk melaksanakan uji kompetensi bidang konstruksi di:
</div>


<table class="detail">

<tr>
<td class="label">Nama TUK</td>
<td class="colon">:</td>
<td><span class="highlight">{{ $nama_tuk ?? '-' }}</span></td>
</tr>

<tr>
<td class="label">Alamat</td>
<td class="colon">:</td>
<td>
<span class="highlight">
{{ $alamat_tuk ?? '-' }}
</span>
</td>
</tr>

<tr>
<td class="label">Hari/tanggal Uji</td>
<td class="colon">:</td>
<td><span class="highlight">{{ $hari_tanggal ?? '-' }}</span></td>
</tr>

<tr>
<td class="label">Jumlah Peserta</td>
<td class="colon">:</td>
<td><span class="highlight">{{ $jmlh_peserta ?? count($nama_peserta ?? []) }} orang</span></td>
</tr>

<tr>
<td class="label">Skema Sertifikasi</td>
<td class="colon">:</td>
<td><span class="highlight">{{ $nama_skema ?? '-' }}</span></td>
</tr>

<tr>
<td class="label">Nama Peserta</td>
<td class="colon">:</td>
<td>
<span class="highlight">
@foreach($nama_peserta ?? [] as $index => $peserta)
{{ $index + 1 }}. {{ $peserta }}<br/>
@endforeach
</span>
</td>
</tr>

</table>


<div class="footer">

<div class="signature">
<span style="font-weight:normal">Jakarta,
<span class="highlight">{{ now()->isoFormat('D MMMM Y') }}</span></span><br>
LSP Gatensi Karya Konstruksi<br>
<div class="sign-space"></div>
@if($qr)
{!! $qr !!}
@else
Radihal Efendy, S.T.<br>
Direktur
@endif
</div>

</div>


</div>

</body>
</html>