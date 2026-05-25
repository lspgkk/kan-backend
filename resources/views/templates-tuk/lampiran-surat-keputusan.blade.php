@php
    $noSurat = '';
    $skemaSertifikasi = '';
    $jenjang = '';
@endphp
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Lampiran Surat Keputusan</title>
  <link rel="stylesheet" href="{{ asset('assets/css/tuk-verification.css') }}">
</head>
<body class="doc-content">
  <div class="watermark"></div>

  <p class="p-center-top-xs">
    <span class="text-bold">LAMPIRAN SURAT KEPUTUSAN NOMOR: </span><span class="text-bold">{{ $noSurat }}</span>
  </p>

  <p class="p-center-top-xs">
    <span class="text-bold">TENTANG PENETAPAN TEMPAT UJI KOMPETENSI (TUK) TERVERIFIKASI </span>
  </p>

  <p class="p-center-line1">
    <span class="text-bold-arial">LEMBAGA SERTIFIKASI PROFESI GATENSI KARYA KONSTRUKSI</span><a id="id.apk5zihhin3l"></a>
  </p>

  <p class="row-h11 p-center-line1"><span class="text-bold"></span></p>
  <p class="row-h11 p-center-line1"><span class="text-bold"></span></p>
  <p class="row-h11 p-center-line1"><span class="text-bold"></span></p>

  <p class="p-left-line1">
    <span class="text-bold-arial">Daftar Skema</span>
  </p>

  <ol class="ol-reset" style="margin-left: 18pt;">
    <li class="p-left"><span class="text-normal">{{ $skemaSertifikasi }} — {{ $jenjang }}</span></li>
  </ol>

  <br/><br/><br/>

  <table class="table-sign" style="float: right;">
    <tr class="row-h0">
      <td class="cell-w225" colspan="3">
        <p class="p-center"><span class="text-bold">LSP GATENSI KARYA KONSTRUKSI</span></p>
      </td>
    </tr>
    <tr class="row-h0">
      <td class="cell-w225" colspan="3">
        <p class="p-center"><span class="text-bold-underline">RADINAL EFENDY, S.T.</span></p>
      </td>
    </tr>
    <tr class="row-h0">
      <td class="cell-w225" colspan="3">
        <p class="p-center"><span class="text-normal">Direktur</span></p>
      </td>
    </tr>
  </table>
  <div style="clear: both;"></div>
</body>
</html>
