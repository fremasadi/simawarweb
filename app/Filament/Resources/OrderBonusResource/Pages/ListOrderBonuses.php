<?php

namespace App\Filament\Resources\OrderBonusResource\Pages;

use App\Filament\Resources\OrderBonusResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOrderBonuses extends ListRecords
{
    protected static string $resource = OrderBonusResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Actions\CreateAction::make(),
        ];
    }
}
