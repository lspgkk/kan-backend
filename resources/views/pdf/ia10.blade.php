<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>FR.IA.10 Klarifikasi Bukti Pihak Ketiga</title>
<style>
@page {
    size: A4;
    margin: 15mm;
}

body {
    font-family: 'DejaVu Sans', Arial, sans-serif;
    font-size: 12px;
    margin: 0;
    color: #000;
}

.title {
    font-weight: bold;
    font-size: 14px;
    margin-bottom: 10px;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin: 0;
}

table + table {
    margin-top: 10px;
}

td, th {
    border: 1px solid #000;
    padding: 6px;
    vertical-align: top;
    font-size: 12px;
}

.no-border td {
    border: none;
}

.label {
    width: 200px;
}

.small {
    font-size: 11px;
}

.right {
    text-align: right;
}

.center {
    text-align: center;
}

.bold {
    font-weight: bold;
}

.section-title {
    background: #c00000;
    color: #000;
    font-weight: bold;
    padding: 6px;
}

.box {
    border: 1px solid #000;
    margin-top: 10px;
}

.space {
    height: 60px;
}

.space-lg {
    height: 100px;
}

.footer {
    font-size: 10px;
    margin-top: 10px;
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

.checked::after {
    content: "✔";
}
</style>
</head>
<body>

<div class="title">FR.IA.10 KLARIFIKASI BUKTI PIHAK KETIGA</div>

  <!-- HEADER -->
  <table>
    <tr>
      <td rowspan="2" class="label">
        Skema Sertifikasi<br>
        (KKNI/Okupasi/Klaster)
      </td>
      <td>Judul</td>
      <td>:</td>
      <td>
        {{ $dokumenHeader['jabatan_kerja'] ?? '' }}
      </td>
    </tr>
    <tr>
      <td>Nomor</td>
      <td>:</td>
      <td>{{ $dokumenHeader['nomor_skema'] ?? '' }}</td>
    </tr>

    <tr><td>TUK</td><td>:</td><td colspan="2">{{ $dokumenHeader['tuk'] ?? '' }}</td></tr>
    <tr><td>Nama Asesor 1</td><td>:</td><td colspan="2">{{ strtoupper($dokumenHeader['asesor_1'] ?? '') }}</td></tr>
    <tr><td>Nama Asesor 2</td><td>:</td><td colspan="2">{{ strtoupper($dokumenHeader['asesor_2'] ?? '') }}</td></tr>
    <tr><td>Nama Asesi</td><td>:</td><td colspan="2">{{ strtoupper($dokumenHeader['nama_asesi'] ?? '') }}</td></tr>
    <tr><td>Tanggal</td><td>:</td><td colspan="2">{{ isset($dokumenHeader['tanggal_uji']) ? \Carbon\Carbon::parse($dokumenHeader['tanggal_uji'])->locale('id')->translatedFormat('d F Y') : '' }}</td></tr>
  </table>

  <div class="small">*Coret yang tidak perlu</div>
  <div class="right small"><i>Informasi Rahasia</i></div>

  <!-- PANDUAN -->
  <div class="box">
    <div class="section-title">PANDUAN BAGI ASESOR</div>
    <table class="no-border">
      <tr><td>
        1. Verifikasi pihak ketiga dapat dilakukan untuk keseluruhan unit kompetensi dalam skema sertifikasi atau dilakukan untuk masing-masing kelompok pekerjaan dalam satu skema sertifikasi.<br>
        2. Tentukan pihak ketiga yang akan dimintai verifikasi.<br>
        3. Ajukan pertanyaan kepada pihak ketiga.<br>
        4. Berikan penilaian kepada asesi berdasarkan verifikasi pihak ketiga.<br>
        5. Pertanyaan/pernyataan dapat dikembangkan sesuai dengan konteks pekerjaan dan relasi.
      </td></tr>
    </table>
  </div>

  <!-- DATA PIHAK -->
  <table style="margin-top:10px;">
    <tr><td>Nama Pengawas/penyelia/atasan/orang lain di perusahaan :</td></tr>
    <tr><td>{{ $namaPengawas ?? '' }}</td></tr>
    <tr><td>Tempat kerja :</td></tr>
    <tr><td>{{ $tempatKerja ?? '' }}</td></tr>
    <tr><td>Alamat :</td></tr>
    <tr><td>{{ $alamatPengawas ?? '' }}</td></tr>
    <tr><td>Telepon :</td></tr>
    <tr><td>{{ $teleponPengawas ?? '' }}</td></tr>
  </table>

  <!-- PERTANYAAN YA/TIDAK -->
  @if(isset($referensi_form) && isset($referensi_form[2]))
  <table style="margin-top:10px;">
    <tr class="center bold">
      <td>Pertanyaan</td>
      <td style="width:60px;">Ya</td>
      <td style="width:60px;">Tidak</td>
    </tr>

    @foreach($referensi_form[2] as $item)
    <tr>
      <td>- {{ $item->nama ?? '' }}</td>
      <td class="center">@if(isset($answers[$item->id]) && $answers[$item->id] === true)<span class="checkbox checked"></span>@endif</td>
      <td class="center">@if(isset($answers[$item->id]) && $answers[$item->id] === false)<span class="checkbox checked"></span>@endif</td>
    </tr>
    @endforeach
  </table>
  @endif

  <!-- ESSAY -->
  @if(isset($referensi_form) && isset($referensi_form[3]))
  <table style="margin-top:10px;">
    @foreach($referensi_form[3] as $item)
    <tr><td>{{ $item->nama ?? '' }}</td></tr>
    <tr><td>{{ $essayAnswers[$item->id] ?? '' }}</td></tr>
    @endforeach
  </table>
  @endif

  @if(isset($referensi_form) && isset($referensi_form[4]))
  <table style="margin-top:10px;">
    @foreach($referensi_form[4] as $item)
    <tr><td class="space-lg">{{ $item->nama ?? '' }}</td></tr>
    <tr><td>{{ $essayAnswers[$item->id] ?? '' }}</td></tr>
    @endforeach
  </table>
  @endif

  <table style="margin-top:10px;">
    <tr>
      <td>
        Tanda tangan Asesor 1: <span style="float:right;">Tanggal: {{ isset($barcodes['asesor1']['tanggal']) ? \Carbon\Carbon::parse($barcodes['asesor1']['tanggal'])->locale('id')->translatedFormat('d F Y') : '' }}</span>
        <div class="space"><img src="{{ $barcodes['asesor1']['url'] ?? '' }}" style="height:50px; width:50px; object-fit:contain;" /></div>
      </td>
    </tr>

    <tr>
      <td>
        Tanda tangan Asesor 2: <span style="float:right;">Tanggal: {{ isset($barcodes['asesor2']['tanggal']) ? \Carbon\Carbon::parse($barcodes['asesor2']['tanggal'])->locale('id')->translatedFormat('d F Y') : '' }}</span>
        <div class="space"><img src="{{ $barcodes['asesor2']['url'] ?? '' }}" style="height:50px; width:50px; object-fit:contain;" /></div>
      </td>
    </tr>
  </table>

  <div class="footer">
    *Diadopsi dari template yang disediakan di Departemen Pendidikan dan Pelatihan, Australia.<br>
    Merancang alat asesmen untuk hasil yang berkualitas di VET. 2008
  </div>

</body>
</html>