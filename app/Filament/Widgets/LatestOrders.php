<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use App\Models\User;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class LatestOrders extends BaseWidget
{
    protected static ?int $sort = 4;
    
    protected int | string | array $columnSpan = 'full';
    
    protected function getTableQuery(): Builder
    {
        return Order::query()
            ->latest()
            ->limit(10);
    }
    
    protected function getTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('id')
                ->label('ID')
                ->sortable(),
                
            Tables\Columns\TextColumn::make('name')
                ->label('Nama Pesanan')
                ->searchable()
                ->sortable(),
                
            Tables\Columns\TextColumn::make('quantity')
                ->label('Jumlah')
                ->sortable(),
            
            Tables\Columns\TextColumn::make('status')
                ->label('Status')
                ->badge()
                ->color(fn (string $state): string => match ($state) {
                    'selesai' => 'success',
                    'dibatalkan' => 'danger',
                    'ditugaskan' => 'warning',
                    'dalam proses' => 'info',
                    default => 'gray',
                }),
                
            Tables\Columns\TextColumn::make('deadline')
                ->label('Batas Waktu')
                ->dateTime('d M Y H:i')
                ->sortable()
                ->color(fn (Order $record): string => 
                    now() > $record->deadline && $record->status !== 'selesai' 
                        ? 'danger' 
                        : 'gray'
                ),
                
            Tables\Columns\TextColumn::make('ditugaskan_ke')
                ->label('Ditugaskan Kepada')
                ->getStateUsing(fn (Order $record): ?string => 
                    $record->ditugaskan_ke 
                        ? User::find($record->ditugaskan_ke)?->name 
                        : 'Belum ditugaskan'
                ),
                
            Tables\Columns\TextColumn::make('created_at')
                ->label('Dibuat Pada')
                ->dateTime('d M Y H:i')
                ->sortable(),
        ];
    }
    
    protected function getTableHeading(): string
    {
        return 'Pesanan Terbaru';
    }
    
    protected function getTableActions(): array
    {
        return [
            Tables\Actions\Action::make('view')
                ->label('Lihat')
                ->icon('heroicon-m-eye')
                ->url(fn (Order $record): string => route('filament.admin.resources.orders.view', $record)),
        ];
    }
    
    protected function getTableEmptyStateHeading(): ?string
    {
        return 'Tidak ada pesanan';
    }
    
    protected function getTableEmptyStateDescription(): ?string
    {
        return 'Pesanan akan muncul di sini setelah ada pesanan baru.';
    }
    
    protected function getTableEmptyStateIcon(): ?string
    {
        return 'heroicon-o-shopping-bag';
    }
}