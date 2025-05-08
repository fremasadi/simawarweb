<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Salary;
use App\Models\SalarySetting;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('salaries', function (Blueprint $table) {
            // Tambahkan kolom base_salary yang akan menyimpan nilai salary dari setting saat pembuatan
            $table->decimal('base_salary', 15, 2)->after('salary_setting_id')->nullable();
        });

        // Migrasi data yang sudah ada: isi base_salary dari salary_setting yang terkait
        $this->migrateExistingData();
    }

    /**
     * Migrasi data yang sudah ada
     */
    private function migrateExistingData(): void 
    {
        // Dapatkan semua salary
        $salaries = Salary::all();
        
        foreach ($salaries as $salary) {
            // Ambil setting gaji
            $salarySetting = SalarySetting::find($salary->salary_setting_id);
            
            if ($salarySetting) {
                // Update base_salary dengan nilai dari setting
                $salary->base_salary = $salarySetting->salary;
                // Pastikan total salary dihitung dengan benar
                $salary->total_salary = $salarySetting->salary - ($salary->total_deduction ?? 0);
                $salary->save();
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('salaries', function (Blueprint $table) {
            $table->dropColumn('base_salary');
        });
    }
};