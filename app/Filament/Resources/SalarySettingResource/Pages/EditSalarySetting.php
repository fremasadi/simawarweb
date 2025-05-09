<?php

namespace App\Filament\Resources\SalarySettingResource\Pages;

use App\Filament\Resources\SalarySettingResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Artisan;
use App\Models\Salary;
use Carbon\Carbon;

class EditSalarySetting extends EditRecord
{
    protected static string $resource = SalarySettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
        ];
    }

    protected function afterSave(): void
    {
        $salarySetting = $this->record;
        $salarySettingId = $salarySetting->id;
        $baseSalary = $salarySetting->salary;
        
        // Ambil gaji yang tanggal pembayarannya belum lewat (masa depan atau hari ini)
        // dan status masih pending (belum dibayar)
        $salaries = Salary::where('salary_setting_id', $salarySettingId)
            ->where('pay_date', '>=', now()->startOfDay())
            ->where('status', 'pending')
            ->get();
    
        $updatedCount = 0;
    
        foreach ($salaries as $salary) {
            // Update base_salary ke nilai baru
            $salary->base_salary = $baseSalary;
            
            // Hitung ulang total_salary
            $deduction = $salary->total_deduction ?? 0;
            $salary->total_salary = $baseSalary - $deduction;
            
            $salary->save();
            $updatedCount++;
        }
    
        Notification::make()
            ->title('Update Gaji')
            ->body("Berhasil memperbarui base_salary dan total_salary untuk $updatedCount entri gaji yang pembayarannya belum jatuh tempo.")
            ->success()
            ->send();
    }
}
