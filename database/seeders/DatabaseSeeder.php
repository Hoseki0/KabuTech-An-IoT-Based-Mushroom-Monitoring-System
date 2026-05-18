<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     * Creates (or updates) the default admin account in the admins table.
     */
    public function run(): void
    {
        $email    = env('ADMIN_EMAIL', 'admin@kabutech.local');
        $password = env('ADMIN_PASSWORD', 'changeme123');

        Admin::query()->updateOrCreate(
            ['email' => $email],
            [
                'name'     => 'Administrator',
                'password' => Hash::make($password),
            ]
        );
    }
}
