<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TukChecklistTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $fasilitas = [
            ['Lokasi Gedung Dengan Akses Masuk & Keluar', null],
            ['Kondisi Bangunan Baik Dengan Penerangan Yang Cukup', null],
            ['Ruangan Uji Tulis Sesuai Kapasitas Asesi', null],
            ['Jaringan Internet', 'Min. 10 Mbps'],
            ['Monitor / Proyektor', null],
            ['Laptop / Komputer', 'Min. 1'],
            ['Meja Dan Kursi Asesor', null],
            ['Meja Dan Kursi Asesi', null],
            ['Alat Komunikasi (HT/HP)', null],
            ['Alat Dokumentasi (HP/Kamera)', null],
            ['Pulpen', null],
            ['Pensil', null],
            ['Correction Tape', null],
            ['Penghapus Pensil', null],
            ['Spidol', null],
            ['Penggaris', 'Min. 30cm'],
            ['HVS A4', null],
            ['APD', null],
            ['APK', null],
            ['P3K', null],
            ['Rambu Keselamatan Kerja', null],
        ];

        $peralatan = [
            ['Theodolite', null],
            ['Meteran', null],
            ['Waterpass', null],
            ['Autocad', null],
            ['Perancah', null],
            ['Bouwplank', null],
            ['Patok / Bench Mark', null],
            ['Jidar', null],
            ['Lot / Bandul', null],
            ['Palu Karet', null],
            ['Palu Besi', null],
            ['Tang Jepit', null],
            ['Tang Potong', null],
            ['Gergaji Kayu', null],
            ['Gergaji Besi', null],
            ['Mesin Gerinda', null],
            ['Pembengkok Besi', null],
            ['Pahat Kayu', null],
            ['Obeng', null],
            ['Cangkul / Sekop', null],
            ['Sendok Semen', null],
            ['Ember', null],
            ['Alat Pengerik / Kape', null],
            ['Kuas Roll Cat', null],
            ['Cat', null],
            ['Kuas Cat', null],
            ['Nampan Cat', null],
            ['Benang', null],
            ['Paku', null],
            ['Ampelas', null],
            ['Triplek', null],
            ['Masking Tape / Lakban', null],
            ['Dempul', null],
            ['Papan Applicator', null],
            ['Mesin Bor', null],
            ['Mesin Serut', null],
            ['Mesin Gergaji', null],
            ['Penggaris Siku', null],
        ];

        $insert = [];
        $urutan = 1;
        foreach ($fasilitas as [$nama, $spesifikasi]) {
            $insert[] = [
                'kategori'    => 'fasilitas',
                'nama'        => $nama,
                'spesifikasi' => $spesifikasi,
                'urutan'      => $urutan++,
                'is_active'   => true,
                'created_at'  => $now,
                'updated_at'  => $now,
            ];
        }
        $urutan = 1;
        foreach ($peralatan as [$nama, $spesifikasi]) {
            $insert[] = [
                'kategori'    => 'peralatan',
                'nama'        => $nama,
                'spesifikasi' => $spesifikasi,
                'urutan'      => $urutan++,
                'is_active'   => true,
                'created_at'  => $now,
                'updated_at'  => $now,
            ];
        }

        DB::table('tuk_checklist_templates')->insert($insert);
    }
}
