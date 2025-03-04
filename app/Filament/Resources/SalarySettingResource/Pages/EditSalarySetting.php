<?php

namespace App\Filament\Resources\SalarySettingResource\Pages;

use App\Filament\Resources\SalarySettingResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Artisan;
class EditSalarySetting extends EditRecord
{
    protected static string $resource = SalarySettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        // Jalankan perintah Artisan
        Artisan::call('salary:calculate-deductions');

        // Tampilkan notifikasi sukses
        Notification::make()
            ->title('Perhitungan potongan berhasil dijalankan')
            ->success()
            ->send();
    }
}
