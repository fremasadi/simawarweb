<?php

namespace App\Filament\Resources\SalaryDeductionHistoriesResource\Pages;

use App\Filament\Resources\SalaryDeductionHistoriesResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateSalaryDeductionHistories extends CreateRecord
{
    protected static string $resource = SalaryDeductionHistoriesResource::class;

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
