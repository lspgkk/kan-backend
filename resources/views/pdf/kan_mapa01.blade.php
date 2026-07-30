<!doctype html>
<html lang="id">
<head><meta charset="UTF-8" /><title>FR.MAPA.01 - KAN</title>
<style>
body{font-family:"Open Sans",Calibri,Candara,Segoe,"Segoe UI",Optima,Arial,sans-serif;font-size:11pt;margin-top:0}
.hd-dok{background-color:#c00000;color:#fff}.hd-dok-b{background-color:#e79291;color:#000}
td{border:.2px solid black}table{border:0 solid black;border-collapse:collapse;page-break-inside:avoid}
.noborder{border:0 transparent!important}.no-top-bottom{border-bottom-color:transparent!important;border-top-color:transparent!important}
.no-bottom{border-bottom-color:transparent!important}.no-top{border-top-color:transparent!important}
.checkbox{width:12px;height:12px;border:1px solid #000;display:inline-block;text-align:center;line-height:11px;font-size:15px;font-weight:bold;font-family:"DejaVu Sans",sans-serif;margin-right:4px}
.checked::after{content:"✔"}.radio{width:12px;height:12px;border:1px solid #000;border-radius:50%;display:inline-block;text-align:center;line-height:11px;font-size:10px;margin-right:4px}
.radio-checked::after{content:"•";font-size:14px}
td.vhead{position:relative;padding:0;height:280px;vertical-align:middle;text-align:center}
td.vhead .vtext{position:absolute;top:50%;left:50%;width:280px;transform:translate(-50%,-50%) rotate(-90deg);transform-origin:center;white-space:normal;font-size:9pt}
</style>
</head>
<body>

{{-- LOGO HEADERS --}}
<table width="100%" align="center" class="noborder" cellpadding="5" cellspacing="0">
    <tr>
        <td class="noborder"><img src="{{ public_path("img/kan/logo-left.png") }}"/></td>
        <td align="right" class="noborder"><img src="{{ public_path("img/kan/logo-right.png") }}"/></td>
    </tr>
</table><br>

<table width="100%" cellspacing="0" cellpadding="5" align="center">
    <tr><td class="noborder" style="font-weight:bold;font-size:16px">FR. MAPA.01 MERENCANAKAN AKTIVITAS DAN PROSES ASESMEN<br/></td></tr>
</table><br>

{{-- SKEMA HEADER --}}
<table width="100%" cellpadding="5" cellspacing="0" align="center">
    <tr><td style="width:30%" rowspan="2">Skema Sertifikasi<br />(<s>KKNI</s>/Okupasi/<s>Klaster</s>)</td><td style="width:15%">Judul</td><td style="width:3%">:</td><td style="width:52%">{{ $dokumenHeader['jabatan_kerja'] }}</td></tr>
    <tr><td>Nomor</td><td>:</td><td>{{ $dokumenHeader['nomor_skema'] }}</td></tr>
</table><br />

{{-- SECTION 1: Pendekatan Asesmen --}}
<strong>1. Pendekatan Asesmen</strong><br/>
<table width="100%" cellpadding="5" cellspacing="0" align="center" style="page-break-inside:auto">
    <tr><td class="hd-dok" colspan="5" style="font-weight:bold">1.1. Asesi</td></tr>
    <tr><td rowspan="21" style="width:5%;vertical-align:top">1.1.</td><td rowspan="5" style="width:20%;vertical-align:top">Asesi</td><td colspan="3"><span class="checkbox {{ $asesi['hasil_pelatihan_telusur']?'checked':'' }}"></span>Hasil pelatihan dan/atau pendidikan, dimana kurikulum dan fasilitas praktek mampu telusur terhadap standar kompetensi.</td></tr>
    <tr><td colspan="3"><span class="checkbox {{ $asesi['hasil_pelatihan_tidak_kompetensi']?'checked':'' }}"></span>Hasil pelatihan dan/atau pendidikan, dimana kurikulum belum berbasis kompetensi.</td></tr>
    <tr><td colspan="3"><span class="checkbox {{ $asesi['pekerja_berpengalaman_telusur']?'checked':'' }}"></span>Pekerja berpengalaman, dimana berasal dari industry/tempat kerja yang dalam operasionalnya mampu telusur dengan standar kompetensi.</td></tr>
    <tr><td colspan="3"><span class="checkbox {{ $asesi['pekerja_berpengalaman_tidak_telusur']?'checked':'' }}"></span>Pekerja berpengalaman, dimana berasal dari industry/tempat kerja yang dalam operasionalnya belum berbasis standar kompetensi.</td></tr>
    <tr><td colspan="3"><span class="checkbox {{ $asesi['otodidak']?'checked':'' }}"></span>Pelatihan/belajar mandiri atau otodidak.</td></tr>

    {{-- Tujuan --}}
    <tr><td rowspan="4" style="vertical-align:top">Tujuan Asesmen</td><td colspan="3"><span class="checkbox {{ $tujuan['sertifikasi']?'checked':'' }}"></span>Sertifikasi</td></tr>
    <tr><td colspan="3"><span class="checkbox {{ $tujuan['pkt']?'checked':'' }}"></span>Pengakuan Kompetensi Terkini (PKT)</td></tr>
    <tr><td colspan="3"><span class="checkbox {{ $tujuan['rpl']?'checked':'' }}"></span>Rekognisi pembelajaran lampau (RPL)</td></tr>
    <tr><td colspan="3"><span class="checkbox {{ $tujuan['lainnya']?'checked':'' }}"></span>Lainnya</td></tr>

    {{-- Konteks --}}
    <tr><td rowspan="8" style="vertical-align:top">Konteks Asesmen</td><td style="width:15%">Lingkungan</td><td style="width:25%"><span class="checkbox {{ $konteks['tempat_kerja_nyata']?'checked':'' }}"></span>Tempat kerja nyata</td><td style="width:25%"><span class="checkbox {{ $konteks['tempat_kerja_simulasi']?'checked':'' }}"></span>Tempat kerja simulasi</td></tr>
    <tr><td>Peluang untuk mengumpulkan bukti dalam sejumlah situasi</td><td><span class="checkbox {{ $konteks['tersedia']?'checked':'' }}"></span>Tersedia</td><td><span class="checkbox {{ $konteks['terbatas']?'checked':'' }}"></span>Terbatas</td></tr>
    <tr><td rowspan="3">Hubungan antara standar kompetensi dan</td><td colspan="2"><span class="checkbox {{ $konteks['bukti_asesmen_rpl']?'checked':'' }}"></span>Bukti untuk mendukung asesmen / RPL</td></tr>
    <tr><td colspan="2"><span class="checkbox {{ $konteks['aktivitas_kerja']?'checked':'' }}"></span>Aktivitas kerja di tempat kerja kandidat</td></tr>
    <tr><td colspan="2"><span class="checkbox {{ $konteks['kegiatan_pembelajaran']?'checked':'' }}"></span>Kegiatan Pembelajaran</td></tr>
    <tr><td rowspan="3">Siapa yang melakukan asesmen / RPL</td><td colspan="2"><span class="checkbox {{ $konteks['lsp_gatensi']?'checked':'' }}"></span>LSP Gatensi Karya Konstruksi</td></tr>
    <tr><td colspan="2"><span class="checkbox {{ $konteks['organisasi_pelatihan']?'checked':'' }}"></span>Organisasi Pelatihan</td></tr>
    <tr><td colspan="2"><span class="checkbox {{ $konteks['asesor_perusahaan']?'checked':'' }}"></span>Asesor perusahaan</td></tr>

    {{-- Orang Relevan --}}
    <tr><td rowspan="4" style="vertical-align:top">Orang yang relevan untuk dikonfirmasi</td><td colspan="3"><span class="checkbox {{ $orang_relevan['manajer_sertifikasi_lsp']?'checked':'' }}"></span>Manajer sertifikasi LSP Gatensi Karya Konstruksi</td></tr>
    <tr><td colspan="3"><span class="checkbox {{ $orang_relevan['master_assessor_lsp']?'checked':'' }}"></span>Master Assessor / Master Trainer / Asesor Utama kompetensi</td></tr>
    <tr><td colspan="3"><span class="checkbox {{ $orang_relevan['manajer_pelatihan_lsp']?'checked':'' }}"></span>Manajer pelatihan Lembaga Training terakreditasi / Lembaga Training terdaftar</td></tr>
    <tr><td colspan="3"><span class="checkbox {{ $orang_relevan['lainnya']?'checked':'' }}"></span>Lainnya</td></tr>

    {{-- Tolak Ukur --}}
    <tr><td class="hd-dok" colspan="5" style="font-weight:bold">1.2. Tolak ukur asesmen</td></tr>
    <tr><td rowspan="5" style="vertical-align:top">1.2</td><td rowspan="5" style="vertical-align:top">Tolak ukur asesmen</td><td colspan="3"><span class="checkbox {{ $tolak_ukur['skkni']?'checked':'' }}"></span>Standar Kompetensi {{ $skkni }}</td></tr>
    <tr><td colspan="3"><span class="checkbox {{ $tolak_ukur['kriteria_kurikulum']?'checked':'' }}"></span>Kriteria asesmen dari kurikulum pelatihan</td></tr>
    <tr><td colspan="3"><span class="checkbox {{ $tolak_ukur['spesifikasi_kinerja']?'checked':'' }}"></span>Spesifikasi kinerja suatu perusahaan atau industri</td></tr>
    <tr><td colspan="3"><span class="checkbox {{ $tolak_ukur['spesifikasi_produk']?'checked':'' }}"></span>Spesifikasi Produk</td></tr>
    <tr><td colspan="3"><span class="checkbox {{ $tolak_ukur['pedoman_khusus']?'checked':'' }}"></span>Pedoman khusus</td></tr>
</table><br>

{{-- SECTION 2: Rencana Asesmen --}}
<strong>2. Mempersiapkan Rencana Asesmen</strong><br/>

@foreach ($kelompok_kerja['kelompok_kerja'] as $kelompok)
    @php $unitCount = count($kelompok['units']); @endphp

    {{-- Kelompok header --}}
    <table width="100%" cellpadding="5" cellspacing="0" align="center">
        <tr><td style="width:20%" class="no-bottom"><strong>{{ $kelompok['nama'] }}</strong></td><td style="width:8%" align="center"><strong>No.</strong></td><td style="width:22%" align="center"><strong>Kode Unit</strong></td><td style="width:50%" align="center"><strong>Judul Unit</strong></td></tr>
        @foreach ($kelompok['units'] as $i => $unit)
            @php $kcls = ($i === $unitCount - 1) ? 'no-top' : 'no-top-bottom'; @endphp
            <tr><td class="{{ $kcls }}"></td><td align="center">{{ $i + 1 }}.</td><td align="center">{{ $unit['kode_unit'] }}</td><td>{{ $unit['nama_unit'] }}</td></tr>
        @endforeach
    </table><br>

    {{-- Metode Asesmen table dengan VHEAD --}}
    <table width="100%" cellpadding="5" cellspacing="0" align="center" style="font-size:9pt">
        <tr>
            <td class="no-bottom" style="width:11%" align="center"></td>
            <td class="no-bottom" style="width:14%" align="center"></td>
            <td colspan="3" align="center"><strong>Jenis Bukti</strong></td>
            <td colspan="6" align="center"><strong>Metode dan Perangkat Asesmen<br/>CL (Ceklis Observasi), DIT (Daftar Instruksi Terstruktur), DPL (Daftar Pertanyaan Lisan), DPT (Daftar Pertanyaan Tertulis), VPK (Verifikasi Pihak Ketiga), CVP (Ceklis Verifikasi Portofolio), CRP (Ceklis Reviu Produk), PW (Pertanyaan Wawancara)</strong></td>
        </tr>
        <tr>
            <td class="no-top" style="width:11%" align="center"><strong>Unit Kompetensi</strong></td>
            <td class="no-top" style="width:14%" align="center"><strong>Bukti-Bukti</strong><br/><small>(Kinerja, Produk, Portofolio, dan/atau Pengetahuan) diidentifikasi berdasarkan Kriteria Unjuk Kerja dan Pendekatan Asesmen.</small></td>
            <td style="width:3%" align="center"><strong>L</strong></td><td style="width:3%" align="center"><strong>TL</strong></td><td style="width:3%" align="center"><strong>T</strong></td>
            <td class="vhead" style="width:11%"><div class="vtext"><strong>Observasi langsung</strong><br><small>(kerja nyata / aktivitas waktu nyata di tempat kerja di lingkungan tempat kerja yang disimulasikan)</small></div></td>
            <td class="vhead" style="width:11%"><div class="vtext"><strong>Kegiatan Terstruktur</strong><br><small>(latihan simulasi dan bermain peran, proyek, presentasi, lembar kegiatan)</small></div></td>
            <td class="vhead" style="width:11%"><div class="vtext"><strong>Tanya Jawab</strong><br><small>(pertanyaan tertulis, wawancara, asesmen diri, tanya jawab lisan, angket, ujian lisan atau tertulis)</small></div></td>
            <td class="vhead" style="width:12%"><div class="vtext"><strong>Verifikasi Portfolio</strong><br><small>(sampel pekerjaan yang disusun oleh Asesi, produk dengan dokumentasi pendukung, bukti sejarah, jurnal atau buku catatan, informasi tentang pengalaman hidup)</small></div></td>
            <td class="vhead" style="width:10%"><div class="vtext"><strong>Reviu Produk</strong><br><small>Produk hasil proyek, contoh hasil kerja/produk</small></div></td>
            <td class="vhead" style="width:11%"><div class="vtext"><strong>Verifikasi Pihak Ketiga</strong><br><small>(testimoni dan laporan dari atasan, bukti pelatihan, otentikasi pencapaian sebelumnya, wawancara dengan atasan, atau rekan kerja)</small></div></td>
        </tr>
        @foreach ($kelompok['units'] as $i => $unit)
            @php
                $td3=$td4=$td5=$td6=$td7=$td8=$td9=$td10=$td11='';
                $j = $dokumenHeader['jenjang'] ?? 0;
                $m = strtolower($dokumenHeader['metode'] ?? '');
                if ($j >= 1 && $j <= 3) { $td3='L'; $td5='T'; $td6='CL'; $td8='DPT'; }
                elseif ($j >= 4 && $j <= 9) {
                    if ($m === 'portofolio') { $td4='TL'; $td5='T'; $td8='PW'; $td9='VP'; $td11='VPK'; }
                    elseif ($m === 'observasi') { $td3='L'; $td5='T'; $td7='DIT'; $td8='DPT'; }
                }
            @endphp
            <tr><td style="width:11%">{{ $i + 1 }}. {{ $unit['nama_unit'] }}</td><td style="width:14%">Hasil tanya jawab tentang: {{ $unit['nama_unit'] }}</td><td style="width:3%" align="center">{{ $td3 }}</td><td style="width:3%" align="center">{{ $td4 }}</td><td style="width:3%" align="center">{{ $td5 }}</td><td style="width:11%" align="center">{!! $td6 ?: '&nbsp;' !!}</td><td style="width:11%" align="center">{!! $td7 ?: '&nbsp;' !!}</td><td style="width:11%" align="center">{!! $td8 ?: '&nbsp;' !!}</td><td style="width:12%" align="center">{!! $td9 ?: '&nbsp;' !!}</td><td style="width:10%" align="center">{!! $td10 ?: '&nbsp;' !!}</td><td style="width:11%" align="center">{!! $td11 ?: '&nbsp;' !!}</td></tr>
        @endforeach
    </table><br>
@endforeach

{{-- SECTION 3: Modifikasi --}}
<strong>3. Modifikasi dan Kontekstualisasi:</strong><br/>
<table width="100%" cellpadding="5" cellspacing="0" align="center">
    <tr><td class="hd-dok" colspan="2" style="font-weight:bold">Modifikasi dan Kontekstualisasi</td></tr>
    @foreach ($modifikasi_items as $idx => $item)
    <tr><td style="width:50%">3.{{ $idx + 1 }} {{ $item['nama'] }}</td><td style="width:50%">
        @if ($item['value']) Ada / <s>Tidak ada</s><br/><br/><strong>Jika ada, tuliskan:</strong><br/>{!! $item['alasan'] !!}
        @else <s>Ada</s> / Tidak ada @endif
    </td></tr>
    @endforeach
</table><br>

{{-- KONFIRMASI --}}
<strong>Konfirmasi dengan orang yang relevan:</strong><br/>
<table width="100%" cellpadding="5" cellspacing="0" align="center">
    <tr><td class="hd-dok" style="width:40%">Orang yang relevan</td><td class="hd-dok" style="width:30%" align="center">Nama</td><td class="hd-dok" style="width:30%">Tanda tangan</td></tr>
    <tr><td><span class="checkbox {{ $orang_relevan['manajer_sertifikasi_lsp']?'checked':'' }}"></span>Manajer sertifikasi</td><td>{{ $dokumenHeader['nama_manajer'] ?? '' }}</td><td>
        @if($dokumenHeader['barcode_manajer'] ?? null)<img src="{{ $dokumenHeader['barcode_manajer'] }}" width="60" height="60"/><br/>@endif
        {{ ($dokumenHeader['tanggal_manajer'] ?? null) ? \Carbon\Carbon::parse($dokumenHeader['tanggal_manajer'])->locale('id')->translatedFormat('d F Y') : '' }}
    </td></tr>
    <tr><td><span class="checkbox {{ $orang_relevan['master_assessor_lsp']?'checked':'' }}"></span>Master Assessor / Master Trainer / Asesor Utama kompetensi</td><td></td><td></td></tr>
    <tr><td><span class="checkbox {{ $orang_relevan['manajer_pelatihan_lsp']?'checked':'' }}"></span>Manajer pelatihan Lembaga Training terakreditasi / terdaftar</td><td></td><td></td></tr>
    <tr><td><span class="checkbox {{ $orang_relevan['lainnya']?'checked':'' }}"></span>Lainnya:</td><td></td><td></td></tr>
</table><br>

{{-- TTD --}}
<table width="100%" cellpadding="5" cellspacing="0" align="center">
    <tr><td class="hd-dok" style="width:20%">Status</td><td class="hd-dok" style="width:10%" align="center">No</td><td class="hd-dok" style="width:25%" align="center">Nama</td><td class="hd-dok" style="width:20%">Nomor MET</td><td class="hd-dok" style="width:25%">Tanda Tangan dan Tanggal</td></tr>
    <tr><td>Penyusun</td><td align="center">1</td><td>{{ $dokumenHeader['nama_penyusun'] ?? '' }}</td><td>{{ $dokumenHeader['noreg_penyusun'] ?? '' }}</td><td>
        @if($dokumenHeader['barcode_penyusun'] ?? null)<img src="{{ $dokumenHeader['barcode_penyusun'] }}" width="80" height="80"/><br/>@endif
        {{ ($dokumenHeader['tanggal_penyusun'] ?? null) ? \Carbon\Carbon::parse($dokumenHeader['tanggal_penyusun'])->locale('id')->translatedFormat('d F Y') : '' }}
    </td></tr>
    <tr><td>Validator</td><td align="center">1</td><td>{{ $dokumenHeader['nama_validator'] ?? '' }}</td><td>{{ $dokumenHeader['noreg_validator'] ?? '' }}</td><td>
        @if($dokumenHeader['barcode_validator'] ?? null)<img src="{{ $dokumenHeader['barcode_validator'] }}" width="80" height="80"/><br/>@endif
        {{ ($dokumenHeader['tanggal_validator'] ?? null) ? \Carbon\Carbon::parse($dokumenHeader['tanggal_validator'])->locale('id')->translatedFormat('d F Y') : '' }}
    </td></tr>
</table>
</body>
</html>
