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
    // Ambil ID dari salary_setting yang sedang diedit
    $salarySettingId = $this->record->id;
    $newSalary = $this->record->salary;

    // Update hanya salaries dengan pay_date yang sudah lewat
    Salary::where('salary_setting_id', $salarySettingId)
        ->whereDate('pay_date', '<', now()->toDateString())
        ->get()
        ->each(function ($salary) use ($newSalary) {
            $salary->total_salary = $newSalary - $salary->total_deduction;
            $salary->save();
        });

    // Tampilkan notifikasi sukses
    Notification::make()
        ->title('Pengaturan gaji berhasil disimpan')
        ->body('Total gaji karyawan yang sudah jatuh tempo berhasil diperbarui')
        ->success()
        ->send();
}

}