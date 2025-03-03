<?php

namespace App\Filament\Widgets;

use App\Models\Attendance;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class AttendanceOverview extends ChartWidget
{
    protected static ?string $heading = 'Statistik Kehadiran';
    
    protected static ?string $pollingInterval = '60s';
    
    protected static ?int $sort = 2;
    
    protected function getData(): array
    {
        // Mendapatkan statistik kehadiran 30 hari terakhir
        $startDate = Carbon::now()->subDays(30);
        $endDate = Carbon::now();
        
        $attendanceStats = Attendance::select(
                'status',
                DB::raw('DATE(date) as attendance_date'),
                DB::raw('COUNT(*) as count')
            )
            ->whereBetween('date', [$startDate, $endDate])
            ->groupBy('attendance_date', 'status')
            ->get()
            ->groupBy('attendance_date');
        
        // Memformat data untuk chart
        $dates = [];
        $hadirData = [];
        $izinData = [];
        $telatData = [];
        $tidakHadirData = [];
        
        $dateRange = new \DatePeriod(
            new \DateTime($startDate->format('Y-m-d')),
            new \DateInterval('P1D'),
            new \DateTime($endDate->addDay()->format('Y-m-d'))
        );
        
        foreach ($dateRange as $date) {
            $currentDate = $date->format('Y-m-d');
            $dates[] = $date->format('d M');
            
            $dayStats = $attendanceStats[$currentDate] ?? collect();
            
            $hadirData[] = $dayStats->firstWhere('status', 'hadir')->count ?? 0;
            $izinData[] = $dayStats->firstWhere('status', 'izin')->count ?? 0;
            $telatData[] = $dayStats->firstWhere('status', 'telat')->count ?? 0;
            $tidakHadirData[] = $dayStats->firstWhere('status', 'tidak hadir')->count ?? 0;
        }
        
        return [
            'labels' => $dates,
            'datasets' => [
                [
                    'label' => 'Hadir',
                    'data' => $hadirData,
                    'backgroundColor' => 'rgba(34, 197, 94, 0.5)',
                    'borderColor' => 'rgb(34, 197, 94)',
                ],
                [
                    'label' => 'Izin',
                    'data' => $izinData,
                    'backgroundColor' => 'rgba(59, 130, 246, 0.5)',
                    'borderColor' => 'rgb(59, 130, 246)',
                ],
                [
                    'label' => 'Telat',
                    'data' => $telatData,
                    'backgroundColor' => 'rgba(245, 158, 11, 0.5)',
                    'borderColor' => 'rgb(245, 158, 11)',
                ],
                [
                    'label' => 'Tidak Hadir',
                    'data' => $tidakHadirData,
                    'backgroundColor' => 'rgba(239, 68, 68, 0.5)', 
                    'borderColor' => 'rgb(239, 68, 68)',
                ],
            ],
        ];
    }
    
    protected function getType(): string
    {
        return 'bar';
    }
    
    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'title' => [
                        'display' => true,
                        'text' => 'Jumlah Karyawan',
                    ],
                ],
                'x' => [
                    'title' => [
                        'display' => true,
                        'text' => 'Tanggal',
                    ],
                ],
            ],
        ];
    }
}