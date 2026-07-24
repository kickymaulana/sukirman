<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminRoleAssigner extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'kickymaulana@gmail.com'],
            ['name' => 'Kicky Maulana', 'password' => bcrypt('password')]
        );
        $user->assignRole('admin');
        $user->update(['is_approved' => true, 'nik' => 'admin001']);
        $this->command->info('Admin user ready: kickymaulana@gmail.com');
    }
}
