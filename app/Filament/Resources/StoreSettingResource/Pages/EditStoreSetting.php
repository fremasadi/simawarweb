<?php

namespace App\Filament\Resources\StoreSettingResource\Pages;

use App\Filament\Resources\StoreSettingResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditStoreSetting extends EditRecord
{
    protected static string $resource = StoreSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
