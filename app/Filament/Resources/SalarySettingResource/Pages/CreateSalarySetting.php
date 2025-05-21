<?php

namespace App\Filament\Resources\SalarySettingResource\Pages;

use App\Filament\Resources\SalarySettingResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Artisan;
class CreateSalarySetting extends CreateRecord
{
    protected static string $resource = SalarySettingResource::class;

    protected function afterCreate(): void
    {
        // Jalankan perintah Artisan
        Artisan::call('salary:calculate-deductions');

        // Tampilkan notifikasi sukses
        Notification::make()
            ->title('Perhitungan potongan berhasil dijalankan')
            ->success()
            ->send();
    }

    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction()
                ->label('Simpan'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
