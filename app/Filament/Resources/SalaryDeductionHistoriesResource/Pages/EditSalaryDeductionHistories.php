<?php

namespace App\Filament\Resources\SalaryDeductionHistoriesResource\Pages;

use App\Filament\Resources\SalaryDeductionHistoriesResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSalaryDeductionHistories extends EditRecord
{
    protected static string $resource = SalaryDeductionHistoriesResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
