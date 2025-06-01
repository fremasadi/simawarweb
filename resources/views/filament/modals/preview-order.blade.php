<div class="space-y-4">
    <div>
        <strong>Nama:</strong> {{ $data['name'] ?? '-' }}
    </div>
    <div>
        <strong>No. HP:</strong> {{ $data['phone'] ?? '-' }}
    </div>
    <div>
        <strong>Alamat:</strong> {{ $data['address'] ?? '-' }}
    </div>
    <div>
        <strong>Harga:</strong> Rp {{ number_format($data['price'] ?? 0, 0, ',', '.') }}
    </div>
    <!-- Tambah lainnya sesuai kebutuhan -->
</div>
