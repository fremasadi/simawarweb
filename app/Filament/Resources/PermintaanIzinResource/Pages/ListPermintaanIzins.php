<?php

namespace App\Filament\Resources\PermintaanIzinResource\Pages;

use App\Filament\Resources\PermintaanIzinResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPermintaanIzins extends ListRecords
{
    protected static string $resource = PermintaanIzinResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
