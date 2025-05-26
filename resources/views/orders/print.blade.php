<!DOCTYPE html>
<html>
<head>
    <title>Cetak Data Order</title>
    <style>
        body { font-family: sans-serif; }
        table { border-collapse: collapse; width: 100%; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <h2>Data Order - {{ ucfirst(str_replace('_', ' ', $range)) }}</h2>

    <table>
        <thead>
            <tr>
                <th>Nama Pemesan</th>
                <th>No. Telepon</th>
                <th>Jumlah</th>
                <th>Status</th>
                <th>Model Ukuran</th>
                <th>Ditugaskan Ke</th>
                <th>Deadline</th>
            </tr>
        </thead>
        <tbody>
            @forelse($orders as $order)
                <tr>
                    <td>{{ $order->name }}</td>
                    <td>{{ $order->phone }}</td>
                    <td>{{ $order->quantity }}</td>
                    <td>{{ $order->status }}</td>
                    <td>{{ $order->sizeModel->name ?? '-' }}</td>
                    <td>{{ $order->user->name ?? '-' }}</td>
                    <td>{{ \Carbon\Carbon::parse($order->deadline)->format('d-m-Y H:i') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" style="text-align: center;">Tidak ada data.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <script>
        window.onload = () => {
            window.print();
        };
    </script>
</body>
</html>
