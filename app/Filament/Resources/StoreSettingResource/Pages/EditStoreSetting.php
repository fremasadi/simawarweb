<?php

namespace App\Filament\Resources\StoreSettingResource\Pages;

use App\Filament\Resources\StoreSettingResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Artisan;
use Filament\Notifications\Notification;

class EditStoreSetting extends EditRecord
{
    protected static string $resource = StoreSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Actions\DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        // Jalankan perintah Artisan
        Artisan::call('attendance:check');

        // Tampilkan notifikasi sukses
        Notification::make()
            ->title('Jam Toko Berhasil Diubah')
            ->success()
            ->send();
    }
}
