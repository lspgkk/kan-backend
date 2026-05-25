@php
    $tanggalSurat = '';
    $noSurat = '';
    $namaKetuaTUK = '';
    $hariTanggal = '';
    $waktu = '';
    $tempat = '';
    $tanggalVerifikasi = '';
    $namaDirektur = '';
@endphp
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Permohonan Peminjaman Tempat Uji Kompetensi</title>
  <link rel="stylesheet" href="{{ asset('assets/css/tuk-verification.css') }}">
</head>
<body class="doc-content">
  <div class="watermark"></div>

  <table class="table-info">
    <tr class="row-h0">
      <td class="cell-w450" colspan="3"><p class="p-right"><span class="text-normal">Jakarta, {{ $tanggalSurat }}</span></p></td>
    </tr>
    <tr class="row-h11">
      <td class="cell-w450" colspan="3"><p class="p-right row-h11"><span class="text-normal"></span></p></td>
    </tr>
    <tr class="row-h0">
      <td class="cell-w57"><p class="p-left"><span class="text-normal">No</span></p></td>
      <td class="cell-w14c"><p class="p-left"><span class="text-normal">:</span></p></td>
      <td class="cell-w379"><p class="p-left"><span class="text-normal">{{ $noSurat }}</span></p></td>
    </tr>
    <tr class="row-h0">
      <td class="cell-w57"><p class="p-left"><span class="text-normal">Lampiran</span></p></td>
      <td class="cell-w14c"><p class="p-left"><span class="text-normal">:</span></p></td>
      <td class="cell-w379"><p class="p-left"><span class="text-normal">1 (satu) lembar</span></p></td>
    </tr>
    <tr class="row-h0">
      <td class="cell-w57"><p class="p-left"><span class="text-normal">Hal</span></p></td>
      <td class="cell-w14c"><p class="p-left"><span class="text-normal">:</span></p></td>
      <td class="cell-w379"><p class="p-left"><span class="text-normal">Permohonan Peminjaman Tempat Uji Kompetensi</span></p></td>
    </tr>
  </table>
<br/><br/>
  <p class="p-left"><span class="text-normal">Kepada Yth.</span></p>
  <p class="p-left-top-1-6"><span class="text-normal">Pimpinan TUK </span><span class="text-normal">{{ $namaKetuaTUK }}</span></p>
  <p class="p-left-top-1-6"><span class="text-normal">di- </span></p>
  <p class="p-left-top-1-6 indent-14"><span class="text-normal">Tempat</span></p>
<br/>
  <p class="p-left-top-3pt"><span class="text-normal"></span></p>

  <p class="p-justify-15">
    <span class="text-normal">Sehubungan dengan adanya asesmen atau uji kompetensi oleh Lembaga Sertifikasi Profesi Gatensi Karya Konstruksi yang akan dilaksanakan pada:</span>
  </p>
<br/>
  <table class="table-info">
    <tr class="row-h0">
      <td style="width: 40px;"><p class="p-justify-15"><span class="text-normal"></span></p></td>
      <td class="cell-w108"><p class="p-justify-15"><span class="text-normal">Hari, Tanggal</span></p></td>
      <td class="cell-w14d"><p class="p-justify-15"><span class="text-normal">:</span></p></td>
      <td class="cell-w287b"><p class="p-justify-15"><span class="text-normal">{{ $hariTanggal }}</span></p></td>
    </tr>
    <tr class="row-h0">
      <td style="width: 40px;"><p class="p-justify-15"><span class="text-normal"></span></p></td>
      <td class="cell-w108"><p class="p-justify-15"><span class="text-normal">Waktu</span></p></td>
      <td class="cell-w14d"><p class="p-justify-15"><span class="text-normal">:</span></p></td>
      <td class="cell-w287b"><p class="p-justify-15"><span class="text-normal">{{ $waktu }}</span></p></td>
    </tr>
    <tr class="row-h0">
      <td style="width: 40px;"><p class="p-justify-15"><span class="text-normal"></span></p></td>
      <td class="cell-w108"><p class="p-justify-15"><span class="text-normal">Tempat</span></p></td>
      <td class="cell-w14d"><p class="p-justify-15"><span class="text-normal">:</span></p></td>
      <td class="cell-w287b"><p class="p-justify-15"><span class="text-normal">{{ $tempat }}</span></p></td>
    </tr>
  </table>
<br/>
  <p class="p-justify-15">
    <span class="text-normal">Dengan ini mengajukan permohonan pemakaian tempat uji kompetensi untuk skema sertifikasi sebagaimana terlampir.</span>
  </p>

  <p class="p-left-top-3pt">
    <span class="text-normal">Demikian surat ini kami sampaikan, atas kerja samanya kami ucapkan terima kasih.</span>
  </p>
  <br/><br/><br/>
  <table class="table-sign" style="float: right;">
    <tr class="row-h0">
      <td class="cell-w206"><p class="p-left"><span class="text-normal">Hormat kami,<br/> Jakarta, {{ $tanggalVerifikasi }}</span></p></td>
    </tr>
    <tr class="row-h85">
      <td class="cell-w206">
        <p class="p-left"><span class="text-bold">LSP GATENSI KARYA KONSTRUKSI</span></p>
      </td>
    </tr>
    <tr class="row-h0">
      <td class="cell-w206"><p class="p-center"><span class="text-bold-underline text-12pt">{{ $namaDirektur }}</span></p></td>
    </tr>
    <tr class="row-h0">
      <td class="cell-w206"><p class="p-center"><span class="text-normal">Direktur</span></p></td>
    </tr>
  </table>
  <div style="clear: both;"></div>
</body>
</html>
