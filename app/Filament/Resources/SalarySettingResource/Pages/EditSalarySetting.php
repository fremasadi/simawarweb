<?php

namespace App\Filament\Resources\SalarySettingResource\Pages;

use App\Filament\Resources\SalarySettingResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSalarySetting extends EditRecord
{
    protected static string $resource = SalarySettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
