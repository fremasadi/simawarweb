<?php

namespace App\Filament\Resources\ImageModelResource\Pages;

use App\Filament\Resources\ImageModelResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditImageModel extends EditRecord
{
    protected static string $resource = ImageModelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
