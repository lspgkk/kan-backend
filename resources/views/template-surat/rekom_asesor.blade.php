{{-- <!DOCTYPE html>
<html lang="en">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Rekom Asesor</title>
    <link rel="stylesheet" href="rekomasesor.css" />
</head>

<body>
    <div class="page nobg">
        <div class="header" align="center">
            <header><img src="./kop_satu.png" width="795" /></header>

        </div>
    </div>
</body>

</html> --}}


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
        <div>
            <h3 style="text-align: center; padding-bottom: 8px; margin-bottom: 0px">
                BERITA ACARA ASESMEN/UJI KOMPETENSI<br />PELAKSANAAN SERTIFIKASI
                TAHUN 2023<br />GATENSI KARYA KONSTRUKSI
            </h3>
            <h3
                style="
          text-align: center;
          padding-top: 0px;
          margin-top: 0px;
          font-weight: 300;
        ">
                Nomor : {{ $no_surat }}
            </h3>
            <table width="90%" style="margin: 50px 0 0 0">
                <tbody>
                    <tr>
                        <td style="text-align: justify">
                            Pada hari ini {{ $hari_tanggal_split[0] }} tanggal
                            {{ $hari_tanggal_split[1] . ' ' . $hari_tanggal_split[2] . ' ' . $hari_tanggal_split[3] }},
                            bertempat
                            di {{ $tuk }} ({{ $tuk_alamat }})
                            telah dilakukan Uji Kompetensi Skema {{ $all_detect_skema }}dengan penjelasan sebagai
                            berikut :
                        </td>
                    </tr>
                </tbody>
            </table>

            <table width="90%" style="margin: 20px 0; font-weight: 700">
                <tbody>
                    <tr>
                        <td colspan="1">Asesor</td>
                        <td>:</td>
                        <td>{{ $nama_asesor }}</td>
                    </tr>
                </tbody>
            </table>

            <table width="90%" style="margin: 0">
                <tbody>
                    <tr>
                        <td style="text-align: justify">
                            Berdasarkan hasil penilaian Asesor, dengan ini menetapkan
                            hasil uji kompetensi unit kompetensi terhadap peserta sebagai
                            berikut :
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
            <table width="40%"
                style="
          border: 0px;
          float: right;
          margin-right: 60px;
          margin-top: 25px;
          text-align: center;
        ">
                <tbody>
                    <tr>
                        <td style="font-weight: 700; margin: 0; padding: 0">
                            {{ $tempat }}, {{ $hari_tanggal }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 25px 0px">{!! $svg !!}</td>
                    </tr>
                    <tr>
                        <td style="margin: 0; padding: 0">{{ $nama_asesor_ttd }}</td>
                    </tr>
                    <tr>
                        <td style="font-weight: 700">Nama Asesor</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </main>
</body>

</html>
