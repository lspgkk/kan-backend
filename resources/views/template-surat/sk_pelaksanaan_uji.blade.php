<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8" />
    <title>SK Direktur - Template</title>
    <style>
        @page {
            size: A4;
            margin: 0;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11pt;
            line-height: 1.5;
            background-color: #f0f0f0;
        }

        .page {
            width: 210mm;
            height: 297mm;
            padding: 25mm 30mm;
            background-color: white;
            background-image: './images/untuk-template/template-skdirektur.png';
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            page-break-after: always;
            position: relative;
            margin: 10px auto;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }

        .page:last-child {
            page-break-after: auto;
        }

        /* Header Styles */
        .header {
            text-align: center;
            margin-bottom: 10px;
        }

        .header h1 {
            font-size: 11pt;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .header h2 {
            font-size: 11pt;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .nomor {
            text-align: center;
            margin-bottom: 10px;
        }

        .tentang {
            text-align: center;
            font-weight: bold;
            margin-bottom: 20px;
        }

        .tentang h3 {
            font-size: 11pt;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .double-line {
            border-top: 2px solid #000;
            border-bottom: 1px solid #000;
            height: 3px;
            margin-bottom: 20px;
        }

        /* --- KUNCI PERAPIAN TITIK DUA --- */
        .section {
            display: flex;
            margin-bottom: 15px;
            align-items: flex-start;
        }

        .section-title {
            width: 100px; /* Lebar label kiri */
            flex-shrink: 0;
            font-weight: normal;
        }

        .section-separator {
            width: 20px; /* Ruang untuk titik dua */
            flex-shrink: 0;
            text-align: center;
        }

        .section-content {
            flex: 1;
            text-align: justify;
        }

        .section-content ol {
            padding-left: 18px; /* Ruang untuk angka list */
            margin: 0;
        }

        .section-content ol li {
            margin-bottom: 8px;
        }

        .section-content p {
            margin: 0;
        }
        /* ------------------------------- */

        .memutuskan {
            text-align: center;
            font-weight: bold;
            font-size: 11pt;
            margin-bottom: 30px;
            text-transform: uppercase;
        }

        .ttd-area {
            position: absolute;
            bottom: 80px;
            right: 80px;
            text-align: left;
            width: 280px;
        }

        .ttd-area .tanggal {
            margin-bottom: 5px;
        }

        .ttd-area .lembaga {
            font-weight: bold;
            margin-bottom: 60px;
            display: block;
        }

        .ttd-area .nama {
            font-weight: bold;
            text-decoration: underline;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        table th, table td {
            border: 1px solid black;
            padding: 8px;
        }

        @media print {
            body { background-color: white; }
            .page { margin: 0; box-shadow: none; 
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;}
        }
    </style>
</head>
<body>

    <div class="page">
        <div class="header">
            <h1>SURAT KEPUTUSAN</h1>
            <h2>DIREKTUR LEMBAGA SERTIFIKASI PROFESI GATENSI KARYA KONSTRUKSI</h2>
        </div>
        <div class="nomor">
            <strong>Nomor : {{ $nomor }}</strong>
        </div>
        <div class="tentang">
            <h3>TENTANG</h3>
            <h3>PENETAPAN HASIL SERTIFIKASI KOMPETENSI</h3>
            <h3>LEMBAGA SERTIFIKASI PROFESI GATENSI KARYA KONSTRUKSI</h3>
        </div>

        <div class="double-line"></div>

        <div style="font-weight: bold; text-align: center; margin-bottom: 25px;">
            DIREKTUR LEMBAGA SERTIFIKASI PROFESI GATENSI KARYA KONSTRUKSI
        </div>

        <div class="section">
            <div class="section-title">Menimbang</div>
            <div class="section-separator">:</div>
            <div class="section-content">
                <ol type="a">
                    <li>Undang-Undang Republik Indonesia Nomor 13 Tahun 2003 Tentang Ketenagakerjaan;</li>
                    <li>Bahwa hasil sertifikasi Kompetensi harus ditetapkan oleh Lembaga Sertifikasi Profesi;</li>
                    <li>Bahwa berdasarkan berita acara rapat pleno komite teknis pada tanggal {{ $tanggal_pleno_komtek }}</li>
                    <li>Bahwa berdasarkan poin b dan c maka perlu ditetapkan hasil sertifikasi kompetensi Lembaga Sertifikasi Profesi Gatensi Karya Konstruksi.</li>
                </ol>
            </div>
        </div>

        <div class="section">
            <div class="section-title">Mengingat</div>
            <div class="section-separator">:</div>
            <div class="section-content">
                <ol type="a">
                    <li>Peraturan Pemerintah Republik Indonesia Nomor 10 Tahun 2018 tentang Badan Nasional Sertifikasi Profesi;</li>
                    <li>Peraturan Badan Nasional Sertifikasi Profesi Nomor: 1/BNSP/III/2014 tentang pedoman penilaian kesesuaian persyaratan umum Lembaga Sertifikasi Profesi;</li>
                    <li>Peraturan Badan Nasional Sertifikasi Profesi Nomor: 2/BNSP/III/2014 tentang pedoman pembentukan Lembaga Sertifikasi Profesi;</li>
                    <li>Peraturan Badan Nasional Sertifikasi Profesi Nomor: 09/BNSP.301/XI/2013 tentang pedoman pelaksanaan asesmen kompetensi.</li>
                </ol>
            </div>
        </div>

        <div class="section">
            <div class="section-title">Memperhatikan</div>
            <div class="section-separator">:</div>
            <div class="section-content">
                <p>Hasil berita acara pleno komite teknis pada tanggal {{ $tanggal_pleno_komtek }} dengan nomor {{ $nomor_pleno_komtek }}</p>
            </div>
        </div>
    </div>

    <div class="page">
        <div class="header" style="margin-bottom: 30px;">
            <h1>M E M U T U S K A N</h1>
        </div>

        <div class="section">
            <div class="section-title">Menetapkan</div>
            <div class="section-separator">:</div>
            <div class="section-content">
                <p>Hasil sertifikasi kompetensi Lembaga Sertifikasi Profesi Gatensi Karya Konstruksi yang dilaksanakan pada tanggal {{ $tanggal_uji_sertifikasi }} sebagaimana terlampir.</p>
            </div>
        </div>

        <div class="ttd-area">
            <div class="tanggal">Diterbitkan di : Jakarta</div>
            <div class="tanggal">Pada Tanggal : {{ $tanggal_direktur_lsp }}</div>
            <div class="lembaga" style="margin-top: 15px;">Direktur Lembaga Sertifikasi Profesi<br>Gatensi Karya Konstruksi</div>
            <br><br><br>
            <div class="nama">Radinal Efendy, ST</div>
            <div class="jabatan">Direktur LSP</div>
            @if($qr)
            <div style="margin-top: 10px;">{!! $qr !!}</div>
            @endif
        </div>
    </div>

    <div class="page">
        <div style="font-weight: bold; text-align: center; margin-bottom: 20px;">LAMPIRAN SURAT KEPUTUSAN</div>
        <div style="text-align: center; margin-bottom: 30px;">
            <strong>Nomor : {{ $nomor }}</strong><br>
            <strong>Tentang Penetapan Hasil Sertifikasi Kompetensi</strong>
        </div>
        <div style="font-weight: bold; margin-bottom: 15px;">HASIL SERTIFIKASI KOMPETENSI:</div>
        <table>
            <thead>
                <tr>
                    <th style="width: 10%;">No</th>
                    <th style="width: 35%;">Nama</th>
                    <th style="width: 35%;">Skema</th>
                    <th style="width: 20%;">Hasil</th>
                </tr>
            </thead>
            <tbody>
                @foreach($hasil_sertifikasi as $item)
                <tr>
                    <td style="text-align: center;">{{ $item['no'] }}</td>
                    <td>{{ $item['nama'] }}</td>
                    <td>{{ $item['skema'] }}</td>
                    <td style="text-align: center;">{{ $item['hasil'] }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

</body>
</html>