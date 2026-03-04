<!DOCTYPE html>
<html>

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <title>SK Direktur - Template</title>
    <style>
        /**
            Set the margins of the page to 0, so the footer and the header
            can be of the full height and width !
         **/
        @page {
            margin: 0cm 0cm;
            size: A4;
        }

        /** Define now the real margins of every page in the PDF **/
        body {
            margin-top: 3cm;
            margin-left: 2cm;
            margin-right: 2cm;
            margin-bottom: 2cm;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 14px;
            line-height: 1.4;
            -webkit-print-color-adjust: exact !important;
        }

        /** Define the header rules **/
        header {
            position: fixed;
            top: 0cm;
            left: 0cm;
            right: 0cm;
            height: 3cm;
        }

        /** Define the footer rules **/
        footer {
            position: fixed;
            bottom: 0cm;
            left: 0cm;
            right: 0cm;
            height: 2cm;
        }

        table {
            width: 100%;
            border: 1px solid black;
            border-collapse: collapse;
            font-size: 13px;
        }

        td {
            border: 1px solid black;
            vertical-align: top;
            padding: 5px;
            word-wrap: break-word;
        }

        th {
            border: 1px solid black;
            padding: 5px;
            text-align: center;
            font-weight: bold;
        }

        li {
            padding-left: 5px;
            margin-bottom: 5px;
        }

        @media print {
            .noprint {
                display: none;
            }

            .nobg {
                box-shadow: 0px 0px 5px 0px rgb(255, 255, 255);
            }

            .pagebreak {
                page-break-after: always;
            }
        }
    </style>
</head>

<body>
    <!-- Define header and footer blocks before your content -->
    <header>
        <img src="./images/untuk-template/kop_satu.png" width="100%" />
    </header>

    <footer>
        <img src="./images/untuk-template/kop_dua.png" width="100%" />
    </footer>

    <!-- Wrap the content of your PDF inside a main tag -->
    <main>
        <div style="text-align: center; margin-bottom: 15px;">
            <h3 style="margin: 0; padding: 0; font-weight: bold;">SURAT KEPUTUSAN</h3>
            <h3 style="margin: 5px 0; padding: 0; font-weight: bold;">DIREKTUR LEMBAGA SERTIFIKASI PROFESI GATENSI KARYA KONSTRUKSI</h3>
        </div>

        <div style="text-align: center; margin-bottom: 15px;">
            <strong>Nomor : {{ $nomor }}</strong>
        </div>

        <div style="text-align: center; margin-bottom: 20px;">
            <h3 style="margin: 0; font-weight: bold;">TENTANG</h3>
            <h3 style="margin: 5px 0; font-weight: bold;">PENETAPAN HASIL SERTIFIKASI KOMPETENSI</h3>
            <h3 style="margin: 5px 0; font-weight: bold;">LEMBAGA SERTIFIKASI PROFESI GATENSI KARYA KONSTRUKSI</h3>
        </div>

        <div style="border-top: 2px solid #000; border-bottom: 1px solid #000; height: 3px; margin-bottom: 15px;"></div>

        <div style="text-align: center; font-weight: bold; margin-bottom: 20px;">
            DIREKTUR LEMBAGA SERTIFIKASI PROFESI GATENSI KARYA KONSTRUKSI
        </div>

        <table style="width: 100%; margin-bottom: 15px; border: none;">
            <tr style="border: none;">
                <td style="width: 130px; border: none; padding: 0; vertical-align: top; font-weight: normal;">Menimbang</td>
                <td style="width: 10px; border: none; padding: 0; vertical-align: top; text-align: center;">:</td>
                <td style="border: none; padding: 0; vertical-align: top;">
                    <ol type="a" style="padding-left: 20px; margin: 0; text-align: justify;">
                        <li>Undang-Undang Republik Indonesia Nomor 13 Tahun 2003 Tentang Ketenagakerjaan;</li>
                        <li>Bahwa hasil sertifikasi Kompetensi harus ditetapkan oleh Lembaga Sertifikasi Profesi;</li>
                        <li>Bahwa berdasarkan berita acara rapat pleno komite teknis pada tanggal {{ $tanggal_pleno_komtek }}</li>
                        <li>Bahwa berdasarkan poin b dan c maka perlu ditetapkan hasil sertifikasi kompetensi Lembaga Sertifikasi Profesi Gatensi Karya Konstruksi.</li>
                    </ol>
                </td>
            </tr>
        </table>

        <table style="width: 100%; margin-bottom: 15px; border: none;">
            <tr style="border: none;">
                <td style="width: 130px; border: none; padding: 0; vertical-align: top; font-weight: normal;">Mengingat</td>
                <td style="width: 10px; border: none; padding: 0; vertical-align: top; text-align: center;">:</td>
                <td style="border: none; padding: 0; vertical-align: top;">
                    <ol type="a" style="padding-left: 20px; margin: 0; text-align: justify;">
                        <li>Peraturan Pemerintah Republik Indonesia Nomor 10 Tahun 2018 tentang Badan Nasional Sertifikasi Profesi;</li>
                        <li>Peraturan Badan Nasional Sertifikasi Profesi Nomor: 1/BNSP/III/2014 tentang pedoman penilaian kesesuaian persyaratan umum Lembaga Sertifikasi Profesi;</li>
                        <li>Peraturan Badan Nasional Sertifikasi Profesi Nomor: 2/BNSP/III/2014 tentang pedoman pembentukan Lembaga Sertifikasi Profesi;</li>
                        <li>Peraturan Badan Nasional Sertifikasi Profesi Nomor: 09/BNSP.301/XI/2013 tentang pedoman pelaksanaan asesmen kompetensi.</li>
                    </ol>
                </td>
            </tr>
        </table>

        <table style="width: 100%; margin-bottom: 15px; border: none;">
            <tr style="border: none;">
                <td style="width: 130px; border: none; padding: 0; vertical-align: top; font-weight: normal;">Memperhatikan</td>
                <td style="width: 10px; border: none; padding: 0; vertical-align: top; text-align: center;">:</td>
                <td style="border: none; padding: 0; vertical-align: top; text-align: justify;">
                    Hasil berita acara pleno komte teknis pada tanggal {{ $tanggal_pleno_komtek }} dengan nomor {{ $nomor_pleno_komtek }}
                </td>
            </tr>
        </table>

        <br>
        <br>
        <br>
        <br>
        <br>
        <br>
        <br>
        <br>
        <br>
        <br>

        <div style="text-align: center; margin-bottom: 30px;">
            <h3 style="margin: 0; font-weight: bold; text-transform: uppercase;">M E M U T U S K A N</h3>
        </div>

        <table style="width: 100%; margin-bottom: 15px; border: none;">
            <tr style="border: none;">
                <td style="width: 130px; border: none; padding: 0; vertical-align: top; font-weight: normal;">Menetapkan</td>
                <td style="width: 10px; border: none; padding: 0; vertical-align: top; text-align: center;">:</td>
                <td style="border: none; padding: 0; vertical-align: top; text-align: justify;">
                    Hasil sertifikasi kompetensi Lembaga Sertifikasi Profesi Gatensi Karya Konstruksi yang dilaksanakan pada tanggal {{ $tanggal_uji_sertifikasi }} sebagaimana terlampir.
                </td>
            </tr>
        </table>

        <div style="position: absolute; bottom: 100px; right: 80px; text-align: left; width: 280px;">
            <div style="margin-bottom: 5px;">Diterbitkan di : Jakarta</div>
            <div style="margin-bottom: 5px;">Pada Tanggal : {{ $tanggal_direktur_lsp }}</div>
            <div style="font-weight: bold; margin-bottom: 60px; display: block; margin-top: 15px;">Direktur Lembaga Sertifikasi Profesi<br>Gatensi Karya Konstruksi</div>
            <br><br><br>
            <div style="font-weight: bold; text-decoration: underline;">Radinal Efendy, ST</div>
            <div style="font-size: 13px;">Direktur LSP</div>
            @if($qr)
            <img src="data:image/png;base64,{{ $qr }}" style="width: 120px; height: 120px; margin-top: 10px;" />
            @endif
        </div>

        <!-- Page 3 - Lampiran -->

        <br>
        <br>
        <br>
        <br>
        <br>
        <br>
        <br>
        <br>
        <br>
        <br>
        <br>
        <br>
        <br>
        <br>
        <br>
        <br>
        <br>
        <br>
        <br>
        <br>
        <br>
        <br>
        <br>
        <br>
        <br>
        <br>
        <br>
        <br>
        <br>
        <br>
        <br>
        <br>
        <br>
        <br>
        <br>
        <br>
        <br>
        <br>
        <br>
        <br>
        <br>

        <div>
            <div style="text-align: center; font-weight: bold; margin-bottom: 15px;">LAMPIRAN SURAT KEPUTUSAN</div>
            <div style="text-align: center; margin-bottom: 20px;">
                <strong>Nomor : {{ $nomor }}</strong><br>
                <strong>Tentang Penetapan Hasil Sertifikasi Kompetensi</strong>
            </div>
            <div style="font-weight: bold; margin-bottom: 10px; text-align: center;">HASIL SERTIFIKASI KOMPETENSI:</div>
            <table>
                <thead>
                    <tr>
                        <th style="width: 8%;">No</th>
                        <th style="width: 37%;">Nama</th>
                        <th style="width: 37%;">Skema</th>
                        <th style="width: 18%;">Hasil</th>
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
    </main>
</body>

</html>
