<!doctype html>
<html lang="id">
    <head>
        <meta charset="UTF-8" />
        <title>FR.IA.05 - PERTANYAAN TERTULIS PILIHAN GANDA (KAN)</title>
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
                margin-right: 4px;
            }
            .checked::after { content: "✔"; font-family: "DejaVu Sans", sans-serif; }
            .radio {
                width: 12px; height: 12px;
                border: 1px solid #000;
                border-radius: 50%;
                display: inline-block;
                text-align: center;
                line-height: 10px;
                font-size: 10px;
                margin-right: 4px;
            }
            .radio.checked::after { content: "•"; font-size: 14px; }
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
                    FR.IA.05. PERTANYAAN TERTULIS PILIHAN GANDA
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
                    1. Pertanyaan pilihan ganda merupakan jenis bukti tambahan untuk mendukung bukti-bukti yang sudah ada.<br/>
                    2. Asesor menilai jawaban peserta uji berdasarkan jawaban yang diberikan. Penilaian dapat diisi dengan centang (✓) pada kolom jawaban benar atau jawaban salah, dengan ketentuan skor penilaian sebagai berikut:<br/>
                    &nbsp;&nbsp;0 = Jawaban Salah<br/>
                    &nbsp;&nbsp;1 = Jawaban Benar<br/>
                    3. Dibutuhkan justifikasi profesional asesor untuk memutuskan hal ini.
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
                    1. Pertanyaan pilihan ganda merupakan jenis bukti tambahan untuk mendukung bukti-bukti yang sudah ada.<br/>
                    2. Baca dengan teliti dan cermat pertanyaan Pilihan Ganda pada lembar soal.<br/>
                    3. Tuliskan jawaban Anda pada Lembar Jawaban Pertanyaan Pilihan Ganda.
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
                    {{ $soal->unit_kode }}<br />{{ $soal->kuk_kode }}
                </td>
                <td colspan="2" style="font-weight: bold; color: #fff;">
                    SOAL, Pilih Jawaban semua pertanyaan berikut (A / B / C / D) :
                </td>
            </tr>
            <tr>
                <td style="text-align: center; font-weight: bold;" class="hd-dok-b">{{ $soal->unit_kode }}<br />{{ $soal->kuk_kode }}</td>
                <td style="width: 40px; text-align: center;">{{ $no++ }}.</td>
                <td>{{ $soal->soal }}</td>
            </tr>
            <tr>
                <td></td>
                <td></td>
                <td><span class="radio @if($soal->jawaban_asesi === 'A') checked @endif"></span> a. {{ $soal->jawab_a }}</td>
            </tr>
            <tr>
                <td></td>
                <td></td>
                <td><span class="radio @if($soal->jawaban_asesi === 'B') checked @endif"></span> b. {{ $soal->jawab_b }}</td>
            </tr>
            <tr>
                <td></td>
                <td></td>
                <td><span class="radio @if($soal->jawaban_asesi === 'C') checked @endif"></span> c. {{ $soal->jawab_c }}</td>
            </tr>
            <tr>
                <td></td>
                <td></td>
                <td><span class="radio @if($soal->jawaban_asesi === 'D') checked @endif"></span> d. {{ $soal->jawab_d }}</td>
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

        <!-- FR.05.C LEMBAR JAWABAN -->
        <h2 style="font-size: 14px; font-weight: bold;">FR.05.C. LEMBAR JAWABAN PERTANYAAN TERTULIS PILIHAN GANDA</h2>
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
                <td colspan="2">Lembar Jawaban</td>
                <td colspan="2">Rekomendasi</td>
            </tr>
            <tr class="hd-dok-b" style="font-weight: bold; text-align: center;">
                <td style="width: 10%;">No</td>
                <td style="width: 40%;">Jawaban</td>
                <td style="width: 25%;">Benar</td>
                <td style="width: 25%;">Salah</td>
            </tr>

            @php $no2 = 1; @endphp
            @foreach($soal_list as $soal)
            <tr>
                <td style="text-align: center;">{{ $no2++ }}</td>
                <td>
                    @if($soal->jawaban_asesi)
                        {{ $soal->jawaban_asesi }} -
                        @if($soal->jawaban_asesi === 'A') {{ $soal->jawab_a }}
                        @elseif($soal->jawaban_asesi === 'B') {{ $soal->jawab_b }}
                        @elseif($soal->jawaban_asesi === 'C') {{ $soal->jawab_c }}
                        @elseif($soal->jawaban_asesi === 'D') {{ $soal->jawab_d }}
                        @endif
                    @else
                        <span style="color: #999; font-style: italic;">Belum dijawab</span>
                    @endif
                </td>
                <td style="text-align: center;">
                    <span class="checkbox @if($soal->jawaban_asesi && $soal->jawaban_asesi === $soal->kunci_jawaban) checked @endif"></span>
                </td>
                <td style="text-align: center;">
                    <span class="checkbox @if($soal->jawaban_asesi && $soal->jawaban_asesi !== $soal->kunci_jawaban) checked @endif"></span>
                </td>
            </tr>
            @endforeach
        </table>
        <br />

        <!-- REKAPITULASI -->
        <table style="width: 100%; border: 1px solid #000; border-collapse: collapse; text-align: center;">
            <tr style="font-weight: bold; background-color: #c40000; color: #fff;">
                <td colspan="2" style="text-align: center; padding-right: 20px;">Rekapitulasi Penilaian Pertanyaan Pilihan Ganda</td>
            </tr>
            <tr>
                <td style="font-weight: bold; text-align: center; background-color: #c40000; color: #fff;">Benar</td>
                <td style="font-weight: bold; text-align: center; background-color: #c40000; color: #fff;">Salah</td>
            </tr>
            <tr>
                <td style="text-align: center;">{{ $jumlah_benar ?? '_____' }}</td>
                <td style="text-align: center;">{{ $jumlah_salah ?? '_____' }}</td>
            </tr>
        </table>
        <br/><br/>

        <!-- UMPAN BALIK + ASESI TTD -->
        <table width="100%" cellpadding="5" bgcolor="#fff" cellspacing="0" align="center" style="border: 1px solid #000;">
            <tr>
                <td style="font-weight: bold;">Umpan balik untuk asesi:</td>
                <td>:</td>
                <td>Aspek pengetahuan seluruh unit kompetensi yang diujikan (tercapai / belum tercapai)*<br/><br/>Tuliskan unit/elemen/KUK jika belum tercapai: …</td>
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
