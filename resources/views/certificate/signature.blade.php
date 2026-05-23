<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background-color: #f5f5f5;
            font-family: Arial, Helvetica, sans-serif;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: #fff;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .header img {
            width: 100px;
            height: 100px;
        }
        .header h3 {
            color: #111;
            margin: 10px 0 5px;
            font-size: 15px;
        }
        .header h2 {
            color: #111;
            font-size: 18px;
            margin-top: 20px;
        }
        hr {
            border: 0;
            border-top: 1px solid #ddd;
            margin: 20px 0;
        }
        table {
            width: 100%;
            font-size: 16px;
            border-collapse: collapse;
        }
        table td {
            padding: 8px 4px;
            vertical-align: top;
        }
        table td:first-child {
            width: 200px;
            color: #555;
        }
        .error {
            text-align: center;
            color: #c00;
            font-size: 18px;
            padding: 40px 0;
        }
        .footer {
            text-align: center;
            color: #999;
            font-size: 12px;
            margin-top: 30px;
        }
    </style>
</head>
<body>
    <div class="container">
        @if ($error)
            <div class="error">{{ $error }}</div>
        @else
            <div class="header">
                <img src="{{ asset('images/logo-lsp.png') }}" alt="Logo LSP" onerror="this.style.display='none'">
                <h3>Lembaga Sertifikasi Profesi<br>GATENSI KARYA KONSTRUKSI</h3>
                <h2>Data Validasi Signature SKK-K</h2>
            </div>
            <hr>
            <table>
                <tr>
                    <td>Nama Pemohon</td>
                    <td>:</td>
                    <td>{{ $result['nama'] }}</td>
                </tr>
                <tr>
                    <td>Jabatan Kerja</td>
                    <td>:</td>
                    <td>{{ $result['jabker'] }}</td>
                </tr>
                <tr>
                    <td>Nama Ketua Pelaksana</td>
                    <td>:</td>
                    <td>Radinal Efendy, ST</td>
                </tr>
                <tr>
                    <td>Tanggal &amp; Waktu Pengesahan</td>
                    <td>:</td>
                    <td>{{ $result['tgl'] }}</td>
                </tr>
            </table>
            <div class="footer">
                &copy; LSP Gatensi Karya Konstruksi &mdash; Data ini bersifat resmi dan tervalidasi.
            </div>
        @endif
    </div>
</body>
</html>
