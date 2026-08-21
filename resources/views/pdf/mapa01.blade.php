<!doctype html>
<html lang="id">
    <head>
        <meta charset="UTF-8" />
        <title>FR.MAPA.01</title>
        <style>
            body {
                font-family:
                    "Open Sans",
                    Calibri,
                    Candara,
                    Segoe,
                    "Segoe UI",
                    Optima,
                    Arial,
                    sans-serif;
                font-size: 11pt;
                margin-top: 0;
            }
            .hd-dok {
                background-color: #c00000;
                color: #fff;
            }
            .hd-dok-b {
                background-color: #e79291;
                color: #000;
            }
            td {
                border: 0.2px solid black;
            }
            table {
                border: 0px solid black;
            }
            .fnt {
                font-family:
                    "Open Sans",
                    Calibri,
                    Candara,
                    Segoe,
                    "Segoe UI",
                    Optima,
                    Arial,
                    sans-serif;
            }
            .noborder {
                border: 0 transparent !important;
            }
            .no-top-bottom {
                border-bottom-color: transparent !important;
                border-top-color: transparent !important;
            }
            .no-bottom {
                border-bottom-color: transparent !important;
            }
            .no-top {
                border-top-color: transparent !important;
            }
            .no-break {
                page-break-inside: avoid;
            }
            .headera,
            .footera {
                position: static; /* was fixed, changed for DomPDF performance */
            }
            .headera {
                top: 0px;
            }
            .tba td {
                border: 0 transparent !important;
            }

            .checkbox {
                width: 12px;
                height: 12px;
                border: 1px solid #000;
                display: inline-block;
                text-align: center;
                line-height: 11px;
                font-size: 15px;
                font-weight: bold;
                font-family: "DejaVu Sans", sans-serif;
            }

            .checked::after {
                content: "✔";
            }

            .radio {
                width: 12px;
                height: 12px;
                border: 1px solid #000;
                border-radius: 50%;
                display: inline-block;
                text-align: center;
                line-height: 11px;
                font-size: 10px;
            }

            .radio-checked::after {
                content: "•";
                font-size: 14px;
            }
            td.vhead {
                position: relative;
                padding: 0;
                height: 280px;          /* must be explicit */
                vertical-align: middle;
                text-align: center;
            }

            td.vhead .vtext {
                position: absolute;
                top: 50%;
                left: 50%;
                width: 280px;           /* SAME as td height */
                transform: translate(-50%, -50%) rotate(-90deg);
                transform-origin: center;
                white-space: normal;
                font-size: 9pt;
            }
        </style>
    </head>
    <body>
        <table width=100% align=center class="noborder headera" cellpadding=5 cellspacing=0>
            <tr>
                <td class=noborder><img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAGUAAAA3CAIAAACaWBNkAAAACXBIWXMAAA7DAAAOwwHHb6hkAAANX0lEQVR4nOyaC1SVVRaAj69JTUdLTW35StN0tEKnZerKGZUeY7U0l29dlaMt07Q1DqOOz3HGV+IbAXk/BAkEBSlSUhQ0QLhcBERE5S0PIUERIjWUf777n7xdLnDhJq1sdfdise5//vPY+zt777PPBaFYxBwRv7YCvzGx8DJPLLzMEwsv88TCyzyx8DJPLLzMEwsv86SZeOXlKe7uzTPV4y3NwauiQpkxUwkNbYapHnt5VF411dXKihXKP22U+/ebRaHHXB7Zvzy9lEGDldLS5lDmNyCPxismRhFCiYxsHl1+C/IIvAoKdLBs/qXU1DSfPo+7/Fxed+4oI/6siDbKrVuPqMHt27cfPHjQlJ50q64t9Q6sqanRd/hBFX37fVVov3fv3nfffVehyp07d5qogGLE6+7du9ebJsrChTrnOnvWcHh2draLi8sKVVauXLlKlbVr127fvv3IkSMlJSX1amBjY/Phhx/m5uY2qqu/vz+dp02bNmzYsOHDhy9evPjYsWP6t4A4fvz4ggULpk+f/sknn4wfP55uQ4cOHT16tOyg1Wrff//9l19+mfbBgwcPGDCgb9++ffr0GTRo0MiRI3nl6elZ2lgirsVr7969TzdB/ita6GAt+MhoLjROTEx86qmnhBD9+vWLjo6+cuVKenp6YGAgOvXu3dve3t5oJ9lzLGzRogUGREREmNaV+fGL1NTUJ5544qWXXsI19LPxasOGDc8+++zOnTuLi4txmaKiIjs7ux49enTp0kX2oXNOTs4zzzwjVJk5c2ZSUlJYWNj69etfeOEFdGjduvWQIUPO1nYCU7w2btwoDKRVS/HmSLH4PdG3+0+N1pAS4pYQgR4edadDoV69etFt4MCBaKxvh0X79u1RCGRGvObPn09/1MUwb2/vRkMD7+7cufOoUaMMe8bGxtK4adMmo+FpaWkg0D/evHmzf//+0pCPP/5Y337jxo05c+a0bNlS7nRhYWFjvOLjlTVrbRcuNORlM1MoMUKJF/mHRR91V1iqUuU1UQgPk7yef/55w1VJGVZWVrRjFYnDkBf+pV8RpjgInU3wwjbIWltb1xicM1u2bIG4n59f3f6kgkZ5Ifn5+YSn3DnizCSvL75QVAr8AKyVOl3rVuKsvVA06k+8mPoX0VmIC2qfA2oH07xQCw0MX82bN08qGkMVUpsX9r/++utye1u1arVu3ToyaUMak2K6du06ceJEw8ZFixYxdtKkSd9//31DA03zQsiM8tUHH3zQkJsLpaxMD0v++AjxtDpsx2KhnNPxqggXVn2Fv/q2TIhuTeD13HPP1cuL3SNrGPEitZHmFi5cSMBKZOT1qqoqE7zefvttw8bVq1fLBETOut/wTcM0r6VLl8pXM2bMaJhXebkyeowRsmQhhgnxx/bCZpZwXi7GW4k1D19Nfhg7pnmRBa5du6ZvxwYyjsxrhuGm50VnAIGJXE43fG3JkiWVlZUN8XrnnXcMG8PDw4llBj755JMODg76GsIsXrNmzZI7unnz5nqHKz/G4717iqOjEbIqIWYL8Qd16vceNroTp03jxTnN5weqAIKjul27dj179jQ6fQx5KWpBQ/1BT+llRFldZJIXoWfYSBjiFDKiIb5mzRrKOrN4FRQUyFfstInixuB8zM2tGjfOiJqfEFOEuK1+zhGih8FpYJoX+zxlyhQSwdy5c1977TVsaNu2ra2tLdnaBC8E19i6dSvDpZfVRSZ5MbnR0hwvJDUZ0Qx89913r1692kReHEFUc4ylsIg0eb2rVU9s2rjxr0KcrY1M/zNB1BLTvChzoOPo6EiNgp+jJdpgBgpxYOmPtrq8FPUw3bVrV6dOnQgNiczwSJW8pk6dWnf1srIySmUKQAbKHBoQEGAYm0a8WAi3AtDs2bNRbPny5ehvApYxL1l/tRVimprCDGGtJLDN4UX1jCr6doom6skOHTrwqmPHjidOnDDBS1FrS1dXV2pjiezTTz/VH3ySF0V8vfYw8OTJk2PGjCGcZWySE6ly6+X17bff7t69myOCiwFVrmlSDfKS0lGIOUJohKgRYp8Q7YSxNMrLqOoDDfUkxoPg1VdfladYQ7zkK19f327dusn4AhlVu54X2cqEVfRZtmwZGUDmbzc3t3p5NQWQkZiq7/v1EKumi5D54h+TxKihonOHR+KlqFdreR3B0aTnA4U6o15eUgje7t27t1AFT+FAkLy4zZg2jDCkjpPpjOuhbPwFefXuLrSuukpVlqw1USLZQyybIbp0+vm8EOpSXci3bXvhwgVFDR8uuiZ4KWqtQAd4tWnThgs80zaFl6Jm8RdffJHluEXKll+KV5vWwmWFDtYPp8SJXcLRRvisFdcCdeVrnLPo1f3n8yJP85ZcLt/Ci1xrmhdCFSIvK+Qj0j8Z3ZAXx392dna9AyniAE3il4/NzIsUO1CVyeO7Vp8SylmxfN6APw2RbQPfHDsw/+gf8bXl83ry+AW3qDrSKC8yMQZMmDBBHpFkMQg2ygtJTExkTqHWZaQzQ16cHkFBQfWOkryoFeRjM/PiDOIgK8rPqdL8TReG6e/xYPi1V3XOHpzudv4JPusPHUMxzYubI1U4yUtf43CiT548uSm8EEJ46NCh0lrD85GApcqt27+kpIR95ZDNyMiQLc3M60epiFUShKLlanm89osHytUZSpq1Un2noekoEeWJRikfERFRXl5+TxXukl5eXugKL2dnZ339xZH3xhtvgLjRwkc//yuvvML8hvUXvNie06dPy1s6Mc7Bwurjxo1DGaPvJyjfJS+qwqasaCR1eD24p2TO1aX5jLnK/dpfElRolKS+SqW2obny8vIoZ0g02I/L4AtU9tTc3PXGjh3LZ0oHMpG8yvIbC6lLBw0axOWJC7OPj4/p7+qkQJYJp02bpm8hHtkGTt4RI0a89dZb1tbWVlZWw4cPJ9MlJyfr9wZ3c3FxYc96qcJRsG3bttDQ0FvmfKVeh1f5GZ1zJfdXqowvE0qJt1LsauKvGzgLG1hWn6ATp5XRpR/vu1FbKioqmqI0Uxle8XiUX+SmpKScP38+NTUVpqxotBwVRl31aDHxfUZdqc2rulK5ZK1oWys3v6qnb/VNnff9vsWAV819Jf9/SkILpcSHh19PpcdaHvJ6UK0UbFISuyqlwb+qPo+7qLyI85IDSpGdcregsf6/d/kpHjlHyJQXUy+S/yorKwvy8mjkMVGrLVdPEC5u8hKffukSnbncV6kp/Mrlywwhv3KKV1VVyUxcUFCQoNHcLr9dVFREPZGoTUxPS6czWZnf5HVyLe1ZmVmK+rcG+a0LBYFWq2XpnKwczbk4+sTHx5O8iwqLYqJjks4nFRYUMDbu3DnmYRTzF18vZgmW1ql3/TqTXL58+WJKCo/kct1j+uWU5BTOouTERF6xeklxcVxMDPqrU8Wh8KW0S9oELY/ozLQF+fkNfx/9UNDVwc7O28v7clpa5OnTWzdtxp799g7nYmPt9+1D9eCgICcHRyZaungJjdScuVlZ6LFu1eqIkychkpyUpNFoNqxbDztHB0ceszIzjx45cvjQoegzZ4ICAxM1mv+sXRf2ZRj6YXPqhQurVvybvTno6ysP9ejo6NDgEDT28/GN/uYbLLHba2e79bO83LzNGzdTfAUFHGJ79u3Zm5WVxShAuLm6+vr4sE8shLbsqMt+J7jExsRq4xOY02737q/CwkKCg3dt3+7u7KKNj6cbJ6mzk1NcXNwBb2/2wMHeIen8+RNfn0A3Vg/w95ffhTTC67OtW3dus01LS3N0cHB1cr6cnu7t4UXRH3r06MXU1L179uy03V5644aHuwcaHw4Kkry83T28PL1CgkPYHHcXFwZyd4mMOOXl7n4pLQ0z3Jxd6IYl6MGEtGMAO+zh7u7t4QEjPS+uDZ5ubriSp5s73TB7hY0N9nMN4DdbJXmtX7M2KjJK8vro7wvoBnT7ffZ7du/B2uCgIwH+AdHfREteO7bZMhWj1q1evWv7DgxBVdpDQkJQY9uWLWi7Y8eOw4d05ni4usXGxvp//nnjvPAmp/37qSFB7urkFBoczJ6wCUzHLFGRkeiHHlGnTuGDpTdKlyxanJ2VBWVfH19KJ2wIP37c2cERJfAOriA4VFTEKeZhVIIm4diXYYmahAOeXtiG6l+Hh+M7J8O/1lHz8mKTCEnm4QMtBw/4UElh/MEDvpDCfdgGeB0+FEipxYQkBL+DB1X/cmP4magovAw7w4+HZ1zNwOmYXxOvwS68ic7oiVE+Xt5Xr1x1cXbGVVlFEx+PvfRxcXbJuJJxveg6wevq4hrg59c4L7TBS/ER7n3gYEvxeWLqXOw5gpF8RFmISTnZ2fLukpmZKUtQmbDy8/L5QA1NS2ZGJlOR+EgrDMTa+Li4lORk+Rd5mbDycnMLCwqxhM44MuFJt7LS0pjoaBQANw5CDlJ75oGY0JYDyTIyt9KOVxYX6oTMW6n+5wgDMeHixYugLy8ro5u8POqWzs6WKZhJ5Cq3y8vlf3WgLRpeL7xOOxMyf+P5yyJNEQsv88TCyzyx8DJPLLzMEwsv88TCyzyx8DJPLLzMEwsv8+T/AAAA///b2E7dAAAABklEQVQDAFepbZVmJ0i7AAAAAElFTkSuQmCC"/>
                </td>
                <td align=right class=noborder>
                    <img src="data:image/png;base64,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"/>
                </td>
            </tr>
        </table><br>
        <table width="100%" cellspacing="0" cellpadding="5" bgcolor="#fff" align="center">
            <tr>
                <td class="judul noborder">
                    FR. MAPA.01 MERENCANAKAN AKTIVITAS DAN PROSES ASESMEN<br />
                </td>
            </tr>
        </table>
        <br />

        <!-- Header: Skema Sertifikasi -->
        <table width="100%" cellpadding="5" cellspacing="0" align="center">
            <tr>
                <td style="width: 30%;" rowspan="2">Skema Sertifikasi<br />(<s>KKNI</s>/Okupasi/<s>Klaster</s>)</td>
                <td style="width: 15%;">Judul</td>
                <td style="width: 3%;">:</td>
                <td style="width: 52%;">{{ $dokumenHeader['jabatan_kerja'] }}</td>
            </tr>
            <tr>
                <td>Nomor</td>
                <td>:</td>
                <td>{{ $dokumenHeader['nomor_skema'] }}</td>
            </tr>
        </table>
        <br />

        <!-- ================= SECTION 1: Pendekatan Asesmen ================= -->
        <table width="100%" cellpadding="5" cellspacing="0" align="center">
            <tr>
                <td class="hd-dok" style="font-weight: bold;">1. Pendekatan Asesmen</td>
            </tr>
        </table>
        @php
        $refMap = collect($referensi_form)
        ->flatMap(fn ($f) => $f['kelompok']['kategoris'] ?? [])
        ->flatMap(fn ($k) => $k['subkategoris'] ?? [])
        ->flatMap(fn ($s) => $s['referensis'] ?? [])
        ->keyBy('id');

        $asesi = [
            'hasil_pelatihan_telusur'           => $refMap[82]['value'] ?? false,
            'hasil_pelatihan_tidak_kompetensi'  => $refMap[83]['value'] ?? false,
            'pekerja_berpengalaman_telusur'     => $refMap[84]['value'] ?? false,
            'pekerja_berpengalaman_tidak_telusur'=> $refMap[85]['value'] ?? false,
            'otodidak'                          => $refMap[86]['value'] ?? false,
        ];

        $tujuan = [
            'sertifikasi' => $refMap[87]['value'] ?? false,
            'pkt'         => $refMap[88]['value'] ?? false,
            'rpl'         => $refMap[89]['value'] ?? false,
            'lainnya'     => $refMap[90]['value'] ?? false,
        ];

        $konteks = [
            'tempat_kerja_nyata'     => $refMap[91]['value'] ?? false,
            'tempat_kerja_simulasi'  => $refMap[92]['value'] ?? false,
            'tersedia'               => $refMap[93]['value'] ?? false,
            'terbatas'               => $refMap[94]['value'] ?? false,
            'bukti_asesmen_rpl'      => $refMap[95]['value'] ?? false,
            'aktivitas_kerja'        => $refMap[96]['value'] ?? false,
            'kegiatan_pembelajaran'  => $refMap[97]['value'] ?? false,
            'lsp_gatensi'            => $refMap[98]['value'] ?? false,
            'organisasi_pelatihan'   => $refMap[99]['value'] ?? false,
            'asesor_perusahaan'      => $refMap[100]['value'] ?? false,
        ];

        $orang_relevan = [
            'manajer_sertifikasi_lsp' => $refMap[101]['value'] ?? false,
            'master_assessor_lsp'     => $refMap[102]['value'] ?? false,
            'manajer_pelatihan_lsp'   => $refMap[103]['value'] ?? false,
            'lainnya'                 => $refMap[104]['value'] ?? false,
        ];

        $tolak_ukur = [
            'skkni'               => $refMap[105]['value'] ?? false,
            'kriteria_kurikulum'  => $refMap[106]['value'] ?? false,
            'spesifikasi_kinerja' => $refMap[107]['value'] ?? false,
            'spesifikasi_produk'  => $refMap[108]['value'] ?? false,
            'pedoman_khusus'      => $refMap[109]['value'] ?? false,
        ];
        @endphp
        <table width="100%" cellpadding="5" cellspacing="0" align="center">
            <!-- Asesi Options -->
            <tr>
                <td rowspan="21" style="width: 5%; vertical-align: top;">1.1.</td>
                <td rowspan="5" style="width: 20%; vertical-align: top;">Asesi</td>
                <td colspan="3">
                    <span class="checkbox {{ $asesi['hasil_pelatihan_telusur'] ? 'checked' : '' }}"></span>
                    Hasil pelatihan dan/atau pendidikan, dimana kurikulum dan fasilitas praktek mampu telusur terhadap standar kompetensi.
                </td>
            </tr>
            <tr>
                <td colspan="3">
                    <span class="checkbox {{ $asesi['hasil_pelatihan_tidak_kompetensi'] ? 'checked' : '' }}"></span>
                    Hasil pelatihan dan/atau pendidikan, dimana kurikulum belum berbasis kompetensi.
                </td>
            </tr>
            <tr>
                <td colspan="3">
                    <span class="checkbox {{ $asesi['pekerja_berpengalaman_telusur'] ? 'checked' : '' }}"></span>
                    Pekerja berpengalaman, dimana berasal dari industry/tempat kerja yang dalam operasionalnya mampu telusur dengan standar kompetensi.
                </td>
            </tr>
            <tr>
                <td colspan="3">
                    <span class="checkbox {{ $asesi['pekerja_berpengalaman_tidak_telusur'] ? 'checked' : '' }}"></span>
                    Pekerja berpengalaman, dimana berasal dari industry/tempat kerja yang dalam operasionalnya belum berbasis standar kompetensi.
                </td>
            </tr>
            <tr>
                <td colspan="3">
                    <span class="checkbox {{ $asesi['otodidak'] ? 'checked' : '' }}"></span>
                    Pelatihan/belajar mandiri atau otodidak.
                </td>
            </tr>

            <!-- Tujuan Asesmen -->
            <tr>
                <td rowspan="4" style="vertical-align: top;">Tujuan Asesmen</td>
                <td colspan="3">
                    <span class="checkbox {{ $tujuan['sertifikasi'] ? 'checked' : '' }}"></span>
                    Sertifikasi
                </td>
            </tr>
            <tr>
                <td colspan="3">
                    <span class="checkbox {{ $tujuan['pkt'] ? 'checked' : '' }}"></span>
                    Pengakuan Kompetensi Terkini (PKT)
                </td>
            </tr>
            <tr>
                <td colspan="3">
                    <span class="checkbox {{ $tujuan['rpl'] ? 'checked' : '' }}"></span>
                    Rekognisi pembelajaran lampau (RPL)
                </td>
            </tr>
            <tr>
                <td colspan="3">
                    <span class="checkbox {{ $tujuan['lainnya'] ? 'checked' : '' }}"></span>
                    Lainnya
                </td>
            </tr>

            <!-- Konteks Asesmen -->
            <tr>
                <td rowspan="8" style="vertical-align: top;">Konteks Asesmen</td>
                <td style="width: 15%;">Lingkungan</td>
                <td style="width: 25%;">
                    <span class="checkbox {{ $konteks['tempat_kerja_nyata'] ? 'checked' : '' }}"></span>
                    Tempat kerja nyata
                </td>
                <td style="width: 25%;">
                    <span class="checkbox {{ $konteks['tempat_kerja_simulasi'] ? 'checked' : '' }}"></span>
                    Tempat kerja simulasi
                </td>
            </tr>
            <tr>
                <td>Peluang untuk mengumpulkan bukti dalam sejumlah situasi</td>
                <td>
                    <span class="checkbox {{ $konteks['tersedia'] ? 'checked' : '' }}"></span>
                    Tersedia
                </td>
                <td>
                    <span class="checkbox {{ $konteks['terbatas'] ? 'checked' : '' }}"></span>
                    Terbatas
                </td>
            </tr>
            <tr>
                <td rowspan="3">Hubungan antara standar kompetensi dan</td>
                <td colspan="2">
                    <span class="checkbox {{ $konteks['bukti_asesmen_rpl'] ? 'checked' : '' }}"></span>
                    Bukti untuk mendukung asesmen / RPL
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <span class="checkbox {{ $konteks['aktivitas_kerja'] ? 'checked' : '' }}"></span>
                    Aktivitas kerja di tempat kerja kandidat
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <span class="checkbox {{ $konteks['kegiatan_pembelajaran'] ? 'checked' : '' }}"></span>
                    Kegiatan Pembelajaran
                </td>
            </tr>
            <tr>
                <td rowspan="3">Siapa yang melakukan asesmen / RPL</td>
                <td colspan="2">
                    <span class="checkbox {{ $konteks['lsp_gatensi'] ? 'checked' : '' }}"></span>
                    LSP Gatensi Karya Konstruksi
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <span class="checkbox {{ $konteks['organisasi_pelatihan'] ? 'checked' : '' }}"></span>
                    Organisasi Pelatihan
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <span class="checkbox {{ $konteks['asesor_perusahaan'] ? 'checked' : '' }}"></span>
                    Asesor perusahaan
                </td>
            </tr>

            <!-- Orang yang relevan -->
            <tr>
                <td rowspan="4" style="vertical-align: top;">Orang yang relevan untuk dikonfirmasi</td>
                <td colspan="3">
                    <span class="checkbox {{ $orang_relevan['manajer_sertifikasi_lsp'] ? 'checked' : '' }}"></span>
                    Manajer sertifikasi LSP Gatensi Karya Konstruksi
                </td>
            </tr>
            <tr>
                <td colspan="3">
                    <span class="checkbox {{ $orang_relevan['master_assessor_lsp'] ? 'checked' : '' }}"></span>
                    Master Assessor / Master Trainer / Asesor Utama kompetensi
                </td>
            </tr>
            <tr>
                <td colspan="3">
                    <span class="checkbox {{ $orang_relevan['manajer_pelatihan_lsp'] ? 'checked' : '' }}"></span>
                    Manajer pelatihan Lembaga Training terakreditasi / Lembaga Training terdaftar
                </td>
            </tr>
            <tr>
                <td colspan="3">
                    <span class="checkbox {{ $orang_relevan['lainnya'] ? 'checked' : '' }}"></span>
                    Lainnya
                </td>
            </tr>

            <!-- Tolak Ukur Asesmen -->
            <tr>
                <td rowspan="5" style="vertical-align: top;">1.2</td>
                <td rowspan="5" style="vertical-align: top;">Tolak ukur asesmen</td>
                <td colspan="3">
                    <span class="checkbox {{ $tolak_ukur['skkni'] ? 'checked' : '' }}"></span>
                    Standar Kompetensi {{ $skkni }}
                </td>
            </tr>
            <tr>
                <td colspan="3">
                    <span class="checkbox {{ $tolak_ukur['kriteria_kurikulum'] ? 'checked' : '' }}"></span>
                    Kriteria asesmen dari kurikulum pelatihan
                </td>
            </tr>
            <tr>
                <td colspan="3">
                    <span class="checkbox {{ $tolak_ukur['spesifikasi_kinerja'] ? 'checked' : '' }}"></span>
                    Spesifikasi kinerja suatu perusahaan atau industri
                </td>
            </tr>
            <tr>
                <td colspan="3">
                    <span class="checkbox {{ $tolak_ukur['spesifikasi_produk'] ? 'checked' : '' }}"></span>
                    Spesifikasi Produk
                </td>
            </tr>
            <tr>
                <td colspan="3">
                    <span class="checkbox {{ $tolak_ukur['pedoman_khusus'] ? 'checked' : '' }}"></span>
                    Pedoman khusus
                </td>
            </tr>
        </table>
        <br />

        <!-- ================= SECTION 2: Mempersiapkan Rencana Asesmen ================= -->
        <table width="100%" cellpadding="5" cellspacing="0" align="center">
            <tr>
                <td class="hd-dok" style="font-weight: bold;">2. Mempersiapkan Rencana Asesmen</td>
            </tr>
        </table>

        @foreach ($kelompok_kerja['kelompok_kerja'] as $kelompok)
            @php
                $unitCount = count($kelompok['units']);
                $namaLabel = trim((string) ($kelompok['nama'] ?? ''));
                $namaSudahUrutan = preg_match('/^kelompok pekerjaan/i', $namaLabel);
            @endphp

            <!-- Kelompok Pekerjaan {{ $kelompok['urut'] }} -->
            <table width="100%" cellpadding="5" cellspacing="0" align="center">
                <tr>
                    {{-- Kelompok name (header row) — MUK: "Kelompok Pekerjaan N" BOLD rata tengah --}}
                    <td style="width: 20%; text-align: center;" class="no-bottom">
                        <strong>Kelompok Pekerjaan {{ $kelompok['urut'] }}</strong>
                    </td>
                    <td style="width: 8%;" align="center"><strong>No.</strong></td>
                    <td style="width: 22%;" align="center"><strong>Kode Unit</strong></td>
                    <td style="width: 50%;" align="center"><strong>Judul Unit</strong></td>
                </tr>

                @foreach ($kelompok['units'] as $i => $unit)
                    @php
                        // Determine border class for the empty kelompok cell
                        if ($i === $unitCount - 1) {
                            $kelompokClass = 'no-top'; // last row
                        } else {
                            $kelompokClass = 'no-top-bottom'; // middle rows
                        }
                    @endphp

                    <tr>
                        {{-- Merged kelompok cell (rowspan replacement) — label kelompok non-bold, MUK --}}
                        @if ($i === 0)
                            <td class="{{ $kelompokClass }}" style="vertical-align: middle; text-align: center;">
                                @if (!$namaSudahUrutan)
                                    {{ $namaLabel }}
                                @endif
                                &nbsp;
                            </td>
                        @else
                            <td class="{{ $kelompokClass }}"></td>
                        @endif

                        <td align="center">{{ $i + 1 }}.</td>
                        <td align="center">{{ $unit['kode_unit'] }}</td>
                        <td>{{ $unit['nama_unit'] }}</td>
                    </tr>
                @endforeach
            </table>

            <br />

        <!-- Tabel Metode Asesmen untuk Kelompok ini -->
        <table width="100%" cellpadding="5" cellspacing="0" align="center" style="font-size: 9pt;">
            <tr>
                <td class="no-bottom" style="width: 11%;" align="center"></td>
                <td class="no-bottom" style="width: 14%;" align="center">
                </td>
                <td colspan="3" align="center"><strong>Jenis Bukti</strong></td>
                <td colspan="6" align="center"><strong>Metode dan Perangkat Asesmen<br/>
                    CL (Ceklis Observasi), DIT (Daftar Instruksi
                    Terstruktur), DPL (Daftar Pertanyaan Lisan),
                    DPT (Daftar Pertanyaan Tertulis), VPK (Verifikasi
                    Pihak Ketiga), CVP (Ceklis Verifikasi Portofolio),
                    CRP (Ceklis Reviu Produk), PW (Pertanyaan
                    Wawancara)
                    </strong>
                </td>
            </tr>
            <tr>
                <td class="no-top" style="width: 11%;" align="center"><strong>Unit Kompetensi</strong></td>
                <td class="no-top" style="width: 14%;" align="center">
                    <strong>Bukti-Bukti</strong><br />
                    <small>(Kinerja, Produk, Portofolio, dan/atau Pengetahuan) diidentifikasi berdasarkan Kriteria Unjuk Kerja dan Pendekatan Asesmen.</small>
                </td>
                <td style="width: 3%;" align="center"><strong>L</strong></td>
                <td style="width: 3%;" align="center"><strong>TL</strong></td>
                <td style="width: 3%;" align="center"><strong>T</strong></td>
                <td class="vhead" style="width: 11%;">
                    <div class="vtext">
                        <strong>Observasi langsung</strong><br>
                        <small>
                            (kerja nyata / aktivitas waktu nyata di tempat kerja
                            di lingkungan tempat kerja yang disimulasikan)
                        </small>
                    </div>
                </td>
                <td class="vhead" style="width: 11%;">
                    <div class="vtext">
                        <strong>Kegiatan Terstruktur</strong><br />
                        <small>
                            (latihan simulasi dan bermain peran, proyek, presentasi, lembar kegiatan)
                        </small>
                    </div>
                </td>

                <td class="vhead" style="width: 11%;">
                    <div class="vtext">
                        <strong>Tanya Jawab</strong><br />
                        <small>
                            (pertanyaan tertulis, wawancara, asesmen diri, tanya jawab lisan,
                            angket, ujian lisan atau tertulis)
                        </small>
                    </div>
                </td>

                <td class="vhead" style="width: 12%;">
                    <div class="vtext">
                        <strong>Verifikasi Portfolio</strong><br />
                        <small>
                            (sampel pekerjaan yang disusun oleh Asesi, produk dengan dokumentasi
                            pendukung, bukti sejarah, jurnal atau buku catatan, informasi tentang
                            pengalaman hidup)
                        </small>
                    </div>
                </td>

                <td class="vhead" style="width: 10%;">
                    <div class="vtext">
                        <strong>Reviu Produk</strong><br />
                        <small>
                            Produk hasil proyek, contoh hasil kerja/produk
                        </small>
                    </div>
                </td>

                <td class="vhead" style="width: 11%;">
                    <div class="vtext">
                        <strong>Verifikasi Pihak Ketiga</strong><br />
                        <small>
                            (testimoni dan laporan dari atasan, bukti pelatihan, otentikasi pencapaian sebelumnya, wawancara dengan atasan, atau rekan kerja)
                        </small>
                    </div>
                </td>
            </tr>
            @foreach ($kelompok['units'] as $i => $unit)
                @php
                    // Determine cell values based on jenjang and metode
                    $td3 = ''; // L column
                    $td4 = ''; // TL column
                    $td5 = ''; // T column
                    $td6 = ''; // Observasi langsung column
                    $td7 = ''; // Kegiatan Terstruktur column
                    $td8 = ''; // Tanya Jawab column
                    $td9 = ''; // Verifikasi Portfolio column
                    $td10 = ''; // Reviu Produk column
                    $td11 = ''; // Verifikasi Pihak Ketiga column

                    $jenjang = $dokumenHeader['jenjang'] ?? null;
                    $metode = strtolower($dokumenHeader['metode'] ?? '');

                    // Kalimat metode asesmen dinamis sesuai jenjang & metode uji
                    // (referensi: obs 4-9 = kegiatan terstruktur+tanya jawab;
                    //  obs 1-3 = observasi langsung+tanya jawab; portofolio = verifikasi portofolio+wawancara)
                    $metodeLabel = 'Hasil kegiatan terstruktur dan hasil tanya jawab tentang:';
                    if ($metode === 'portofolio') {
                        $metodeLabel = 'Hasil verifikasi portofolio dan hasil wawancara tentang:';
                    } elseif ($jenjang >= 1 && $jenjang <= 3) {
                        $metodeLabel = 'Hasil observasi langsung dan hasil tanya jawab tentang:';
                    }

                    if ($jenjang >= 1 && $jenjang <= 3) {
                        // jenjang 1-3
                        $td3 = 'L';
                        $td5 = 'T';
                        $td6 = 'CL';
                        $td8 = 'DPT';
                    } elseif ($jenjang >= 4 && $jenjang <= 9) {
                        if ($metode === 'portofolio') {
                            // jenjang 4-9 and metode is portofolio
                            $td4 = 'TL';
                            $td5 = 'T';
                            $td8 = 'PW';
                            $td9 = 'VP';
                            $td11 = 'VPK';
                        } elseif ($metode === 'observasi') {
                            // jenjang 4-9 and metode is observasi
                            $td3 = 'L';
                            $td5 = 'T';
                            $td7 = 'DIT';
                            $td8 = 'DPT';
                        }
                    }
                @endphp
                <tr>
                    <td style="width: 11%; word-wrap: break-word; overflow-wrap: break-word;">{{ $i + 1 }}. {{ $unit['nama_unit'] }}</td>
                    <td style="width: 14%; word-wrap: break-word; overflow-wrap: break-word;">{{ $metodeLabel }} {{ $unit['nama_unit'] }}</td>
                    <td style="width: 3%;" align="center">{{ $td3 }}</td>
                    <td style="width: 3%;" align="center">{{ $td4 }}</td>
                    <td style="width: 3%;" align="center">{{ $td5 }}</td>
                    <td style="width: 11%;" align="center">{!! $td6 ?: '&nbsp;' !!}</td>
                    <td style="width: 11%;" align="center">{!! $td7 ?: '&nbsp;' !!}</td>
                    <td style="width: 11%;" align="center">{!! $td8 ?: '&nbsp;' !!}</td>
                    <td style="width: 12%;" align="center">{!! $td9 ?: '&nbsp;' !!}</td>
                    <td style="width: 10%;" align="center">{!! $td10 ?: '&nbsp;' !!}</td>
                    <td style="width: 11%;" align="center">{!! $td11 ?: '&nbsp;' !!}</td>
                </tr>
            @endforeach
        </table>
        <br />
        @endforeach

        <!-- ================= SECTION 3: Modifikasi dan Kontekstualisasi ================= -->
        <table width="100%" cellpadding="5" cellspacing="0" align="center">
            <tr>
                <td class="hd-dok" style="font-weight: bold;">3. Modifikasi dan Kontekstualisasi</td>
            </tr>
        </table>
        <table width="100%" cellpadding="5" cellspacing="0" align="center">
            @foreach ($referensi_form['2']['kelompok']['kategoris'][0]['subkategoris'][0]['referensis'] as $idx => $item)
            <tr>
                <td style="width: 50%;">3.{{ $idx + 1 }} {{ $item['nama'] }}</td>
                <td style="width: 50%;">
                    @if ($item['value'])
                        Ada / <s>Tidak ada</s>
                        <br /><br />
                        <strong>Jika ada, tuliskan:</strong><br />
                        {!! $item['alasan'] !!}
                    @else
                        <s>Ada</s> / Tidak ada
                    @endif
                </td>
            </tr>
            @endforeach
        </table>
        <br />

        <!-- ================= KONFIRMASI DENGAN ORANG YANG RELEVAN ================= -->
        <table width="100%" cellpadding="5" cellspacing="0" align="center" class="noborder">
            <tr>
                <td class="noborder">
                    <strong>Konfirmasi dengan orang yang relevan:</strong><br />
                </td>
            </tr>
        </table>
        <table width="100%" cellpadding="5" cellspacing="0" align="center">
            <tr>
                <td class="hd-dok" style="width: 40%;">Orang yang relevan</td>
                <td class="hd-dok" style="width: 30%;" align="center">Nama</td>
                <td class="hd-dok" style="width: 30%;">Tanda tangan</td>
            </tr>
            <tr>
                <td>
                    <span class="checkbox {{ $orang_relevan['manajer_sertifikasi_lsp'] ? 'checked' : '' }}"></span>
                    Manajer sertifikasi
                </td>
                <td>{{ $dokumenHeader['nama_manajer'] ?? '' }}</td>
                <td>
                    @if($dokumenHeader['barcode_manajer'] ?? null)
                    <img src="{{ $dokumenHeader['barcode_manajer'] }}" width="60" height="60" /><br />
                    @endif
                    {{ isset($dokumenHeader['tanggal_manajer']) ? \Carbon\Carbon::parse($dokumenHeader['tanggal_manajer'])->locale('id')->translatedFormat('d F Y') : '' }}
                </td>
            </tr>
            <tr>
                <td>
                    <span class="checkbox {{ $orang_relevan['master_assessor_lsp'] ? 'checked' : '' }}"></span>
                    Master Assessor / Master Trainer / Asesor Utama kompetensi
                </td>
                <td></td>
                <td></td>
            </tr>
            <tr>
                <td>
                    <span class="checkbox {{ $orang_relevan['manajer_pelatihan_lsp'] ? 'checked' : '' }}"></span>
                    Manajer pelatihan Lembaga Training terakreditasi / Lembaga Training terdaftar
                </td>
                <td></td>
                <td></td>
            </tr>
            <tr>
                <td>
                    <span class="checkbox {{ $orang_relevan['lainnya'] ? 'checked' : '' }}"></span>
                    Lainnya:
                </td>
                <td></td>
                <td></td>
            </tr>
        </table>
        <br />

        <!-- ================= TANDA TANGAN ================= -->
        <table width="100%" cellpadding="5" cellspacing="0" align="center">
            <tr>
                <td class="hd-dok" style="width: 20%;">Status</td>
                <td class="hd-dok" style="width: 10%;" align="center">No</td>
                <td class="hd-dok" style="width: 25%;" align="center">Nama</td>
                <td class="hd-dok" style="width: 20%;">Nomor MET</td>
                <td class="hd-dok" style="width: 25%;">Tanda Tangan dan Tanggal</td>
            </tr>
            <tr>
                <td>Penyusun</td>
                <td align="center">1</td>
                <td>{{ $dokumenHeader['nama_penyusun'] ?? '' }}</td>
                <td>{{ $dokumenHeader['noreg_penyusun'] ?? '' }}</td>
                <td>
                    @if($dokumenHeader['barcode_penyusun'] ?? null)
                    <img src="{{ $dokumenHeader['barcode_penyusun'] }}" width="80" height="80" /><br />
                    @endif
                    {{ isset($dokumenHeader['tanggal_penyusun']) ? \Carbon\Carbon::parse($dokumenHeader['tanggal_penyusun'])->locale('id')->translatedFormat('d F Y') : '' }}
                </td>
            </tr>
            <tr>
                <td>Validator</td>
                <td align="center">1</td>
                <td>{{ $dokumenHeader['nama_validator'] ?? '' }}</td>
                <td>{{ $dokumenHeader['noreg_validator'] ?? '' }}</td>
                <td>
                    @if($dokumenHeader['barcode_validator'] ?? null)
                    <img src="{{ $dokumenHeader['barcode_validator'] }}" width="80" height="80" /><br />
                    @endif
                    {{ isset($dokumenHeader['tanggal_validator']) ? \Carbon\Carbon::parse($dokumenHeader['tanggal_validator'])->locale('id')->translatedFormat('d F Y') : '' }}
                </td>
            </tr>
        </table>
    </body>
</html>
