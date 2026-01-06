<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Asosiasi;
use Illuminate\Support\Facades\File;

class AsosiasiSeeder extends Seeder
{
    public function run(): void
    {
        $path = resource_path('json/asosiasi.json');

        if (!File::exists($path)) {
            $this->command->error('asosiasi.json not found');
            return;
        }

        $json = File::get($path);
        $payload = json_decode($json, true);

        if (!isset($payload['data'])) {
            $this->command->error('Invalid JSON structure');
            return;
        }

        foreach ($payload['data'] as $item) {
            Asosiasi::updateOrCreate(
                [
                    'id' => $item['ID_Asosiasi_Profesi'], // PRIMARY KEY
                ],
                [
                    'nama'         => $item['Nama'],
                    'nama_lengkap' => $item['Nama_Lengkap'],
                ]
            );
        }

        $this->command->info('Asosiasi imported successfully');
    }
}
