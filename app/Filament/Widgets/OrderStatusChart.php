<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class OrderStatusChart extends ChartWidget
{
    protected static ?string $heading = 'Status Pesanan Bulan Ini';

    protected static ?int $sort = 3;

    protected function getData(): array
    {
        $now = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth();
        $endOfMonth = $now->copy()->endOfMonth();
        
        // Dapatkan semua tanggal dalam bulan ini
        $dates = [];
        $labels = [];
        $current = $startOfMonth->copy();
        
        while ($current <= $endOfMonth) {
            $dates[] = $current->format('Y-m-d');
            $labels[] = $current->format('d M');
            $current->addDay();
        }
        
        // Status yang akan ditampilkan
        $statuses = ['ditugaskan', 'dikerjakan', 'selesai'];
        $statusColors = [
            'ditugaskan' => '#FFB020',
            'dikerjakan' => '#3F51B5',
            'selesai' => '#10B981',
        ];
        
        $datasets = [];
        
        foreach ($statuses as $status) {
            // Dapatkan jumlah pesanan per hari untuk status ini
            $statusData = Order::where('status', $status)
                ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
                ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as total'))
                ->groupBy('date')
                ->pluck('total', 'date')
                ->toArray();
                
            $data = [];
            
            // Isi data untuk setiap tanggal
            foreach ($dates as $date) {
                $data[] = $statusData[$date] ?? 0;
            }
            
            $datasets[] = [
                'label' => ucfirst($status),
                'data' => $data,
                'borderColor' => $statusColors[$status],
                'fill' => false,
            ];
        }
        
        return [
            'labels' => $labels,
            'datasets' => $datasets,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}