<?php

namespace App\Filament\Resources\SizeModelResource\Pages;

use App\Filament\Resources\SizeModelResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateSizeModel extends CreateRecord
{
    protected static string $resource = SizeModelResource::class;

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
