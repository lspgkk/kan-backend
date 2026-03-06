<!DOCTYPE html>
<html>

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <title>SK Komte Teknis - Template</title>
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
        <!-- Page 1 -->
        <div class="pagebreak">
            <div style="text-align: center; margin-bottom: 15px;">
                <h3 style="margin: 0; padding: 0; font-weight: bold;">SURAT KEPUTUSAN DIREKTUR</h3>
                <h3 style="margin: 5px 0; padding: 0; font-weight: bold;">LEMBAGA SERTIFIKASI PROFESI GATENSI KARYA KONSTRUKSI</h3>
            </div>

            <div style="text-align: center; margin-bottom: 15px;">
                <strong>Nomor : {{ $nomor }}</strong>
            </div>

            <div style="text-align: center; margin-bottom: 20px;">
                <h3 style="margin: 0; font-weight: bold;">TENTANG</h3>
                <h3 style="margin: 5px 0; font-weight: bold;">PENETAPAN KOMITE TEKNIS LEMBAGA SERTIFIKASI PROFESI GATENSI KARYA KONSTRUKSI</h3>
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
                        <ol style="padding-left: 20px; margin: 0; text-align: justify;">
                            <li>Bahwa untuk kelancaran dalam melaksanakan kegiatan operasional LSP Gatensi Karya Konstruksi sesuai dengan tugas, fungsi dan wewenang yang diberikan oleh Badan Nasional Sertifikasi Profesi;</li>
                            <li>Bahwa untuk keperluan tersebut perlu ditetapkan Surat Keputusan LSP Gatensi Karya Konstruksi tentang Komite Teknis.</li>
                        </ol>
                    </td>
                </tr>
            </table>

            <table style="width: 100%; margin-bottom: 15px; border: none;">
                <tr style="border: none;">
                    <td style="width: 130px; border: none; padding: 0; vertical-align: top; font-weight: normal;">Mengingat</td>
                    <td style="width: 10px; border: none; padding: 0; vertical-align: top; text-align: center;">:</td>
                    <td style="border: none; padding: 0; vertical-align: top;">
                        <ol style="padding-left: 20px; margin: 0; text-align: justify;">
                            <li>Undang-undang Nomor 13 Tahun 2003 tentang Ketenagakerjaan</li>
                            <li>Peraturan Pemerintah Nomor 10 Tahun 2018 tentang Badan Nasional Sertifikasi Profesi</li>
                            <li>Peraturan Presiden Nomor 8 Tahun 2012 Tentang Kerangka Kualifikasi Nasional Indonesia</li>
                            <li>Peraturan Menteri Tenaga Kerja dan Transmigrasi Republik Indonesia Nomor 5 Tahun 2012 tentang Sistem Standardisasi Kompetensi Kerja Nasional</li>
                            <li>Akte Notaris Suci Hastuti Zamachsyarief, SH., M.Kn Nomor 2 Tahun 2021;</li>
                            <li>ISO/IEC 17024:2012, Tentang Penilaian Kesesuaian Persyaratan Umum untuk Lembaga Sertifikasi Person</li>
                        </ol>
                    </td>
                </tr>
            </table>
        </div>

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
        <!-- Page 2 -->
        <div class="pagebreak">
            <div style="text-align: center; margin-bottom: 30px;">
                <h3 style="margin: 0; font-weight: bold; text-transform: uppercase;">M E M U T U S K A N :</h3>
            </div>

            <table style="width: 100%; margin-bottom: 15px; border: none;">
                <tr style="border: none;">
                    <td style="width: 130px; border: none; padding: 0; vertical-align: top; font-weight: normal;">Menetapkan</td>
                    <td style="width: 10px; border: none; padding: 0; vertical-align: top; text-align: center;">:</td>
                    <td style="border: none; padding: 0; vertical-align: top; text-align: justify;">
                        Surat Keputusan Direktur Lembaga Sertifikasi Profesi Gatensi Karya Konstruksi Tentang Penetapan Komite Teknis Lembaga Sertifikasi Profesi Gatensi Karya Konstruksi
                    </td>
                </tr>
            </table>

            <table style="width: 100%; margin-bottom: 15px; border: none;">
                <tr style="border: none;">
                    <td style="width: 130px; border: none; padding: 0; vertical-align: top; font-weight: normal;">Pertama</td>
                    <td style="width: 10px; border: none; padding: 0; vertical-align: top; text-align: center;">:</td>
                    <td style="border: none; padding: 0; vertical-align: top; text-align: justify;">
                        Komite Teknis Lembaga Sertifikasi Profesi Gatensi Karya Konstruksi.
                    </td>
                </tr>
            </table>

            <table style="width: 100%; margin-bottom: 15px; border: none;">
                <tr style="border: none;">
                    <td style="width: 130px; border: none; padding: 0; vertical-align: top; font-weight: normal;">Kedua</td>
                    <td style="width: 10px; border: none; padding: 0; vertical-align: top; text-align: center;">:</td>
                    <td style="border: none; padding: 0; vertical-align: top; text-align: justify;">
                        Keputusan ini berlaku sejak tanggal ditetapkan
                    </td>
                </tr>
            </table>

            <div style="position: absolute; bottom: 100px; right: 80px; text-align: left; width: 280px;">
                <div style="margin-bottom: 5px;">Ditetapkan di : Jakarta</div>
                <div style="margin-bottom: 5px;">Pada Tanggal : {{ $tanggal_penetapan }}</div>
                <div style="font-weight: bold; margin-bottom: 3px; display: block; margin-top: 15px;">LSP GATENSI KARYA KONSTRUKSI</div>
                @if($qr)
                <img src="data:image/png;base64,{{ $qr }}" style="width: 120px; height: 120px;" />
                @endif
                <div style="font-weight: bold; text-decoration: underline;">RADINAL EFENDY, ST</div>
                <div style="font-size: 13px;">Direktur</div>
            </div>
        </div>

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

        <!-- Page 3 - Lampiran -->
        <div>
            <div style="text-align: center; font-weight: bold; margin-bottom: 15px;">LAMPIRAN SURAT KEPUTUSAN NOMOR: {{ $nomor }} TENTANG PENETAPAN KOMITE TEKNIS LEMBAGA SERTIFIKASI PROFESI GATENSI KARYA KONSTRUKSI
            </div>

            <div style="text-align: center; font-weight: bold; margin-bottom: 20px;">
                LEMBAGA SERTIFIKASI PROFESI GATENSI KARYA KONSTRUKSI
            </div>

            <div style="font-weight: bold; margin-bottom: 10px; text-align: center;">SUSUNAN KOMITE TEKNIS:</div>

            <table>
                <thead>
                    <tr>
                        <th style="width: 10%;">No.</th>
                        <th style="width: 60%;">Nama</th>
                        <th style="width: 30%;">Jabatan</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($komtek_members as $member)
                    <tr>
                        <td style="text-align: center;">{{ $loop->iteration }}.</td>
                        <td>{{ $member['nama'] }}</td>
                        <td style="text-align: center;">{{ $member['jabatan'] }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>

            <div style="position: absolute; bottom: 150px; right: 80px; text-align: left; width: 280px;">
                <div style="text-align: right; width: 300px; margin-left: auto;">
                    <div style="text-align: center; margin-bottom: 5px;">Jakarta, {{ $tanggal_penetapan }}</div>
                    <div style="text-align: center; font-weight: bold; margin-bottom: 3px;">LSP GATENSI KARYA KONSTRUKSI</div>
                    @if($qr)
                    <img src="data:image/png;base64,{{ $qr }}" style="width: 120px; height: 120px; margin-right: 86px;" />
                    @endif
                    <div style="text-align: center; font-weight: bold; text-decoration: underline;">RADINAL EFENDY, ST</div>
                    <div style="text-align: center; font-size: 13px;">Direktur</div>
                </div>
            </div>
        </div>
    </main>
</body>

</html>
