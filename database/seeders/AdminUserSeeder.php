<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Buat User Admin Pertama (jika belum ada)
        DB::table('users')->updateOrInsert(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin NOC',
                'password' => Hash::make('password123'),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // 2. Buat Default API Key Pertama (jika belum ada)
        DB::table('api_keys')->updateOrInsert(
            ['name' => 'Default System Key'],
            [
                'key' => 'rad_' . Str::random(40),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // 3. Buat Default User Isolate (jika belum ada)
        DB::table('radgroupreply')->updateOrInsert(
            [
                'groupname' => "ISOLIREBILLING",
                'attribute' => "Mikrotik-Group",
                'op' => "=",
                'value' => "ISOLIREBILLING",
                'created_at' => now(),
            ]
        );
    }
}
