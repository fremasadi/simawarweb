<!DOCTYPE html>
<html>
<head>
    <title>Cetak Order</title>
    <style>
        body { font-family: sans-serif; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
    </style>
</head>
<body>
    <h2>Data Order</h2>
    <table>
        <thead>
            <tr>
                <th>Nama</th>
                <th>Deadline</th>
                <th>Telepon</th>
                <th>Jumlah</th>
                <th>Status</th>
                <th>Model Ukuran</th>
                <th>Ditugaskan Ke</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($orders as $order)
                <tr>
                    <td>{{ $order->name }}</td>
                    <td>{{ $order->deadline }}</td>
                    <td>{{ $order->phone }}</td>
                    <td>{{ $order->quantity }}</td>
                    <td>{{ $order->status }}</td>
                    <td>{{ $order->sizeModel->name ?? '-' }}</td>
                    <td>{{ $order->user->name ?? '-' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
