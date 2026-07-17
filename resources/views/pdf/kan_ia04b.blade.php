<!doctype html>
<html lang="id">
    <head>
        <meta charset="UTF-8" />
        <title>FR.IA.04.B - LEMBAR PERIKSA KEGIATAN TERSTRUKTUR (KAN)</title>
        <style>
            body {
                font-family: "Open Sans", Calibri, Candara, Segoe, Segoe UI, Optima, Arial, sans-serif;
                font-size: 11pt;
                margin-top: 65px;
            }
            .hd-dok { background-color: #c40000; color: #fff; }
            td { border: 0.2px solid black; }
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
        </style>
    </head>
    <body>
        <table width="100%" cellspacing="0" cellpadding="5" bgcolor="#fff" align="center">
            <tr>
                <td class="judul noborder" style="font-weight: bold; font-size: 16px; letter-spacing: 1px; color: #4F81BD;">
                    FR.IA.04.B {{ $nama_dokumen ?? 'LEMBAR PERIKSA KEGIATAN TERSTRUKTUR' }}
                </td>
            </tr>
        </table>
        <br />

        <!-- IDENTITAS -->
        <table width="100%" cellpadding="5" bgcolor="#fff" cellspacing="0" align="center" style="border: 1px solid #000;">
            <tr>
                <td rowspan="2" style="width: 30%; vertical-align: top;">Skema Sertifikasi (KKNI/Okupasi/Klaster)</td>
                <td style="width: 12%;">Judul</td>
                <td>{{ strtoupper($jabatan_kerja) }}</td>
            </tr>
            <tr>
                <td>Nomor</td>
                <td>{{ strtoupper($nomor_skema) }}</td>
            </tr>
            <tr>
                <td>TUK</td>
                <td>:</td>
                <td>{{ strtoupper($tuk) }}</td>
            </tr>
            <tr>
                <td>Nama Asesi</td>
                <td>:</td>
                <td>{{ strtoupper($nama_asesi) }}</td>
            </tr>
            <tr>
                <td>Tanggal</td>
                <td>:</td>
                <td>{{ $tanggal }}</td>
            </tr>
        </table>
        <p style="font-size: 12px;">*Coret yang tidak perlu</p>

        <!-- PANDUAN BAGI ASESOR -->
        <table width="100%" cellpadding="5" bgcolor="#fff" cellspacing="0" align="center" style="border: 1px solid #000;">
            <tr class="hd-dok">
                <td style="font-weight: bold;">PANDUAN BAGI ASESOR</td>
            </tr>
            <tr>
                <td>
                    <ul style="margin: 4px 0 4px 18px; padding-left: 18px; list-style-type: disc;">
                        <li style="margin-bottom: 4px;">Lakukan penilaian pencapaian hasil proyek singkat atau kegiatan terstruktur lainnya melalui presentasi.</li>
                        <li style="margin-bottom: 4px;">Penilaian dapat dilakukan untuk keseluruhan unit kompetensi dalam skema sertifikasi atau dapat pula dilakukan untuk masing-masing kelompok pekerjaan.</li>
                        <li style="margin-bottom: 4px;">Pertanyaan disampaikan oleh asesor pada saat asesi melakukan presentasi kegiatan terstruktur.</li>
                        <li style="margin-bottom: 4px;">Asesor menilai jawaban peserta uji berdasarkan jawaban yang diberikan. Penilaian dilakukan dengan memberikan tanda centang (✓) pada salah satu kolom skor penilaian 0, 1, 2, atau 3 sesuai dengan tingkat kesesuaian dan kelengkapan jawaban peserta, dengan ketentuan sebagai berikut:<br/>
0 = Jawaban tidak sesuai, keliru, atau tidak menjawab<br/>
1 = Jawaban sebagian benar, namun tidak lengkap/kurang tepat<br/>
2 = Jawaban benar dan sesuai, namun belum sepenuhnya lengkap<br/>
3 = Jawaban lengkap, tepat, runtut dan sesuai konteks</li>
                        <li style="margin-bottom: 4px;">Dibutuhkan justifikasi profesional asesor untuk memutuskan hal ini.</li>
                        <li style="margin-bottom: 4px;">Seluruh hasil penilaian dijumlahkan dan dicatat pada kolom Rekapitulasi Skor Penilaian.</li>
                        <li style="margin-bottom: 0;">Durasi presentasi yaitu 15 menit dan tanya jawab 15 menit.</li>
                    </ul>
                </td>
            </tr>
        </table>
        <br />

        <!-- MAIN ASSESSMENT TABLE -->
        <table width="100%" cellpadding="5" bgcolor="#fff" cellspacing="0" align="center" style="border: 1px solid #000;">
            <tr class="hd-dok" style="font-weight: bold; text-align: center;">
                <td rowspan="2" style="width: 5%;">No</td>
                <td colspan="3" style="width: 14%;">Aspek Penilaian</td>
                <td colspan="4" style="width: 14%;">Pencapaian</td>
            </tr>
            <tr class="hd-dok" style="font-weight: bold; text-align: center;">
                <td style="width: 30%;">Lingkup Penyajian Proyek atau Kegiatan Terstruktur Lainnya</td>
                <td style="width: 25%;">Daftar Pertanyaan</td>
                <td style="width: 25%;">Kesesuaian dengan standar kompetensi kerja</td>
                <td style="width: 3%;">0</td>
                <td style="width: 3%;">1</td>
                <td style="width: 3%;">2</td>
                <td style="width: 3%;">3</td>
            </tr>

            @foreach($soal_list as $soal)
            <tr>
                <td style="text-align: center; vertical-align: top;">{{ $soal->no }}</td>
                <td style="vertical-align: top;">{{ $soal->soal }}</td>
                <td style="vertical-align: top;">
                    <div>{{ $soal->soal1 }}</div>
                    <p style="margin: 8px 0 4px 0; font-size: 12px; font-weight: bold;">Jawaban asesi:</p>
                    <div style="min-height: 50px; border: 1px solid #ccc; padding: 6px; font-size: 12px; background: #f9f9f9;">
                        {{ $soal->jawaban ?? '-' }}
                    </div>
                </td>
                <td style="vertical-align: top;">{{ $soal->soal2 }}</td>
                <td style="text-align: center; vertical-align: middle;">
                    <span class="checkbox @if($soal->pencapaian === 0) checked @endif"></span>
                </td>
                <td style="text-align: center; vertical-align: middle;">
                    <span class="checkbox @if($soal->pencapaian === 1) checked @endif"></span>
                </td>
                <td style="text-align: center; vertical-align: middle;">
                    <span class="checkbox @if($soal->pencapaian === 2) checked @endif"></span>
                </td>
                <td style="text-align: center; vertical-align: middle;">
                    <span class="checkbox @if($soal->pencapaian === 3) checked @endif"></span>
                </td>
            </tr>
            @endforeach
        </table>
        <br />

        <!-- PENYUSUN DAN VALIDATOR -->
        <h2 style="font-size: 14px; font-weight: bold;">PENYUSUN DAN VALIDATOR</h2>
        <table width="100%" cellpadding="5" bgcolor="#fff" cellspacing="0" align="center" style="border: 1px solid #000;">
            <tr style="background: #fff; font-weight: bold; text-align: center;">
                <td style="width: 15%;">Status</td>
                <td style="width: 8%;">No</td>
                <td>Nama</td>
                <td style="width: 20%;">Nomor MET</td>
                <td style="width: 20%;">Tanda Tangan Dan Tanggal</td>
            </tr>
            <tr style="font-weight: bold;">
                <td rowspan="2">Penyusun</td>
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
                <td rowspan="2">Validator</td>
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
        <br/><br/>

        <!-- REKAPITULASI SKOR -->
        <table width="100%" cellpadding="5" bgcolor="#fff" cellspacing="0" align="center" style="border: 1px solid #000;">
            <tr class="hd-dok" style="font-weight: bold; text-align: center;">
                <td colspan="2">Rekapitulasi Skor Penilaian Pertanyaan IA04B<br/>
                    <span style="font-weight: normal;">(Penilaian = Jumlah skor seluruh butir soal)</span>
                </td>
            </tr>
            <tr style="text-align: center;">
                <td style="font-weight: bold; text-align: center;">Total Skor Penilaian</td>
                <td style="font-weight: bold; text-align: center; height: 50px;">{{ $total_skor ?? 0 }}</td>
            </tr>
        </table>
        <br/>

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
                <td>{{ $asesor->noreg ?? '' }}</td>
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
