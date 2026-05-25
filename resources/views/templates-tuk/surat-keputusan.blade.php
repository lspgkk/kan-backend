@php
    $noSurat = '';
    $namaTUK = '';
    $namaAdminTUK = '';
    $namaKetuaTUK = '';
    $statusTUK = '';
    $tanggalVerifikasi = '';
    $tanggalKeputusan = '';
    $namaDirektur = '';
@endphp
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Surat Keputusan</title>
  <link rel="stylesheet" href="{{ asset('assets/css/tuk-verification.css') }}">
</head>
<body class="doc-content">
  <div class="watermark"></div>

  <p class="p-center-line1"><span class="text-bold">SURAT KEPUTUSAN</span></p>
  <p class="p-center-line1"><span class="text-bold">DIREKTUR LEMBAGA SERTIFIKASI PROFESI GATENSI KARYA KONSTRUKSI</span></p>
  <p class="p-center-line1"><span class="text-serif-normal">NOMOR: </span><span class="text-serif-normal">{{ $noSurat }}</span></p><br/><br/>
  <p class="p-center-line1" style="padding-top:0; padding-bottom:12pt;"><span class="text-normal">TENTANG</span></p>
  <p class="p-center-top-1-9"><span class="text-bold">PENETAPAN TEMPAT UJI KOMPETENSI (TUK) TERVERIFIKASI</span></p>
  <p class="p-center-top-1-9"><span class="text-bold">LEMBAGA SERTIFIKASI PROFESI GATENSI KARYA KONSTRUKSI</span></p>
  <table class="table-sign" style="border-top: black solid 3px;">
    <tr class="row-h11">
      <td class="cell-w467" colspan="3"><p class="p-center-line1"><span class="text-bold"></span></p></td>
    </tr>
  </table>

  <table class="table-sign">
    <tr class="row-h79">
      <td class="cell-w96"><p class="p-left-pad12"><span class="text-normal">Menimbang</span></p></td>
      <td class="cell-w14"><p class="p-left-pad12"><span class="text-normal">:</span></p></td>
      <td class="cell-w357">
        <ol class="ol-reset" start="1">
          <li class="orphans-2 margin-left-18">
            <span class="text-normal">bahwa dalam rangka penjaminan mutu sertifikasi kompetensi perlu Tempat Uji Kompetensi (TUK) yang sudah sesuai dengan prosedur;</span>
          </li>
          <li class="orphans-2 margin-left-18">
            <span class="text-normal">bahwa berdasarkan poin b maka perlu ditetapkan Tempat Uji Kompetensi (TUK) yang sudah terverifikasi.</span>
          </li>
        </ol>
      </td>
    </tr>
    <tr class="row-h290">
      <td class="cell-w96"><p class="p-left-line1"><span class="text-normal">Mengingat</span></p></td>
      <td class="cell-w14"><p class="p-left-line1"><span class="text-normal">:</span></p></td>
      <td class="cell-w357">
        <ol class="ol-reset" start="1">
          <li class="margin-left-18"><span class="text-normal">Undang-Undang Republik Indonesia Nomor 13 Tahun 2003 Tentang Ketenagakerjaan;</span></li>
          <li class="margin-left-18"><span class="text-normal">Undang-undang (UU) Nomor 6 Tahun 2023 tentang Penetapan Peraturan Pemerintah Pengganti UndangUndang Nomor 2 Tahun 2022 tentang Cipta Kerja menjadi Undang-Undang;</span></li>
          <li class="margin-left-18"><span class="text-normal">Undang-undang Republik Indonesia Nomor 2 Tahun 2017 tentang Jasa Konstruksi;</span></li>
          <li class="margin-left-18"><span class="text-normal">Peraturan Pemerintah Republik Indonesia Nomor 23 Tahun 2004 tentang Badan Nasional Sertifikasi;</span></li>
          <li class="margin-left-18"><span class="text-normal">Peraturan Pemerintah Republik Indonesia Nomor 10 Tahun 2018 Tentang Badan Nasional Sertifikasi Profesi;</span></li>
          <li class="margin-left-18"><span class="text-normal">Peraturan Pemerintah Republik Indonesia Nomor 14 Tahun 2021 tentang Perubahan Atas Peraturan Pemerintah No 22 Tahun 2020 Tentang Peraturan Pelaksanaan UUD No 2 Tahun 2017 Tentang Jasa Konstruksi;</span></li>
          <li class="margin-left-18"><span class="text-normal">Peraturan Badan Nasional Sertifikasi Profesi Nomor: 1/BNSP/III/2014 tentang pedoman penilaian kesesuaiaan persyaratan umum Lembaga Sertifikasi Profesi;</span></li>
          <li class="margin-left-18"><span class="text-normal">Peraturan Badan Nasional Sertifikasi Profesi Nomor 5 Tahun 2014 Tentang Pedoman Persyaratan Umum Tempat Uji Kompetensi;</span></li>
        </ol>
      </td>
    </tr>
    <tr class="row-h23">
      <td class="cell-w467" colspan="3">
        <p class="p-center-line1"><span class="text-bold">M E M U T U S K A N</span></p>
      </td>
    </tr>
    <tr class="row-h65">
      <td class="cell-w96"><p class="p-left-line1"><span class="text-normal">Menetapkan</span></p></td>
      <td class="cell-w14"><p class="p-left-line1"><span class="text-normal">:</span></p></td>
      <td class="cell-w357"><p class="p-justify"><span class="text-normal">SURAT KEPUTUSAN DIREKTUR LEMBAGA SERTIFIKASI PROFESI GATENSI KARYA KONSTRUKSI TENTANG PENETAPAN TEMPAT UJI KOMPETENSI (TUK) TERVERIFIKASI LEMBAGA SERTIFIKASI PROFESI GATENSI KARYA KONSTRUKSI</span></p></td>
    </tr>
    <tr class="row-h27">
      <td class="cell-w96"><p class="p-left-line1"><span class="text-normal">Kesatu</span></p></td>
      <td class="cell-w14"><p class="p-left-line1"><span class="text-normal">:</span></p></td>
      <td class="cell-w357"><p class="p-justify-pad12"><span class="text-normal">{{ $namaTUK }} sebagai TUK Sewaktu Ulang Terverifikasi.</span></p></td>
    </tr>
    <tr class="row-h29">
      <td class="cell-w96"><p class="p-left-line1"><span class="text-normal">Kedua</span></p></td>
      <td class="cell-w14"><p class="p-left-line1"><span class="text-normal">:</span></p></td>
      <td class="cell-w357"><p class="p-justify-pad12"><span class="text-normal">{{ $namaAdminTUK }} sebagai Admin TUK {{ $namaTUK }}</span></p></td>
    </tr>
    <tr class="row-h28">
      <td class="cell-w96"><p class="p-left-line1"><span class="text-normal">Ketiga</span></p></td>
      <td class="cell-w14"><p class="p-left-line1"><span class="text-normal">:</span></p></td>
      <td class="cell-w357"><p class="p-justify-pad12"><span class="text-normal">{{ $namaKetuaTUK }} sebagai Penanggungjawab TUK {{ $namaTUK }}</span></p></td>
    </tr>
  </table>

  <div style="page-break-before: always; padding-top: 72pt;"></div>

  <table class="table-sign">
    <tr class="row-h37">
      <td class="cell-w96"><p class="p-left-line1"><span class="text-normal">Keempat</span></p></td>
      <td class="cell-w14"><p class="p-left-line1"><span class="text-normal">:</span></p></td>
      <td class="cell-w357"><p class="p-justify-pad12"><span class="text-normal">Berdasarkan hasil berita acara verifikasi, TUK {{ $namaTUK }} ditetapkan {{ $statusTUK }} untuk melaksanakan Uji Kompetensi pada tanggal {{ $tanggalVerifikasi }}.</span></p></td>
    </tr>
    <tr class="row-h37">
      <td class="cell-w96"><p class="p-left-line1"><span class="text-normal">Kelima</span></p></td>
      <td class="cell-w14"><p class="p-left-line1"><span class="text-normal">:</span></p></td>
      <td class="cell-w357"><p class="p-justify-pad12"><span class="text-normal">Berdasarkan hasil berita acara verifikasi, TUK {{ $namaTUK }} ditetapkan {{ $statusTUK }} untuk melaksanakan Uji Kompetensi dengan skema terlampir.</span></p></td>
    </tr>
    <tr class="row-h11b">
      <td class="cell-w96"><p class="p-left-line1"><span class="text-normal">Keenam</span></p></td>
      <td class="cell-w14"><p class="p-left-line1"><span class="text-normal">:</span></p></td>
      <td class="cell-w357"><p class="p-justify"><span class="text-normal">Keputusan ini berlaku sejak tanggal ditetapkan.</span></p></td>
    </tr>
  </table>
<br/><br/><br/>
  <table class="table-sign" style="float: right;">
    <tr class="row-h0">
      <td class="cell-w94"><p class="p-left-line1 text-indent-7"><span class="text-normal">Ditetapkan di</span></p></td>
      <td class="cell-w14b"><p class="p-left-line1"><span class="text-normal">:</span></p></td>
      <td class="cell-w116"><p class="p-left-line1"><span class="text-normal">Jakarta</span></p></td>
    </tr>
    <tr class="row-h0">
      <td class="cell-w94"><p class="p-left-line1 text-indent-7"><span class="text-normal">Tanggal</span></p></td>
      <td class="cell-w14b"><p class="p-left-line1"><span class="text-normal">:</span></p></td>
      <td class="cell-w116"><p class="p-left-line1"><span class="text-normal">{{ $tanggalKeputusan }}</span></p></td>
    </tr>
    <tr class="row-h0">
      <td class="cell-w225" colspan="3">
        <br/>
        <p class="p-center"><span class="text-bold">LSP GATENSI KARYA KONSTRUKSI</span></p>
      </td>
    </tr>
    <tr class="row-h0">
      <td class="cell-w225" colspan="3">
        <p class="p-center"><span class="text-bold-underline">{{ $namaDirektur }}</span></p>
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
