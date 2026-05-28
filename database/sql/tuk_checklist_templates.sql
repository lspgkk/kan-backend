-- ============================================================
-- Create table
-- ============================================================
CREATE TABLE IF NOT EXISTS `tuk_checklist_templates` (
    `id` bigint unsigned NOT NULL AUTO_INCREMENT,
    `kategori` varchar(255) NOT NULL COMMENT 'fasilitas or peralatan',
    `nama` varchar(255) NOT NULL,
    `spesifikasi` varchar(255) DEFAULT NULL,
    `urutan` int NOT NULL DEFAULT 0,
    `is_active` tinyint(1) NOT NULL DEFAULT 1,
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `tuk_checklist_templates_kategori_index` (`kategori`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Seed data: STANDAR PERSYARATAN JABATAN KERJA (fasilitas)
-- ============================================================
INSERT INTO `tuk_checklist_templates` (`kategori`, `nama`, `spesifikasi`, `urutan`, `is_active`, `created_at`, `updated_at`) VALUES
('fasilitas', 'Lokasi Gedung Dengan Akses Masuk & Keluar',            NULL,             1,  1, NOW(), NOW()),
('fasilitas', 'Kondisi Bangunan Baik Dengan Penerangan Yang Cukup',    NULL,             2,  1, NOW(), NOW()),
('fasilitas', 'Ruangan Uji Tulis Sesuai Kapasitas Asesi',              NULL,             3,  1, NOW(), NOW()),
('fasilitas', 'Jaringan Internet',                                     'Min. 10 Mbps',   4,  1, NOW(), NOW()),
('fasilitas', 'Monitor / Proyektor',                                   NULL,             5,  1, NOW(), NOW()),
('fasilitas', 'Laptop / Komputer',                                     'Min. 1',         6,  1, NOW(), NOW()),
('fasilitas', 'Meja Dan Kursi Asesor',                                 NULL,             7,  1, NOW(), NOW()),
('fasilitas', 'Meja Dan Kursi Asesi',                                  NULL,             8,  1, NOW(), NOW()),
('fasilitas', 'Alat Komunikasi (HT/HP)',                               NULL,             9,  1, NOW(), NOW()),
('fasilitas', 'Alat Dokumentasi (HP/Kamera)',                          NULL,            10,  1, NOW(), NOW()),
('fasilitas', 'Pulpen',                                                NULL,            11,  1, NOW(), NOW()),
('fasilitas', 'Pensil',                                                NULL,            12,  1, NOW(), NOW()),
('fasilitas', 'Correction Tape',                                       NULL,            13,  1, NOW(), NOW()),
('fasilitas', 'Penghapus Pensil',                                      NULL,            14,  1, NOW(), NOW()),
('fasilitas', 'Spidol',                                                NULL,            15,  1, NOW(), NOW()),
('fasilitas', 'Penggaris',                                             'Min. 30cm',     16,  1, NOW(), NOW()),
('fasilitas', 'HVS A4',                                                NULL,            17,  1, NOW(), NOW()),
('fasilitas', 'APD',                                                   NULL,            18,  1, NOW(), NOW()),
('fasilitas', 'APK',                                                   NULL,            19,  1, NOW(), NOW()),
('fasilitas', 'P3K',                                                   NULL,            20,  1, NOW(), NOW()),
('fasilitas', 'Rambu Keselamatan Kerja',                               NULL,            21,  1, NOW(), NOW());

-- ============================================================
-- Seed data: PERALATAN PRAKTIK (peralatan)
-- ============================================================
INSERT INTO `tuk_checklist_templates` (`kategori`, `nama`, `spesifikasi`, `urutan`, `is_active`, `created_at`, `updated_at`) VALUES
('peralatan', 'Theodolite',                  NULL,  1, 1, NOW(), NOW()),
('peralatan', 'Meteran',                     NULL,  2, 1, NOW(), NOW()),
('peralatan', 'Waterpass',                   NULL,  3, 1, NOW(), NOW()),
('peralatan', 'Autocad',                     NULL,  4, 1, NOW(), NOW()),
('peralatan', 'Perancah',                    NULL,  5, 1, NOW(), NOW()),
('peralatan', 'Bouwplank',                   NULL,  6, 1, NOW(), NOW()),
('peralatan', 'Patok / Bench Mark',          NULL,  7, 1, NOW(), NOW()),
('peralatan', 'Jidar',                       NULL,  8, 1, NOW(), NOW()),
('peralatan', 'Lot / Bandul',                NULL,  9, 1, NOW(), NOW()),
('peralatan', 'Palu Karet',                  NULL, 10, 1, NOW(), NOW()),
('peralatan', 'Palu Besi',                   NULL, 11, 1, NOW(), NOW()),
('peralatan', 'Tang Jepit',                  NULL, 12, 1, NOW(), NOW()),
('peralatan', 'Tang Potong',                 NULL, 13, 1, NOW(), NOW()),
('peralatan', 'Gergaji Kayu',                NULL, 14, 1, NOW(), NOW()),
('peralatan', 'Gergaji Besi',                NULL, 15, 1, NOW(), NOW()),
('peralatan', 'Mesin Gerinda',               NULL, 16, 1, NOW(), NOW()),
('peralatan', 'Pembengkok Besi',             NULL, 17, 1, NOW(), NOW()),
('peralatan', 'Pahat Kayu',                  NULL, 18, 1, NOW(), NOW()),
('peralatan', 'Obeng',                       NULL, 19, 1, NOW(), NOW()),
('peralatan', 'Cangkul / Sekop',             NULL, 20, 1, NOW(), NOW()),
('peralatan', 'Sendok Semen',                NULL, 21, 1, NOW(), NOW()),
('peralatan', 'Ember',                       NULL, 22, 1, NOW(), NOW()),
('peralatan', 'Alat Pengerik / Kape',        NULL, 23, 1, NOW(), NOW()),
('peralatan', 'Kuas Roll Cat',               NULL, 24, 1, NOW(), NOW()),
('peralatan', 'Cat',                         NULL, 25, 1, NOW(), NOW()),
('peralatan', 'Kuas Cat',                    NULL, 26, 1, NOW(), NOW()),
('peralatan', 'Nampan Cat',                  NULL, 27, 1, NOW(), NOW()),
('peralatan', 'Benang',                      NULL, 28, 1, NOW(), NOW()),
('peralatan', 'Paku',                        NULL, 29, 1, NOW(), NOW()),
('peralatan', 'Ampelas',                     NULL, 30, 1, NOW(), NOW()),
('peralatan', 'Triplek',                     NULL, 31, 1, NOW(), NOW()),
('peralatan', 'Masking Tape / Lakban',       NULL, 32, 1, NOW(), NOW()),
('peralatan', 'Dempul',                      NULL, 33, 1, NOW(), NOW()),
('peralatan', 'Papan Applicator',            NULL, 34, 1, NOW(), NOW()),
('peralatan', 'Mesin Bor',                   NULL, 35, 1, NOW(), NOW()),
('peralatan', 'Mesin Serut',                 NULL, 36, 1, NOW(), NOW()),
('peralatan', 'Mesin Gergaji',               NULL, 37, 1, NOW(), NOW()),
('peralatan', 'Penggaris Siku',              NULL, 38, 1, NOW(), NOW());
