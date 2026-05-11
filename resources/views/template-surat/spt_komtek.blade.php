<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Surat Perintah Tugas</title>
<style>
    @page { margin: 0cm 0cm; size: A4; }

    body{
        margin-top: 3cm;
        margin-left: 2cm;
        margin-right: 2cm;
        margin-bottom: 2cm;
        font-family: Arial, sans-serif;
        font-size: 14px;
        background: #fff;
    }

    header{
        position: fixed;
        top: 0cm;
        left: 0cm;
        right: 0cm;
        height: 3cm;
    }

    header img{
        width: 100%;
        height: 100%;
    }

    footer{
        position: fixed;
        bottom: 0cm;
        left: 0cm;
        right: 0cm;
        height: 2cm;
    }

    footer img{
        width: 100%;
        height: 100%;
    }

    .title {
        text-align: center;
        font-weight: bold;
        text-decoration: underline;
        font-size: 14px;
        margin-bottom: 5px;
    }

    .number {
        text-align: center;
        margin-bottom: 25px;
        font-size: 14px;
    }

    .highlight {
        font-weight: bold;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 10px;
        margin-bottom: 20px;
        font-size: 14px;
    }

    th, td {
        border: 1px solid black;
        padding: 6px;
    }

    th {
        background-color: #d9e1e7;
        text-align: center;
    }

    .no-border td {
        border: none;
        padding: 4px 0;
    }

    .section-text {
        font-size: 14px;
        margin-bottom: 10px;
        text-align: justify;
    }

    .info-table {
        width: 100%;
        font-size: 14px;
        margin-top: 10px;
    }

    .info-table td {
        border: none;
        padding: 10px 0;
        vertical-align: top;
    }

    .label {
        width: 180px;
    }

    .colon {
        width: 10px;
    }

    .footer {
        margin-top: 40px;
        display: flex;
        justify-content: flex-end;
        font-size: 14px;
    }

    .director {
        text-align: left;
        margin-top: 5px;
        font-weight: bold;
    }

    .director .sign-space {
        display: inline-block;
        width: 100%;
        height: 80px;
    }
</style>
</head>

<body>

<header>
    <img src="./images/untuk-template/kop_satu.png" width="100%" />
</header>

<footer>
    <img src="./images/untuk-template/kop_dua.png" width="100%" />
</footer>

    <div class="title">SURAT PERINTAH TUGAS</div>

    <div class="number highlight">
        Nomor : <span class="highlight">{{ $nomor ?? 'XXX/LSP GKK B.001-D/ROMAN/TAHUN' }}</span>
    </div>

    <div class="section-text">
        Yang bertandatangan di bawah ini Direktur LSP Gatensi Karya Konstruksi,
        dengan ini memberi tugas kepada Komite Teknis:
    </div>
    <br/>
    <table>
        <tr>
            <th>No</th>
            <th>Nama</th>
            <th>Jabatan</th>
        </tr>
        @if($nama_komtek1 ?? false)
        <tr>
            <td>1.</td>
            <td>{{ $nama_komtek1 }}</td>
            <td>Ketua</td>
        </tr>
        @endif
        @if($nama_komtek2 ?? false)
        <tr>
            <td>2.</td>
            <td>{{ $nama_komtek2 }}</td>
            <td>Anggota</td>
        </tr>
        @endif
        @if($nama_komtek3 ?? false)
        <tr>
            <td>3.</td>
            <td>{{ $nama_komtek3 }}</td>
            <td>Anggota</td>
        </tr>
        @endif
    </table>

    <div class="section-text">
        Untuk melaksanakan evaluasi hasil uji kompetensi dan pengambilan keputusan
        atas uji kompetensi yang telah dilaksanakan dengan rincian sebagai berikut:
    </div>
    <br/>
    <table class="info-table">
        <tr>
            <td class="label">Nama TUK</td>
            <td class="colon">:</td>
            <td><span class="highlight">{{ $nama_tuk ?? '-' }}</span></td>
        </tr>
        <tr>
            <td class="label">Alamat</td>
            <td class="colon">:</td>
            <td>
                <span class="highlight">
                {{ $alamat_tuk ?? '-' }}
                </span>
            </td>
        </tr>

        <tr>
            <td class="label">Hari/tanggal Uji</td>
            <td class="colon">:</td>
            <td><span class="highlight">{{ $hari_tanggal ?? '-' }}</span></td>
        </tr>

        <tr>
            <td class="label">Jumlah Peserta</td>
            <td class="colon">:</td>
            <td><span class="highlight">{{ $jmlh_peserta ?? count($peserta ?? []) }} orang</span></td>
        </tr>

        <tr>
            <td class="label">Skema Sertifikasi</td>
            <td class="colon">:</td>
            <td><span class="highlight">{{ $nama_skema ?? '-' }}</span></td>
        </tr>

        <tr>
            <td class="label">Nama Peserta</td>
            <td class="colon">:</td>
            <td><span class="highlight">
            @foreach($peserta ?? [] as $index => $asesi)
            {{ $index + 1 }}. {{ $asesi['nama'] }}<br/>
            @endforeach
            </span></td>
        </tr>
    </table>
    <br/><br/>
    <div class="footer">
        <div class="director">
            <span style="font-weight:normal">Jakarta, <span>{{ now()->isoFormat('D MMMM Y') }}</span></span><br>
            LSP Gatensi Karya Konstruksi<br>
            <div class="sign-space"></div>
            @if($qr)
            {!! $qr !!}
            @else
            Radihal Efendy, S.T.<br>
            Direktur
            @endif
        </div>
    </div>

</body>
</html>
