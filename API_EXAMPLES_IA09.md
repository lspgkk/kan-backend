# API IA09 — Pertanyaan Wawancara

---

## GET `/api/asesmen/{id_izin}/ia09`

Mengambil data IA09. `files` adalah list file dari APL02 (tanpa soal mapping). `answers` adalah jawaban wawancara per soal (group 2).

### Full Response

```json
{
    "message": "Success",
    "data": {
        "barcodes": {
            "asesi": {
                "url": "https://moon.lspgatensi.id/files/new_gatensi/barcodes/ttd_ia09_abc.png",
                "tanggal": "2026-06-26T12:15:42.000000Z",
                "nama": "KEVIN SINAR UTAMA GIRSANG"
            },
            "asesor1": {
                "url": "https://moon.lspgatensi.id/files/new_gatensi/barcodes/ttd_ia09_def.png",
                "tanggal": "2026-06-26T14:00:00.000000Z",
                "nama": "ASESOR SATU"
            },
            "asesor2": null
        },
        "dokumen": {
            "id": 8603,
            "nama_dokumen": "PERTANYAAN WAWANCARA"
        },
        "soal": {
            "1": [
                {
                    "id": 179332,
                    "id_dokumen": "8603",
                    "soal": "Referensi Kerja",
                    "no": "1",
                    "id_kelompok": "1"
                },
                {
                    "id": 179333,
                    "id_dokumen": "8603",
                    "soal": "Laporan Pekerjaan",
                    "no": "3",
                    "id_kelompok": "1"
                }
            ],
            "2": [
                {
                    "id": 160408,
                    "id_dokumen": "8603",
                    "soal": "Bagaimana Anda menangani konflik atau ketidaksesuaian antara jadwal internal dengan jadwal pihak luar?\n\nKode Unit: F.41PLG00.002.2\nElemen: 4\nKUK: 4.2",
                    "no": "1",
                    "id_kelompok": "2"
                },
                {
                    "id": 160409,
                    "id_dokumen": "8603",
                    "soal": "Apa langkah yang Anda ambil untuk menghindari kekurangan bahan atau tenaga kerja yang dapat mempengaruhi kelancaran proyek?\n\nKode Unit: F.410100.003.02\nElemen: 2\nKUK: 2.2",
                    "no": "2",
                    "id_kelompok": "2"
                },
                {
                    "id": 160411,
                    "id_dokumen": "8603",
                    "soal": "Apa yang Anda lakukan untuk memastikan bahwa gambar kerja dan spesifikasi teknis dapat dipahami oleh semua pihak?\n\nKode Unit: F.41PLG00.003.2\nElemen: 1\nKUK: 1.1",
                    "no": "3",
                    "id_kelompok": "2"
                },
                {
                    "id": 160412,
                    "id_dokumen": "8603",
                    "soal": "Apa saja metode yang Anda terapkan untuk memastikan hasil pengecoran Nut (Nat) pasangan penutup lantai/dinding memenuhi kualitas?\n\nKode Unit: F.41PLG00.006.02\nElemen: 6\nKUK: 6.5",
                    "no": "4",
                    "id_kelompok": "2"
                },
                {
                    "id": 160414,
                    "id_dokumen": "8603",
                    "soal": "Dalam situasi di mana laporan Anda harus diserahkan pada banyak pihak, bagaimana Anda menyusun laporan?\n\nKode Unit: F.41PLG00.007.02\nElemen: 2\nKUK: 2.1",
                    "no": "5",
                    "id_kelompok": "2"
                }
            ]
        },
        "files": [
            {
                "id": 149,
                "original_name": "Ijazah",
                "path": "https://perizinan.pu.go.id/portal/admin/assets/upload/cdn/document/2026/06/23/2009802/965740-IJAZHA-KEVIN-6a3a650ce23d5.pdf",
                "filetype": "referensi_external"
            },
            {
                "id": 150,
                "original_name": "Sertifikat Pelatihan K3",
                "path": "https://moon.lspgatensi.id/files/new_gatensi/apl02/I-2026062317514743576/abc123.pdf",
                "filetype": null
            }
        ],
        "answers": {
            "160408": {
                "kesimpulan": "Asesi menjelaskan dengan baik proses koordinasi dengan pihak luar",
                "is_kompeten": true
            },
            "160409": {
                "kesimpulan": "Asesi kurang memahami perencanaan sumber daya",
                "is_kompeten": false
            },
            "160411": {
                "kesimpulan": null,
                "is_kompeten": null
            },
            "160412": {
                "kesimpulan": null,
                "is_kompeten": null
            },
            "160414": {
                "kesimpulan": null,
                "is_kompeten": null
            }
        }
    }
}
```

---

## POST `/api/asesmen/{id_izin}/ia09`

Submit jawaban wawancara. **Jawaban per soal wawancara** (group 2), bukan per file.

### Full Payload

```json
{
    "dokumen_id": 8603,
    "answers": [
        {
            "soal_id": 160408,
            "kesimpulan": "Asesi menjelaskan dengan baik proses koordinasi dengan pihak luar",
            "is_kompeten": true
        },
        {
            "soal_id": 160409,
            "kesimpulan": "Asesi kurang memahami perencanaan sumber daya",
            "is_kompeten": false
        },
        {
            "soal_id": 160411,
            "kesimpulan": null,
            "is_kompeten": null
        },
        {
            "soal_id": 160412,
            "kesimpulan": null,
            "is_kompeten": null
        },
        {
            "soal_id": 160414,
            "kesimpulan": null,
            "is_kompeten": null
        }
    ]
}
```

### Fields

| Field | Type | Required | Description |
|---|---|---|---|
| `dokumen_id` | int | Yes | ID dokumen IA09 dari `jabatan_dokumens` |
| `answers` | array | Yes | Jawaban per soal wawancara |
| `answers.*.soal_id` | int | Yes | ID soal wawancara (group 2) |
| `answers.*.kesimpulan` | string | No | Kesimpulan jawaban asesi |
| `answers.*.is_kompeten` | boolean | No | `true` = Kompeten, `false` = Belum Kompeten, `null` = belum dinilai |

### Response

```json
{
    "message": "IA09 answers saved successfully"
}
```

---

## POST `/api/qr/{id_izin}/ia09`

Generate PDF IA09 (TTD).

### Payload

```json
{
    "id_jadwal": "8797"
}
```

### Response

```json
{
    "message": "Success",
    "data": {
        "url_image": "https://moon.lspgatensi.id/files/new_gatensi/barcodes/ttd_ia09_xxx.png",
        "url_ttd": "https://barcode.lspgatensi.id/ttd/xxx"
    }
}
```

---

## Ringkasan Perubahan

| Item | Sebelum | Sesudah |
|---|---|---|
| GET `files` | Flat + `soal_id` dari `filetypeSoalMap` string matching | Flat array dari APL02, tanpa `soal_id` |
| GET `answers` | Keyed by `soal_id` | Tetap keyed by `soal_id` (wawancara) |
| Submit | `answers.*.soal_id` | Tidak berubah |
| PDF file section | Loop `$files` langsung | Tidak berubah (sudah benar) |
