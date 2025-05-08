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
        $salarySettingId = $this->record->id;
        $updatedSalary = $this->record->salary;
    
        // Ambil semua salary yang memakai salary_setting ini dan pay_date < hari ini
        $salaries = Salary::where('salary_setting_id', $salarySettingId)
            ->where('pay_date', '<', now()->startOfDay())
            ->get();
    
        $count = $salaries->count();
    
        // Update total_salary hanya pada salary yang sudah lewat waktunya
        foreach ($salaries as $salary) {
            $salary->total_salary = $updatedSalary;
            $salary->save();
        }
    
        // Notifikasi
        Notification::make()
            ->title('Pengaturan gaji diperbarui')
            ->body("Total gaji berhasil diperbarui untuk $count entri salary yang sudah lewat.")
            ->success()
            ->send();
    }
    

}