<?php

namespace App\Filament\Widgets;

use App\Models\Order; // Ganti dengan model sebenarnya
use Carbon\Carbon;
use Filament\Widgets\LineChartWidget;
use Illuminate\Support\Facades\DB;

class TaskStatusChart extends LineChartWidget
{
    protected static ?string $heading = 'Grafik Status Tugas Bulan Ini';

    protected function getData(): array
    {
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();

        $data = Order::select(
                DB::raw("DATE(created_at) as date"),
                DB::raw("status"),
                DB::raw("count(*) as total")
            )
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->groupBy('date', 'status')
            ->orderBy('date')
            ->get()
            ->groupBy('status');

        $labels = collect(Carbon::now()->startOfMonth()->daysUntil(Carbon::now()->endOfMonth()))
            ->map(fn($date) => $date->toDateString())
            ->toArray();

        $statuses = ['ditugaskan', 'dikerjakan', 'selesai'];

        $datasets = [];

        foreach ($statuses as $status) {
            $dailyData = array_fill_keys($labels, 0);

            if (isset($data[$status])) {
                foreach ($data[$status] as $row) {
                    $dailyData[$row->date] = $row->total;
                }
            }

            $datasets[] = [
                'label' => ucfirst($status),
                'data' => array_values($dailyData),
                'borderColor' => match($status) {
                    'ditugaskan' => '#f59e0b',
                    'dikerjakan' => '#3b82f6',
                    'selesai' => '#10b981',
                    default => '#6b7280',
                },
            ];
        }

        return [
            'datasets' => $datasets,
            'labels' => $labels,
        ];
    }
}
