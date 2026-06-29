@php
    if (!isset($tanggalSurat))         $tanggalSurat         = '';
    if (!isset($noSuratPeminjaman))    $noSuratPeminjaman    = '';
    if (!isset($noSuratTugas))         $noSuratTugas         = '';
    if (!isset($noBeritaAcara))        $noBeritaAcara        = '';
    if (!isset($noSk))                 $noSk                 = '';
    if (!isset($namaKetuaTUK))         $namaKetuaTUK         = '';
    if (!isset($namaDirektur))         $namaDirektur         = '';
    if (!isset($namaTUK))              $namaTUK              = '';
    if (!isset($alamatTUK))            $alamatTUK            = '';
    if (!isset($hariTanggal))          $hariTanggal          = '';
    if (!isset($waktu))                $waktu                = '';
    if (!isset($tempat))               $tempat               = '';
    if (!isset($tanggalVerifikasi))    $tanggalVerifikasi    = '';
    if (!isset($tanggalKeputusan))     $tanggalKeputusan     = '';
    if (!isset($skemaSertifikasi))     $skemaSertifikasi     = '';
    if (!isset($jenjang))              $jenjang              = '';
    if (!isset($metodeVerifikasi))     $metodeVerifikasi     = 'Daring';
    if (!isset($namaVerifikator))      $namaVerifikator      = '';
    if (!isset($verifikatorNoreg))     $verifikatorNoreg     = '';
    if (!isset($asesorList))           $asesorList           = [];
    if (!isset($rekomendasi))          $rekomendasi          = '';
    if (!isset($statusTUK))            $statusTUK            = '';
    if (!isset($qrDirektur))           $qrDirektur           = '';
    if (!isset($qrKetuaTuk))           $qrKetuaTuk           = '';
    if (!isset($qrVerifikator))        $qrVerifikator        = '';
    if (!isset($qrDirekturSk))         $qrDirekturSk         = '';
    if (!isset($showSk))               $showSk               = false;
    if (!isset($ruanganUjiImages))     $ruanganUjiImages     = [];
    if (!isset($laptopImages))         $laptopImages         = [];
    if (!isset($jaringanImages))       $jaringanImages       = [];
    if (!isset($kondisiRuangan))       $kondisiRuangan       = null;
    if (!isset($keteranganRuangan))    $keteranganRuangan    = null;
    if (!isset($kondisiLaptop))        $kondisiLaptop        = null;
    if (!isset($keteranganLaptop))     $keteranganLaptop     = null;
    if (!isset($kondisiJaringan))      $kondisiJaringan      = null;
    if (!isset($keteranganJaringan))   $keteranganJaringan   = null;
    if (!isset($jumlahPeserta))        $jumlahPeserta        = 0;
    if (!isset($skemaSertifikasiList)) $skemaSertifikasiList = [];
    if (!isset($namaAdminTUK))         $namaAdminTUK         = '';
    if (!isset($namaAsesi))            $namaAsesi            = '';
    if (!isset($background_image))     $background_image     = '';
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Verifikasi TUK AJJ</title>
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

.center { text-align: center; }
.left   { text-align: left; }
.right  { text-align: right; }
.justify { text-align: justify; }
.bold       { font-weight: bold; }
.underline  { text-decoration: underline; }
.mt-1  { margin-top: 6px; }
.mt-2  { margin-top: 12px; }
.mt-3  { margin-top: 20px; }
.mt-4  { margin-top: 30px; }
.mb-1  { margin-bottom: 6px; }
.mb-2  { margin-bottom: 12px; }

.para {
    text-align: justify;
    margin-bottom: 10px;
}

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

.doc-img {
    max-width: 120px;
    max-height: 100px;
}

.sign-wrap {
    margin-top: 30px;
    display: flex;
    justify-content: flex-end;
}
.sign-box {
    text-align: left;
}

.sk-content {
    margin-top: 6px;
}
.sk-content table {
    width: 100%;
    border-collapse: collapse;
}
.sk-content td {
    vertical-align: top;
    padding: 3px 0;
    font-size: 13px;
}
.sk-content td.label {
    width: 110px;
}
.sk-content td.separator {
    width: 16px;
    text-align: center;
}
.sk-content td.index {
    width: 22px;
}

.nowrap { white-space: nowrap; }
.vtop   { vertical-align: top; }

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
        <td class="lbl">Perihal</td>
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
    Sehubungan dengan adanya pelaksanaan kegiatan Asesmen atau uji kompetensi oleh Lembaga Sertifikasi
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
        <td class="lbl">Pukul</td>
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
    Dengan ini mengajukan permohonan pemakaian Tempat Uji Kompetensi untuk Skema sertifikasi
    sebagaimana terlampir.
</p>

<p class="para">
    Demikian permohonan ini kami sampaikan, atas perhatian dan kerja samanya kami ucapkan terima kasih.
</p>

<br><br>

<div class="sign-wrap">
    <div class="sign-box">
        Hormat kami,<br>
        Jakarta, {{ $tanggalSurat }}<br>
        <br>
        <span class="bold">LSP GATENSI KARYA KONSTRUKSI</span><br>
        <br>
        @if($qrDirektur)
        {!! $qrDirektur !!}<br>
        @endif
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
    Karya Konstruksi dengan ini memberi tugas kepada Tim Verifikator Tempat Uji
    Kompetensi (TUK):
</p>

<br>

@if(count($asesorList) > 0)
<ul style="list-style-type: disc; padding-left: 24px;">
    @foreach($asesorList as $asesor)
    <li class="bold">{{ $asesor }}</li>
    @endforeach
</ul>
@else
<div class="center bold" style="margin: 14px 0;">
    {{ $namaVerifikator ?: '-' }}
</div>
@endif

<br>

<p class="para">
    Untuk melakukan verifikasi Persyaratan Teknis Tempat Uji Kompetensi (TUK) bidang
    konstruksi di:
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
        <td class="lbl">Metode Verifikasi</td>
        <td class="cln">:</td>
        <td class="bold">{{ $metodeVerifikasi }}</td>
    </tr>
    <tr>
        <td class="lbl">Skema Sertifikasi</td>
        <td class="cln">:</td>
        <td>
            <ul style="list-style-type: disc; padding-left: 18px; margin: 0;">
                @foreach($skemaSertifikasiList as $skema)
                <li class="bold">{{ $skema }}</li>
                @endforeach
            </ul>
        </td>
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
        <br>
        @if($qrDirektur)
        {!! $qrDirektur !!}<br>
        @endif
        <div class="bold underline" style="font-size: 14px;">{{ $namaDirektur }}</div>
        <div>Direktur</div>
    </div>
</div>

<!-- ============================================================
     PAGE 3 — BERITA ACARA VERIFIKASI TUK
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
        <td class="lbl">Hari, Tanggal</td>
        <td class="cln">:</td>
        <td>{{ $hariTanggal }}</td>
    </tr>
    <tr>
        <td class="lbl">Jenis Asesmen</td>
        <td class="cln">:</td>
        <td class="bold">AJJ (Asesmen Jarak Jauh)</td>
    </tr>
    <tr>
        <td class="lbl">Metode Asesmen</td>
        <td class="cln">:</td>
        <td class="bold">Daring</td>
    </tr>
</table>

<br>

<p class="para">
    Untuk menyelenggarakan Uji Kompetensi dengan Skema sertifikasi:
</p>

<table class="bordered">
    <tr>
        <th style="width: 36px;">No.</th>
        <th>Skema Sertifikasi</th>
        <th>Jenjang</th>
    </tr>
    <tr>
        <td>1.</td>
        <td class="left bold">{{ $skemaSertifikasi }}</td>
        <td>{{ $jenjang }}</td>
    </tr>
</table>

<br>

<p class="para">
    Dengan ini menetapkan TUK Sewaktu tersebut di atas
    <span class="bold">
        @if($rekomendasi === 'layak')
            Layak / <span style="text-decoration: line-through;">Tidak Layak</span>
        @elseif($rekomendasi === 'tidak_layak')
            <span style="text-decoration: line-through;">Layak</span> / Tidak Layak
        @else
            Layak / Tidak Layak
        @endif
    </span> sebagai Tempat Uji
    Kompetensi (TUK) terverifikasi.
</p>

<br>

<table class="bordered">
    <tr>
        <th style="width: 36px;">No.</th>
        <th>Tim Verifikator</th>
        <th style="width: 100px;">Tanda Tangan</th>
    </tr>
    <tr>
        <td>1.</td>
        <td style="height: 60px;">{{ $namaVerifikator }} ({{ $verifikatorNoreg }})</td>
        <td>@if($qrVerifikator){!! $qrVerifikator !!}@endif</td>
    </tr>
</table>

<br>

<div style="text-align: right; margin-bottom: 16px;">Jakarta, {{ $tanggalVerifikasi }}</div>

<!-- ============================================================
     PAGE 4 — DOKUMENTASI TUK
     ============================================================ -->
<div style="page-break-before: always;"></div>

<div class="center bold" style="font-size: 15px; margin-bottom: 16px;">
    DOKUMENTASI TUK
</div>

<table class="bordered">
    <tr>
        <th style="width: 36px;">No.</th>
        <th>Perlengkapan Sarana dan Prasarana</th>
        <th>Dokumentasi</th>
    </tr>
    <tr>
        <td>1.</td>
        <td class="left">Ruangan Uji yang tersedia meja dan kursi</td>
        <td>
            @if(count($ruanganUjiImages) > 0)
                @foreach($ruanganUjiImages as $img)
                <img src="{{ $img }}" class="doc-img" style="max-width:120px;max-height:100px;margin:2px;"><br>
                @endforeach
            @else
                -
            @endif
        </td>
    </tr>
    <tr>
        <td>2.</td>
        <td class="left">Laptop</td>
        <td>
            @if(count($laptopImages) > 0)
                @foreach($laptopImages as $img)
                <img src="{{ $img }}" class="doc-img" style="max-width:120px;max-height:100px;margin:2px;"><br>
                @endforeach
            @else
                -
            @endif
        </td>
    </tr>
    <tr>
        <td>3.</td>
        <td class="left">Kecepatan Jaringan</td>
        <td>
            @if(count($jaringanImages) > 0)
                @foreach($jaringanImages as $img)
                <img src="{{ $img }}" class="doc-img" style="max-width:120px;max-height:100px;margin:2px;"><br>
                @endforeach
            @else
                -
            @endif
        </td>
    </tr>
</table>

<!-- ============================================================
     PAGE 5 — FORM CHECKLIST VERIFIKASI TUK
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
        <td class="lbl">Jenis Asesmen</td>
        <td class="cln">:</td>
        <td class="bold">AJJ (Asesmen Jarak Jauh)</td>
    </tr>
    <tr>
        <td class="lbl">Metode Asesmen</td>
        <td class="cln">:</td>
        <td class="bold">Daring</td>
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
        <th rowspan="2">Perlengkapan<br>Sarana dan Prasarana</th>
        <th rowspan="2">Spesifikasi</th>
        <th rowspan="2" style="width: 50px;">Jml</th>
        <th colspan="2">Kondisi</th>
        <th rowspan="2" style="min-width: 80px;">Keterangan</th>
    </tr>
    <tr>
        <th style="width: 38px;">Baik</th>
        <th style="width: 38px;">Rusak</th>
    </tr>
    <tr>
        <td>1</td>
        <td class="left">Ruangan Uji (meja & kursi)</td>
        <td class="left" style="font-size: 12px;">
            <ul style="padding-left: 14px; margin: 0;">
                <li>Ruangan Tertutup</li>
                <li>Meja dan Kursi</li>
                <li>Tersedia Kelistrikan</li>
                <li>Pencahayaan cukup</li>
            </ul>
        </td>
        <td>1</td>
        <td>@if($kondisiRuangan === 'baik') v @endif</td>
        <td>@if($kondisiRuangan === 'rusak') v @endif</td>
        <td class="left">{{ $keteranganRuangan ?? '' }}</td>
    </tr>
    <tr>
        <td>2</td>
        <td class="left">Laptop</td>
        <td class="left" style="font-size: 12px;">
            <ul style="padding-left: 14px; margin: 0;">
                <li>Laptop + Webcam</li>
                <li>Min. Windows 10</li>
                <li>Min. RAM 4 GB</li>
                <li>Min. Memory 256 GB</li>
                <li>Browser Google Chrome</li>
                <li>Aplikasi Zoom</li>
            </ul>
        </td>
        <td>1</td>
        <td>@if($kondisiLaptop === 'baik') v @endif</td>
        <td>@if($kondisiLaptop === 'rusak') v @endif</td>
        <td class="left">{{ $keteranganLaptop ?? '' }}</td>
    </tr>
    <tr>
        <td>3</td>
        <td class="left">Kecepatan Jaringan</td>
        <td class="left" style="font-size: 12px;">
            <ul style="padding-left: 14px; margin: 0;">
                <li>Min. 10 Mbps</li>
            </ul>
        </td>
        <td>1</td>
        <td>@if($kondisiJaringan === 'baik') v @endif</td>
        <td>@if($kondisiJaringan === 'rusak') v @endif</td>
        <td class="left">{{ $keteranganJaringan ?? '' }}</td>
    </tr>
</table>

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
    @if($qrKetuaTuk || $qrVerifikator)
    <tr>
        <td>@if($qrKetuaTuk){!! $qrKetuaTuk !!}@endif</td>
        <td>@if($qrVerifikator){!! $qrVerifikator !!}@endif</td>
    </tr>
    @endif
    <tr>
        <td>
            <div class="bold">{{ $namaKetuaTUK }}</div>
            <div>Ketua TUK</div>
        </td>
        <td>
            <div class="bold">{{ $namaVerifikator }}</div>
            <div>Asesor Kompetensi</div>
        </td>
    </tr>
</table>

<!-- ============================================================
     PAGE 6 — HASIL VERIFIKASI DAN STANDARDISASI TUK
     ============================================================ -->
<div style="page-break-before: always;"></div>

<div class="center bold" style="font-size: 15px; margin-bottom: 16px;">
    HASIL VERIFIKASI DAN STANDARDISASI TUK
</div>

<br>

<div>
    Rekomendasi:
    <span class="bold">
        @if($rekomendasi === 'layak')
            Layak / <span style="text-decoration: line-through;">Tidak Layak</span>
        @elseif($rekomendasi === 'tidak_layak')
            <span style="text-decoration: line-through;">Layak</span> / Tidak Layak
        @else
            Layak / Tidak Layak
        @endif
    </span>
</div>
<div style="padding-left: 95px;">
    Sebagai Tempat Penyelenggaraan Uji Sertifikasi Kompetensi
</div>

<br><br>

<div class="bold">Penanggung Jawab TUK</div>
<table class="bordered" style="margin-top: 8px;">
    <tr>
        <th style="width: 36px;">No.</th>
        <th>Ketua TUK</th>
        <th style="width: 100px;">Tanda Tangan</th>
    </tr>
    <tr>
        <td>1.</td>
        <td style="height: 60px;">{{ $namaKetuaTUK }}</td>
        <td>@if($qrKetuaTuk){!! $qrKetuaTuk !!}@endif</td>
    </tr>
</table>

<br>

<div style="text-align: right; margin-bottom: 8px;">Jakarta, {{ $tanggalVerifikasi }}</div>

<table class="bordered">
    <tr>
        <th style="width: 36px;">No.</th>
        <th>Tim Verifikator</th>
        <th style="width: 100px;">Tanda Tangan</th>
    </tr>
    <tr>
        <td>1.</td>
        <td style="height: 60px;">{{ $namaVerifikator }}</td>
        <td>@if($qrVerifikator){!! $qrVerifikator !!}@endif</td>
    </tr>
</table>

<!-- ============================================================
     PAGE 7-8 — SURAT KEPUTUSAN (only if $showSk is true)
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
<div style="border-top: 3px solid #000; margin: 8px 0; height: 12px;"></div>

<div class="sk-content">
<table>
    <tr>
        <td class="label" style="width: 96px; padding-left: 8px;">Menimbang</td>
        <td class="separator">:</td>
        <td class="index">a.</td>
        <td>bahwa dalam rangka menjamin mutu pelaksanaan sertifikasi kompetensi kerja, diperlukan Tempat Uji Kompetensi (TUK) yang memenuhi persyaratan dan prosedur yang telah ditetapkan;</td>
    </tr>
    <tr><td></td><td></td><td class="index">b.</td><td>bahwa berdasarkan hasil verifikasi kelayakan Tempat Uji Kompetensi (TUK), perlu menetapkan TUK yang telah memenuhi persyaratan sebagai TUK Terverifikasi;</td></tr>
    <tr><td></td><td></td><td class="index">c.</td><td>bahwa berdasarkan pertimbangan sebagaimana dimaksud pada huruf a dan huruf b, perlu menetapkan Keputusan Direktur tentang Penetapan Tempat Uji Kompetensi (TUK) Terverifikasi.</td></tr>

    <tr>
        <td class="label" style="width: 96px; padding-left: 8px;">Mengingat</td>
        <td class="separator">:</td>
        <td class="index">a.</td>
        <td>Undang-Undang Republik Indonesia Nomor 13 Tahun 2003 Tentang Ketenagakerjaan;</td>
    </tr>
    <tr><td></td><td></td><td class="index">b.</td><td>Undang-undang (UU) Nomor 6 Tahun 2023 tentang Penetapan Peraturan Pemerintah Pengganti Undang-Undang Nomor 2 Tahun 2022 tentang Cipta Kerja menjadi Undang-Undang;</td></tr>
    <tr><td></td><td></td><td class="index">c.</td><td>Peraturan Pemerintah Nomor: 10 Tahun 2018 tentang Badan Nasional Sertifikasi Profesi;</td></tr>
    <tr><td></td><td></td><td class="index">d.</td><td>Peraturan Badan Nasional Sertifikasi Profesi Nomor: 05/BNSP.508/X/2013 Tentang Pedoman Manajemen Sistem Informasi Sertifikasi LSP dan BNSP;</td></tr>
    <tr><td></td><td></td><td class="index">e.</td><td>Peraturan Badan Nasional Sertifikasi Profesi Nomor: 1/BNSP/III/2014 tentang pedoman penilaian kesesuaian persyaratan umum Lembaga Sertifikasi Profesi;</td></tr>
    <tr><td></td><td></td><td class="index">f.</td><td>Peraturan Badan Nasional Sertifikasi Profesi Nomor: 5/BNSP/VII/2014 tentang pedoman persyaratan umum tempat uji kompetensi;</td></tr>
    <tr><td></td><td></td><td class="index">g.</td><td>ISO/IEC 17024:2012, IDT Tentang Penilaian Kesesuaian – Persyaratan Umum untuk Lembaga Sertifikasi Person.</td></tr>
</table>
</div>

<div style="page-break-before: always;"></div>

<div class="sk-content">
    <div class="center bold" style="font-size: 14px; margin: 16px 0;">M E M U T U S K A N</div>

    <table>
        <tr>
            <td class="label" style="width: 96px; padding-left: 8px;">Menetapkan</td>
            <td class="separator">:</td>
            <td>SURAT KEPUTUSAN DIREKTUR LEMBAGA SERTIFIKASI PROFESI GATENSI KARYA KONSTRUKSI TENTANG PENETAPAN TEMPAT UJI KOMPETENSI (TUK) TERVERIFIKASI</td>
        </tr>
        <tr>
            <td class="label" style="width: 96px; padding-left: 8px;">Kesatu</td>
            <td class="separator">:</td>
            <td>{{ $namaTUK }} sebagai TUK Sewaktu Terverifikasi.</td>
        </tr>
        <tr>
            <td class="label" style="width: 96px; padding-left: 8px;">Kedua</td>
            <td class="separator">:</td>
            <td>{{ $namaKetuaTUK }} sebagai Penanggung jawab {{ $namaTUK }}</td>
        </tr>
        <tr>
            <td class="label" style="width: 96px; padding-left: 8px;">Ketiga</td>
            <td class="separator">:</td>
            <td>Nama Skema Sertifikasi yang di Asesmen:<br>{{ $skemaSertifikasi }} Jenjang {{ $jenjang }}</td>
        </tr>
        <tr>
            <td class="label" style="width: 96px; padding-left: 8px;">Keempat</td>
            <td class="separator">:</td>
            <td>Berdasarkan hasil berita acara verifikasi {{ $namaTUK }} ditetapkan <span class="bold">{{ $statusTUK }}</span> untuk melaksanakan Uji Kompetensi Pada tanggal {{ $hariTanggal }}.</td>
        </tr>
        <tr>
            <td class="label" style="width: 96px; padding-left: 8px;">Kelima</td>
            <td class="separator">:</td>
            <td>Keputusan ini berlaku sejak tanggal ditetapkan.</td>
        </tr>
    </table>
</div>

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
        <span class="bold">LSP GATENSI KARYA KONSTRUKSI</span><br>
        <br>
        @if($qrDirekturSk)
        {!! $qrDirekturSk !!}<br>
        @elseif($qrDirektur)
        {!! $qrDirektur !!}<br>
        @endif
        <div class="bold underline" style="font-size: 14px;">{{ $namaDirektur }}</div>
        <div>Direktur</div>
    </div>
</div>

<!-- ============================================================
     PAGE 9 — LAMPIRAN SURAT KEPUTUSAN
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
    @foreach($skemaSertifikasiList as $skema)
    <li class="bold">{{ $skema }} — {{ $jenjang }}</li>
    @endforeach
</ol>

<br><br><br>

<div class="sign-wrap">
    <div class="sign-box">
        <span class="bold">LSP GATENSI KARYA KONSTRUKSI</span><br>
        <br>
        @if($qrDirekturSk)
        {!! $qrDirekturSk !!}<br>
        @elseif($qrDirektur)
        {!! $qrDirektur !!}<br>
        @endif
        <div class="bold underline" style="font-size: 14px;">{{ $namaDirektur }}</div>
        <div>Direktur</div>
    </div>
</div>

@endif

</body>
</html>
