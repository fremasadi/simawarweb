<?php
namespace App\Filament\Pages;

use App\Filament\Widgets\AttendanceOverview;
use App\Filament\Widgets\LatestOrders;
use App\Filament\Widgets\OrderStatusChart;
use App\Filament\Widgets\SalaryStats;
use App\Filament\Widgets\StatsOverview;
use App\Filament\Widgets\UserRoleChart;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';
    
    protected static string $view = 'filament.pages.dashboard';
    
    public function getHeaderWidgets(): array
    {
        return [
            StatsOverview::class,
        ];
    }
    
    public function getFooterWidgets(): array
    {
        return [];
    }
    
    public function getColumns(): int | array
    {
        return 2;
    }
    
    public function getWidgets(): array
    {
        return [
            StatsOverview::class,
            AttendanceOverview::class,
            SalaryStats::class,
            LatestOrders::class,
            OrderStatusChart::class,
        ];
    }
}