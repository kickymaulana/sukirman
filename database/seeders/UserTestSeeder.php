<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserTestSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['name' => 'Supervisor', 'email' => 'supervisor@sukirman.test', 'role' => 'Supervisor'],
            ['name' => 'Manager', 'email' => 'manager@sukirman.test', 'role' => 'Manager'],
            ['name' => 'FM GM', 'email' => 'fmgm@sukirman.test', 'role' => 'FM/GM'],
            ['name' => 'Direksi A', 'email' => 'direksi1@sukirman.test', 'role' => 'Direksi'],
            ['name' => 'Direksi B', 'email' => 'direksi2@sukirman.test', 'role' => 'Direksi'],
            ['name' => 'Gudang', 'email' => 'gudang@sukirman.test', 'role' => 'Gudang'],
            ['name' => 'Purchasing', 'email' => 'purchasing@sukirman.test', 'role' => 'Purchasing'],
        ];

        foreach ($users as $data) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                ['name' => $data['name'], 'password' => bcrypt('password')]
            );
            $user->assignRole($data['role']);
            $this->command->info("User {$data['email']} — role: {$data['role']}");
        }

        $this->command->info('');
        $this->command->info('✅ Password untuk semua user: password');
    }
}
