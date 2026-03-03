<!DOCTYPE html>
<html lang="en">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>SPT Asesor</title>
    <link rel="stylesheet" href="{{ 'template/spt_asesor.css' }}" />
</head>

<body>
    <div class="page nobg">
        <div class="header">
            <table style="margin-left: auto; margin-right:auto;" width="90%" border="0" cellspacing="0"
                cellpadding="0">
                <tbody>
                    <tr>
                        <td rowspan="2" style="width: 25%; text-align: center; vertical-align: middle">
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
                            LEMBAGA SERTIFIKASI PROFESI GATENSI KARYA KONSTRUKSI
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
                            Graha Gapensi Jl. Raya Ragunan No. 1C, Jati Padang, Pasar
                            Minggu, Jakarta Selatan Telp. 0217810444 | Email:
                            admin@lspgatensi.id
                        </td>
                        <td></td>
                    </tr>
                    <tr>
                        <td colspan="3" style="height: 5px; border-bottom: 4px double blue"></td>
                    </tr>
                </tbody>
            </table>

            <h3
                style="
            text-align: center;
            text-decoration: underline;
            padding-bottom: 8px;
            margin-bottom: 0px;
          ">
                SURAT PERINTAH TUGAS
            </h3>
            <h3
                style="
            text-align: center;
            text-decoration: underline;
            padding-top: 0px;
            margin-top: 0px;
          ">
                Nomor : {{ $no_surat }}
            </h3>

            <div style="width: 100%; margin-left: 60px;">
                <table style="margin-left: auto; margin-right:auto;" width="85%" cellspacing="0" cellpadding="0"
                    style="border: 0px">
                    <tbody>
                        <tr>
                            <td colspan="3" style="width: 100%; padding: 12px 6px">
                                Yang bertandatangan di bawah ini Ketua LSP GKK, dengan ini
                                memberi tugas kepada Asesor Utama:
                            </td>
                        </tr>
                        <tr>
                            <td style="width: 20%">Nama</td>
                            <td style="width: 5%">:</td>
                            <td>{{ $nama_asesor }}</td>
                        </tr>
                        <tr>
                            <td style="width: 20%">No. Reg</td>
                            <td style="width: 5%">:</td>
                            <td>{{ $nama_met }}</td>
                        </tr>
                        <tr>
                            <td colspan="3" style="width: 100%; padding: 12px 6px">
                                dan kepada Asesor Peninjau :
                            </td>
                        </tr>
                        <tr>
                            <td style="width: 20%">Nama</td>
                            <td style="width: 5%">:</td>
                            <td>{{ $nama_asesor2 }}</td>
                        </tr>
                        <tr>
                            <td style="width: 20%">No. Reg</td>
                            <td style="width: 5%">:</td>
                            <td>{{ $nama_met2 }}</td>
                        </tr>
                        <tr>
                            <td colspan="3" style="width: 100%; padding: 12px 6px 6px 5px">
                                Untuk melaksanakan uji kompetensi
                                <span style="text-decoration: underline">bidang konstruksi</span>
                                di :
                            </td>
                        </tr>
                        <tr>
                            <td style="width: 20%">Nama TUK</td>
                            <td style="width: 5%">:</td>
                            <td>{{ $nama_tuk }}</td>
                        </tr>
                        <tr>
                            <td style="width: 20%">Alamat</td>
                            <td style="width: 5%">:</td>
                            <td>
                                {{ $alamat_tuk }}
                            </td>
                        </tr>
                        <tr>
                            <td style="width: 20%">Hari/tanggal</td>
                            <td style="width: 5%">:</td>
                            <td>{{ $hari_tanggal }}</td>
                        </tr>
                        <tr>
                            <td style="width: 20%">Jumlah Peserta</td>
                            <td style="width: 5%">:</td>
                            <td>{{ $jmlh_peserta }}</td>
                        </tr>
                        <tr>
                            <td style="width: 20%">Skema Sertifikasi</td>
                            <td style="width: 5%">:</td>
                            <td>{{ $nama_skema }}</td>
                        </tr>
                    </tbody>
                </table>
                <div style="width: 100%; margin-left: 5px;">
                    <div style="width: 17%; float: left;"> Nama Peserta</div>
                    <div style="float: left;">:</div>
                    <div style="margin-left: 19%;">
                        <ol></ol>
                        <ol style="margin: 0;">
                            @foreach ($nama_peserta as $peserta)
                                <li style="margin: 0 0 5px 0">{{ $peserta }}</li>
                            @endforeach
                        </ol>
                    </div>
                </div>


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
                    <tr>
                        <td style="margin: 0; padding: 0">
                            {{ \Carbon\Carbon::parse($tanggal_surat)->setTimezone('Asia/Jakarta')->isoFormat('dddd, D MMMM YYYY') }}
                        </td>
                    </tr>
                    <tr>
                        <td>Hormat kami,</td>
                    </tr>
                    <tr>
                        <td style="font-weight: 700; margin: 0; padding: 0">
                            Lembaga Sertifikasi Profesi Gatensi Karya Konstruksi
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 20px 0px">{!! $svg !!}</td>
                    </tr>
                    <tr>
                        <td style="margin: 0; padding: 0">Radinal Efendy, ST</td>
                    </tr>
                    <tr>
                        <td>Ketua LSP</td>
                    </tr>
                </tbody>
            </table>
            <br>
            <br>

            <br>
            <table width="40%"
                style="
            border: 0px;
            margin-left: 60px;
            margin-top: 250px;
          ">
                <tbody>
                    <tr>
                        <td>
                            <div style="margin-bottom:4px;">Tembusan :</div>
                            <div style="margin-bottom:4px;">1. Ketua Dewan Pengarah</div>
                            <div style="margin-bottom:4px;">2. Yang bersangkutan</div>
                            <div style="margin-bottom:4px;">3. Arsip</div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</body>

</html>
