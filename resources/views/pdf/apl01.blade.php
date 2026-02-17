<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>FR.APL-01</title>
<style>
    @font-face {
        font-family: "Open Sans";
        font-style: normal;
        font-weight: normal;
        src: url('.media_url().'fonts/cJZKeOuBrn4kERxqtaUH3aCWcynf_cDxXwCLxiixG1c) format("truetype");
    }   
        body{
            font-family: "Open Sans", Calibri,Candara,Segoe,Segoe UI,Optima,Arial,sans-serif;
            font-size:11pt;
            margin-top:65px;
        }
    .hd-dok{
        background-color:#D60000;
        color:#fff;
    }
    .hd-dok-b{
        background-color:#E79291;
        color:#000;
    }
    td {
        border: 0.2px solid black;
    }
    table {
        border: 0px solid black;
    }
    .fnt{
        font-family: "Open Sans", Calibri,Candara,Segoe,Segoe UI,Optima,Arial,sans-serif;
    }
    .noborder{
        border:0 transparent!important;
    }
    .no-top-bottom {
        border-bottom-color: transparent !important;
        border-top-color: transparent !important;
    }
    .no-bottom {
        border-bottom-color: transparent !important;
    }
    .no-top {
        border-top-color: transparent !important;
    }
    .no-bottom-a {
        border-bottom-color: #D60000 !important;
    }
    .no-top-a {
        border-top-color: #D60000 !important;
    }
    .no-top-left {
        border-top:0!important;
        border-left:0!important;
    }
    .no-top-right {
        border-top:0!important;
        border-right:0px!important;
    }
    .no-top-left-right {
        border-top:0!important;
        border-left:0!important;
        border-right:0!important;
    }
    .no-break{
        page-break-inside: avoid;
    }
    .headera, .footera {
        position: fixed;
        }
    .headera {
        top: 0px;
    }
    .tba td{
        border:0 transparent!important;
    }
    .rekom_jdl{
    }

    .checkbox {
        width: 12px;
        height: 12px;
        border: 1px solid #000;
        display: inline-block;
        text-align: center;
        line-height: 11px;
        font-size: 10px;
    }

    .checked::after { content: "✔"; }
</style>
</head>
<body>

<table width=100% cellspacing=0 cellpadding=5 bgcolor="#fff" align=center>
    <tr>
        <td class="judul noborder">FR.APL-01. PERMOHONAN SERTIFIKASI KOMPETENSI<br></td>
    </tr>
</table><br>
<table width=100% cellpadding=5 cellspacing=0 align=center class="noborder">
    <tr>
        <td class="noborder"><strong>Bagian 1: Rincian Data Pemohon Sertifikasi</strong><br></td>
    </tr>
    <tr>
        <td class="noborder">Pada bagian ini, cantumkan data pribadi, data pendidikan formal serta data pekerjaan anda pada saat ini.<br></td>
    </tr>
</table>
<table width=100% cellpadding=5 cellspacing=0 align=center class="noborder">
    <tr>
        <td class="noborder"><strong>a. Data Pribadi</strong><br></td>
    </tr>
</table>
<table width=100% cellpadding=5 bgcolor="#fff" cellspacing=0 align=center>
    <tr>
        <td>Nama</td>
        <td colspan=4>{{ $data_pribadi['nama'] }}</td>
    </tr>
    <tr>
        <td>No. NIK</td>
        <td colspan=4>{{ $data_pribadi['nik'] }}</td>
    </tr>
    <tr>
        <td>Tempat/tgl. Lahir</td>
        <td colspan=4>
            {{ $data_pribadi['tempat_lahir'] }},
            {{ \Carbon\Carbon::parse($data_pribadi['tanggal_lahir'])->translatedFormat('d F Y') }}
        </td>
    </tr>
    <tr>
        <td>Jenis kelamin</td>
        <td colspan=4>{{ $data_pribadi['jenis_kelamin'] }}</td>
    </tr>
    <tr>
        <td>Kebangsaan</td>
        <td colspan=4>{{ $data_pribadi['kebangsaan'] }}</td>
    </tr>
    <tr>
        <td>Alamat rumah</td>
        <td colspan=4>{{ $data_pribadi['alamat'] }}</td>
    </tr>
    <tr>
        <td></td>
        <td colspan=2></td>
        <td>Kodepos :</td>
        <td>{{ $data_pribadi['kode_pos'] }}</td>
    </tr>
    <tr>
        <td>No. Telp/E-mail</td>
        <td>Rumah</td>
        <td></td>
        <td>Kantor</td>
        <td></td>
    </tr>
    <tr>
        <td width=20%></td>
        <td width=15%>HP</td>
        <td width=25%>{{ $data_pribadi['telepon_hp'] }}</td>
        <td width=15%>Email</td>
        <td width=25%>{{ $data_pribadi['email'] }}</td>
    </tr>
    <tr>
        <td>Kualifikasi/Pendidikan</td>
        <td colspan=4>{{ $data_pribadi['kualifikasi'] }}</td>
    </tr>
</table><br>
<table width=100% cellpadding=5 cellspacing=0 align=center class="noborder">
    <tr>
        <td class="noborder"><strong>b. Data Pekerjaan Sekarang</strong><br></td>
    </tr>
</table>
<table width=100% cellpadding=5 bgcolor="#fff" cellspacing=0 align=center>
    <tr>
        <td>Perusahaan/Lembaga</td>
        <td colspan=4>{{ $data_pekerjaan['perusahaan'] }}</td>
    </tr>
    <tr>
        <td>Jabatan</td>
        <td colspan=4>{{ $data_pekerjaan['jabatan'] }}</td>
    </tr>
    <tr>
        <td>Alamat</td>
        <td colspan=4>{{ $data_pekerjaan['alamat'] }}</td>
    </tr>
    <tr>
        <td></td>
        <td colspan=2></td>
        <td>Kodepos :</td>
        <td>{{ $data_pekerjaan['kode_pos'] }}</td>
    </tr>
    <tr>
        <td width=20%></td>
        <td width=15%>Telp</td>
        <td width=25%>{{ $data_pekerjaan['telepon'] }}</td>
        <td width=15%>Fax</td>
        <td width=25%>{{ $data_pekerjaan['fax'] }}</td>
    </tr>
    <tr>
        <td></td>
        <td>Email</td>
        <td>{{ $data_pekerjaan['email'] }}</td>
        <td></td>
        <td></td>
    </tr>
</table><br>
<table width=100% cellpadding=5 cellspacing=0 align=center class="noborder">
    <tr>
        <td class="noborder"><strong>Bagian 2 : Data Sertifikasi</strong><br></td>
    </tr>
    <tr>
        <td class="noborder">Tuliskan Judul dan Nomor Skema Sertifikasi serta Daftar Unit Kompetensi sesuai
        kemasan pada skema sertifikasi yang anda ajukan untuk mendapatkan pengakuan
        sesuai dengan latar belakang pendidikan, pelatihan serta pengalaman kerja yang
        anda miliki.<br>
        </td>
    </tr>
</table>
<table width=100% cellpadding=5  bgcolor="#fff" cellspacing=0 align=center class="no-break">
    <tr>
        <td class="no-bottom" width=30%>Skema Sertifikasi Okupasi Nasional </td>
        <td width=8%>Judul</td>
        <td width=2%>:</td>
        <td width=60%>{{ $sertifikasi['judul'] }}</td>
    </tr>
    <tr>
        <td class="no-top"></td>
        <td>Nomor</td>
        <td>:</td>
        <td>{{ $sertifikasi['nomor'] }}</td>
    </tr>
    <tr>
        <td class="no-bottom">Tujuan Asesmen</td>
        <td colspan=2 align=center><span class="checkbox checked"></span></td>
        <td>Sertifikasi</td>
    </tr>
    <tr>
        <td class="no-top-bottom"></td>
        <td colspan=2 align=center><span class="checkbox"></span></td>
        <td>Sertifikasi Ulang</td>
    </tr>
    <tr>
        <td class="no-top-bottom"></td>
        <td colspan=2 align=center><span class="checkbox"></span></td><td>Pengakuan Kompetensi Terkini (PKT)</td>
    </tr>
    <tr>
        <td class="no-top-bottom"></td>
        <td colspan=2 align=center><span class="checkbox"></span></td><td>Rekognisi pembelajaran lampau</td>
    </tr>
    <tr>
        <td class="no-top"></td>
        <td colspan=2 align=center><span class="checkbox"></span></td><td>Lainnya</td>
    </tr>
</table>
<table width=100% cellpadding=5 cellspacing=0 align=center class="noborder">
    <tr>
        <td class="noborder"><br><strong>Daftar Unit Kompetensi Sesuai Kemasan:</strong><br></td>
    </tr>
</table>
<table width=100% cellpadding=5 border=1 bgcolor="#fff" cellspacing=0 align=center>
    <tr>
        <td class=hd-dok width=5% align=center >No</td>
        <td class=hd-dok width=30% align=center >Kode Unit</td>
        <td class=hd-dok width=50% align=center >Judul Unit</td>
        <td class=hd-dok width=15% align=center >Jenis Standar (SKKNI/Standar Internasional/ Standar Khusus )</td>
    </tr>
    @foreach ($unit_kompetensi as $i => $unit)
    @php
        $jml=count($unit_kompetensi);
    @endphp
    <tr>
        <td valign=top>{{ $i + 1 }}</td>
        <td>{{ $unit->kode }}</td>
        <td>{{ $unit->nama }}</td>';
        @if ($i == 1)
            <td class="no-bottom" align="center">{{ $sertifikasi['skkni'] }}</td>
        @elseif ($i == $jml)
            <td class="no-top" align="center"></td>
        @else
            <td class="no-top-bottom" align="center"></td>
        @endif
    </tr>
    @endforeach
</table>
<table width=100% cellpadding=5 cellspacing=0 align=center class="noborder">
    <tr>
        <td class="noborder" height=20>
            <strong>Bagian 3 : Bukti Kelengkapan Pemohon</strong>
        </td>
    </tr>
    <tr>
        <td class="noborder" height=20>
            Bukti Persyaratan Dasar Pemohon
        </td>
    </tr>
</table>
<table width=100% cellpadding=5 bgcolor="#fff" cellspacing=0 align=center>
    <tr>
        <td class=hd-dok width=5% class="hd-dok no-bottom-a" align=center>No</td>
        <td class=hd-dok width=56% class="hd-dok no-bottom-a" align=center>Bukti Persyaratan Dasar</td>
        <td class=hd-dok colspan=2 align=center>Ada</td>
        <td class=hd-dok width=13% class="hd-dok no-bottom-a" align=center>Tidak Ada</td>
    </tr>
    <tr>
        <td class="hd-dok no-top-a"></td>
        <td class="hd-dok no-top-a"></td>
        <td class=hd-dok width=13% align=center>Memenuhi syarat</td>
        <td class=hd-dok width=13% align=center>Tidak Memenuhi syarat</td>
        <td class="hd-dok no-top-a"></td>
    </tr>
    @foreach ($bukti_persyaratan as $b)
    <tr>
        <td valign=top>{{ $b->no }}</td>
        <td>{{ $b->bukti }}</td>
        <td align=center><span class="checkbox checked"></span></td>
        <td align=center></td>
        <td align=center></td>
    </tr>
    @endforeach
</table><br>
<table width=100% cellpadding=5 bgcolor="#fff" cellspacing=0 align=center class="no-break">
    <tr>
        <td rowspan=3 width=40% valign=top>
            <strong>Rekomendasi (diisi oleh LSP GKK)</strong><br>
            Berdasarkan ketentuan persyaratan dasar, maka pemohon :<br>
            Diterima / <s>Tidak diterima</s> *) sebagai peserta sertifikasi<br>
            *Coret yang tidak sesuai
        </td>
        <td colspan=2>Pemohon</td>
    </tr>
    <tr>
        <td>Nama</td>
        <td>{{ $data_pribadi['nama'] }}</td>
    </tr>
    <tr>
        <td height=60 valign=top>Tanda tangan/ Tanggal</td>
        <td>
            @if($barcodes['asesi'])
            <img src="{{ $barcodes['asesi']->path }}"><br>{{ now()->translatedFormat('d F Y') }}
            @else
                <div></div>
            @endif
        </td>
    </tr>
    <tr>
        <td rowspan=4 valign=top><strong>Catatan :</strong><br></td>
        <td colspan=2>Admin LSP GKK:</td>
    </tr>
    <tr>
        <td>Nama</td>
        <td>{{ $barcodes['admin']?->user?->name }}</td>
    </tr>
    <tr>
        <td>No Reg</td>
        <td></td>
    </tr>
    <tr>
        <td height=60 valign=top>Tanda tangan/ Tanggal</td>
        <td>
            @if($barcodes['admin'])
            <img src="{{ $barcodes['admin']->path }}"><br>{{ now()->translatedFormat('d F Y') }}
            @else
                <div></div>
            @endif
        </td>
    </tr>
</table>

</body>
</html>
