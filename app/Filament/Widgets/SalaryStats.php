<?php

namespace App\Filament\Widgets;

use App\Models\Salary;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class SalaryStats extends ChartWidget
{
    protected static ?string $heading = 'Statistik Gaji';
    
    protected static ?int $sort = 3;
    
    protected function getData(): array
    {
        // Data gaji 6 bulan terakhir
        $months = collect();
        $data = collect();
        $statuses = collect(['paid', 'pending', 'canceled']);
        
        for ($i = 5; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $months->push($date->format('M Y'));
            
            $monthlyData = Salary::whereYear('pay_date', $date->year)
                ->whereMonth('pay_date', $date->month)
                ->select('status', DB::raw('SUM(total_salary) as total'))
                ->groupBy('status')
                ->get()
                ->pluck('total', 'status')
                ->toArray();
            
            $data->push([
                'paid' => $monthlyData['paid'] ?? 0,
                'pending' => $monthlyData['pending'] ?? 0,
                'canceled' => $monthlyData['canceled'] ?? 0,
            ]);
        }
        
        return [
            'labels' => $months->toArray(),
            'datasets' => [
                [
                    'label' => 'Dibayar',
                    'data' => $data->pluck('paid')->toArray(),
                    'backgroundColor' => 'rgba(34, 197, 94, 0.7)',
                ],
                [
                    'label' => 'Tertunda',
                    'data' => $data->pluck('pending')->toArray(),
                    'backgroundColor' => 'rgba(245, 158, 11, 0.7)',
                ],
                [
                    'label' => 'Dibatalkan',
                    'data' => $data->pluck('canceled')->toArray(),
                    'backgroundColor' => 'rgba(239, 68, 68, 0.7)',
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
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'callback' => 'function(value) { 
                            return "Rp " + new Intl.NumberFormat("id-ID").format(value); 
                        }',
                    ],
                ],
            ],
            'plugins' => [
                'tooltip' => [
                    'callbacks' => [
                        'label' => 'function(context) { 
                            return context.dataset.label + ": Rp " + 
                            new Intl.NumberFormat("id-ID").format(context.raw);
                        }',
                    ],
                ],
            ],
        ];
    }
}