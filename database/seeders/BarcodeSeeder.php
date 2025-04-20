<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BarcodeSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('barcodes')->insert([
            'title' => 'absenmasuk',
            'created_at' => '2025-02-25 16:21:09',
            'updated_at' => '2025-02-25 16:21:09',
        ]);
    }
}
