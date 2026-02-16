<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>FR.APL-01</title>

<style>
    body {
        font-family: Arial, Helvetica, sans-serif;
        font-size: 11px;
        margin: 20px;
        color: #000;
    }

    table {
        border-collapse: collapse;
        width: 100%;
    }

    td, th {
        border: 1px solid #000;
        padding: 4px 6px;
        vertical-align: top;
    }

    .no-border td {
        border: none;
    }

    .header {
        font-weight: bold;
        font-size: 13px;
        margin-bottom: 8px;
    }

    .section-title {
        font-weight: bold;
        margin: 10px 0 5px 0;
    }

    .red {
        background: #c00000;
        color: #fff;
        text-align: center;
        font-weight: bold;
    }

    .center {
        text-align: center;
    }

    .bold {
        font-weight: bold;
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

    .checked::after {
        content: "✔";
    }

    .qr {
        width: 80px;
        height: 80px;
        border: 1px solid #000;
        display: inline-block;
    }

    .logo {
        height: 40px;
    }

    .small {
        font-size: 10px;
    }
</style>
</head>
<body>

<!-- HEADER -->
<table class="no-border">
    <tr>
        <td width="20%">
            <img src="{{ public_path('images/bnsp.webp') }}" class="logo">
        </td>
        <td width="60%" class="center bold">
            FR.APL-01. PERMOHONAN SERTIFIKASI KOMPETENSI
        </td>
        <td width="20%" class="center">
            <img src="{{ public_path('images/logo-lsp.png') }}" class="logo">
        </td>
    </tr>
</table>

<!-- BAGIAN 1 -->
<div class="section-title">Bagian 1: Rincian Data Pemohon Sertifikasi</div>

<table>
    <tr>
        <td width="25%">Nama</td>
        <td colspan="3">KEVIN SINAR UTAMA GIRSANG</td>
    </tr>
    <tr>
        <td>No. NIK</td>
        <td colspan="3">3275092107000015</td>
    </tr>
    <tr>
        <td>Tempat/tgl. Lahir</td>
        <td colspan="3">T. TIRAM, 21-Juli-2000</td>
    </tr>
    <tr>
        <td>Jenis kelamin</td>
        <td colspan="3">Pria</td>
    </tr>
    <tr>
        <td>Kebangsaan</td>
        <td colspan="3">ID</td>
    </tr>
    <tr>
        <td>Alamat rumah</td>
        <td colspan="3">
            PERUM. TAMAN JATISARI PERMAI BDP, JL. CILIWUNG BLOK DM 17 RT 05/RW 15,
            KEL. JATISARI, KEC. JATIASIH
        </td>
    </tr>
    <tr>
        <td rowspan="2">No. Telp / Email</td>
        <td>Rumah: 0895333459697</td>
        <td>HP:</td>
        <td>Email: kevingirsang123@gmail.com</td>
    </tr>
    <tr>
        <td>Kantor:</td>
        <td colspan="2"></td>
    </tr>
    <tr>
        <td>Kualifikasi / Pendidikan</td>
        <td colspan="3">Teknisi / Analis</td>
    </tr>
</table>

<!-- DATA PEKERJAAN -->
<div class="section-title">b. Data Pekerjaan Sekarang</div>

<table>
    <tr>
        <td width="25%">Perusahaan / Lembaga</td>
        <td colspan="3"></td>
    </tr>
    <tr>
        <td>Jabatan</td>
        <td colspan="3"></td>
    </tr>
    <tr>
        <td>Alamat</td>
        <td colspan="3"></td>
    </tr>
    <tr>
        <td>Telp</td>
        <td></td>
        <td>Fax</td>
        <td></td>
    </tr>
    <tr>
        <td>Email</td>
        <td colspan="3"></td>
    </tr>
</table>

<!-- BAGIAN 2 -->
<div class="section-title">Bagian 2: Data Sertifikasi</div>

<table>
    <tr>
        <td width="25%">Skema Sertifikasi Okupasi Nasional</td>
        <td>Judul: Perakit Struktur Bangunan RISHA<br>Nomor: SKEMA-116/LSP-GKK/2023</td>
    </tr>
    <tr>
        <td>Tujuan Asesmen</td>
        <td>
            <span class="checkbox checked"></span> Sertifikasi &nbsp;&nbsp;
            <span class="checkbox"></span> Sertifikasi Ulang &nbsp;&nbsp;
            <span class="checkbox"></span> PKT &nbsp;&nbsp;
            <span class="checkbox"></span> RPL
        </td>
    </tr>
</table>

<!-- UNIT KOMPETENSI -->
<table>
    <tr class="red">
        <td width="5%">No</td>
        <td width="20%">Kode Unit</td>
        <td>Judul Unit</td>
        <td width="20%">Jenis Standar</td>
    </tr>

    @php
        $units = [
            ['F.41GED01.010.1','Melaksanakan Pekerjaan Persiapan Lokasi Kerja'],
            ['F.41GED01.011.1','Melaksanakan Pekerjaan Pondasi'],
            ['F.41GED01.012.1','Melaksanakan Pekerjaan Struktur RISHA'],
            ['F.41GED01.013.1','Melaksanakan Pekerjaan Pemasangan Rangka Atap'],
            ['F.41GED01.014.1','Melaksanakan Pekerjaan Dinding'],
            ['F.41GED01.015.1','Melaksanakan Pekerjaan Lantai'],
            ['F.41GED01.016.1','Melaksanakan Pekerjaan Pemasangan Penutup Atap'],
            ['F.41GED01.019.1','Melakukan Pemeriksaan Pascaperakitan'],
            ['F.41GED01.020.1','Melaksanakan Pemeliharaan Bangunan RISHA'],
            ['F.41GED01.021.1','Melaksanakan Pemeliharaan Utilitas'],
            ['F.41GED01.018.1','Melakukan Pemeriksaan Praperakitan'],
        ];
    @endphp

    @foreach ($units as $i => $u)
    <tr>
        <td class="center">{{ $i+1 }}</td>
        <td>{{ $u[0] }}</td>
        <td>{{ $u[1] }}</td>
        <td class="center">SKKNI 221-2018</td>
    </tr>
    @endforeach
</table>

<!-- BAGIAN 3 -->
<div class="section-title">Bagian 3: Bukti Kelengkapan Pemohon</div>

<table>
    <tr class="red">
        <td>No</td>
        <td>Bukti Persyaratan Dasar</td>
        <td colspan="2">Ada</td>
        <td>Tidak Ada</td>
    </tr>

    <tr>
        <td>1</td>
        <td>Copy ijazah S1/S1 Terapan/D4 Terapan</td>
        <td class="center"><span class="checkbox checked"></span></td>
        <td></td>
        <td></td>
    </tr>
</table>

<table>
    <tr class="red">
        <td>No</td>
        <td>Bukti Administratif</td>
        <td colspan="2">Ada</td>
        <td>Tidak Ada</td>
    </tr>

    <tr>
        <td>1</td>
        <td>Pas foto 3x4 (2 lembar)</td>
        <td class="center"><span class="checkbox checked"></span></td>
        <td></td>
        <td></td>
    </tr>
    <tr>
        <td>2</td>
        <td>Copy KTP</td>
        <td class="center"><span class="checkbox checked"></span></td>
        <td></td>
        <td></td>
    </tr>
</table>

<!-- SIGNATURE -->
<table>
    <tr>
        <td width="50%">
            <b>Pemohon</b><br>
            Nama: KEVIN SINAR UTAMA GIRSANG<br><br>
            <div class="qr"></div><br>
            27-Agustus-2025
        </td>
        <td width="50%">
            <b>Admin LSP GKK</b><br>
            Nama: Alfiana Dhora<br><br>
            <div class="qr"></div><br>
            27-Agustus-2025
        </td>
    </tr>
</table>

</body>
</html>
