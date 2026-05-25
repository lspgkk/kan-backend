@php
    // ========================
    // Page 1 - Surat Peminjaman TUK
    // ========================
    $tanggalSurat        = '';
    $noSuratPeminjaman   = '';
    $namaKetuaTUK        = '';
    $hariTanggal         = '';
    $waktu               = '';
    $tempat              = '';
    $namaDirektur        = '';

    // ========================
    // Page 2 - Surat Tugas
    // ========================
    $noSuratTugas        = '';
    $namaVerifikator     = '';
    $namaTUK             = '';
    $alamatTUK           = '';
    $skemaSertifikasi    = '';
    $tanggalVerifikasi   = '';

    // ========================
    // Page 3 - Form Checklist
    // ========================
    $metodeUji           = '';
    $checklistItems      = [];

    // ========================
    // Page 4 - Berita Acara
    // ========================
    $noBeritaAcara        = '';
    $hariTanggalVerifikasi = '';
    $metodeVerifikasi     = '';
    $hariTanggalUji       = '';
    $namaAsesor           = '';

    // ========================
    // Page 5 - Hasil Verifikasi
    // ========================
    $rekomendasi          = '';

    // ========================
    // Page 6 & 7 - SK & Lampiran (conditional)
    // ========================
    $noSk                = '';
    $namaAdminTUK        = '';
    $statusTUK           = '';
    $tanggalKeputusan    = '';
    $jenjang             = '';

    // ========================
    // Control flags
    // ========================
    $showSk              = false;
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Verifikasi TUK Non-AJJ</title>
<style>

@page { margin: 0cm 0cm; size: A4; }

body {
    margin-top: 3cm;
    margin-left: 2cm;
    margin-right: 2cm;
    margin-bottom: 2cm;
    font-family: Arial, sans-serif;
    font-size: 14px;
    background: #fff;
    line-height: 1.35;
    color: #000;
}

header {
    position: fixed;
    top: 0cm; left: 0cm; right: 0cm;
    height: 3cm;
}

header img { width: 100%; height: 100%; }

footer {
    position: fixed;
    bottom: 0cm; left: 0cm; right: 0cm;
    height: 2cm;
}

footer img { width: 100%; height: 100%; }

/* ---------- Layout ---------- */
.center { text-align: center; }
.left   { text-align: left; }
.right  { text-align: right; }
.justify { text-align: justify; }

.bold       { font-weight: bold; }
.underline  { text-decoration: underline; }
.normal     { font-weight: normal; }

.mt-1  { margin-top: 6px; }
.mt-2  { margin-top: 12px; }
.mt-3  { margin-top: 20px; }
.mt-4  { margin-top: 30px; }
.mb-1  { margin-bottom: 6px; }
.mb-2  { margin-bottom: 12px; }

/* ---------- Paragraph helpers ---------- */
.para {
    text-align: justify;
    margin-bottom: 10px;
}

/* ---------- Key-value detail table ---------- */
.detail {
    width: 100%;
    border-collapse: collapse;
}
.detail td {
    padding: 5px 0;
    vertical-align: top;
}
.detail .lbl {
    width: 160px;
}
.detail .cln {
    width: 14px;
}

/* ---------- Bordered table ---------- */
table.bordered {
    width: 100%;
    border-collapse: collapse;
}
table.bordered td, table.bordered th {
    border: 1px solid #000;
    padding: 6px 4px;
    vertical-align: middle;
    text-align: center;
}
table.bordered td.left {
    text-align: left;
}

/* ---------- Signature block ---------- */
.sign-wrap {
    margin-top: 30px;
    display: flex;
    justify-content: flex-end;
}
.sign-box {
    text-align: left;
}
.sign-box .gap {
    display: inline-block;
    width: 100%;
    height: 80px;
}

/* ---------- Small utility ---------- */
.nowrap { white-space: nowrap; }
.vtop   { vertical-align: top; }

/* ---------- Print ---------- */
@media print {
    body {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
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

<!-- ============================================================
     PAGE 1 — SURAT PEMINJAMAN TUK
     ============================================================ -->
<div class="right">
    Jakarta, {{ $tanggalSurat }}
</div>

<br>

<table class="detail">
    <tr>
        <td class="lbl">No</td>
        <td class="cln">:</td>
        <td>{{ $noSuratPeminjaman }}</td>
    </tr>
    <tr>
        <td class="lbl">Lampiran</td>
        <td class="cln">:</td>
        <td>1 (satu) lembar</td>
    </tr>
    <tr>
        <td class="lbl">Hal</td>
        <td class="cln">:</td>
        <td>Permohonan Peminjaman Tempat Uji Kompetensi</td>
    </tr>
</table>

<br>

<div>Kepada Yth.</div>
<div>Pimpinan TUK {{ $namaKetuaTUK }}</div>
<div>di-</div>
<div style="padding-left: 14px;">Tempat</div>

<br>

<p class="para">
    Sehubungan dengan adanya asesmen atau uji kompetensi oleh Lembaga Sertifikasi
    Profesi Gatensi Karya Konstruksi yang akan dilaksanakan pada:
</p>

<table class="detail">
    <tr>
        <td style="width: 40px;"></td>
        <td class="lbl">Hari, Tanggal</td>
        <td class="cln">:</td>
        <td>{{ $hariTanggal }}</td>
    </tr>
    <tr>
        <td style="width: 40px;"></td>
        <td class="lbl">Waktu</td>
        <td class="cln">:</td>
        <td>{{ $waktu }}</td>
    </tr>
    <tr>
        <td style="width: 40px;"></td>
        <td class="lbl">Tempat</td>
        <td class="cln">:</td>
        <td>{{ $tempat }}</td>
    </tr>
</table>

<br>

<p class="para">
    Dengan ini mengajukan permohonan pemakaian tempat uji kompetensi untuk skema
    sertifikasi sebagaimana terlampir.
</p>

<p class="para">
    Demikian surat ini kami sampaikan, atas kerja samanya kami ucapkan terima kasih.
</p>

<br><br>

<div class="sign-wrap">
    <div class="sign-box">
        Hormat kami,<br>
        Jakarta, {{ $tanggalSurat }}<br>
        <br>
        <span class="bold">LSP GATENSI KARYA KONSTRUKSI</span><br>
        <br><br><br>
        <div class="bold underline" style="font-size: 14px;">{{ $namaDirektur }}</div>
        <div>Direktur</div>
    </div>
</div>

<!-- ============================================================
     PAGE 2 — SURAT TUGAS
     ============================================================ -->
<div style="page-break-before: always;"></div>

<div class="center bold underline" style="font-size: 15px;">
    SURAT TUGAS
</div>

<div class="center" style="margin-top: 4px; margin-bottom: 28px;">
    Nomor : <span class="bold">{{ $noSuratTugas }}</span>
</div>

<p class="para">
    Yang bertandatangan di bawah ini Direktur Lembaga Sertifikasi Profesi Gatensi
    Karya Konstruksi dengan ini memberi tugas kepada Verifikator Tempat Uji
    Kompetensi (TUK):
</p>

<div class="center bold" style="margin: 14px 0;">
    {{ $namaVerifikator }}
</div>

<p class="para">
    Untuk melakukan verifikasi Persyaratan Teknis Tempat Uji Kompetensi (TUK)
    bidang konstruksi di:
</p>

<table class="detail">
    <tr>
        <td class="lbl">Nama TUK</td>
        <td class="cln">:</td>
        <td>{{ $namaTUK }}</td>
    </tr>
    <tr>
        <td class="lbl">Alamat</td>
        <td class="cln">:</td>
        <td>{{ $alamatTUK }}</td>
    </tr>
    <tr>
        <td class="lbl">Hari, Tanggal</td>
        <td class="cln">:</td>
        <td>{{ $hariTanggal }}</td>
    </tr>
    <tr>
        <td class="lbl">Skema Sertifikasi</td>
        <td class="cln">:</td>
        <td>{{ $skemaSertifikasi }}</td>
    </tr>
</table>

<br>

<p class="para">
    Demikian Surat tugas ini diberikan untuk dilaksanakan dengan penuh tanggung jawab.
</p>

<br><br>

<div class="sign-wrap">
    <div class="sign-box">
        Jakarta, {{ $tanggalVerifikasi }}<br>
        <br>
        <span class="bold">LSP GATENSI KARYA KONSTRUKSI</span><br>
        <br><br><br>
        <div class="bold underline" style="font-size: 14px;">{{ $namaDirektur }}</div>
        <div>Direktur</div>
    </div>
</div>

<!-- ============================================================
     PAGE 3 — FORM CHECKLIST VERIFIKASI TEMPAT UJI KOMPETENSI
     ============================================================ -->
<div style="page-break-before: always;"></div>

<div class="center bold" style="font-size: 15px; margin-bottom: 16px;">
    FORM CHECKLIST VERIFIKASI TEMPAT UJI KOMPETENSI
</div>

<table class="detail">
    <tr>
        <td class="lbl">Nama TUK</td>
        <td class="cln">:</td>
        <td>{{ $namaTUK }}</td>
    </tr>
    <tr>
        <td class="lbl">Alamat</td>
        <td class="cln">:</td>
        <td>{{ $alamatTUK }}</td>
    </tr>
    <tr>
        <td class="lbl">Hari, Tanggal</td>
        <td class="cln">:</td>
        <td>{{ $hariTanggal }}</td>
    </tr>
    <tr>
        <td class="lbl">Metode Asesmen</td>
        <td class="cln">:</td>
        <td class="bold">{{ $metodeUji }}</td>
    </tr>
    <tr>
        <td class="lbl">Skema Sertifikasi</td>
        <td class="cln">:</td>
        <td class="bold">{{ $skemaSertifikasi }}</td>
    </tr>
</table>

<br>
<table class="bordered">
    <tr>
        <th rowspan="2" style="width: 30px;">No.</th>
        <th rowspan="2">Perlengkapan</th>
        <th rowspan="2">Spesifikasi</th>
        <th rowspan="2" style="width: 55px;">Jumlah</th>
        <th colspan="2">Ketersediaan</th>
        <th colspan="2">Kelayakan</th>
    </tr>
    <tr>
        <th style="width: 45px;">Ada</th>
        <th style="width: 55px;">Tidak Ada</th>
        <th style="width: 48px;">Layak</th>
        <th style="width: 60px;">Tidak Layak</th>
    </tr>

    @if(count($checklistItems) > 0)
        @foreach($checklistItems as $idx => $item)
        <tr>
            <td>{{ $idx + 1 }}</td>
            <td class="left">{{ $item['nama'] ?? '' }}</td>
            <td class="left">{{ $item['spesifikasi'] ?? '' }}</td>
            <td>{{ $item['jumlah'] ?? '' }}</td>
            <td>@if(!empty($item['tersedia'])) {{ 'v' }} @endif</td>
            <td>@if(empty($item['tersedia']) && isset($item['tersedia'])) {{ 'v' }} @endif</td>
            <td>@if(!empty($item['layak'])) {{ 'v' }} @endif</td>
            <td>@if(empty($item['layak']) && isset($item['layak'])) {{ 'v' }} @endif</td>
        </tr>
        @endforeach
    @else
        <tr>
            <td colspan="8" style="text-align: center; padding: 20px 0;">
                (Belum ada data checklist)
            </td>
        </tr>
    @endif
</table>

<!-- ============================================================
     PAGE 4 — BERITA ACARA VERIFIKASI TUK
     ============================================================ -->
<div style="page-break-before: always;"></div>

<br>

<div class="center bold underline" style="font-size: 15px;">
    BERITA ACARA VERIFIKASI TUK
</div>

<div class="center" style="margin-top: 4px; margin-bottom: 24px;">
    <span class="bold">Nomor : </span><span class="bold">{{ $noBeritaAcara }}</span>
</div>

<p class="para">
    Bahwa telah dilakukan Verifikasi TUK Sewaktu terhadap:
</p>

<table class="detail" style="margin-top: 10px;">
    <tr>
        <td class="lbl">Nama TUK</td>
        <td class="cln">:</td>
        <td>{{ $namaTUK }}</td>
    </tr>
    <tr>
        <td class="lbl">Alamat</td>
        <td class="cln">:</td>
        <td>{{ $alamatTUK }}</td>
    </tr>
    <tr>
        <td class="lbl">Hari, Tanggal Verifikasi</td>
        <td class="cln">:</td>
        <td>{{ $hariTanggalVerifikasi }}</td>
    </tr>
    <tr>
        <td class="lbl">Metode Verifikasi</td>
        <td class="cln">:</td>
        <td class="bold">{{ $metodeVerifikasi }}</td>
    </tr>
    <tr>
        <td class="lbl">Hari, Tanggal Uji</td>
        <td class="cln">:</td>
        <td>{{ $hariTanggalUji }}</td>
    </tr>
    <tr>
        <td class="lbl">Metode Uji</td>
        <td class="cln">:</td>
        <td class="bold">{{ $metodeUji }}</td>
    </tr>
</table>

<br>

<p class="para">
    Untuk menyelenggarakan Uji Kompetensi dengan Skema sertifikasi:
</p>

<table class="bordered">
    <tr>
        <th style="width: 36px;">No.</th>
        <th>TUK Skema Sertifikasi</th>
    </tr>
    <tr>
        <td>1.</td>
        <td class="left">{{ $skemaSertifikasi }}</td>
    </tr>
</table>

<br>

<p class="para">
    Dengan ini menetapkan TUK sesuai Skema Sertifikasi dengan metode uji Observasi
    di atas <span class="bold">Layak / Tidak Layak</span> sebagai Tempat Uji
    Kompetensi (TUK) terverifikasi.
</p>

<br>

<table class="bordered" style="width: 80%; margin: 0 auto;">
    <tr>
        <th>Verifikator</th>
        <th>Tanda Tangan</th>
    </tr>
    <tr>
        <td style="height: 60px;">{{ $namaAsesor }}</td>
        <td></td>
    </tr>
</table>

<br>

<div style="font-style: italic;">*) Coret yang tidak perlu</div>

<!-- ============================================================
     PAGE 5 — HASIL VERIFIKASI DAN STANDARDISASI TUK
     ============================================================ -->
<div style="page-break-before: always;"></div>

<div class="center bold" style="font-size: 15px; margin-bottom: 16px;">
    HASIL VERIFIKASI DAN STANDARDISASI TUK
</div>

<table class="bordered">
    <tr>
        <th rowspan="2" style="width: 30px;">No</th>
        <th rowspan="2">Unsur Yang Diverifikasi</th>
        <th colspan="2">Hasil Verifikasi</th>
    </tr>
    <tr>
        <th style="width: 70px;">Layak</th>
        <th style="width: 70px;">Tidak Layak</th>
    </tr>
    <tr>
        <td>1</td>
        <td class="left">
            1. Standar Persyaratan Sarana dan Prasarana Metode Uji {{ $metodeUji }}
        </td>
        <td>@if($rekomendasi === 'layak') v @endif</td>
        <td>@if($rekomendasi === 'tidak_layak') v @endif</td>
    </tr>
    <tr>
        <td>2</td>
        <td class="left">
            2. Standar Persyaratan Peralatan Metode Uji {{ $metodeUji }}
        </td>
        <td>@if($rekomendasi === 'layak') v @endif</td>
        <td>@if($rekomendasi === 'tidak_layak') v @endif</td>
    </tr>
</table>

<br>

<div>
    Rekomendasi:
    <span class="bold">
        @if($rekomendasi === 'layak') Layak @else Tidak Layak @endif
    </span>
</div>
<div style="padding-left: 145px;">
    Sebagai tempat Penyelenggaraan Uji Sertifikasi Kompetensi
</div>

<br>

<table class="detail">
    <tr>
        <td style="width: 50%;"></td>
        <td style="width: 50%;">
            Jakarta, {{ $tanggalVerifikasi }}
        </td>
    </tr>
    <tr>
        <td class="bold">Penanggung Jawab TUK</td>
        <td class="bold">Pemeriksa</td>
    </tr>
    <tr>
        <td style="height: 80px; vertical-align: bottom;">
            <div class="bold">{{ $namaKetuaTUK }}</div>
            <div>Ketua TUK</div>
        </td>
        <td style="height: 80px; vertical-align: bottom;">
            <div class="bold">{{ $namaVerifikator }}</div>
            <div>Asesor Kompetensi</div>
        </td>
    </tr>
</table>

<!-- ============================================================
     PAGE 6 — SURAT KEPUTUSAN (only if $showSk is true)
     ============================================================ -->
@if($showSk)
<div style="page-break-before: always;"></div>

<div class="center bold" style="font-size: 15px;">
    SURAT KEPUTUSAN
</div>
<div class="center bold" style="font-size: 15px;">
    DIREKTUR LEMBAGA SERTIFIKASI PROFESI GATENSI KARYA KONSTRUKSI
</div>
<div class="center" style="margin-top: 4px; margin-bottom: 10px;">
    NOMOR: {{ $noSk }}
</div>
<br>
<div class="center" style="margin-bottom: 10px;">
    TENTANG
</div>
<div class="center bold">
    PENETAPAN TEMPAT UJI KOMPETENSI (TUK) TERVERIFIKASI
</div>
<div class="center bold">
    LEMBAGA SERTIFIKASI PROFESI GATENSI KARYA KONSTRUKSI
</div>
<div style="border-top: 3px solid #000; margin: 8px 0; height: 12px;"></div>

<table class="detail">
    <tr>
        <td style="width: 96px; vertical-align: top; padding-left: 8px;">Menimbang</td>
        <td style="width: 14px;">:</td>
        <td>
            <ol style="padding-left: 18px; margin: 0;">
                <li>bahwa dalam rangka penjaminan mutu sertifikasi kompetensi perlu Tempat Uji Kompetensi (TUK) yang sudah sesuai dengan prosedur;</li>
                <li>bahwa berdasarkan poin b maka perlu ditetapkan Tempat Uji Kompetensi (TUK) yang sudah terverifikasi.</li>
            </ol>
        </td>
    </tr>
    <tr>
        <td style="width: 96px; vertical-align: top; padding-left: 8px;">Mengingat</td>
        <td style="width: 14px;">:</td>
        <td>
            <ol style="padding-left: 18px; margin: 0;">
                <li>Undang-Undang Republik Indonesia Nomor 13 Tahun 2003 Tentang Ketenagakerjaan;</li>
                <li>Undang-undang (UU) Nomor 6 Tahun 2023 tentang Penetapan Peraturan Pemerintah Pengganti Undang-Undang Nomor 2 Tahun 2022 tentang Cipta Kerja menjadi Undang-Undang;</li>
                <li>Undang-undang Republik Indonesia Nomor 2 Tahun 2017 tentang Jasa Konstruksi;</li>
                <li>Peraturan Pemerintah Republik Indonesia Nomor 23 Tahun 2004 tentang Badan Nasional Sertifikasi;</li>
                <li>Peraturan Pemerintah Republik Indonesia Nomor 10 Tahun 2018 Tentang Badan Nasional Sertifikasi Profesi;</li>
                <li>Peraturan Pemerintah Republik Indonesia Nomor 14 Tahun 2021 tentang Perubahan Atas Peraturan Pemerintah No 22 Tahun 2020 Tentang Peraturan Pelaksanaan UUD No 2 Tahun 2017 Tentang Jasa Konstruksi;</li>
                <li>Peraturan Badan Nasional Sertifikasi Profesi Nomor: 1/BNSP/III/2014 tentang pedoman penilaian kesesuaian persyaratan umum Lembaga Sertifikasi Profesi;</li>
                <li>Peraturan Badan Nasional Sertifikasi Profesi Nomor 5 Tahun 2014 Tentang Pedoman Persyaratan Umum Tempat Uji Kompetensi;</li>
            </ol>
        </td>
    </tr>
    <tr>
        <td colspan="3" class="center bold" style="padding: 24px 0 8px 0;">
            M E M U T U S K A N
        </td>
    </tr>
    <tr>
        <td style="width: 96px; vertical-align: top; padding-left: 8px;">Menetapkan</td>
        <td style="width: 14px;">:</td>
        <td>
            SURAT KEPUTUSAN DIREKTUR LEMBAGA SERTIFIKASI PROFESI GATENSI KARYA KONSTRUKSI TENTANG PENETAPAN TEMPAT UJI KOMPETENSI (TUK) TERVERIFIKASI LEMBAGA SERTIFIKASI PROFESI GATENSI KARYA KONSTRUKSI
        </td>
    </tr>
    <tr>
        <td style="width: 96px; vertical-align: top; padding-left: 8px;">Kesatu</td>
        <td style="width: 14px;">:</td>
        <td>{{ $namaTUK }} sebagai TUK Sewaktu Ulang Terverifikasi.</td>
    </tr>
    <tr>
        <td style="width: 96px; vertical-align: top; padding-left: 8px;">Kedua</td>
        <td style="width: 14px;">:</td>
        <td>{{ $namaAdminTUK }} sebagai Admin TUK {{ $namaTUK }}</td>
    </tr>
    <tr>
        <td style="width: 96px; vertical-align: top; padding-left: 8px;">Ketiga</td>
        <td style="width: 14px;">:</td>
        <td>{{ $namaKetuaTUK }} sebagai Penanggungjawab TUK {{ $namaTUK }}</td>
    </tr>
</table>

<div style="page-break-before: always;"></div>

<table class="detail">
    <tr>
        <td style="width: 96px; vertical-align: top; padding-left: 8px;">Keempat</td>
        <td style="width: 14px;">:</td>
        <td>
            Berdasarkan hasil berita acara verifikasi, TUK {{ $namaTUK }} ditetapkan
            {{ $statusTUK }} untuk melaksanakan Uji Kompetensi pada tanggal
            {{ $tanggalVerifikasi }}.
        </td>
    </tr>
    <tr>
        <td style="width: 96px; vertical-align: top; padding-left: 8px;">Kelima</td>
        <td style="width: 14px;">:</td>
        <td>
            Berdasarkan hasil berita acara verifikasi, TUK {{ $namaTUK }} ditetapkan
            {{ $statusTUK }} untuk melaksanakan Uji Kompetensi dengan skema terlampir.
        </td>
    </tr>
    <tr>
        <td style="width: 96px; vertical-align: top; padding-left: 8px;">Keenam</td>
        <td style="width: 14px;">:</td>
        <td>Keputusan ini berlaku sejak tanggal ditetapkan.</td>
    </tr>
</table>

<br><br><br>

<div class="sign-wrap">
    <div class="sign-box">
        <table class="detail">
            <tr>
                <td style="width: 100px; padding-left: 8px;">Ditetapkan di</td>
                <td style="width: 14px;">:</td>
                <td>Jakarta</td>
            </tr>
            <tr>
                <td style="width: 100px; padding-left: 8px;">Tanggal</td>
                <td style="width: 14px;">:</td>
                <td>{{ $tanggalKeputusan }}</td>
            </tr>
        </table>
        <br>
        <div class="center bold">LSP GATENSI KARYA KONSTRUKSI</div>
        <br><br><br>
        <div class="center bold underline" style="font-size: 14px;">{{ $namaDirektur }}</div>
        <div class="center">Direktur</div>
    </div>
</div>

<!-- ============================================================
     PAGE 7 — LAMPIRAN SURAT KEPUTUSAN (only if $showSk is true)
     ============================================================ -->
<div style="page-break-before: always;"></div>

<div class="center bold" style="font-size: 13px; margin-top: 2px;">
    LAMPIRAN SURAT KEPUTUSAN NOMOR: {{ $noSk }}
</div>

<div class="center bold" style="font-size: 13px; margin-top: 2px;">
    TENTANG PENETAPAN TEMPAT UJI KOMPETENSI (TUK) TERVERIFIKASI
</div>

<div class="center bold" style="margin-top: 2px;">
    LEMBAGA SERTIFIKASI PROFESI GATENSI KARYA KONSTRUKSI
</div>

<br><br><br>

<div class="bold">Daftar Skema</div>

<ol style="padding-left: 18pt;">
    <li>{{ $skemaSertifikasi }} — {{ $jenjang }}</li>
</ol>

<br><br><br>

<div class="sign-wrap">
    <div class="sign-box">
        <div class="center bold">LSP GATENSI KARYA KONSTRUKSI</div>
        <br><br><br>
        <div class="center bold underline" style="font-size: 14px;">{{ $namaDirektur }}</div>
        <div class="center">Direktur</div>
    </div>
</div>

@endif

</body>
</html>
