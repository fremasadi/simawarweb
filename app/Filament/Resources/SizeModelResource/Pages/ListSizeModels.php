<?php

namespace App\Filament\Resources\SizeModelResource\Pages;

use App\Filament\Resources\SizeModelResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSizeModels extends ListRecords
{
    protected static string $resource = SizeModelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
