<!doctype html>
<html lang="id">
    <head>
        <meta charset="UTF-8" />
        <title>FR.IA.06 - PERTANYAAN TERTULIS ESAI (KAN)</title>
        <style>
            body {
                font-family: "Arial Narrow", Calibri, Candara, Segoe, Segoe UI, Optima, Arial, sans-serif;
                font-size: 12pt;
                margin-top: 65px;
            }
            .hd-dok { background-color: #c40000; color: #fff; }
            .hd-dok-b { background-color: #d58a94; color: #000; }
            td { border: 0.2px solid black; padding: 4px 6px; }
            table { border: 0px solid black; border-collapse: collapse; page-break-inside: avoid; }
            tr { page-break-inside: avoid; }
            .noborder { border: 0 transparent !important; }
            .checkbox {
                width: 12px; height: 12px;
                border: 1px solid #000;
                display: inline-block;
                text-align: center;
                line-height: 11px;
                font-size: 10px;
            }
            .checked::after { content: "✔"; font-family: "DejaVu Sans", sans-serif; }

            .panduan-title {
                background-color: #c00000;
                color: #fff;
                font-weight: bold;
                padding: 4px 8px;
                font-size: 11pt;
            }
        </style>
    </head>
    <body>
        <!-- HEADER -->
        <table width="100%" cellspacing="0" cellpadding="5" bgcolor="#fff" align="center">
            <tr>
                <td class="noborder" style="font-weight: bold; font-size: 16px; letter-spacing: 1px; color: #4F81BD;">
                    FR.IA.06. PERTANYAAN TERTULIS ESAI
                </td>
            </tr>
        </table>
        <br />

        <!-- IDENTITAS -->
        <table width="100%" cellpadding="5" bgcolor="#fff" cellspacing="0" align="center" style="border: 2px solid #000;">
            <tr>
                <td rowspan="2" style="width: 30%;">Skema Sertifikasi (KKNI/Okupasi/Klaster)</td>
                <td style="width: 12%;">Judul</td>
                <td style="width: 3%; text-align: center;">:</td>
                <td style="text-transform: uppercase;">{{ $jabatan_kerja }}</td>
            </tr>
            <tr>
                <td>Nomor</td>
                <td style="text-align: center;">:</td>
                <td style="text-transform: uppercase;">{{ $nomor_skema }}</td>
            </tr>
            <tr>
                <td>TUK</td>
                <td style="text-align: center;">:</td>
                <td colspan="2" style="text-transform: uppercase;">{{ $tuk }}</td>
            </tr>
            <tr>
                <td>Nama Asesi</td>
                <td style="text-align: center;">:</td>
                <td colspan="2" style="text-transform: uppercase;">{{ $nama_asesi }}</td>
            </tr>
            <tr>
                <td>Tanggal</td>
                <td style="text-align: center;">:</td>
                <td colspan="2">{{ $tanggal }}</td>
            </tr>
        </table>
        <p style="font-size: 12px;">*Coret yang tidak perlu</p>

        <!-- PANDUAN BAGI ASESOR -->
        <div class="panduan-title">PANDUAN BAGI ASESOR</div>
        <table width="100%" cellpadding="5" bgcolor="#fff" cellspacing="0" align="center" style="border: 1px solid #000;">
            <tr>
                <td style="border: none; font-size: 11pt;">
                    <b>Instruksi:</b><br/>
                    • Pertanyaan esai merupakan jenis bukti tambahan untuk mendukung bukti-bukti yang sudah ada.<br/>
                    • Asesor menilai jawaban peserta uji berdasarkan jawaban yang diberikan. Penilaian dilakukan dengan memberikan tanda centang (✓) pada salah satu kolom skor penilaian 0, 1, 2, atau 3 sesuai dengan tingkat kesesuaian dan kelengkapan jawaban peserta, dengan ketentuan sebagai berikut:<br/>
                    • Dibutuhkan justifikasi profesional asesor untuk memutuskan hal ini.<br/>
                    &nbsp;&nbsp;0 = Jawaban tidak sesuai, keliru atau tidak menjawab.<br/>
                    &nbsp;&nbsp;1 = Jawaban sebagian benar, namun tidak lengkap/kurang tepat.<br/>
                    &nbsp;&nbsp;2 = Jawaban benar dan sesuai, namun belum sepenuhnya lengkap.<br/>
                    &nbsp;&nbsp;3 = Jawaban lengkap, tepat, runtut dan sesuai konteks.<br/>
                    • Seluruh hasil penilaian dijumlahkan dan dicatat pada kolom Rekapitulasi Skor Penilaian Pertanyaan Esai.
                </td>
            </tr>
        </table>
        <br/>

        <!-- PANDUAN BAGI ASESI -->
        <div class="panduan-title">PANDUAN BAGI ASESI</div>
        <table width="100%" cellpadding="5" bgcolor="#fff" cellspacing="0" align="center" style="border: 1px solid #000;">
            <tr>
                <td style="border: none; font-size: 11pt;">
                    <b>Instruksi:</b><br/>
                    • Pertanyaan esai merupakan jenis bukti tambahan untuk mendukung bukti-bukti yang sudah ada.<br/>
                    • Baca dengan teliti dan cermat pertanyaan esai pada lembar soal.<br/>
                    • Tuliskan jawaban Anda pada Lembar Jawaban Pertanyaan Tertulis Esai.
                </td>
            </tr>
        </table>
        <br/>

        <!-- SOAL -->
        @php $no = 1; @endphp
        @foreach($soal_list as $soal)
        <div style="page-break-inside: avoid;">
        <table width="100%" cellpadding="5" bgcolor="#fff" cellspacing="0" align="center" style="border: 2px solid #000;">
            <tr class="panduan-title">
                <td style="width: 100px; text-align: center; font-weight: bold; color: #fff;">
                    KUK
                </td>
                <td colspan="2" style="font-weight: bold; color: #fff; text-align: center;">
                    SOAL ESAI
                </td>
            </tr>
            <tr>
                <td style="text-align: center; font-weight: bold;" class="hd-dok-b">{{ $soal->unit_kode }}<br />{{ $soal->kuk_kode }}</td>
                <td style="width: 40px; text-align: center;">{{ $no++ }}.</td>
                <td>{{ $soal->soal }}</td>
            </tr>
        </table>
        </div>
        <br />
        @endforeach

        <!-- PENYUSUN DAN VALIDATOR -->
        <h2 style="font-size: 14px; font-weight: bold;">PENYUSUN DAN VALIDATOR</h2>
        <table width="100%" cellpadding="5" bgcolor="#fff" cellspacing="0" align="center" style="border: 1px solid #000;">
            <tr style="background: #fff; font-weight: bold; text-align: center;">
                <td style="width: 15%;">Status</td>
                <td style="width: 8%;">No</td>
                <td>Nama</td>
                <td style="width: 20%;">Nomor MET</td>
                <td style="width: 25%;">Tanda Tangan Dan Tanggal</td>
            </tr>
            <tr style="font-weight: bold;">
                <td rowspan="2">PENYUSUN</td>
                <td style="text-align: center;">1</td>
                <td>{{ $nama_penyusun ?? '' }}</td>
                <td>{{ $noreg_penyusun ?? '' }}</td>
                <td style="height: 50px;">
                    @if($barcode_penyusun ?? null)
                        <img src="{{ $barcode_penyusun }}" style="height: 50px; width: 50px; object-fit: contain;" />
                    @endif
                </td>
            </tr>
            <tr>
                <td style="text-align: center;">2</td>
                <td></td>
                <td></td>
                <td style="height: 50px;"></td>
            </tr>
            <tr style="font-weight: bold;">
                <td rowspan="2">VALIDATOR</td>
                <td style="text-align: center;">1</td>
                <td>{{ $nama_validator ?? '' }}</td>
                <td>{{ $noreg_validator ?? '' }}</td>
                <td style="height: 50px;">
                    @if($barcode_validator ?? null)
                        <img src="{{ $barcode_validator }}" style="height: 50px; width: 50px; object-fit: contain;" />
                    @endif
                </td>
            </tr>
            <tr>
                <td style="text-align: center;">2</td>
                <td></td>
                <td></td>
                <td style="height: 50px;"></td>
            </tr>
        </table>
        <br />

        <!-- FR.IA.06C LEMBAR JAWABAN -->
        <h2 style="font-size: 14px; font-weight: bold;">FR.IA.06C. LEMBAR JAWABAN PERTANYAAN TERTULIS ESAI</h2>
        <table width="100%" cellpadding="5" bgcolor="#fff" cellspacing="0" align="center" style="border: 2px solid #000;">
            <tr>
                <td rowspan="2" style="width: 30%;">Skema Sertifikasi (KKNI/Okupasi/Klaster)</td>
                <td style="width: 12%;">Judul</td>
                <td style="width: 3%; text-align: center;">:</td>
                <td style="text-transform: uppercase;">{{ $jabatan_kerja }}</td>
            </tr>
            <tr>
                <td>Nomor</td>
                <td style="text-align: center;">:</td>
                <td style="text-transform: uppercase;">{{ $nomor_skema }}</td>
            </tr>
            <tr>
                <td>TUK</td>
                <td style="text-align: center;">:</td>
                <td colspan="2" style="text-transform: uppercase;">{{ $tuk }}</td>
            </tr>
            <tr>
                <td>Nama Asesi</td>
                <td style="text-align: center;">:</td>
                <td colspan="2" style="text-transform: uppercase;">{{ $nama_asesi }}</td>
            </tr>
            <tr>
                <td>Tanggal</td>
                <td style="text-align: center;">:</td>
                <td colspan="2">{{ $tanggal }}</td>
            </tr>
        </table>
        <p style="font-size: 12px;">*Coret yang tidak perlu</p>

        <!-- LEMBAR JAWABAN TABLE -->
        <table width="100%" cellpadding="5" bgcolor="#fff" cellspacing="0" align="center" style="border: 1px solid #000;">
            <tr class="hd-dok" style="font-weight: bold; text-align: center;">
                <td rowspan="2">KUK</td>
                <td rowspan="2" colspan="2">JAWABAN SOAL ESAI</td>
                <td colspan="4">Skor Penilaian</td>
            </tr>
            <tr class="hd-dok" style="font-weight: bold; text-align: center;">
                <td style="width: 2%;">0</td>
                <td style="width: 2%;">1</td>
                <td style="width: 2%;">2</td>
                <td style="width: 2%;">3</td>
            </tr>

            @php $no2 = 1; @endphp
            @foreach($soal_list as $soal)
            <tr style="text-align: center;">
                <td style="width: 20%; font-weight: bold;" class="hd-dok-b">{{ $soal->unit_kode }}<br/>{{ $soal->kuk_kode }}</td>
                <td style="width: 5%;">{{ $no2++ }}</td>
                <td style="width: 50%; text-align: left;">
                    <b>Soal:</b> {{ $soal->soal }}<br/>
                    <div style="min-height: 40px; border: 1px solid #ccc; padding: 4px; margin-top: 4px; font-size: 11px; background: #f9f9f9;">
                        <b>Jawaban:</b> {{ $soal->jawaban ?? '-' }}
                    </div>
                </td>
                <td style="width: 5%;"><span class="checkbox @if($soal->skor === 0) checked @endif"></span></td>
                <td style="width: 5%;"><span class="checkbox @if($soal->skor === 1) checked @endif"></span></td>
                <td style="width: 5%;"><span class="checkbox @if($soal->skor === 2) checked @endif"></span></td>
                <td style="width: 5%;"><span class="checkbox @if($soal->skor === 3) checked @endif"></span></td>
            </tr>
            @endforeach
        </table>
        <br />

        <!-- REKAPITULASI -->
        <table style="width: 100%; border: 1px solid #000; border-collapse: collapse; text-align: center;">
            <tr style="font-weight: bold; background-color: #c40000; color: #fff;">
                <td colspan="2" style="text-align: center; padding-right: 20px;">Rekapitulasi Skor Penilaian Pertanyaan IA06<br/>
                    <span style="font-weight: normal;">(Penilaian = Jumlah skor seluruh butir soal)</span>
                </td>
            </tr>
            <tr>
                <td style="text-align: center; font-weight: bold; width: 50%;">Total Skor Penilaian</td>
                <td style="text-align: center; width: 50%;">{{ $total_skor ?? 0 }}</td>
            </tr>
        </table>
        <br/><br/>

        <!-- UMPAN BALIK + ASESI TTD -->
        <table width="100%" cellpadding="5" bgcolor="#fff" cellspacing="0" align="center" style="border: 1px solid #000;">
            <tr>
                <td style="font-weight: bold;">Umpan balik untuk asesi:</td>
                <td>:</td>
                <td>
                    Aspek pengetahuan seluruh unit kompetensi yang diujikan (tercapai / belum tercapai)*<br/><br/>
                    @if($unit_elemen_kuk ?? null)
                        Unit/Elemen/KUK belum tercapai: {{ $unit_elemen_kuk }}
                    @else
                        Tuliskan unit/elemen/KUK jika belum tercapai: …
                    @endif
                </td>
            </tr>
            <tr style="font-weight: bold;">
                <td colspan="3">Asesi :</td>
            </tr>
            <tr>
                <td style="width: 20%;">Nama</td>
                <td style="width: 5%;">:</td>
                <td>{{ strtoupper($nama_asesi) }}</td>
            </tr>
            <tr>
                <td>Tanda tangan/ Tanggal</td>
                <td>:</td>
                <td style="height: 70px; vertical-align: middle; text-align: center;">
                    @if($barcodes['asesi']->url ?? null)
                    <img src="{{ $barcodes['asesi']->url }}" style="height: 50px; width: 50px; object-fit: contain;" /><br />
                    <span style="font-size: 11px;">
                        {{ $barcodes['asesi']->tanggal ? \Carbon\Carbon::parse($barcodes['asesi']->tanggal)->translatedFormat('d F Y') : '' }}
                    </span>
                    @endif
                </td>
            </tr>
        </table>

        <!-- ASESOR TTD -->
        @foreach($asesor_list as $idx => $asesor)
        @php $asesor_key = 'asesor' . ($idx + 1); @endphp
        <table width="100%" cellpadding="5" bgcolor="#fff" cellspacing="0" align="center" style="border: 1px solid #000; border-top: none;">
            <tr style="font-weight: bold;">
                <td colspan="3">Asesor {{ count($asesor_list) > 1 ? ($idx + 1) : '' }} :</td>
            </tr>
            <tr>
                <td style="width: 20%;">Nama</td>
                <td style="width: 5%;">:</td>
                <td>{{ strtoupper($asesor->nama) }}</td>
            </tr>
            <tr>
                <td>No. Reg</td>
                <td>:</td>
                <td>{{ $asesor->no_reg ?? '' }}</td>
            </tr>
            <tr>
                <td>Tanda tangan/ Tanggal</td>
                <td>:</td>
                <td style="height: 70px; vertical-align: middle; text-align: center;">
                    @if($barcodes[$asesor_key]->url ?? null)
                    <img src="{{ $barcodes[$asesor_key]->url }}" style="height: 50px; width: 50px; object-fit: contain;" /><br />
                    <span style="font-size: 11px;">
                        {{ $barcodes[$asesor_key]->tanggal ? \Carbon\Carbon::parse($barcodes[$asesor_key]->tanggal)->translatedFormat('d F Y') : '' }}
                    </span>
                    @endif
                </td>
            </tr>
        </table>
        @endforeach
    </body>
</html>
