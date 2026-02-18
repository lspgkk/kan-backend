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
        src: url('.media_url().' fonts/cJZKeOuBrn4kERxqtaUH3aCWcynf_cDxXwCLxiixG1c) format("truetype");
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
        <td class="judul noborder">APL-02. ASESMEN MANDIRI<br></td>
    </tr>
</table><br>
<table width=100% cellpadding=5 cellspacing=0 align=center border=0 bgcolor="#fff">
    <tr>
        <td width=24% rowspan=2>Skema Sertifikasi<br>(<s>KKNI</s>/Okupasi/<s>Klaster</s></td>
        <td width=10%>Judul</td>
        <td width=66%>{{ $dokumenHeader['jabatan_kerja'] }}</td>
    </tr>
    <tr>
        <td>Nomor</td>
        <td>{{ $dokumenHeader['nomor_skema'] }}</td>
    </tr>
    <tr>
        <td colspan=2>TUK</td>
        <td>{{ $dokumenHeader['tuk'] }}</td>
    </tr>
    <tr>
        <td colspan=2>Nama Asesor</td>
        <td>{{ $dokumenHeader['asesor_1'] }}</td>
    </tr>
    <tr>
        <td colspan=2>Nama Asesi</td>
        <td>{{ $dokumenHeader['nama_asesi'] }}</td>
    </tr>
    <tr>
        <td colspan=2>Tanggal</td>
        <td>{{ $dokumenHeader['tanggal_uji'] }}</td>
    </tr>
</table><br>
<table width=100% cellpadding=5 cellspacing=0 align=center border=0 bgcolor="#fff">
    <tr>
        <td class=hd-dok>PANDUAN ASESMEN MANDIRI</td>
    </tr>
    <tr>
        <td>
            Instruksi:
            <ol>
                <li>Baca setiap pertanyaan di kolom sebelah kir</li>
                <li>Beri tanda centang ?) pada kotak jika Anda yakin dapat melakukan tugas yang dijelaskan</li>
                <li>Isi kolom di sebelah kanan dengan mendaftar bukti yang Anda miliki untuk menunjukkan bahwa Anda melakukan tugas-tugas ini.</li>
            </ol>
        </td>
    </tr>
</table><br>
<table width=100% cellpadding=5 cellspacing=0 align=center>
    <tr>
        <td class="no-top-left-right" width=14%></td>
        <td class="no-top-left-right" width=14%></td>
        <td class="no-top-left-right" width=14%></td>
        <td class="no-top-left-right" width=3%></td>
        <td class="no-top-left-right" width=14%></td>
        <td class="no-top-left-right" width=14%></td>
        <td class="no-top-left-right" width=14%></td>
        <td class="no-top-left-right" width=14%></td>
    </tr>
    @foreach($units as $i => $row)
        <tr>
            <td colspan=3 class="no-bottom">Kode & Judul Kompetensi {{ $i + 1 }} :</td>
            <td align=center>:</td>
            <td colspan=4>{{ $row['kode'] }}</td>
        </tr>
        <tr>
            <td class="no-top" colspan=3></td>
            <td align=center>:</td>
            <td colspan=4>{{ $row['judul_kompetensi'] }}</td>
        </tr>
        <tr>
            <td colspan=5>Dapatkah saya ?</td>
            <td align=center>K</td>
            <td align=center>BK</td>
            <td align=center>Bukti</td>
        </tr>
        @foreach ($row['subunits'] as $no_subuk => $rowsubuk)
            <tr>
                <td colspan=2>Elemen {{ $no_subuk + 1 }} :</td>
                <td colspan=6>{{ $rowsubuk['judul_elemen'] }}</td>
            </tr>
            <tr>
                <td></td>
                <td colspan=4>Kriteria Unjuk Kerja:</td>
                <td></td>
                <td></td>
                <td></td>
            </tr>
            @foreach ($rowsubuk['kuk_list'] as $no_kuk => $row_kuk)
            <tr>
                <td></td>
                <td colspan=4>{{ $row_kuk['no_kuk'] }} {{ $row_kuk['judul_kuk'] }}</td>
                @if ($no_kuk === 0)
                    @if (count($rowsubuk['kuk_list']) === 1)
                        <td valign=top align=center></td>
                        <td valign=top align=center></td>
                        <td valign=top align=center></td>
                    @else
                    <td class="no-bottom" valign=top align=center></td>
                    <td class="no-bottom" valign=top align=center></td>
                    <td class="no-bottom" valign=top align=center></td>
                    @endif
                @else
                    @if ($no_kuk + 1 < count($rowsubuk['kuk_list']))
                    <td class="no-top-bottom"></td>
                    <td class="no-top-bottom"></td>
                    <td class="no-top-bottom"></td>
                    @else
                    <td class="no-top"></td>
                    <td class="no-top"></td>
                    <td class="no-top"></td>
                    @endif
                @endif
            </tr>
            @endforeach
        @endforeach
    @endforeach
</table><br>
<table width=100% cellpadding=5 bgcolor="#fff" cellspacing=0 align=center class="no-break">
    <tr>
        <td rowspan=3 width=40% valign=top class="no-bottom">
            <strong>Rekomendasi untuk Asesi</strong><br>
            Proses asesment dapat dilanjutkan / <s>tidak dapat</s> *) dilanjutkan
        </td>
        <td colspan=2><strong>Asesi</strong></td>
    </tr>
    <tr>
        <td>Nama</td>
        <td>{{ $barcodes['asesi']['nama'] }}</td>
    </tr>
    <tr>
        <td height=60 valign=top>Tanda tangan/ Tanggal</td>
        <td>
            @if($barcodes['asesi'])
            <img src="{{ $barcodes['asesi']['url'] }}"><br>{{ now()->translatedFormat('d F Y') }}
            @else
                <div></div>
            @endif
        </td>
    </tr>
    <tr>
        <td rowspan=4 valign=top><br></td>
        <td colspan=2><strong>Asesor 1:</strong></td>
    </tr>
    <tr>
        <td>Nama</td>
        <td>{{ $barcodes['asesor1']['nama'] }}</td>
    </tr>
    <tr>
        <td>No Reg</td>
        <td></td>
    </tr>
    <tr>
        <td height=60 valign=top>Tanda tangan/ Tanggal</td>
        <td>
            @if($barcodes['asesor1'])
            <img src="{{ $barcodes['asesor1']['url'] }}"><br>{{ now()->translatedFormat('d F Y') }}
            @else
                <div></div>
            @endif
        </td>
    </tr>
</table>

</body>
</html>
