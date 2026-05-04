<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

use Spatie\Permission\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = ['Super_Admin', 'Partner', 'Operator', 'Worker'];

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role]);
        }

        $admin = User::firstOrCreate(
            ['email' => 'admin@itcloud.uz'],
            [
                'name' => 'ITCloud Admin',
                'password' => Hash::make('password'),
                'pinfl' => '12345678901234',
                'salary_rate' => 5000.00
            ]
        );

        if (!$admin->hasRole('Super_Admin')) {
            $admin->assignRole('Super_Admin');
        }
    }
}
