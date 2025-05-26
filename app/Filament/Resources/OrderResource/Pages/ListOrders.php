<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Forms;
use Filament\Tables;
use Illuminate\Support\Carbon;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('Cetak Data')
    ->icon('heroicon-o-printer')
    ->form([
        Forms\Components\TextInput::make('name')
            ->label('Nama Pemesan')
            ->placeholder('Cari berdasarkan nama')
            ->reactive(),

        Forms\Components\Select::make('range')
            ->label('Rentang Waktu')
            ->options([
                'today' => 'Hari Ini',
                'this_week' => 'Minggu Ini',
                'this_month' => 'Bulan Ini',
                'custom' => 'Custom',
            ])
            ->required()
            ->reactive(),

        Forms\Components\DatePicker::make('start_date')
            ->label('Tanggal Mulai')
            ->visible(fn ($get) => $get('range') === 'custom')
            ->required(fn ($get) => $get('range') === 'custom'),

        Forms\Components\DatePicker::make('end_date')
            ->label('Tanggal Selesai')
            ->visible(fn ($get) => $get('range') === 'custom')
            ->required(fn ($get) => $get('range') === 'custom'),
    ])
    ->action(function (array $data) {
        return redirect()->route('orders.print', [
            'range' => $data['range'],
            'start' => $data['start_date'] ?? null,
            'end' => $data['end_date'] ?? null,
            'name' => $data['name'] ?? null,
        ]);
    })
    ->color('success'),

        ];
    }
}
