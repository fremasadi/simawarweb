<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seeder user admin
        User::create([
            'name' => 'Admin',
            'email' => 'admin@gmail.com',
            'password' => Hash::make('password123'), // Ganti password sesuai kebutuhan
            'address' => 'Jl.No. 123',
            'phone' => '081234567890',
            'image' => null, // Atau isi dengan path image jika ada
            'role' => 'admin',
        ]);
    }
}
