<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run()
    {
        // 1. Create Superadmin role using Spatie
        $role = Role::firstOrCreate(
            ['name' => 'superadmin', 'guard_name' => 'api']
        );

        // 2. Create superadmin user
        $user = User::firstOrCreate(
            ['email' => 'superadmin@gmail.com'], // prevent duplicates
            [
                'name'      => 'Super Admin',
                'phone'     => '085693405490',
                'avatar'    => null,
                'role_id'   => $role->id,          // ← your custom role_id system
                'password'  => Hash::make('123qweasd'),
                'address'   => 'Jakarta',
            ]
        );

        // 3. Assign Spatie role to the user
        $user->assignRole('superadmin');
    }
}
