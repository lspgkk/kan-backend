@php
    $noSurat = '';
    $namaVerifikator = '';
    $namaTUK = '';
    $alamatTUK = '';
    $hariTanggal = '';
    $skemaSertifikasi = '';
    $tanggalVerifikasi = '';
    $namaDirektur = '';
@endphp
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Surat Tugas</title>
  <link rel="stylesheet" href="{{ asset('assets/css/tuk-verification.css') }}">
</head>
<body class="doc-content">
  <div class="watermark"></div>

  <h1 class="p-center">
    <span class="text-bold-underline text-12pt">SURAT TUGAS</span>
  </h1>
  <h2 class="p-center" style="transform: translateY(-20px);">
     <span class="text-bold vertical-baseline">Nomor :</span><span class="text-bold">{{ $noSurat }}</span>
    </h2>

  <p class="p-left-top-11">
    <span class="text-normal">Yang bertandatangan di bawah ini Direktur Lembaga Sertifikasi Profesi Gatensi Karya Konstruksi dengan ini memberi tugas kepada Verifikator Tempat Uji Kompetensi (TUK):</span>
  </p>


  <h4 class="p-center">
    <span class="text-serif-bold">{{ $namaVerifikator }}</span>
  </h4>



  <p class="p-left">
    <span class="text-normal">Untuk melakukan verifikasi Persyaratan Teknis Tempat Uji Kompetensi (TUK) bidang konstruksi di:</span>
  </p>

  <table class="table-info">
    <tr class="row-h0">
      <td class="cell-w126">
        <p class="p-left"><span class="text-normal">Nama TUK</span></p>
      </td>
      <td class="cell-w14c">
        <p class="p-left"><span class="text-normal">:</span></p>
      </td>
      <td class="cell-w310">
        <p class="p-left"><span class="text-normal">{{ $namaTUK }}</span></p>
      </td>
    </tr>
    <tr class="row-h0">
      <td class="cell-w126">
        <p class="p-left"><span class="text-normal">Alamat</span></p>
      </td>
      <td class="cell-w14c">
        <p class="p-left"><span class="text-normal">:</span></p>
      </td>
      <td class="cell-w310">
        <p class="p-left"><span class="text-normal">{{ $alamatTUK }}</span></p>
      </td>
    </tr>
    <tr class="row-h0">
      <td class="cell-w126">
        <p class="p-left"><span class="text-normal">Hari, Tanggal</span></p>
      </td>
      <td class="cell-w14c">
        <p class="p-left"><span class="text-normal">:</span></p>
      </td>
      <td class="cell-w310">
        <p class="p-left"><span class="text-normal">{{ $hariTanggal }}</span></p>
      </td>
    </tr>
    <tr class="row-h0">
      <td class="cell-w126">
        <p class="p-left"><span class="text-normal">Skema Sertifikasi</span></p>
      </td>
      <td class="cell-w14c">
        <p class="p-left"><span class="text-normal">:</span></p>
      </td>
      <td class="cell-w310">
        <p class="p-spacer"><span class="text-normal">{{ $skemaSertifikasi }}</span></p>
      </td>
    </tr>
    <tr class="row-h0">
      <td class="cell-w126">
        <p class="p-spacer"><span class="text-normal"></span></p>
      </td>
      <td class="cell-w14c">
        <p class="p-spacer"><span class="text-normal"></span></p>
      </td>
      <td class="cell-w310">
        <p class="p-spacer"><span class="text-normal"></span></p>
      </td>
    </tr>
  </table>

  <p class="p-spacer"><span class="text-normal"></span></p>

  <p class="p-left">
    <span class="text-normal">Demikian Surat tugas ini diberikan untuk dilaksanakan dengan penuh tanggung jawab.</span>
  </p>

  <p class="p-spacer"><span class="text-normal"></span></p>
  <br/><br/>
  <table class="table-sign" style="float: right;">
    <tr class="row-h0">
      <td class="cell-w206">
        <p class="p-left">
          <span class="text-serif-normal">Jakarta, </span><span class="text-serif-normal">{{ $tanggalVerifikasi }}</span>
        </p>
      </td>
    </tr>
    <tr class="row-h85">
      <td class="cell-w206">
        <p class="p-center">
          <span class="text-bold">LSP GATENSI KARYA KONSTRUKSI</span>
        </p>
      </td>
    </tr>
    <tr class="row-h0">
      <td class="cell-w206">
        <p class="p-center"><span class="text-bold-underline text-11pt">{{ $namaDirektur }}</span></p>
      </td>
    </tr>
    <tr class="row-h0">
      <td class="cell-w206">
        <p class="p-center"><span class="text-normal">Direktur</span></p>
      </td>
    </tr>
  </table>
  <div style="clear: both;"></div>
</body>
</html>
