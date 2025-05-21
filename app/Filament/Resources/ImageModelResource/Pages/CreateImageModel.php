<?php

namespace App\Filament\Resources\ImageModelResource\Pages;

use App\Filament\Resources\ImageModelResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateImageModel extends CreateRecord
{
    protected static string $resource = ImageModelResource::class;

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
