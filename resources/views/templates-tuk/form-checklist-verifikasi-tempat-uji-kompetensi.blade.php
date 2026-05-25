@php
    $namaTUK = '';
    $alamatTUK = '';
    $hariTanggal = '';
    $metodeUji = '';
    $skemaSertifikasi = '';
    $namaJabker = '';
    $jenjang = '';
    $noAlat = '';
    $namaAlat = '';
    $spesifikasiAlat = '';
    $jumlahAlat = '';
    $ceklisTersedia = '';
    $ceklisTidakTersedia = '';
    $ceklisLayak = '';
    $ceklisTidakLayak = '';
@endphp
<!DOCTYPE html>
<html>
  <head>
    <meta content="text/html; charset=UTF-8" http-equiv="content-type" />
    <link rel="stylesheet" href="{{ asset('assets/css/tuk-verification.css') }}" />
  </head>
  <body style="padding: 0; max-width: none;">
    <div class="page-bg">
      <h1 class="p-center">
        <span class="text-bold">FORM CHECKLIST VERIFIKASI TEMPAT UJI KOMPETENSI</span>
      </h1>

      <table class="table-info">
        <tr class="row-h0">
          <td class="cell-w126">
            <p class="p-left-top-5-5"><span class="text-normal">Nama TUK</span></p>
          </td>
          <td class="cell-w15">
            <p class="p-left-top-5-5"><span class="text-bold">:</span></p>
          </td>
          <td class="cell-w310">
            <p class="p-left-top-5-5"><span class="text-normal">{{ $namaTUK }}</span></p>
          </td>
        </tr>
        <tr class="row-h0">
          <td class="cell-w126">
            <p class="p-left-top-5-5"><span class="text-normal">Alamat</span></p>
          </td>
          <td class="cell-w15">
            <p class="p-left-top-5-5"><span class="text-bold">:</span></p>
          </td>
          <td class="cell-w310">
            <p class="p-left-top-5-5"><span class="text-normal">{{ $alamatTUK }}</span></p>
          </td>
        </tr>
        <tr class="row-h0">
          <td class="cell-w126">
            <p class="p-left-top-5-5"><span class="text-normal">Hari, Tanggal</span></p>
          </td>
          <td class="cell-w15">
            <p class="p-left-top-5-5"><span class="text-bold">:</span></p>
          </td>
          <td class="cell-w310">
            <p class="p-left-top-5-5"><span class="text-normal">{{ $hariTanggal }}</span></p>
          </td>
        </tr>
        <tr class="row-h0">
          <td class="cell-w126">
            <p class="p-left-top-5-5"><span class="text-normal">Metode Asesmen</span></p>
          </td>
          <td class="cell-w15">
            <p class="p-left-top-5-5"><span class="text-bold">:</span></p>
          </td>
          <td class="cell-w310">
            <p class="p-left-top-5-5"><span class="text-bold">{{ $metodeUji }}</span></p>
          </td>
        </tr>
        <tr class="row-h0">
          <td class="cell-w126">
            <p class="p-left-top-5-5"><span class="text-normal">Skema Sertifikasi</span></p>
          </td>
          <td class="cell-w15">
            <p class="p-left-top-5-5"><span class="text-bold">:</span></p>
          </td>
          <td class="cell-w310">
            <p class="p-left-top-5-5 row-h11"><span class="text-bold">{{ $skemaSertifikasi }}</span></p>
          </td>
        </tr>
      </table>

      <p class="p-spacer"><span class="text-normal"></span></p>
    </div>

    <div class="page-plain">
      <table class="table-info">
        <tr class="row-h0">
          <td class="cell-w126">
            <p class="p-left-top-5-5"><span class="text-normal">JABATAN KERJA</span></p>
          </td>
          <td class="cell-w15">
            <p class="p-left-top-5-5"><span class="text-bold">:</span></p>
          </td>
          <td class="cell-w310">
            <p class="p-left-top-5-5"><span class="text-normal">{{ $namaJabker }}</span></p>
          </td>
        </tr>
        <tr class="row-h0">
          <td class="cell-w126">
            <p class="p-left-top-5-5"><span class="text-normal">JENJANG</span></p>
          </td>
          <td class="cell-w15">
            <p class="p-left-top-5-5"><span class="text-bold">:</span></p>
          </td>
          <td class="cell-w310">
            <p class="p-left-top-5-5"><span class="text-normal">{{ $jenjang }}</span></p>
          </td>
        </tr>
        <tr class="row-h0">
          <td class="cell-w126">
            <p class="p-left-top-5-5"><span class="text-normal">METODE UJI</span></p>
          </td>
          <td class="cell-w15">
            <p class="p-left-top-5-5"><span class="text-bold">:</span></p>
          </td>
          <td class="cell-w310">
            <p class="p-left-top-5-5"><span class="text-normal">{{ $metodeUji }}</span></p>
          </td>
        </tr>
      </table>
      <br />
      <table class="tbl">
        <tr>
          <td colspan="1" rowspan="2"><span class="text-bold">No.</span></td>
          <td colspan="1" rowspan="2"><span class="text-bold">Perlengkapan</span></td>
          <td colspan="1" rowspan="2"><span class="text-bold">Spesifikasi</span></td>
          <td colspan="1" rowspan="2"><span class="text-bold">Jumlah</span></td>
          <td colspan="2" rowspan="1"><span class="text-bold">Ketersediaan</span></td>
          <td colspan="2" rowspan="1"><span class="text-bold">Kelayakan</span></td>
        </tr>
        <tr>
          <td><span class="text-bold">Ada</span></td>
          <td><span class="text-bold">Tidak Ada</span></td>
          <td><span class="text-bold">Layak</span></td>
          <td><span class="text-bold">Tidak Layak</span></td>
        </tr>
        <tr>
          <td colspan="8"><span class="text-bold">Alat</span></td>
        </tr>
        <tr>
          <td><span class="text-normal">{{ $noAlat }}</span></td>
          <td><span class="text-normal">{{ $namaAlat }}</span></td>
          <td><span class="text-normal">{{ $spesifikasiAlat }}</span></td>
          <td><span class="text-normal">{{ $jumlahAlat }}</span></td>
          <td><span class="text-normal">{{ $ceklisTersedia }}</span></td>
          <td><span class="text-normal">{{ $ceklisTidakTersedia }}</span></td>
          <td><span class="text-normal">{{ $ceklisLayak }}</span></td>
          <td><span class="text-normal">{{ $ceklisTidakLayak }}</span></td>
        </tr>
      </table>

      <p class="p-spacer"><span class="text-normal"></span></p>
    </div>
  </body>
</html>
