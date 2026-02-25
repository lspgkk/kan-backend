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
        font-size: 15px;
        font-family: "DejaVu Sans", sans-serif;
    }

    .checked::after { content: "✔"; }
</style>
</head>
<body>

<table width=100% align=center class="noborder headera" cellpadding=5 cellspacing=0>
    <tr>
        <td class=noborder><img src="data:image/png;base64,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"/>
        </td>
        <td align=right class=noborder>
            <img src="data:image/png;base64,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"/>
        </td>
    </tr>
</table><br>
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
        <td>{{ $unit->nama }}</td>
        @if ($i == 1)
            <td class="no-bottom" align="center">{{ $sertifikasi['skkni'] }}</td>
        @elseif ($i + 1 == $jml)
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
        @if($barcodes['asesi']['base64'] ?? null)
            <td>
                <img src="{{ $barcodes['asesi']['base64'] }}" width="80" height="80"><br>
                {{ \Carbon\Carbon::parse($barcodes['asesi']['tanggal'])->translatedFormat('d F Y') }}
            </td>
        @else
        <td></td>
        @endif
    </tr>
    <tr>
        <td rowspan=4 valign=top><strong>Catatan :</strong><br>{{ $catatan ?? null }}</td>
        <td colspan=2>Admin LSP GKK:</td>
    </tr>
    <tr>
        <td>Nama</td>
        <td>{{ $barcodes['admin']['nama'] ?? null }}</td>
    </tr>
    <tr>
        <td>No Reg</td>
        <td></td>
    </tr>
    <tr>
        <td height=60 valign=top>Tanda tangan/ Tanggal</td>
        @if($barcodes['admin']['url'] ?? null)
        <td><img src="{{ $barcodes['admin']['url'] }}" style="width: 80px; height: 80px;" /><br>{{ $barcodes['admin']['tanggal'] ? \Carbon\Carbon::parse($barcodes['admin']['tanggal'])->locale('id')->translatedFormat('d F Y') : '' }}</td>
        @else
        <td></td>
        @endif
    </tr>
</table>
</div>
</body>
</html>
