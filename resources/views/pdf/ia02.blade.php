<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>FR.IA.02 - TPD Tugas Praktik Demonstrasi</title>
<style>
    body {
        font-family: 'DejaVu Sans', Arial, sans-serif;
        margin: 30px;
        background-color: #fff;
    }
    h2 {
        text-align: center;
    }
    h3 {
        margin-top: 20px;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 20px;
        background: #fff;
    }
    table, th, td {
        border: 1px solid #000;
    }
    th, td {
        padding: 8px;
        font-size: 14px;
        vertical-align: top;
    }
    .no-border {
        border: none;
    }
    .section-title {
        font-weight: bold;
        margin-top: 20px;
        margin-left: 20px;
    }
    .small-text {
        font-size: 14px;
    }
    .center {
        text-align: center;
    }
    .barcode-img {
        max-width: 80px;
        max-height: 40px;
    }
    .content-box {
        margin: 20px 0;
        padding: 15px;
        border: 1px solid #000;
        min-height: 400px;
    }
</style>
</head>
<body>

<h2>FR.IA.02. &nbsp; TPD - TUGAS PRAKTIK DEMONSTRASI</h2>

<!-- IDENTITAS -->
@if(isset($dokumenHeader))
<table>
    <tr>
        <td width="25%">Skema Sertifikasi</td>
        <td width="5%">:</td>
        <td>{{ isset($dokumenHeader['jabatan_kerja']) && is_string($dokumenHeader['jabatan_kerja']) ? $dokumenHeader['jabatan_kerja'] : '-' }}</td>
    </tr>
    <tr>
        <td>Nomor</td>
        <td>:</td>
        <td>{{ isset($dokumenHeader['nomor_skema']) && is_string($dokumenHeader['nomor_skema']) ? $dokumenHeader['nomor_skema'] : '-' }}</td>
    </tr>
    <tr>
        <td>TUK</td>
        <td>:</td>
        <td>{{ isset($dokumenHeader['tuk']) && is_string($dokumenHeader['tuk']) ? $dokumenHeader['tuk'] : '-' }}</td>
    </tr>
    @if(isset($dokumenHeader['asesor_2']) && !empty($dokumenHeader['asesor_2']))
    <tr>
        <td>Nama Asesor</td>
        <td>:</td>
        <td>{{ isset($dokumenHeader['asesor_1']) && is_string($dokumenHeader['asesor_1']) ? $dokumenHeader['asesor_1'] : '-' }}</td>
    </tr>
    <tr>
        <td>Nama Asesor 2</td>
        <td>:</td>
        <td>{{ isset($dokumenHeader['asesor_2']) && is_string($dokumenHeader['asesor_2']) ? $dokumenHeader['asesor_2'] : '-' }}</td>
    </tr>
    @else
    <tr>
        <td>Nama Asesor</td>
        <td>:</td>
        <td>{{ isset($dokumenHeader['asesor_1']) && is_string($dokumenHeader['asesor_1']) ? $dokumenHeader['asesor_1'] : '-' }}</td>
    </tr>
    @endif
    <tr>
        <td>Nama Asesi</td>
        <td>:</td>
        <td>{{ isset($dokumenHeader['nama_asesi']) && is_string($dokumenHeader['nama_asesi']) ? $dokumenHeader['nama_asesi'] : '-' }}</td>
    </tr>
    <tr>
        <td>Tanggal</td>
        <td>:</td>
        <td>{{ isset($dokumenHeader['tanggal_uji']) && is_string($dokumenHeader['tanggal_uji']) ? $dokumenHeader['tanggal_uji'] : '-' }}</td>
    </tr>
</table>
@endif

<p class="small-text"><i>*Coret yang tidak perlu</i></p>

<!-- QUESTIONS CONTENT -->
@if(isset($questions) && isset($questions['isi_nonsoal']))
<div class="content-box">
    {!! isset($questions['isi_nonsoal']) && is_string($questions['isi_nonsoal']) ? html_entity_decode($questions['isi_nonsoal']) : '' !!}
</div>
@endif

<!-- TANDA TANGAN -->
@if(isset($dokumenHeader))
<table>
    <tr><td style="font-weight: bold;" colspan="3">Asesi:</td></tr>
    <tr>
        <td width="30%">Nama Asesi</td>
        <td width="2%">:</td>
        <td>{{ isset($dokumenHeader['nama_asesi']) && is_string($dokumenHeader['nama_asesi']) ? $dokumenHeader['nama_asesi'] : '-' }}</td>
    </tr>
    <tr>
        <td>Tanda Tangan dan Tanggal</td>
        <td>:</td>
        <td>
            @if(isset($barcodes['asesi']['url']) && !empty($barcodes['asesi']['url']))
                <img src="{{ $barcodes['asesi']['url'] }}" class="barcode-img" alt="Barcode Asesi">
            @else
                {{ isset($dokumenHeader['tanggal_uji']) && is_string($dokumenHeader['tanggal_uji']) ? $dokumenHeader['tanggal_uji'] : '-' }}
            @endif
        </td>
    </tr>

    <tr><td style="font-weight: bold;" colspan="3">Asesor @if(isset($dokumenHeader['asesor_2']) && !empty($dokumenHeader['asesor_2']))1 @endif :</td></tr>
    <tr>
        <td>Nama Asesor</td>
        <td>:</td>
        <td>{{ isset($dokumenHeader['asesor_1']) && is_string($dokumenHeader['asesor_1']) ? $dokumenHeader['asesor_1'] : '-' }}</td>
    </tr>
    <tr>
        <td>No. Reg</td>
        <td>:</td>
        <td>{{ isset($dokumenHeader['noreg_asesor_1']) && is_string($dokumenHeader['noreg_asesor_1']) ? $dokumenHeader['noreg_asesor_1'] : '-' }}</td>
    </tr>
    <tr>
        <td>Tanda Tangan dan Tanggal</td>
        <td>:</td>
        <td>
            @if(isset($barcodes['asesor1']['url']) && !empty($barcodes['asesor1']['url']))
                <img src="{{ $barcodes['asesor1']['url'] }}" class="barcode-img" alt="Barcode Asesor 1">
            @else
                {{ isset($dokumenHeader['tanggal_uji']) && is_string($dokumenHeader['tanggal_uji']) ? $dokumenHeader['tanggal_uji'] : '-' }}
            @endif
        </td>
    </tr>

    @if(isset($dokumenHeader['asesor_2']) && !empty($dokumenHeader['asesor_2']))
    <tr><td style="font-weight: bold;" colspan="3">Asesor 2:</td></tr>
    <tr>
        <td>Nama Asesor</td>
        <td>:</td>
        <td>{{ isset($dokumenHeader['asesor_2']) && is_string($dokumenHeader['asesor_2']) ? $dokumenHeader['asesor_2'] : '-' }}</td>
    </tr>
    <tr>
        <td>No. Reg</td>
        <td>:</td>
        <td>{{ isset($dokumenHeader['noreg_asesor_2']) && is_string($dokumenHeader['noreg_asesor_2']) ? $dokumenHeader['noreg_asesor_2'] : '-' }}</td>
    </tr>
    <tr>
        <td>Tanda Tangan dan Tanggal</td>
        <td>:</td>
        <td>
            @if(isset($barcodes['asesor2']['url']) && !empty($barcodes['asesor2']['url']))
                <img src="{{ $barcodes['asesor2']['url'] }}" class="barcode-img" alt="Barcode Asesor 2">
            @else
                {{ isset($dokumenHeader['tanggal_uji']) && is_string($dokumenHeader['tanggal_uji']) ? $dokumenHeader['tanggal_uji'] : '-' }}
            @endif
        </td>
    </tr>
    @endif
</table>
@endif

<!-- PENYUSUN DAN VALIDATOR -->
@if(isset($dokumenHeader) && isset($dokumenHeader['nama_penyusun']))
<h3>PENYUSUN DAN VALIDATOR</h3>
<table>
    <tr>
        <th>Status</th>
        <th>No</th>
        <th>Nama</th>
        <th>Nomor MET</th>
        <th>Tanda Tangan dan Tanggal</th>
    </tr>
    @if(isset($dokumenHeader['nama_penyusun']) && !empty($dokumenHeader['nama_penyusun']))
    <tr>
        <td rowspan="2">Penyusun</td>
        <td>1</td>
        <td>{{ isset($dokumenHeader['nama_penyusun']) && is_string($dokumenHeader['nama_penyusun']) ? $dokumenHeader['nama_penyusun'] : '-' }}</td>
        <td>{{ isset($dokumenHeader['noreg_penyusun']) && is_string($dokumenHeader['noreg_penyusun']) ? $dokumenHeader['noreg_penyusun'] : '-' }}</td>
        <td>{{ isset($dokumenHeader['tanggal_penyusun']) && is_string($dokumenHeader['tanggal_penyusun']) ? $dokumenHeader['tanggal_penyusun'] : '-' }}</td>
    </tr>
    <tr>
        <td>2</td>
        <td></td>
        <td></td>
        <td></td>
    </tr>
    @endif
    @if(isset($dokumenHeader['nama_validator']) && !empty($dokumenHeader['nama_validator']))
    <tr>
        <td rowspan="2">Validator</td>
        <td>1</td>
        <td>{{ isset($dokumenHeader['nama_validator']) && is_string($dokumenHeader['nama_validator']) ? $dokumenHeader['nama_validator'] : '-' }}</td>
        <td>{{ isset($dokumenHeader['noreg_validator']) && is_string($dokumenHeader['noreg_validator']) ? $dokumenHeader['noreg_validator'] : '-' }}</td>
        <td>{{ isset($dokumenHeader['tanggal_validator']) && is_string($dokumenHeader['tanggal_validator']) ? $dokumenHeader['tanggal_validator'] : '-' }}</td>
    </tr>
    <tr>
        <td>2</td>
        <td></td>
        <td></td>
        <td></td>
    </tr>
    @endif
</table>
@endif

</body>
</html>
