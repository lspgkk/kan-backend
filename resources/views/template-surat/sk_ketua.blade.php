<!DOCTYPE html>
<html lang="en">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <title>SPT Asesor</title>
    <link rel="stylesheet" href="{{ 'template/spt_asesor.css' }}" />
</head>

<body>
    <div class="page nobg">
        <div class="header">
            <table style="margin-left: auto; margin-right: auto" width="90%" border="0" cellspacing="0"
                cellpadding="0">
                <tbody>
                    <tr>
                        <td rowspan="2"
                            style="
                                    width: 25%;
                                    text-align: center;
                                    vertical-align: middle;
                                ">
                            <img src="./images/untuk-template/logo.png" width="100px" />
                        </td>
                        <td
                            style="
                                    width: 65%;
                                    font-size: 22px;
                                    color: blue;
                                    text-align: center;
                                    font-weight: bold;
                                    padding: 5px;
                                ">
                            LEMBAGA SERTIFIKASI PROFESI GATENSI KARYA
                            KONSTRUKSI
                        </td>
                        <td style="width: 10%px"></td>
                    </tr>
                    <tr>
                        <td
                            style="
                                    color: black;
                                    text-align: center;
                                    font-size: 12px;
                                    margin-bottom: 20px;
                                ">
                            Graha Gapensi Jl. Raya Ragunan No. 1C, Jati
                            Padang, Pasar Minggu, Jakarta Selatan Telp.
                            0217810444 | Email: admin@lspgatensi.id
                        </td>
                        <td></td>
                    </tr>
                    <tr>
                        <td colspan="3"
                            style="
                                    height: 5px;
                                    border-bottom: 4px double blue;
                                ">
                        </td>
                    </tr>
                </tbody>
            </table>

            <h3 style="text-align: center; margin-bottom: 0px; padding: 0px">
                Surat Keputusan
            </h3>
            <h3
                style="
                        text-align: center;
                        margin-bottom: 0px;
                        margin-top: 0px;
                    ">
                Ketua Lembaga Sertifikasi Profesi Gatensi Karya Konstruksi
            </h3>

            <h3
                style="
                        text-align: center;
                        padding-top: 0px;
                        margin-top: 0px;
                    ">
                Nomor : {{ $no_surat }}
            </h3>

            <h3 style="text-align: center; margin-bottom: 0px">
                Tentang Penetapan Hasil Sertifikasi Kompetensi
            </h3>
            <h3
                style="
                        text-align: center;
                        margin-bottom: 12px;
                        margin-top: 7px;
                    ">
                Lembaga Sertifikasi Profesi Gatensi Karya Konstruksi
            </h3>

            <div style="width: 100%; margin-left: 60px; margin-top: 25px">
                <table style="margin-left: auto; margin-right: auto" width="85%" cellspacing="0" cellpadding="0"
                    style="border: 0px">
                    <tbody>
                        <tr>
                            <td style="width: 5%; vertical-align: top">
                                Menimbang
                            </td>
                            <td style="width: 2%; vertical-align: top">
                                :
                            </td>
                            <td style="width: 75%">
                                <ol type="a" style="margin-top: 0">
                                    <li
                                        style="

                                                margin-bottom: 7px;
                                            ">
                                        Undang-Undang Republik Indonesia
                                        Nomor 13 Tahun 2003 Tentang
                                        Ketenagakerjaan;
                                    </li>
                                    <li
                                        style="

                                                margin-bottom: 7px;
                                            ">
                                        Bahwa hasil sertifikasi Kompetensi
                                        harus ditetapkan oleh Lembaga
                                        Sertifikasi Profesi;
                                    </li>
                                    <li
                                        style="

                                                margin-bottom: 7px;
                                            ">
                                        Bahwa berdasarkan berita acara rapat
                                        pleno komite teknis pada tanggal
                                        {{ \Carbon\Carbon::parse($tanggal_surat)->addDay()->setTimezone('Asia/Jakarta')->isoFormat('D MMMM YYYY') }};
                                    </li>
                                    <li
                                        style="

                                                margin-bottom: 7px;
                                            ">
                                        Bahwa berdasarkan poin b dan c maka
                                        perlu ditetapkan hasil sertifikasi
                                        kompetensi Lembaga Sertifikasi
                                        Profesi Gatensi Karya Konstruksi
                                    </li>
                                </ol>
                            </td>
                        </tr>
                        <tr>
                            <td style="width: 5%; vertical-align: top">
                                Mengingat
                            </td>
                            <td style="width: 2%; vertical-align: top">
                                :
                            </td>
                            <td style="width: 75%">
                                <ol type="a" style="margin-top: 0">
                                    <li
                                        style="

                                                margin-bottom: 7px;
                                            ">
                                        Peraturan Pemerintah Republik
                                        Indonesia Nomor 10 Tahun 2018
                                        tentang Badan Nasional Sertifikasi
                                        Profesi;
                                    </li>
                                    <li
                                        style="

                                                margin-bottom: 7px;
                                            ">
                                        Peraturan Badan Nasional Sertifikasi
                                        Profesi Nomor: 1/BNSP/III/2014
                                        tentang pedoman penilaian kesesuaian
                                        persyaratan umum Lembaga Sertifikasi
                                        Profesi;
                                    </li>
                                    <li
                                        style="

                                                margin-bottom: 7px;
                                            ">
                                        Peraturan Badan Nasional Sertifikasi
                                        Profesi Nomor: 2/BNSP/III/2014
                                        tentang pedoman pembentukan Lembaga
                                        Sertifikasi Profesi;
                                    </li>
                                    <li
                                        style="

                                                margin-bottom: 7px;
                                            ">
                                        Peraturan Badan Nasional Sertifikasi
                                        Profesi Nomor: 09/BNSP.301/XI/2013
                                        tentang pedoman pelaksanaan asesmen
                                        kompetensi.
                                    </li>
                                </ol>
                            </td>
                        </tr>
                        <tr>
                            <td style="width: 5%; vertical-align: top">
                                Memperhatikan
                            </td>
                            <td style="width: 2%; vertical-align: top">
                                :
                            </td>
                            <td style="width: 75%">
                                Hasil berita acara pleno komite teknis pada
                                tanggal
                                {{ \Carbon\Carbon::parse($tanggal_surat)->addDay()->setTimezone('Asia/Jakarta')->isoFormat('D MMMM YYYY') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <h3 style="text-align: center; margin-bottom: 0px; padding: 0px">
                MEMUTUSKAN
            </h3>
            <div style="width: 100%; margin-left: 60px; margin-top: 10px">
                <table style="margin-left: auto; margin-right: auto" width="85%" cellspacing="0" cellpadding="0"
                    style="border: 0px">
                    <tbody>
                        <tr>
                            <td style="width: 12.6%; vertical-align: top">
                                Mengingat
                            </td>
                            <td style="width: 2%; vertical-align: top">
                                :
                            </td>
                            <td style="width: 75%">
                                Hasil sertifikasi kompetensi Lembaga
                                Sertifikasi Profesi Gatensi Karya Konstruksi
                                yang dilaksanakan pada tanggal
                                {{ \Carbon\Carbon::parse($tanggal_surat)->setTimezone('Asia/Jakarta')->isoFormat('D MMMM YYYY') }}
                                sebagaimana terlampir.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div style="width: 100%; margin-left: 350px; margin-top: 10px">
                <table style="margin-left: auto; margin-right: auto" width="85%" cellspacing="0" cellpadding="0"
                    style="border: 0px">
                    <tbody>
                        <tr>
                            <td style="width: 12.6%; vertical-align: top">
                                Diterbitkan
                            </td>
                            <td style="width: 2%; vertical-align: top">
                                :
                            </td>
                            <td style="width: 75%">Jakarta</td>
                        </tr>
                        <tr>
                            <td style="width: 12.6%; vertical-align: top">
                                Pada Tanggal
                            </td>
                            <td style="width: 2%; vertical-align: top">
                                :
                            </td>
                            <td style="width: 75%">
                                {{ \Carbon\Carbon::parse($tanggal_surat)->addDay()->setTimezone('Asia/Jakarta')->isoFormat('D MMMM YYYY') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <table width="40%"
                style="
                        border: 0px;
                        float: right;
                        margin-right: 60px;
                        margin-top: 25px;
                        text-align: center;
                    ">
                <tbody>
                    <!-- <tr>
                            <td style="margin: 0; padding: 0">
                                \Carbon\Carbon::parse($tanggal_surat)->setTimezone('Asia/Jakarta')->isoFormat('dddd,D MMMM YYYY')
                            </td>
                        </tr>
                        <tr>
                            <td>Hormat kami,</td>
                        </tr> -->
                    <tr>
                        <td style="font-weight: 700; margin: 0; padding: 0">
                            Ketua Lembaga Sertifikasi Profesi
                        </td>
                    </tr>
                    <tr>
                        <td style="font-weight: 700; margin: 0; padding: 0">
                            Gatensi Karya Konstruksi
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 20px 0px">{!! $svg !!}</td>
                    </tr>
                    <tr>
                        <td
                            style="
                                    margin: 0;
                                    padding: 0;
                                    margin-bottom: 12px;
                                    text-decoration: underline;
                                    font-weight: 700;
                                ">
                            Radinal Efendy, ST
                        </td>
                    </tr>
                    <tr>
                        <td style="font-weight: 700">Ketua LSP</td>
                    </tr>
                </tbody>
            </table>
            <br />
            <br />
            <br />
            <br />
            <br />
            <br />
            <br />
            <br />
            <br />
            <br />
            <br />
            <br />
            <br />
            <br />
            <br />
            <br />
            <br />
            <br />
            <br />
            <br />
            <br />
            <br />
            <br />
            <div style="width: 100%; margin-left: 50px; margin-top: 25px">
                <div style="margin-top: 45px">*Lampiran</div>
                <table width="90%" border="1" style="margin: 12px 0">
                    <thead>
                        <tr>
                            <th style="width: 30px">No</th>
                            <th>Nama</th>
                            <th>Skema Sertifikasi</th>
                            <th>Hasil</th>
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
                                    {{ $peserta['kompeten'] }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>

</html>
