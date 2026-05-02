# API Payload Examples for IA08, IA09, IA10

## 📋 **IA08 - Portfolio Verification (FR.IA.08)**

### **GET `/api/asesmen/{id}/ia08`**
Get IA08 data with saved answers.

**Response:**
```json
{
  "message": "Success",
  "data": {
    "soal": {
      "1": [
        {
          "id": 160396,
          "soal": "Referensi Kerja",
          "no": "1",
          "id_kelompok": "1"
        }
      ],
      "2": [
        {
          "id": 160400,
          "unit": {
            "id": 4411,
            "kode": "F.41PLG00.002.2"
          },
          "kuk": {
            "id": 67326,
            "kode": "4.2"
          },
          "subunit": {
            "id": 28861,
            "nama": "Melaksanakan koordinasi dengan pihak luar"
          }
        }
      ]
    },
    "apl2_answers": {
      "160396": {
        "valid": true,
        "asli": true,
        "terkini": true,
        "memadai": false
      }
    },
    "unit_answers": {
      "160400": true
    },
    "recommendation": {
      "is_kompeten": true,
      "bukti_tambahan": "Additional portfolio evidence needed",
      "rekomendasi_unit": "F.41PLG00.002.2",
      "rekomendasi_elemen": "4",
      "rekomendasi_kuk": "4.2"
    }
  }
}
```

### **POST `/api/asesmen/{id}/ia08`**
Submit IA08 portfolio verification answers.

**Payload:**
```json
{
  "dokumen_id": 8602,
  "apl2_answers": [
    {
      "soal_id": 160396,
      "valid": true,
      "asli": true,
      "terkini": true,
      "memadai": false
    },
    {
      "soal_id": 160397,
      "valid": true,
      "asli": false,
      "terkini": true,
      "memadai": true
    }
  ],
  "unit_answers": [
    {
      "soal_id": 160400,
      "is_checked": true
    },
    {
      "soal_id": 160401,
      "is_checked": false
    }
  ],
  "bukti_tambahan": "Bukti tambahan diperlukan pada unit kompetensi F.41PLG00.002.2",
  "is_kompeten": true,
  "rekomendasi_unit": "F.41PLG00.002.2",
  "rekomendasi_elemen": "4",
  "rekomendasi_kuk": "4.2"
}
```

### **POST `/api/qr/{id}/ia08`**
Generate QR code and PDF for IA08.

**Payload:**
```json
{
  "id_jadwal": 123
}
```

**Response:**
```json
{
  "message": "Success",
  "data": {
    "url_image": "https://moon.lspgatensi.id/files/new_gatensi/barcodes/uuid.png",
    "url_ttd": "https://barcode.lspgatensi.id/ttd/uuid"
  }
}
```

---

## 📋 **IA09 - Interview Questions (FR.IA.09)**

### **GET `/api/asesmen/{id}/ia09`**
Get IA09 data with saved answers.

**Response:**
```json
{
  "message": "Success",
  "data": {
    "barcodes": {
      "asesi": null,
      "asesor1": null,
      "asesor2": null
    },
    "dokumen": {
      "id": 8603,
      "nama_dokumen": "PERTANYAAN WAWANCARA"
    },
    "soal": {
      "2": [
        {
          "id": 160408,
          "soal": "Bagaimana Anda menangani konflik atau ketidaksesuaian antara jadwal internal dengan jadwal pihak luar?",
          "no": "1",
          "id_kelompok": "2"
        }
      ]
    },
    "answers": {
      "160408": {
        "kesimpulan": "Asesi dapat menangani konflik dengan baik melalui komunikasi yang efektif",
        "is_kompeten": true
      }
    }
  }
}
```

### **POST `/api/asesmen/{id}/ia09`**
Submit IA09 interview question answers.

**Payload:**
```json
{
  "dokumen_id": 8603,
  "answers": [
    {
      "soal_id": 160408,
      "kesimpulan": "Asesi dapat menangani konflik dengan baik melalui komunikasi yang efektif dan negosiasi yang seimbang",
      "is_kompeten": true
    },
    {
      "soal_id": 160409,
      "kesimpulan": "Asesi telah menerapkan sistem pengadaan bahan yang baik namun perlu improvement dalam monitoring stok",
      "is_kompeten": false
    }
  ]
}
```

### **POST `/api/qr/{id}/ia09`**
Generate QR code and PDF for IA09.

**Payload:**
```json
{
  "id_jadwal": 123
}
```

**Response:**
```json
{
  "message": "Success",
  "data": {
    "url_image": "https://moon.lspgatensi.id/files/new_gatensi/barcodes/uuid.png",
    "url_ttd": "https://barcode.lspgatensi.id/ttd/uuid"
  }
}
```

---

## 📋 **IA10 - Third Party Clarification (FR.IA.10)**

### **GET `/api/asesmen/{id}/ia10`**
Get IA10 data with saved answers.

**Response:**
```json
{
  "message": "Success",
  "data": {
    "barcodes": {
      "asesi": null,
      "asesor1": null,
      "asesor2": null
    },
    "referensi_form": {
      "2": [
        {
          "id": 239,
          "nama": "Apakah asesi bekerja dengan mempertimbangkan Kesehatan, Keamanan dan Keselamatan Kerja?",
          "id_kelompok": "2",
          "no": 1
        }
      ],
      "3": [
        {
          "id": 245,
          "nama": "Apa hubungan Anda dengan asesi?",
          "id_kelompok": "3",
          "no": 1
        }
      ]
    },
    "answers": {
      "239": true
    },
    "essay_answers": {
      "245": "Atasan langsung sebagai supervisor proyek"
    },
    "form_data": {
      "nama_pengawas": "Budi Santoso",
      "tempat_kerja": "PT. Konstruksi Jaya",
      "alamat": "Jl. Raya Konstruksi No. 123, Jakarta",
      "telepon": "08123456789"
    }
  }
}
```

### **POST `/api/asesmen/{id}/ia10`**
Submit IA10 third-party clarification answers.

**Payload:**
```json
{
  "dokumen_id": 8604,
  "nama_pengawas": "Budi Santoso",
  "tempat_kerja": "PT. Konstruksi Jaya",
  "alamat": "Jl. Raya Konstruksi No. 123, Jakarta",
  "telepon": "08123456789",
  "answers": [
    {
      "referensi_id": 239,
      "answer": true
    },
    {
      "referensi_id": 240,
      "answer": true
    },
    {
      "referensi_id": 241,
      "answer": false
    },
    {
      "referensi_id": 242,
      "answer": true
    },
    {
      "referensi_id": 243,
      "answer": true
    },
    {
      "referensi_id": 244,
      "answer": true
    }
  ],
  "essay_answers": [
    {
      "referensi_id": 245,
      "essay_answer": "Atasan langsung sebagai supervisor proyek"
    },
    {
      "referensi_id": 246,
      "essay_answer": "3 tahun"
    },
    {
      "referensi_id": 247,
      "essay_answer": "Bekerja sangat dekat, sehari-hari berkoordinasi langsung"
    },
    {
      "referensi_id": 248,
      "essay_answer": "S1 Teknik Sipil, pengalaman 10 tahun di konstruksi gedung"
    },
    {
      "referensi_id": 249,
      "essay_answer": "Ya, sangat yakin. Asesi konsisten dan sesuai standar"
    },
    {
      "referensi_id": 250,
      "essay_answer": "Perlu training tambahan manajemen proyek dan leadership"
    },
    {
      "referensi_id": 251,
      "essay_answer": "Tidak ada komentar tambahan"
    }
  ]
}
```

**Important Notes:**
- `answers` array contains yes/no questions (group 2) - these require boolean `answer` field
- `essay_answers` array contains text questions (groups 3 & 4) - these require string `essay_answer` field
- Both arrays are optional and can be submitted together or separately
- Each answer in either array must have a `referensi_id` that exists in the `referensis` table

### **POST `/api/qr/{id}/ia10`**
Generate QR code and PDF for IA10.

**Payload:**
```json
{
  "id_jadwal": 123
}
```

**Response:**
```json
{
  "message": "Success",
  "data": {
    "url_image": "https://moon.lspgatensi.id/files/new_gatensi/barcodes/uuid.png",
    "url_ttd": "https://barcode.lspgatensi.id/ttd/uuid"
  }
}
```

---

## 🔧 **Field Descriptions**

### **IA08 Fields:**
- `dokumen_id`: Document ID from database
- `apl2_answers`: Array of portfolio file validations
  - `soal_id`: Question ID
  - `valid`: Is the document valid? (true/false)
  - `asli`: Is the document original? (true/false)
  - `terkini`: Is the document current? (true/false)
  - `memadai`: Is the document sufficient? (true/false)
- `unit_answers`: Array of unit competency checks
  - `soal_id`: Unit question ID
  - `is_checked`: Is unit competency met? (true/false)
- `bukti_tambahan`: Additional evidence text (optional)
- `is_kompeten`: Final competency recommendation (true/false)
- `rekomendasi_unit/elemen/kuk`: If not competent, specify areas for retesting

### **IA09 Fields:**
- `dokumen_id`: Document ID from database
- `answers`: Array of interview question answers
  - `soal_id`: Question ID
  - `kesimpulan`: Summary of asesi's answer
  - `is_kompeten`: Is asesi competent in this area? (true/false)

### **IA10 Fields:**
- `dokumen_id`: Document ID from database
- `nama_pengawas`: Supervisor name
- `tempat_kerja`: Workplace name
- `alamat`: Workplace address
- `telepon`: Contact phone number
- `answers`: Array of yes/no question answers (Group 2 questions)
  - `referensi_id`: Reference ID from referensis table (group 2)
  - `answer`: Yes (true) or No (false)
- `essay_answers`: Array of text question answers (Groups 3 & 4 questions)
  - `referensi_id`: Reference ID from referensis table (groups 3 & 4)
  - `essay_answer`: Text answer for the question

---

## 📝 **Notes:**
1. All POST endpoints require authentication token in header
2. `{id}` in routes should be replaced with actual `id_izin`
3. `id_jadwal` can be obtained from the schedule data
4. All answers are upsert operations (will update if exists, insert if new)
5. PDF files are automatically generated and stored when QR code is submitted
6. Generated PDF URLs are stored in `bukti_asesmen` table