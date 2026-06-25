<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Invoice</title>

    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10px;
            margin: 0;
            padding: 0;
            color: #000;
        }

        .wrapper {
            width: 700px;
            margin: 0 auto;
            padding: 10px 10px;
        }

        .header {
            width: 100%;
            margin-bottom: 20px;
            position: relative;
        }

        .invoice-title {
            font-size: 32px;
            font-weight: bold;
            margin-bottom: 15px;
            letter-spacing: 1px;
        }

        .logo {
            width: 200px;
            margin-bottom: 15px;
        }

        .bold {
            font-weight: bold;
        }

        .info-text {
            line-height: 1.5em;
            font-size: 10px;
        }

        .billed-to {
            font-size: 10px;
            margin-bottom: 5px;
        }

        .left-section {
            width: 55%;
            display: inline-block;
            vertical-align: top;
        }

        .right-section {
            width: 44%;
            display: inline-block;
            vertical-align: top;
            text-align: right;
            position: absolute;
            right: 0;
            top: 0;
        }

        .right-text {
            margin-left: 80px;
        }

        .right-section img {
            display: block;
            margin-left: auto;
            margin-right: 0;
        }

        .invoice-info {
            text-align: left;
            margin-top: 5px;
        }

        .info-section {}

        .info-section table {
            width: 100%;
            border: none;
            margin: 0;
        }

        .info-section td {
            border: none !important;
            padding: 3px 0;
            font-size: 10px;
            vertical-align: top;
        }

        table.participants {
            width: 100%;
            border-collapse: collapse;
            font-size: 9.5px;
            margin-top: 10px;
        }

        table.participants th {
            background: #003d73;
            color: #fff;
            padding: 6px 4px;
            border: 0.5px solid #003d73;
            font-weight: bold;
            text-align: center;
        }

        table.participants td {
            padding: 2px 1px;
            border: 0.5px solid #999;
            font-size: 9.5px;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .bottom-section {
            width: 100%;
            margin-top: 15px;
            display: flex;
            justify-content: space-between;
        }

        .footer-box {
            width: 50%;
            font-size: 9.5px;
            line-height: 1.6em;
        }

        .totals-box {
            width: 45%;
        }

        .totals-box table {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
        }

        .totals-box table td {
            border: none !important;
            padding: 4px 0;
            font-size: 10px;
        }

        .total-line {
            background: #003d73;
            color: #fff;
        }

        .total-line td {
            font-weight: bold;
            padding: 6px 8px !important;
            border: none !important;
        }

        .discount-line {
            background: #003d73;
            color: #fff;
        }

        .discount-line td {
            padding: 6px 8px !important;
        }
    </style>
</head>

<body>
    <div class="wrapper">

        <!-- HEADER -->
        <div class="header">

            <!-- LEFT -->
            <div class="left-section">
                <div class="invoice-title">INVOICE</div>

                <div class="billed-to bold">Ditagihkan Kepada:</div>
                <div class="info-text bold">
                    {{ $data_instansi->nama }} <br>
                    {{ $data_instansi->alamat }}
                </div>
            </div>

            <!-- RIGHT -->
            <div class="right-section">
                <img src="{{ asset('images/logo-template.png') }}" class="logo">

                <div class="info-text right-text bold" style="text-align: left; white-space: nowrap;">
                    <span>No. Invoice : {{ $latest_invno }}</span><br>
                    Tanggal Invoice : {{ \Carbon\Carbon::now()->isoFormat('D MMMM YYYY') }}
                </div>
            </div>
        </div>


        <!-- INFORMASI KOMPETENSI -->
        <div class="info-section">
            <table>
                <tr>
                    <td style="width:30%;">Tempat Uji Kompetensi</td>
                    <td style="width:2%;">:</td>
                    <td style="width:68%;">{{ $data_asesi_beserta_harga[0]['nama_tuk'] }}</td>
                </tr>
                <tr>
                    <td>Tanggal Uji Kompetensi</td>
                    <td>:</td>
                    <td>{{ \Carbon\Carbon::parse($tanggal_uji)->isoFormat('D MMMM YYYY') }}</td>
                </tr>
                <tr>
                    <td>Metode Pelaksanaan</td>
                    <td>:</td>
                    <td>{{ ucwords($metode) }}</td>
                </tr>
                <tr>
                    <td>Jenis Permohonan</td>
                    <td>:</td>
                    <td>Permohonan Baru</td>
                </tr>
                <tr>
                    <td>Kategori TUK</td>
                    <td>:</td>
                    <td>{{ $kategori_tuk }}</td>
                </tr>
            </table>
        </div>


        <!-- TABEL PESERTA -->
        <table class="participants">
            <thead>
                <tr>
                    <th style="width: 30px;">NO</th>
                    <th style="width: 20%;">NAMA</th>
                    <th style="width: 15%;">ID IZIN</th>
                    <th style="width: 40%;">JABATAN KERJA</th>
                    <th style="width: 60px;">JENJANG</th>
                    <th style="width: 90px;">HARGA</th>
                </tr>
            </thead>

            <tbody>
                @foreach($data_asesi_beserta_harga as $key => $item)
                    <tr>
                        <td class="text-center">{{ $key + 1 }}</td>
                        <td>{{ $item['nama'] }}</td>
                        <td>{{ $item['id_izin'] }}</td>
                        <td>{{ $item['jabatan_kerja'] }}</td>
                        <td class="text-center">{{ $item['jenjang'] }}</td>
                        <td class="text-right">Rp {{ number_format($item['harga'] + $item['harga_2'], 0, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>


        <!-- FOOTER + TOTAL -->
        <table style="width:100%; margin-top:16px; border-collapse:collapse; font-size:9.5px;">
            <tr>
                <td style="width:65%; vertical-align:top; line-height:1.4em;">
                    <div style="position:relative;">
                        <strong>Metode Pembayaran:</strong>

                        <span style="
        position:absolute;
        left:140px;
        top:0;
        background:#003d73;
        color:#fff;
        font-style:italic;
        padding:2px 10px;
        white-space:nowrap;
        font-size:9.5px;
        font-weight:bold;
    ">
                            {{ $total_word }}
                        </span>
                    </div>

                    <div style="margin-top:4px;">
                        @if(isset($payment_method) && $payment_method === 'midtrans')
                            <strong>Midtrans Payment Gateway</strong><br>
                            @if(isset($payment_status) && $payment_status === 'paid' && isset($midtrans_detail))
                                <span style="color:#008000;font-weight:bold;">Status: LUNAS</span><br>
                                @if($midtrans_detail['payment_type_display'])
                                    Metode: {{ $midtrans_detail['payment_type_display'] }}<br>
                                @endif
                                @if(!empty($midtrans_detail['bank']) && !empty($midtrans_detail['va_number']))
                                    Bank: {{ strtoupper($midtrans_detail['bank']) }}<br>
                                    VA Number: {{ $midtrans_detail['va_number'] }}<br>
                                @endif
                                @if($midtrans_detail['transaction_time'])
                                    Waktu: {{ $midtrans_detail['transaction_time'] }}<br>
                                @endif
                            @endif
                        @else
                            <strong>Transfer ke Rekening</strong><br>
                            BTN Kantor Cabang Jakarta Kuningan<br>
                            00001.01.30.001326.6<br>
                            Atas Nama LSP GATENSI KARYA KONSTRUKSI
                        @endif

                        <!-- Electronic statement at bottom of left column -->
                        @if(isset($personal_type) && $personal_type === 'reguler')
                            <div style="margin-top:20px; font-size:9px; line-height:1.4; font-weight:bold;">
                                Invoice ini diterbitkan secara elektronik<br>
                                oleh {{ $user_jabatan ?? 'Staff' }} "{{ $issued_by }}"<br>
                                dan sah tanpa tanda tangan<br>
                                maupun cap perusahaan.
                            </div>
                        @endif
                    </div>
                </td>

                <td style="width:25%; vertical-align:top;">
                    <table style="width:100%; border-collapse:collapse; font-size:9.5px;">
                        <tr style="font-weight: bold">
                            <td style="padding:2px 6px;">Total</td>
                            <td style="padding:2px 6px; text-align:right;">
                                Rp {{ number_format($total_harga, 0, ',', '.') }}
                            </td>
                        </tr>

                        <tr style="font-weight: bold">
                            <td style="padding:2px 6px;">Diskon</td>
                            <td style="padding:2px 6px; text-align:right;">
                                Rp {{ number_format($total_diskon, 0, ',', '.') }}
                            </td>
                        </tr>

                        <tr>
                            <td colspan="2" style="border-top:1px solid #003d73; padding-top:2px;"></td>
                        </tr>

                        <tr style="background:#003d73; color:#fff; font-weight:bold;">
                            <td style="padding:5px 6px;">Sisa Tagihan</td>
                            <td style="padding:5px 6px; text-align:right;">
                                Rp {{ number_format($total_harga - $total_diskon, 0, ',', '.') }}
                            </td>
                        </tr>

                        @if(isset($personal_type) && $personal_type !== 'reguler')
                            <tr>
                                <td colspan="2" style="padding-top:25px; font-size:10px; font-weight:bold;">
                                    LSP GATENSI KARYA KONSTRUKSI
                                </td>
                            </tr>
                            <tr>
                                <td colspan="2">
                                    <img src="{{ asset('images/ttd-silviana.png') }}" style="width:70px; height:auto; display:block;">
                                </td>
                            </tr>
                            <tr>
                                <td colspan="2" style="font-size:10px; line-height:1.4;">
                                    <span style="font-weight:bold;">SILVIANA ROSMA DP</span><br>
                                    <span style="font-weight:normal; font-size:9px;">FINANCE</span>
                                </td>
                            </tr>
                        @endif
                    </table>
                    @if(!isset($personal_type) || $personal_type !== 'reguler')
                        <div style="margin-top:8px;">
                            <img src="{{ asset('images/stamp.png') }}" style="width:70px; height:auto;">
                        </div>
                    @endif
                </td>
            </tr>
        </table>



    </div>
</body>

</html>