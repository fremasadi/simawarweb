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
        // Update semua data gaji yang terkait dengan pengaturan gaji ini
        Salary::where('salary_setting_id', $this->record->id)
            ->update(['total_salary' => $this->record->salary]);
            
        // Tampilkan notifikasi sukses
        Notification::make()
            ->title('Pengaturan gaji berhasil disimpan')
            ->body('Total gaji karyawan berhasil diperbarui')
            ->success()
            ->send();
    }
}