<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class OrderStatusChart extends ChartWidget
{
    protected static ?string $heading = 'Status Pesanan Bulan Ini';
    
    protected static ?int $sort = 5;
    
    protected int | string | array $columnSpan = 'full';

    protected function getData(): array
    {
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();
        
        // Get all dates in the current month
        $period = Carbon::parse($startOfMonth)->daysUntil($endOfMonth);
        $dates = [];
        
        foreach ($period as $date) {
            $dates[] = $date->format('Y-m-d');
        }
        
        // Get orders count by status for each day
        $ditugaskanData = $this->getStatusData('ditugaskan', $startOfMonth, $endOfMonth);
        $dikerjakanData = $this->getStatusData('dikerjakan', $startOfMonth, $endOfMonth);
        $selesaiData = $this->getStatusData('selesai', $startOfMonth, $endOfMonth);
        
        // Format dates for display
        $formattedDates = array_map(function ($date) {
            return Carbon::parse($date)->format('d M');
        }, $dates);
        
        return [
            'datasets' => [
                [
                    'label' => 'Ditugaskan',
                    'data' => $ditugaskanData,
                    'borderColor' => 'rgb(255, 99, 132)',
                    'tension' => 0.3,
                ],
                [
                    'label' => 'Dikerjakan',
                    'data' => $dikerjakanData,
                    'borderColor' => 'rgb(54, 162, 235)',
                    'tension' => 0.3,
                ],
                [
                    'label' => 'Selesai',
                    'data' => $selesaiData,
                    'borderColor' => 'rgb(75, 192, 192)',
                    'tension' => 0.3,
                ],
            ],
            'labels' => $formattedDates,
        ];
    }

    private function getStatusData(string $status, $startDate, $endDate): array
    {
        $counts = DB::table('orders')
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as count'))
            ->where('status', $status)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('date')
            ->get()
            ->keyBy('date')
            ->map(function ($item) {
                return $item->count;
            })
            ->toArray();
        
        // Fill in missing dates with zero
        $result = [];
        $currentDate = Carbon::parse($startDate);
        
        while ($currentDate <= $endDate) {
            $dateKey = $currentDate->format('Y-m-d');
            $result[] = $counts[$dateKey] ?? 0;
            $currentDate->addDay();
        }
        
        return $result;
    }

    protected function getType(): string
    {
        return 'line';
    }
}