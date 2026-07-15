<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use App\Models\User;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Buat list Role bawaan aplikasi SUKIRMAN
        $roleSupervisor = Role::create(['name' => 'supervisor']);
        $roleManager    = Role::create(['name' => 'manager']);
        $roleGM         = Role::create(['name' => 'fm/gm']);
        $roleDireksi    = Role::create(['name' => 'direksi']);
        $roleGudang     = Role::create(['name' => 'gudang']);
        $rolePurchasing = Role::create(['name' => 'purchasing']);

        // 2. Contoh membuat 1 user dummy untuk Supervisor IT
        $supervisorUser = User::create([
            'name' => 'Kicky Maulana',
            'email' => 'kicky@gotechdynamics.com',
            'password' => bcrypt('password123'),
        ]);

        // 3. Pasangkan role 'supervisor' ke user Kicky
        $supervisorUser->assignRole($roleSupervisor);
    }
}
