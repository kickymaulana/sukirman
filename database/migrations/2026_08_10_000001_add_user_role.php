<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        if (!Role::where('name', 'User')->where('guard_name', 'web')->exists()) {
            Role::create(['name' => 'User', 'guard_name' => 'web']);
        }
    }

    public function down(): void
    {
        Role::where('name', 'User')->where('guard_name', 'web')->delete();
    }
};