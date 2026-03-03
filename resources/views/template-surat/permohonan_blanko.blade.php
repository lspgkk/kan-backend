<!DOCTYPE html>
<html lang="en">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Permohonan Blanko</title>
    <link rel="stylesheet" href="{{ 'template/spt_asesor.css' }}" />
</head>

<body>
    <div class="page nobg">
        <div class="header">
            <table
                style="margin-left: auto; margin-right: auto"
                width="90%"
                border="0"
                cellspacing="0"
                cellpadding="0"
            >
                <tbody>
                    <tr>
                        <td
                            rowspan="2"
                            style="
                                width: 25%;
                                text-align: center;
                                vertical-align: middle;
                            "
                        >
                            <img
                                src="./images/untuk-template/logo.png"
                                width="100px"
                            />
                        </td>
                        <td
                            style="
                                width: 65%;
                                font-size: 22px;
                                color: blue;
                                text-align: center;
                                font-weight: bold;
                                padding: 5px;
                            "
                        >
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
                            "
                        >
                            Graha Gapensi Jl. Raya Ragunan No. 1C, Jati
                            Padang, Pasar Minggu, Jakarta Selatan Telp.
                            0217810444 | Email: admin@lspgatensi.id
                        </td>
                        <td></td>
                    </tr>
                    <tr>
                        <td
                            colspan="3"
                            style="
                                height: 5px;
                                border-bottom: 4px double blue;
                            "
                        ></td>
                    </tr>
                </tbody>
            </table>

            <div
                style="
                    width: 100%;
                    margin-left: 60px;
                    margin-right: 60px;
                    margin-top: 8px;
                "
            >
                <table
                    style="margin-left: auto; margin-right: auto"
                    width="85%"
                    cellspacing="0"
                    cellpadding="0"
                    style="border: 0px"
                >
                    <tbody>
                        <tr>
                            <td
                                colspan="3"
                                style="
                                    width: 100%;
                                    padding: 12px 6px;
                                    text-align: right;
                                "
                            >
                                Jakarta, {{
                                \Carbon\Carbon::parse($tanggal_surat)->setTimezone('Asia/Jakarta')->isoFormat('dddd,
                                D MMMM YYYY') }}
                            </td>
                        </tr>
                        <tr>
                            <td style="width: 20%">Nomor</td>
                            <td style="width: 5%">:</td>
                            <td>{{ $no_surat }}</td>
                        </tr>
                        <tr>
                            <td style="width: 20%">Perihal</td>
                            <td style="width: 5%">:</td>
                            <td>Permohonan Blanko Sertifikat Kompetensi</td>
                        </tr>
                        <tr>
                            <td
                                colspan="3"
                                style="
                                    width: 100%;
                                    padding: 12px 6px 1px 5px;
                                "
                            >
                                Kepada Yth.
                            </td>
                        </tr>
                        <tr>
                            <td
                                colspan="3"
                                style="
                                    width: 100%;
                                    padding: 1px 6px 1px 5px;
                                "
                            >
                                Ketua Badan Nasional Sertifikasi Profesi
                                (BNSP)
                            </td>
                        </tr>
                        <tr>
                            <td
                                colspan="3"
                                style="
                                    width: 100%;
                                    padding: 1px 6px 1px 5px;
                                "
                            >
                                di -
                            </td>
                        </tr>
                        <tr>
                            <td
                                colspan="3"
                                style="
                                    width: 100%;
                                    padding: 1px 6px 6px 5px;
                                "
                            >
                                Jakarta
                            </td>
                        </tr>
                        <tr>
                            <td colspan="3" style="width: 100%">
                                Bersama ini kami melaporkan bahwa Lembaga
                                Sertifikasi Profesi Gatensi Karya Konstruksi
                                telah melaksanakan uji kompetensi dengan
                                rincian (terlampir) sebagai berikut :
                            </td>
                        </tr>
                        <tr>
                            <td style="width: 50%">1. Kompeten Sumber anggaran biaya Mandiri</td>
                            <td style="width: 5%">:</td>
                            <td>{{ $kompeten }}</td>
                        </tr>
                        <tr>
                            <td style="width: 50%">2. Belum Kompeten</td>
                            <td style="width: 5%">:</td>
                            <td>{{ $belum_kompeten }}</td>
                        </tr>
                        <tr style="border-top: solid 2px;">
                            <td style="width: 50%; text-align: right;">Jumlah</td>
                            <td style="width: 5%">:</td>
                            <td>{{ $kompeten + $belum_kompeten }}</td>
                        </tr>
                        <tr>
                            <td colspan="3" style="width: 100%">
                                Sehubungan dengan hal tersebut di atas, kami
                                membutuhkan blanko sertifikat sesuai jumlah
                                peserta yang kompeten sebanyak {{ $kompeten }}
                                lembar. Blanko sertifikat kompetensi yang
                                diberikan BNSP akan kami pertanggungjawabkan
                                sesuai dengan keperuntukannya.
                            </td>
                        </tr>
                        <tr>
                            <td colspan="3" style="width: 100%">
                                Demikian permohonan kami, atas perhatiannya
                                diucapkan terima kasih.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <table
                width="40%"
                style="
                    border: 0px;
                    float: right;
                    margin-right: 60px;
                    margin-top: 25px;
                    text-align: center;
                "
            >
                <tbody>
                    <tr>
                        <td>Hormat kami,</td>
                    </tr>
                    <tr>
                        <td style="font-weight: 700; margin: 0; padding: 0">
                            Lembaga Sertifikasi Profesi Gatensi Karya
                            Konstruksi
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 20px 0px">{!! $svg !!}</td>
                    </tr>
                    <tr>
                        <td style="margin: 0; padding: 0">
                            Radinal Efendy, ST
                        </td>
                    </tr>
                    <tr>
                        <td>Ketua LSP</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</body>

</html>
