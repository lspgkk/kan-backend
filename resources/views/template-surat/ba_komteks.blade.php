<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Berita Acara Keputusan Hasil Uji Kompetensi</title>

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
    margin-bottom:26px;
}

.highlight{
    font-weight: bold;
}

/* PARAGRAPH */

.paragraph{
    text-align:justify;
    margin-bottom:10px;
}

/* TABLE STANDARD */

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

/* WORD STYLE NUMBER LIST */

.word-list{
    margin-left:22px;
    text-align:justify;
}

.word-list div{
    margin-bottom:4px;
}

/* SIGNATURE TABLE HEIGHT LOCK */

.sign-table td{
    height:130px;
    vertical-align:middle;
}

/* QR PLACEHOLDER */

.qr{
    width:92px;
    height:92px;
    border:2px solid black;
    margin:auto;
}

.footer{
    margin-top:14px;
}
.underline{
    text-decoration:underline;
}
</style>
</head>

<body>

<div class="page first">

<div class="center underline">
BERITA ACARA KEPUTUSAN HASIL UJI KOMPETENSI
</div>

<div class="center">
Nomor :
<span class="highlight">{{ $nomor ?? 'XXX/LSP GKK B.002-A/ROMAN/TAHUN' }}</span>
</div>
<br/><br/><br/>

<div class="paragraph">
Pada hari ini
<span class="highlight">{{ now()->isoFormat('dddd') }}</span>
tanggal
<span class="highlight">{{ $tanggal ?? now()->isoFormat('D MMMM Y') }}</span>,
telah diselenggarakan rapat Komite Teknis untuk menetapkan hasil uji sertifikasi kompetensi bagi asesi yang tercantum dalam daftar hasil asesmen.
</div>


<div class="paragraph">
Rapat Komite Teknis dihadiri oleh:
</div>


<table class="table">

<tr>
<th width="60">No</th>
<th>Nama</th>
<th width="160">Jabatan</th>
</tr>

@if($nama_komtek1 ?? false)
<tr>
<td>1.</td>
<td><span class="highlight">{{ $nama_komtek1 }}</span></td>
<td>Ketua</td>
</tr>
@endif

@if($nama_komtek2 ?? false)
<tr>
<td>2.</td>
<td><span class="highlight">{{ $nama_komtek2 }}</span></td>
<td>Anggota</td>
</tr>
@endif

@if($nama_komtek3 ?? false)
<tr>
<td>3.</td>
<td><span class="highlight">{{ $nama_komtek3 }}</span></td>
<td>Anggota</td>
</tr>
@endif

</table>


<div class="paragraph">
Keputusan ini dibuat berdasarkan dokumen hasil asesmen yang terdiri atas:
</div>

<div class="word-list">
<div>1. Daftar Periksa;</div>
<div>2. Kelengkapan Data Asesi;</div>
<div>3. Buku Kerja; dan</div>
<div>4. Laporan Asesmen</div>
</div>


<div class="paragraph">
Tim Pemutus sepakat memutuskan hal-hal sebagai berikut:
</div>


<div class="word-list">

<div>
1. Keputusan Direktur Jenderal Bina Konstruksi Kementerian Pekerjaan Umum dan Perumahan Rakyat Nomor 114/KPTS/Dk/2024 Tentang Penetapan Jabatan Kerja dan Jenjang Kualifikasi Atas Jabatan Kerja di Bidang Jasa Konstruksi.
</div>

<div>
2. Surat Edaran Ketua Pengembangan Jasa Konstruksi Nomor 01/SE/LPJK/2025 Tentang Pedoman Pemberian Rekomendasi Lisensi Lembaga Sertifikasi Profesi (LSP).
</div>

<div>
3. Surat Keputusan Lembaga Pengembangan Jasa Konstruksi Nomor BK 0401-LK/164 Tentang Penyesuaian Skema Sertifikasi Jabatan Kerja Perbaikan dan Penjelasan Lampiran Surat Edaran Ketua LPJK.
</div>

<div>
4. Seluruh proses asesmen dan alur yang ditetapkan oleh LSP telah sesuai dengan ketentuan yang berlaku.
</div>

<div>
5. Telah dilakukan kegiatan asesmen kepada asesi oleh para asesor kompetensi sesuai dengan aturan pelaksanaan asesmen dengan daftar terlampir sebagai berikut:
</div>

</div>


<table class="table">

<tr>
<th width="60">No</th>
<th>Nama</th>
<th>Skema Sertifikasi</th>
<th width="60">K</th>
<th width="60">BK</th>
</tr>

@foreach($nama_peserta ?? [] as $index => $asesi)
<tr>
<td>{{ $index + 1 }}.</td>
<td><span class="highlight">{{ $asesi['nama'] }}</span></td>
<td><span class="highlight">{{ $asesi['skema'] }}</span></td>
<td style="text-align:center;">@if($asesi['kompeten'] === 'K' || $asesi['kompeten'] === true)✔@endif</td>
<td style="text-align:center;">@if($asesi['kompeten'] === 'BK' || $asesi['kompeten'] === false)✔@endif</td>
</tr>
@endforeach

</table>


<div class="paragraph">
Keterangan:<br>
K = Kompeten; BK = Belum Kompeten.
</div>


<div class="footer">
Demikian berita acara keputusan hasil uji kompetensi ini dibuat dengan sebenarnya untuk dipergunakan sebagaimana mestinya.
</div>


<table class="table sign-table">

<tr>
<th>Komite Teknis</th>
<th width="160">Jabatan</th>
<th width="160">Tanda Tangan</th>
</tr>

@if($nama_komtek1 ?? false)
<tr>
<td><span class="highlight">{{ $nama_komtek1 }}</span></td>
<td style="text-align:center;">Ketua</td>
<td>@if($qr){!! $qr !!}<div class="qr"></div>@else<div class="qr"></div>@endif</td>
</tr>
@endif

@if($nama_komtek2 ?? false)
<tr>
<td><span class="highlight">{{ $nama_komtek2 }}</span></td>
<td style="text-align:center;">Anggota</td>
<td>@if($qr){!! $qr !!}<div class="qr"></div>@else<div class="qr"></div>@endif</td>
</tr>
@endif

@if($nama_komtek3 ?? false)
<tr>
<td><span class="highlight">{{ $nama_komtek3 }}</span></td>
<td style="text-align:center;">Anggota</td>
<td>@if($qr){!! $qr !!}<div class="qr"></div>@else<div class="qr"></div>@endif</td>
</tr>
@endif

</table>

</div>

</body>
</html>