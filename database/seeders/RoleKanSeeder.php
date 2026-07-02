<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleKanSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            13 => 'Manager Mutu',
            14 => 'Penyusun',
            15 => 'Validator KAN',
            16 => 'Praktisi',
        ];

        foreach ($roles as $id => $name) {
            Role::firstOrCreate(
                ['id' => $id],
                [
                    'name'       => $name,
                    'guard_name' => 'api',
                ]
            );
        }
    }
}
