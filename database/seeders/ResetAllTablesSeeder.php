<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ResetAllTablesSeeder extends Seeder
{
    public function run()
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        DB::table('attendances')->truncate();
        DB::table('barcodes')->truncate();
        DB::table('cache')->truncate();
        DB::table('cache_locks')->truncate();
        DB::table('failed_jobs')->truncate();
        DB::table('image_models')->truncate();
        DB::table('job_batches')->truncate();
        DB::table('jobs')->truncate();
        DB::table('migrations')->truncate(); // hati-hati kalau pakai migrate ulang
        DB::table('orders')->truncate();
        DB::table('password_reset_tokens')->truncate();
        DB::table('permintaan_izins')->truncate();
        DB::table('personal_access_tokens')->truncate();
        DB::table('salaries')->truncate();
        DB::table('salary_deduction_histories')->truncate();
        DB::table('salary_settings')->truncate();
        DB::table('sessions')->truncate();
        DB::table('size_models')->truncate();
        DB::table('store_settings')->truncate();
        DB::table('users')->truncate();

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $this->command->info('Semua data tabel berhasil dihapus!');
    }
}
