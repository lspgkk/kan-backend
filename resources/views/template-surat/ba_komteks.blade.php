<!DOCTYPE html>
<html>

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
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
            /* table-layout: fixed; */
            border-color: black;
            border-collapse: collapse;
        }

        td {
            vertical-align: top;
            padding: 5px;
        }

        th {
            padding: 5px;
        }

        li {
            padding-left: 5px;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 14px;
            -webkit-print-color-adjust: exact !important;
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
        <div style="margin: 0 auto;">
            <h3
                style="
                      text-align: center;
                      padding-bottom: 8px;
                      margin-bottom: 0px;
                      font-weight: 200;">
                BERITA ACARA KEPUTUSAN HASIL UJI KOMPETENSI
            </h3>
            <h3
                style="
                      text-align: center;
                      padding-top: 0px;
                      margin-top: 0px;
                      font-weight: 300;
                    ">
                NO: {{ $no_surat }}
            </h3>
            <table width="90%" style="margin: 50px 0 0 0">
                <tbody>
                    <tr>
                        <td style="text-align: justify">
                            Pada hari ini Tanggal {{ $hari_tanggal }}, bertempat di telah
                            diselenggarakan rapat Komite Teknis, yang dihadiri oleh:
                        </td>
                    </tr>
                </tbody>
            </table>

            <table width="90%" style="margin: 12px 0 0 0">
                <tbody>
                    <tr>
                        <td style="width: 12px; padding-bottom: 12px">1)</td>
                        <td>{{ $komtek1 }}</td>
                    </tr>
                    <tr>
                        <td style="width: 12px; padding-bottom: 12px">2)</td>
                        <td>{{ $komtek2 }}</td>
                    </tr>
                    <tr>
                        <td style="width: 12px; padding-bottom: 12px">3)</td>
                        <td>{{ $komtek3 }}</td>
                    </tr>
                </tbody>
            </table>

            <table width="90%" style="margin: 0">
                <tbody>
                    <tr>
                        <td style="text-align: justify; line-height: 20px">
                            Untuk menetapkan hasil uji Sertifikasi Kompetensi pada<br />
                            Keputusan ini dibuat berdasarkan dokumen hasil asesmen
                            yang terdiri dari:<br />1. Daftar Periksa<br />2.
                            Kelengkapan Data Asesi<br />3. Buku Kerja<br />4. Laporan
                            Asesmen
                        </td>
                    </tr>
                </tbody>
            </table>

            <table width="90%" style="margin: 12px 0">
                <tbody>
                    <tr>
                        <td colspan="2" style="padding-bottom: 0">
                            Tim Pemutus sepakat memutuskan hal-hal sebagai berikut :
                        </td>
                    </tr>
                    <tr>
                        <td style="text-align: justify; line-height: 20px">1.</td>
                        <td style="margin-bottom: 0; padding-bottom: 0">
                            Persyaratan Dasar sebagai Asesi berdasarkan dokumen yang
                            ada, semuanya telah sesuai dengan PP 14 Tahun 2021 dan
                            Surat Keputusan DIrektur Jendral Bina Konstruksi,
                            Kementerian Pekerjaan Umum dan Perumahan Rakyat Nomor:
                            12.1/KPTS/Dk/2021, Tentang PenetapanJabatan Kerja Dan
                            Konversi Jabatan Kerja Eksisting serta Jenjang Kualifikasi
                            Bidang Jasa Konstruksi, Tanggal 2 Februari 2022.
                        </td>
                    </tr>
                    <tr>
                        <td style="padding-top: 0; padding-bottom: 0">2.</td>
                        <td style="padding-top: 0; padding-bottom: 0">
                            Semua proses asesmen dan alur yang ditetapkan LSP telah
                            sesuai dengan ketentuan yang berlaku.
                        </td>
                    </tr>
                    <tr>
                        <td style="padding-top: 0; padding-bottom: 0">3.</td>
                        <td style="margin-top: 0; padding-top: 0">
                            Telah dilakukan asesmen kepada asesi oleh para asesor
                            dengan disaksikan oleh asesor peninjau dengan hasil sesuai
                            dengan daftar terlampir sebagai berikut
                        </td>
                    </tr>
                </tbody>
            </table>

            <table width="90%" border="1" style="margin: 24px 0">
                <thead>
                    <tr>
                        <th rowspan="2" style="width: 30px">No</th>
                        <th rowspan="2">Nama</th>
                        <th rowspan="2">Skema Sertifikasi</th>
                        <th colspan="2">Rekomendasi</th>
                    </tr>
                    <tr>
                        <th style="width: 40px">K</th>
                        <th style="width: 40px">BK</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($nama_peserta as $peserta)
                        <tr>
                            <td style="text-align: center; vertical-align: middle">
                                {{ $loop->iteration }}
                            </td>
                            <td style="vertical-align: middle">{{ $peserta['nama'] }}</td>
                            <td style="vertical-align: middle">{{ $peserta['skema'] }}</td>
                            <td style="text-align: center; vertical-align: middle">
                                @if ($peserta['kompeten'] == 'K')
                                    <div style="font-family: ZapfDingbats, sans-serif;">4</div>
                                @endif
                            </td>
                            <td style="text-align: center; vertical-align: middle">
                                @if ($peserta['kompeten'] == 'BK')
                                    <div style="font-family: ZapfDingbats, sans-serif;">4</div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <table width="90%" border="1">
                <thead>
                    <td
                        style="
                          font-weight: 700;
                          vertical-align: middle;
                          text-align: center;
                        ">
                        Ketua Rapat Pleno Komite Teknis
                    </td>
                    <td
                        style="
                          width: 12%;
                          vertical-align: middle;
                          text-align: center;
                        ">
                        Tanda Tangan
                    </td>
                </thead>
                <tbody>
                    <tr>
                        <td style="vertical-align: middle">{{ $komtek1 }}</td>
                        <td style="vertical-align: middle">
                            {!! $svg1 !!}
                        </td>
                    </tr>
                    <tr>
                        <td style="vertical-align: middle">{{ $komtek2 }}</td>
                        <td style="vertical-align: middle">
                            {!! $svg2 !!}
                        </td>
                    </tr>
                    <tr>
                        <td style="vertical-align: middle">{{ $komtek3 }}</td>
                        <td style="vertical-align: middle">
                            {!! $svg3 !!}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </main>
</body>

</html>