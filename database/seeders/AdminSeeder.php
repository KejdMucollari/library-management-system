<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Default admin for production / Railway (safe to re-run).
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@library.com'],
            [
                'name' => 'Admin',
                'password' => Hash::make('Admin@1234'),
                'is_admin' => true,
                'email_verified_at' => now(),
            ],
        );
    }
}
