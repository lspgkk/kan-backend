# API Examples — KAN Assessment Output

Semua endpoint KAN **tidak memerlukan auth token**.  
Cukup `Content-Type: application/json`.

---

## 1. IMPORT SOAL MUK KAN

Import 1 file JSON langsung (struktur sesuai file contoh dari frontend).

```bash
POST /api/kan/import
```

**Request body:**
```json
{
  "nama": "AHLI MUDA PERENCANA BETON PRACETAK UNTUK STRUKTUR BANGUNAN GEDUNG",
  "ia04b": [
    {"no": 1, "soal": "Apa peran RKL dan RPL dalam perencanaan...?", "lingkup": "Pengembangan diri, fungsi umum", "kode_unit": "F.41BPC00.001.2"}
  ],
  "ia05": [
    {"no": 1, "soal": "Jika seorang perencana beton pracetak mengabaikan...", "kode_kuk": "F.41BPC00.001.2\n1.1", "jawab_a": "...", "jawab_b": "...", "jawab_c": "...", "jawab_d": "...", "jawaban": "C"}
  ],
  "ia06": [
    {"no": 1, "soal": "Jelaskan mengapa penting...", "kode_kuk": "F.41BPC00.001.2\n1.2"}
  ]
}
```

**Response success:**
```json
{
  "message": "120 soal berhasil diimport untuk skema 'AHLI MUDA PERENCANA BETON PRACETAK UNTUK STRUKTUR BANGUNAN GEDUNG'",
  "data": {
    "nama_skema": "AHLI MUDA PERENCANA BETON PRACETAK UNTUK STRUKTUR BANGUNAN GEDUNG",
    "total": 120
  }
}
```

### Behavior

| Skenario | Aksi |
|----------|------|
| Soal baru (no + dokumen belum ada) | **Insert** |
| Soal sudah ada (no + dokumen sama) | **Update** (aman di re-import) |
| `dokumen_kode` belum ada di `jabatan_dokumens` | **Auto-create** dokumen |
| `kode_unit` tidak ditemukan | **Skip** (soal tetap masuk tanpa relasi unit) |
| `kode_kuk` tidak ditemukan | **Skip** (soal tetap masuk tanpa relasi KUK) |
| `ia06[].soal` kosong (template placeholder) | **Skip** baris tersebut |
| Field `folder` | **Diabaikan** (tidak diproses) |

### Contoh pakai file

```bash
curl -X POST https://backend.devgatensi.site/api/kan/import \
  -H "Content-Type: application/json" \
  -d @file.json
```

---

## 2. CEK DATA SOAL

```bash
# List soal per dokumen
GET https://backend.devgatensi.site/api/kan/soal?dokumen_id=503

# Filter by tipe (1=PG, 3=rekomendasi, 8=esai)
GET https://backend.devgatensi.site/api/kan/soal?dokumen_id=503&tipe=8

# Detail satu soal
GET https://backend.devgatensi.site/api/kan/soal/{id}

# Cari
GET https://backend.devgatensi.site/api/kan/soal?dokumen_id=503&tipe=1
```

---

## 3. CRUD SOAL (Manual)

```bash
# Create
POST https://backend.devgatensi.site/api/kan/soal
body: {"id_dokumen": 503, "tipe": 8, "no": 1, "soal": "..."}

# Bulk create
POST https://backend.devgatensi.site/api/kan/soal/bulk
body: {"soals": [{"id_dokumen": 503, "tipe": 8, "no": 1, "soal": "..."}]}

# Update
PUT https://backend.devgatensi.site/api/kan/soal/{id}
body: {"soal": "revisi..."}

# Delete
DELETE https://backend.devgatensi.site/api/kan/soal/{id}
```

---

## 4. REFERENCE DATA (Dropdown Frontend)

```bash
# Semua referensi skema dalam 1 panggilan
GET https://backend.devgatensi.site/api/kan/ref/skema/{id_jabatan}

# Unit kompetensi per skema
GET https://backend.devgatensi.site/api/kan/ref/unit-kompetensi/{id_jabatan}

# KUK per unit kompetensi
GET https://backend.devgatensi.site/api/kan/ref/kuk/{id_unit}

# Dokumen KAN per skema
GET https://backend.devgatensi.site/api/kan/ref/dokumen/{id_jabatan}

# Cari skema
GET https://backend.devgatensi.site/api/jabatan-kerja?search=PRACETAK
```

---

## 5. SUBMIT JAWABAN ASESMEN

> Endpoint ini tetap **memerlukan auth** (berada di grup `auth:api`).

### IA04B
```bash
curl -X POST https://backend.devgatensi.site/api/asesmen/{id}/ia04b?version=kan \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
  "dokumen_id": 503,
  "answers": [
    {"soal_id": 1, "jawaban": "Jawaban asesi...", "skor": 3}
  ],
  "rekomendasi": true
}'
```

### IA05
```bash
curl -X POST https://backend.devgatensi.site/api/asesmen/{id}/ia05?version=kan \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
  "dokumen_id": 504,
  "answers": [
    {"soal_id": 100, "jawaban": "C", "skor": 1}
  ],
  "umpan_balik": "Aspek pengetahuan tercapai."
}'
```

### IA06 (KAN-only, tanpa `?version=kan`)
```bash
curl -X POST https://backend.devgatensi.site/api/asesmen/{id}/ia06 \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
  "dokumen_id": 505,
  "answers": [
    {"soal_id": 200, "jawaban": "Jawaban esai...", "skor": 3}
  ],
  "umpan_balik": "Aspek pengetahuan tercapai.",
  "unit_elemen_kuk": null
}'
```

### AK02
```bash
curl -X POST https://backend.devgatensi.site/api/asesmen/{id}/ak02?version=kan \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
  "answers": [
    {"id_unit_kompetensi": 10, "observasi": true, "portofolio": true, "pertanyaan_wawancara": true, "pertanyaan_lisan": false, "pertanyaan_tertulis": true, "proyek_kerja": false, "lainnya": false}
  ],
  "total_skor_dit": 25,
  "total_skor_pg": 18,
  "total_skor_esai": 23,
  "is_kompeten": true,
  "tindak_lanjut": "Tidak ada, asesi dinyatakan kompeten.",
  "komentar": "Penguasaan baik."
}'
```

---

## 6. GENERATE PDF

> Endpoint ini tetap **memerlukan auth**.

```bash
POST https://backend.devgatensi.site/api/qr/{id}/ia04b?version=kan  body: {"id_jadwal": 50}
POST https://backend.devgatensi.site/api/qr/{id}/ia05?version=kan   body: {"id_jadwal": 50}
POST https://backend.devgatensi.site/api/qr/{id}/ia06              body: {"id_jadwal": 50}
POST https://backend.devgatensi.site/api/qr/{id}/ak02?version=kan  body: {"id_jadwal": 50}
```

---

## 7. CEK DOKUMEN URL

```bash
# Semua URL (BNSP + KAN)
GET https://backend.devgatensi.site/api/bukti/{id_izin}
```
