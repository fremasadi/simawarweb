<?php

namespace App\Filament\Resources\SalaryDeductionHistoriesResource\Pages;

use App\Filament\Resources\SalaryDeductionHistoriesResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSalaryDeductionHistories extends ListRecords
{
    protected static string $resource = SalaryDeductionHistoriesResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
