<?php
namespace App\Filament\Pages;

use App\Filament\Widgets\AttendanceOverview;
use App\Filament\Widgets\LatestOrders;
use App\Filament\Widgets\OrderStatusChart;
use App\Filament\Widgets\SalaryStats;
use App\Filament\Widgets\StatsOverview;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';
    
    protected static string $view = 'filament.pages.dashboard';
    
    // Pastikan tidak ada duplicate widget antara getHeaderWidgets dan getWidgets
    public function getHeaderWidgets(): array
    {
        return [
            StatsOverview::class,
            AttendanceOverview::class,
            SalaryStats::class,
            LatestOrders::class,
        ];
    }
    
    // Tambahkan widget chart di footer
    public function getFooterWidgets(): array
    {
        return [
            OrderStatusChart::class,
        ];
    }
    
    public function getColumns(): int | array
    {
        return 2;
    }
    
    // Kosongkan ini karena sudah didefinisikan di header dan footer
    public function getWidgets(): array
    {
        return [];
    }
}