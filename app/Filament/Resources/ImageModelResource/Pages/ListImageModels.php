<?php

namespace App\Filament\Resources\ImageModelResource\Pages;

use App\Filament\Resources\ImageModelResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListImageModels extends ListRecords
{
    protected static string $resource = ImageModelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
