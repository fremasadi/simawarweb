<?php

namespace App\Filament\Resources\SalarySettingResource\Pages;

use App\Filament\Resources\SalarySettingResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Artisan;
use App\Models\Salary;

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
    
        // Ambil semua salaries dengan setting ini dan pay_date < hari ini
        $salaries = Salary::where('salary_setting_id', $salarySettingId)
            ->where('pay_date', '<', now()->startOfDay())
            ->get();
    
        $updatedCount = 0;
    
        foreach ($salaries as $salary) {
            $deduction = $salary->total_deduction ?? 0;
            $salary->total_salary = $baseSalary - $deduction;
            $salary->save();
            $updatedCount++;
        }
    
        Notification::make()
            ->title('Update Gaji')
            ->body("Berhasil memperbarui total gaji untuk $updatedCount entri salary dengan perhitungan yang benar.")
            ->success()
            ->send();
    }
    

}