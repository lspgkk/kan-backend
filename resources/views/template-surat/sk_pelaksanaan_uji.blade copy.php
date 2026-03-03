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
        }

        .page {
            width: 210mm;
            height: 297mm;
            padding: 25mm 30mm;
            background-image: './images/untuk-template/template-skdirektur.png';
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            page-break-after: always;
            position: relative;
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

        /* Double Line */
        .double-line {
            border-top: 2px solid #000;
            border-bottom: 1px solid #000;
            height: 3px;
        }

        /* Section Styles */
        .section {
            margin-bottom: 20px;
        }

        .section-title {
            font-weight: bold;
            float: left;
            width: 100px;
        }

        .section-content {
            margin-left: 100px;
        }

        .section-content p {
            margin-bottom: 10px;
            text-align: justify;
            padding-left: 30px;
            text-indent: -30px;
        }

        .memutuskan {
            text-align: center;
            font-weight: bold;
            font-size: 11pt;
            margin-bottom: 30px;
        }

        .content-row {
            display: flex;
        }

        .col-left {
            width: 25%;
        }

        .col-middle-right {
            width: 75%;
        }

        .menetapkan-label {
            font-weight: bold;
        }

        .detail-intro {
            margin-bottom: 15px;
        }

        .detail-row {
            display: flex;
            margin-bottom: 8px;
        }

        .detail-label {
            width: 130px;
            flex-shrink: 0;
        }

        .detail-colon {
            width: 15px;
        }

        .detail-value {
            flex: 1;
        }

        .detail-indent {
            margin-left: 145px;
            margin-bottom: 8px;
        }

        .ttd-area {
            position: absolute;
            bottom: 80px;
            right: 100px;
            text-align: center;
            width: 250px;
        }

        .ttd-area .tanggal {
            margin-bottom: 10px;
        }

        .ttd-area .lembaga {
            font-weight: bold;
        }

        .ttd-area .nama {
            font-weight: bold;
        }

        /* Print styles */
        @media print {
            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .page {
                background-image: './images/untuk-template/template-skdirektur.png';
                background-size: cover;
                background-position: center;
                background-repeat: no-repeat;
            }
        }
    </style>
</head>
<body>
    <!-- Page 1 -->
    <div class="page page-1">
        <div class="header">
            <h1>SURAT KEPUTUSAN</h1>
            <h2>DIREKTUR LEMBAGA SERTIFIKASI PROFESI GATENSI KARYA KONSTRUKSI</h2>
        </div>

        <div class="nomor">
            <strong>Nomor : </strong>
        </div>

        <div class="tentang">
            <h3>TENTANG</h3>
            <h3>PELAKSANAAN  UJI  KOMPETENSI  LSP  GATENSI  KARYA  KONSTRUKSI</h3>
        </div>
        

        <div class="double-line"></div>
        <br/>
        <div class="section" style="font-weight: bold;">DIREKTUR LEMBAGA SERTIFIKASI PROFESI GATENSI  KARYA KONSTRUKSI</div>
        <div class="section" style="overflow: hidden; margin-top: 20px;">
            <div class="section-title">Menimbang :</div>
            <div class="section-content">
                <p>1. bahwa dalam rangka menjamin mutu sertifikasi kompetensi, perlu dilaksanakan uji kompetensi</p>
                <p>2. bahwa berdasarkan poin 1 (satu) maka perlu ditetapkan Pelaksanaan Uji Kompetensi</p>
            </div>
        </div>

        <div class="section" style="overflow: hidden;">
            <div class="section-title">Mengingat :</div>
            <div class="section-content">
                <p>1. Peraturan Pemerintah Nomor 10 Tahun 2018 tentang Badan Nasional Sertifikasi Profesi. Peraturan Presiden Nomor 8 Tahun 2012 Tentang Kerangka Kualifikasi Nasional Indonesia</p>
                <p>2. Peraturan Badan Nasional Sertifikasi Profesi Nomor: 01/BNSP/III/2014 Persyaratan Umum Lembaga Sertifikasi Profesi</p>
                <p>3. Peraturan Badan Nasional Sertifikasi Profesi Nomor: 09/BNSP.301/XI/2013 Tentang Pedoman Pelaksanaan Asesmen Kompetensi</p>
            </div>
        </div>
    </div>

    <!-- Page 2 -->
    <div class="page page-2">
        <div class="memutuskan">M E M U T U S K A N</div>

        <div class="content-row">
            <!-- Kiri: Menetapkan -->
            <div class="col-left">
                <span class="menetapkan-label">Menetapkan :</span>
            </div>

            <!-- Tengah-Kanan: Detail -->
            <div class="col-middle-right">
                <div class="detail-intro">Pelaksanaan Uji Kompetensi dilaksanakan pada</div>

                <div class="detail-row">
                    <span class="detail-label">Hari/Tanggal</span>
                    <span class="detail-colon">:</span>
                    <span class="detail-value">Kamis, 28 Agustus 2025</span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">Nama TUK</span>
                    <span class="detail-colon">:</span>
                    <span class="detail-value">TUK SEWAKTU GKK</span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">Alamat</span>
                    <span class="detail-colon">:</span>
                    <span class="detail-value">GRAHA GAPENSI, Jl. Raya Ragunan No.</span>
                </div>
                
                <div class="detail-indent">C/1, Kel. Jatipadang, Kec. Pasar Minggu,</div>
                <div class="detail-indent">Kotamadya Jakarta Selatan, Provinsi DKI</div>
                <div class="detail-indent">Jakarta</div>

                <div class="detail-row">
                    <span class="detail-label">Jumlah Peserta</span>
                    <span class="detail-colon">:</span>
                    <span class="detail-value">1</span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">Skema Sertifikasi</span>
                    <span class="detail-colon">:</span>
                    <span class="detail-value">Perakit Struktur Bangunan RISHA</span>
                </div>
            </div>
        </div>

        <!-- TTD di bawah kanan -->
        <div class="ttd-area">
            <div class="tanggal">Jakarta, 27-Agustus-2025</div>
            <div class="lembaga">LSP GATENSI KARYA KONSTRUKSI</div>
            <h2 style="margin: 20px 0 60px 0;">TTD DISINI</h2>
            <div class="double-line"></div>
            <div class="nama">RADINAL EFENDY, ST</div>
            <div class="jabatan">Direktur</div>
        </div>
    </div>
    <div class="page page-3">
        <div style="font-weight: bold; text-align: center;">LAMPIRAN SURAT KEPUTUSAN NOMOR:</div>
        <div class="memutuskan">TENTANG PENETAPAN KOMITE TEKNIS LSP GATENSI KARYA KONSTRUKSI</div>
        <table style="width: 100%; border-collapse: collapse; margin-top: 20px;" border="1">
            <tr>
                <td style="width: 60px; text-align: center; font-weight: bold;">No.</td>
                <td style="width: 400px; text-align: center; font-weight: bold;">Nama</td>
                <td style="width: 150px; text-align: center; font-weight: bold;">Jabatan</td>
            </tr>
            <tr>
                <td style="text-align: center;">1.</td>
                <td style="text-align: center;">OM UCUP</td>
                <td style="text-align: center;">ORANG KEREN</td>
            </tr>
            
        </table>

        <!-- TTD di bawah kanan -->
        <div class="ttd-area">
            <div class="tanggal">Jakarta, 27-Agustus-2025</div>
            <div class="lembaga">LSP GATENSI KARYA KONSTRUKSI</div>
            <h2 style="margin: 20px 0 60px 0;">TTD DISINI</h2>
            <div class="double-line"></div>
            <div class="nama">RADINAL EFENDY, ST</div>
            <div class="jabatan">Direktur</div>
        </div>
    </div>
</body>
</html>
