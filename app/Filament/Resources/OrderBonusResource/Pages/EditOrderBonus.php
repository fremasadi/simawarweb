<?php

namespace App\Filament\Resources\OrderBonusResource\Pages;

use App\Filament\Resources\OrderBonusResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditOrderBonus extends EditRecord
{
    protected static string $resource = OrderBonusResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Actions\DeleteAction::make(),
        ];
    }
}
