<?php

namespace App\Filament\Widgets;

use App\Models\Attendance;
use App\Models\Order;
use App\Models\Salary;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Carbon\Carbon;

class StatsOverview extends BaseWidget
{
    protected static ?string $pollingInterval = '30s';
    
    protected function getStats(): array
    {
        // Hitung total karyawan
        $totalUsers = User::count();
        $totalKaryawan = User::where('role', 'karyawan')->count();
        
        // Hitung kehadiran hari ini
        $today = Carbon::today()->toDateString();

        $todayAttendances = Attendance::whereDate('date', $today)->count();

        $todayPresent = Attendance::whereDate('date', $today)
            ->whereIn('status', ['hadir', 'telat'])
            ->count();

        $attendancePercentage = $totalKaryawan > 0 
            ? round(($todayPresent / $totalKaryawan) * 100) 
            : 0;

        
        // Hitung pesanan aktif
        $activeOrders = Order::whereNotIn('status', ['selesai', 'dibatalkan'])->count();
        
        // Hitung gaji bulan ini
        $currentMonth = Carbon::now()->format('Y-m');
        $currentMonthSalaries = Salary::whereRaw("DATE_FORMAT(pay_date, '%Y-%m') = ?", [$currentMonth])
            ->sum('total_salary');
        
        return [
            Stat::make('Total Karyawan', $totalKaryawan)
                ->description('Dari total ' . $totalUsers . ' pengguna')
                ->descriptionIcon('heroicon-m-users')
                ->chart([7, 2, 10, 3, 15, 4, $totalKaryawan])
                ->color('primary'),
            
            Stat::make('Kehadiran Hari Ini', $todayPresent)
                ->description($attendancePercentage . '% tingkat kehadiran')
                ->descriptionIcon('heroicon-m-check-circle')
                ->chart([60, 70, 80, 87, 82, 95, $attendancePercentage])
                ->color($attendancePercentage > 80 ? 'success' : 'warning'),
            
            Stat::make('Pesanan Aktif', $activeOrders)
                ->description('Memerlukan tindakan')
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->chart([2, 5, 8, 10, 7, 9, $activeOrders])
                ->color('danger'),
                
            Stat::make('Total Gaji Bulan Ini', 'Rp ' . number_format($currentMonthSalaries, 0, ',', '.'))
                ->description('Pengeluaran bulan ' . Carbon::now()->format('F Y'))
                ->descriptionIcon('heroicon-m-banknotes')
                ->chart([1000000, 1500000, 1200000, 1800000, 2000000, 1700000, $currentMonthSalaries / 1000000])
                ->color('success'),
        ];
    }
}