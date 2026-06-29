# API IA08 — Ceklis Verifikasi Portofolio

---

## GET `/api/asesmen/{id_izin}/ia08`

Mengambil data IA08. `files` adalah list file dari APL02, masing-masing dengan `answer` (hasil verifikasi asesor per file).

### Full Response

```json
{
    "message": "Success",
    "data": {
        "barcodes": {
            "asesi": {
                "url": "https://moon.lspgatensi.id/files/new_gatensi/barcodes/ttd_ia08_abc.png",
                "tanggal": "2026-06-26T12:15:42.000000Z",
                "nama": "KEVIN SINAR UTAMA GIRSANG"
            },
            "asesor1": {
                "url": "https://moon.lspgatensi.id/files/new_gatensi/barcodes/ttd_ia08_def.png",
                "tanggal": "2026-06-26T14:00:00.000000Z",
                "nama": "ASESOR SATU"
            },
            "asesor2": null
        },
        "dokumen": {
            "id": 8602,
            "nama": "CEKLIST VERIFIKASI PORTOFOLIO"
        },
        "soal": {
            "1": [
                {
                    "id": 160396,
                    "id_dokumen": "8602",
                    "soal": "Referensi Kerja",
                    "no": "1",
                    "id_kelompok": "1",
                    "unit": null, "kuk": null, "subunit": null
                },
                {
                    "id": 160397,
                    "soal": "Laporan Pekerjaan",
                    "no": "2",
                    "id_kelompok": "1",
                    "unit": null, "kuk": null, "subunit": null
                },
                {
                    "id": 160398,
                    "soal": "Dokumentasi Pekerjaan",
                    "no": "3",
                    "id_kelompok": "1",
                    "unit": null, "kuk": null, "subunit": null
                }
            ],
            "2": [
                {
                    "id": 160400,
                    "id_kelompok": "2",
                    "unit": { "id": 4411, "kode": "F.41PLG00.002.2" },
                    "kuk": { "id": 67326, "kode": "4.2" },
                    "subunit": { "id": 28861, "nama": "Melaksanakan koordinasi dengan pihak luar" }
                },
                {
                    "id": 160401,
                    "id_kelompok": "2",
                    "unit": { "id": 4412, "kode": "F.410100.003.02" },
                    "kuk": { "id": 67332, "kode": "2.2" },
                    "subunit": { "id": 28863, "nama": "Meninjau kembali prosedur dan metode kerja" }
                },
                {
                    "id": 160402,
                    "id_kelompok": "2",
                    "unit": { "id": 4413, "kode": "F.41PLG00.003.2" },
                    "kuk": { "id": 67347, "kode": "1.1" },
                    "subunit": { "id": 28867, "nama": "Menginterpretasikan gambar kerja dan spesifikasi teknis" }
                },
                {
                    "id": 160403,
                    "id_kelompok": "2",
                    "unit": { "id": 4416, "kode": "F.41PLG00.006.02" },
                    "kuk": { "id": 67423, "kode": "6.5" },
                    "subunit": { "id": 28885, "nama": "Melaksanakan pekerjaan penutup lantai/dinding" }
                },
                {
                    "id": 160404,
                    "id_kelompok": "2",
                    "unit": { "id": 4419, "kode": "F.41PLG00.007.02" },
                    "kuk": { "id": 67479, "kode": "2.1" },
                    "subunit": { "id": 28899, "nama": "Membuat konsep laporan" }
                }
            ],
            "3": [
                {
                    "id": 160399,
                    "soal": "Bukti tambahan diperlukan pada unit / elemen kompetensi<br>sebagai berikut: Contoh :",
                    "no": "4",
                    "id_kelompok": "3",
                    "unit": null, "kuk": null, "subunit": null
                }
            ]
        },
        "referensi": [
            {
                "id": 228,
                "nama": "Referensi Kerja",
                "id_kelompok": 1,
                "id_kategori": null,
                "id_subkategori": null,
                "kategori_rel": null,
                "kelompok_rel": {
                    "id": 1,
                    "kategori": "MAPA01",
                    "no": "1.1",
                    "urut": 1,
                    "nama": null
                },
                "subkategori_rel": null
            },
            {
                "id": 229,
                "nama": "Laporan Pekerjaan",
                "id_kelompok": 1,
                "kelompok_rel": { "id": 1, "kategori": "MAPA01", "no": "1.1", "urut": 1, "nama": null }
            },
            {
                "id": 230,
                "nama": "Dokumentasi Pekerjaan",
                "id_kelompok": 1,
                "kelompok_rel": { "id": 1, "kategori": "MAPA01", "no": "1.1", "urut": 1, "nama": null }
            },
            {
                "id": 231,
                "nama": "Bukti tambahan diperlukan...",
                "id_kelompok": 3,
                "kelompok_rel": { "id": 3, "kategori": "MAPA01", "no": "", "urut": 3, "nama": null }
            }
        ],
        "files": [
            {
                "id": 149,
                "original_name": "Ijazah",
                "path": "https://perizinan.pu.go.id/portal/admin/assets/upload/cdn/document/2026/06/23/2009802/965740-IJAZHA-KEVIN-6a3a650ce23d5.pdf",
                "filetype": "referensi_external",
                "answer": {
                    "valid": true,
                    "asli": true,
                    "terkini": true,
                    "memadai": false
                }
            },
            {
                "id": 150,
                "original_name": "Sertifikat Pelatihan K3",
                "path": "https://moon.lspgatensi.id/files/new_gatensi/apl02/I-2026062317514743576/abc123.pdf",
                "filetype": null,
                "answer": {
                    "valid": true,
                    "asli": false,
                    "terkini": true,
                    "memadai": true
                }
            },
            {
                "id": 151,
                "original_name": "Laporan Proyek Gedung A",
                "path": "https://moon.lspgatensi.id/files/new_gatensi/apl02/I-2026062317514743576/def456.pdf",
                "filetype": null,
                "answer": null
            }
        ],
        "apl2_answers": {
            "149": {
                "valid": true,
                "asli": true,
                "terkini": true,
                "memadai": false
            },
            "150": {
                "valid": true,
                "asli": false,
                "terkini": true,
                "memadai": true
            }
        },
        "unit_answers": {
            "160400": true,
            "160401": false,
            "160402": true,
            "160403": true,
            "160404": true
        },
        "recommendation": {
            "is_kompeten": true,
            "bukti_tambahan": "Tidak ada bukti tambahan",
            "rekomendasi_unit": null,
            "rekomendasi_elemen": null,
            "rekomendasi_kuk": null
        }
    }
}
```

---

## POST `/api/asesmen/{id_izin}/ia08`

Submit jawaban IA08. **Jawaban sekarang per file APL02** (satu entry per `file_id`).

### Full Payload

```json
{
    "dokumen_id": 8602,
    "is_kompeten": true,
    "bukti_tambahan": "Tidak ada bukti tambahan",
    "rekomendasi_unit": null,
    "rekomendasi_elemen": null,
    "rekomendasi_kuk": null,
    "apl2_answers": [
        {
            "file_id": 149,
            "valid": true,
            "asli": true,
            "terkini": true,
            "memadai": false
        },
        {
            "file_id": 150,
            "valid": true,
            "asli": false,
            "terkini": true,
            "memadai": true
        },
        {
            "file_id": 151,
            "valid": false,
            "asli": null,
            "terkini": null,
            "memadai": null
        }
    ],
    "unit_answers": [
        { "soal_id": 160400, "is_checked": true },
        { "soal_id": 160401, "is_checked": false },
        { "soal_id": 160402, "is_checked": true },
        { "soal_id": 160403, "is_checked": true },
        { "soal_id": 160404, "is_checked": true }
    ]
}
```

### Fields

| Field | Type | Required | Description |
|---|---|---|---|
| `dokumen_id` | int | Yes | ID dokumen IA08 dari tabel `jabatan_dokumens` |
| `apl2_answers` | array | Yes | Array jawaban — **satu per file APL02** |
| `apl2_answers.*.file_id` | int | Yes | ID file dari `apl2_files` (file yang sudah di-upload via APL02) |
| `apl2_answers.*.valid` | boolean | No | Centang Valid - Ya |
| `apl2_answers.*.asli` | boolean | No | Centang Asli - Ya |
| `apl2_answers.*.terkini` | boolean | No | Centang Terkini - Ya |
| `apl2_answers.*.memadai` | boolean | No | Centang Memadai - Ya |
| `unit_answers` | array | No | Centang per unit/elemen (group 2) |
| `unit_answers.*.soal_id` | int | Yes | ID soal (group 2) |
| `unit_answers.*.is_checked` | boolean | No | Centang checklist |
| `is_kompeten` | boolean | Yes | Rekomendasi kompeten |
| `bukti_tambahan` | string | No | Catatan bukti tambahan |
| `rekomendasi_unit` | string | No | Unit rekomendasi (jika belum kompeten) |
| `rekomendasi_elemen` | string | No | Elemen rekomendasi |
| `rekomendasi_kuk` | string | No | KUK rekomendasi |

### Response

```json
{
    "message": "IA08 answers saved successfully"
}
```

---

## POST `/api/qr/{id_izin}/ia08`

Generate PDF IA08 (TTD).

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
        "url_image": "https://moon.lspgatensi.id/files/new_gatensi/barcodes/ttd_ia08_xxx.png",
        "url_ttd": "https://barcode.lspgatensi.id/ttd/xxx"
    }
}
```

---

## Ringkasan Perubahan

| Item | Sebelum | Sesudah |
|---|---|---|
| Kunci jawaban | `soal_id` | `apl2_file_id` |
| Submit `apl2_answers.*` | `{ soal_id, valid, asli, terkini, memadai }` | `{ file_id, valid, asli, terkini, memadai }` |
| GET `files` | Flat array, `soal_id: null` | Setiap file punya `answer` object (atau `null`) |
| GET `apl2_answers` | Keyed by `soal_id` | Keyed by `file_id` |
| PDF | Loop soal + mapping filetype | Loop langsung file dari APL02 |
| Tabel | `ia08_answers.soal_id` NOT NULL | `soal_id` nullable + `apl2_file_id` FK |
