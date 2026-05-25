@php
    $metodeUji = '';
    $hasilVerifikasiLayak = '';
    $hasilVerifikasiTidakLayak = '';
    $tanggalVerifikasi = '';
    $namaKetuaTUK = '';
    $namaVerifikator = '';
@endphp
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Hasil Verifikasi dan Standardisasi TUK</title>
  <link rel="stylesheet" href="{{ asset('assets/css/tuk-verification.css') }}">
</head>
<body class="doc-content">
  <div class="watermark"></div>

  <p class="p-left-top-9-2"><span class="text-bold">HASIL VERIFIKASI DAN STANDARDISASI TUK</span></p>

  <p class="p-spacer"><span class="text-bold"></span></p>

  <table class="table-info">
    <tr class="row-h27b">
      <td class="cell-b-27" colspan="1" rowspan="2">
        <p class="row-h11 p-left-top-sm"><span class="text-bold"></span></p>
        <p class="p-left-top-sm"><span class="text-bold">No</span></p>
      </td>
      <td class="cell-b-318" colspan="1" rowspan="2">
        <p class="row-h11 p-left-top-sm"><span class="text-bold"></span></p>
        <p class="p-center-top-sm"><span class="text-bold">Unsur Yang Diverifikasi</span></p>
      </td>
      <td class="cell-b-121" colspan="2" rowspan="1">
        <p class="p-center-top-12b"><span class="text-bold">Hasil Verifikasi</span></p>
      </td>
    </tr>
    <tr class="row-h0">
      <td class="cell-b-64"><p class="p-center-top-sm"><span class="text-bold">Layak</span></p></td>
      <td class="cell-b-57"><p class="p-center-top-sm"><span class="text-bold">Tidak Layak</span></p></td>
    </tr>
    <tr class="row-h0">
      <td class="cell-b-27"><p class="p-left-top-sm"><span class="text-normal">1</span></p></td>
      <td class="cell-b-318">
        <ol class="ol-reset" start="1">
          <li class="p-left-top-sm margin-left-18c">
            <span class="text-normal">Standar Persyaratan Sarana dan Prasana Metode Uji {{ $metodeUji }}</span>
          </li>
        </ol>
      </td>
      <td class="cell-b-64"><p class="row-h11 p-left-top-sm"><span class="text-bold"></span>{{ $hasilVerifikasiLayak }}</p></td>
      <td class="cell-b-57"><p class="row-h11 p-left-top-sm"><span class="text-bold"></span>{{ $hasilVerifikasiTidakLayak }}</p></td>
    </tr>
    <tr class="row-h0">
      <td class="cell-b-27"><p class="row-h11 p-left-top-sm"><span class="text-bold"></span></p></td>
      <td class="cell-b-318">
        <ol class="ol-reset" start="2">
          <li class="p-left-top-sm margin-left-18c">
            <span class="text-normal">&nbsp;Standar Persyaratan Peralatan Metode Uji {{ $metodeUji }}</span>
          </li>
        </ol>
      </td>
      <td class="cell-b-64"><p class="row-h11 p-left-top-sm"><span class="text-bold"></span>{{ $hasilVerifikasiLayak }}</p></td>
      <td class="cell-b-57"><p class="row-h11 p-left-top-sm"><span class="text-bold"></span>{{ $hasilVerifikasiTidakLayak }}</p></td>
    </tr>
    <tr class="row-h0">
      <td class="cell-b-466" colspan="4"><p class="row-h11 p-left-top-sm"><span class="text-bold"></span></p></td>
    </tr>
  </table>
  <br/>
  <p class="p-left-top-sm"><span class="text-normal">Rekomendasi: </span><span class="text-bold">Layak/Tidak</span><span class="text-normal">&nbsp;</span></p>
  <p class="p-left-top-sm"><span class="text-normal">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Sebagai tempat Penyelenggaraan Uji Sertifikasi Kompetensi</span></p>

  <p class="p-spacer"><span class="text-normal"></span></p>
<br/>
  <table class="table-info">
    <tr class="row-h0">
      <td class="cell-w225b"><p class="row-h11 p-left-top-sm"><span class="text-normal"></span></p></td>
      <td class="cell-w225b"><p class="p-left-top-sm"><span class="text-normal">Jakarta, </span><span class="text-normal">{{ $tanggalVerifikasi }}</span></p></td>
    </tr>
    <tr class="row-h0">
      <td class="cell-w225b"><p class="p-left-top-sm"><span class="text-bold">Penanggung Jawab TUK</span></p></td>
      <td class="cell-w225b"><p class="p-left-top-sm"><span class="text-bold">Pemeriksa</span></p></td>
    </tr>
    <tr class="row-h0">
      <td class="cell-w225b">
        <p class="row-h11 p-left-top-sm"><span class="text-normal"></span></p>
        <p class="row-h11 p-left-top-sm"><span class="text-normal"></span></p>
        <p class="row-h11 p-left-top-sm"><span class="text-normal"></span></p>
        <p class="row-h11 p-left-top-sm"><span class="text-normal"></span></p>
        <p class="row-h11 p-left-top-sm"><span class="text-normal"></span></p>
      </td>
      <td class="cell-w225b"><p class="row-h11 p-left-top-sm"><span class="text-normal"></span></p></td>
    </tr>
    <tr class="row-h0">
      <td class="cell-w225b"><p class="p-left-top-sm"><span class="text-normal">{{ $namaKetuaTUK }}</span></p></td>
      <td class="cell-w225b"><p class="p-left-top-sm"><span class="text-normal">{{ $namaVerifikator }}</span></p></td>
    </tr>
    <tr class="row-h0">
      <td class="cell-w225b"><p class="p-left-top-sm"><span class="text-normal">Ketua TUK</span></p></td>
      <td class="cell-w225b"><p class="p-left-top-sm"><span class="text-normal">Asesor Kompetensi</span></p></td>
    </tr>
  </table>

  <p class="p-spacer"><span class="text-bold"></span></p>
  <p class="p-spacer"><span class="text-bold"></span></p>
</body>
</html>
