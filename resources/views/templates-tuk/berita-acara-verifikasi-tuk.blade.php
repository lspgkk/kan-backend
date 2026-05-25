@php
    $nomorSurat = '';
    $namaTUK = '';
    $alamatTUK = '';
    $hariTanggalVerifikasi = '';
    $metodeVerifikasi = '';
    $hariTanggalUji = '';
    $metodeUji = '';
    $tukSkemaSertifikasi = '';
    $namaAsesor = '';
@endphp
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Berita Acara Verifikasi TUK</title>
  <link rel="stylesheet" href="{{ asset('assets/css/tuk-verification.css') }}">
</head>
<body class="doc-content">
  <div class="watermark"></div>

  <p class="p-spacer-1"><span class="text-normal"></span></p>

  <p class="p-center"><span class="text-bold-underline text-11pt">BERITA ACARA VERIFIKASI TUK</span></p>

  <h2 class="p-center-top" style="transform: translateY(-20px)">
    <span class="text-bold" >Nomor : </span><span class="text-bold">{{ $nomorSurat }}</span>
  </h2>

  <p class="p-spacer"><span class="text-normal"></span></p>

  <p class="p-left"><span class="text-normal">Bahwa telah dilakukan Verifikasi TUK Sewaktu terhadap:</span></p>

  <table class="table-info" style="margin-top: 10px;">
    <tr class="row-h0">
      <td class="cell-w150"><p class="p-left"><span class="text-normal">Nama TUK</span></p></td>
      <td class="cell-w14d"><p class="p-left"><span class="text-normal">:</span></p></td>
      <td class="cell-w287"><p class="p-left"><span class="text-normal">{{ $namaTUK }}</span></p></td>
    </tr>
    <tr class="row-h0">
      <td class="cell-w150"><p class="p-left"><span class="text-normal">Alamat</span></p></td>
      <td class="cell-w14d"><p class="p-left"><span class="text-normal">:</span></p></td>
      <td class="cell-w287"><p class="p-left"><span class="text-normal">{{ $alamatTUK }}</span></p></td>
    </tr>
    <tr class="row-h0">
      <td class="cell-w150"><p class="p-left"><span class="text-normal">Hari, Tanggal Verifikasi</span></p></td>
      <td class="cell-w14d"><p class="p-left"><span class="text-normal">:</span></p></td>
      <td class="cell-w287"><p class="p-left"><span class="text-normal">{{ $hariTanggalVerifikasi }}</span></p></td>
    </tr>
    <tr class="row-h0">
      <td class="cell-w150"><p class="p-left"><span class="text-normal">Metode Verifikasi</span></p></td>
      <td class="cell-w14d"><p class="p-left"><span class="text-normal">:</span></p></td>
      <td class="cell-w287"><p class="p-left"><span class="text-bold">{{ $metodeVerifikasi }}</span></p></td>
    </tr>
    <tr class="row-h0">
      <td class="cell-w150"><p class="p-left"><span class="text-normal">Hari, Tanggal Uji</span></p></td>
      <td class="cell-w14d"><p class="p-left"><span class="text-normal">:</span></p></td>
      <td class="cell-w287"><p class="p-left"><span class="text-normal">{{ $hariTanggalUji }}</span></p></td>
    </tr>
    <tr class="row-h0">
      <td class="cell-w150"><p class="p-left"><span class="text-normal">Metode Uji</span></p></td>
      <td class="cell-w14d"><p class="p-left"><span class="text-normal">:</span></p></td>
      <td class="cell-w287"><p class="p-left"><span class="text-bold">{{ $metodeUji }}</span></p></td>
    </tr>
  </table>

  <br/>

  <p class="p-left"><span class="text-normal">Untuk menyelenggarakan Uji Kompetensi dengan Skema sertifikasi:</span></p>

  <p class="p-spacer"><span class="text-normal"></span></p>

  <table class="table-data">
    <tr class="row-h29">
      <td class="cell-g-36"><p class="p-left-top-3-8"><span class="text-bold">No.</span></p></td>
      <td class="cell-g-full"><p class="p-center-top-2"><span class="text-bold">TUK Skema Sertifikasi</span></p></td>
    </tr>
    <tr class="row-h19">
      <td class="cell-g-36"><p class="p-center-top-3-6"><span class="text-normal">1.</span></p></td>
      <td class="cell-g-full"><p class="p-left-top-3-8"><span class="text-normal">{{ $tukSkemaSertifikasi }}</span></p></td>
    </tr>

  </table>

  <br/>

  <p class="p-left-top-6">
    <span class="text-normal">Dengan ini menetapkan TUK sesuai Skema Sertifikasi dengan metode uji Observasi di atas </span>
    <span class="text-bold">Layak/ Tidak Layak (coret pake textdecoration)</span>
    <span class="text-normal">&nbsp;sebagai Tempat Uji Kompetensi (TUK) terverifikasi.</span>
  </p>

  <p class="p-left-top-6 p-spacer"><span class="text-normal"></span></p>

  <table class="table-data">
    <tr class="row-h0">
      <td class="cell-b-220"><p class="p-center"><span class="text-bold">Verifikator</span></p></td>
      <td class="cell-b-206"><p class="p-center"><span class="text-bold">Tanda Tangan</span></p></td>
    </tr>
    <tr class="row-h0">
      <td class="cell-b-220">
        <ol class="ol-reset lst-kix_list_6-0 start" start="1">
          <li class="p-left margin-left-18b li-bullet-1"><span class="text-normal">{{ $namaAsesor }}</span></li>
        </ol>
      </td>
      <td class="cell-b-206"><p class="p-spacer"><span class="text-bold"></span></p></td>
    </tr>
  </table>

  <p class="p-spacer"><span class="text-italic text-serif-bold"></span></p>

  <p class="p-left"><span class="text-italic text-serif-bold">*) Coret yang tidak perlu</span></p>

  <p class="p-left-top-6 p-spacer"><span class="text-normal"></span></p>

  <p class="p-left-top-6 p-spacer"><span class="text-normal"></span></p>
</body>
</html>
