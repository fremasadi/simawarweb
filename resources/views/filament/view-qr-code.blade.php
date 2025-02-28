<div class="text-center">
    <h2 class="text-xl font-bold mb-4">{{ $record->title }}</h2>
    <div class="flex justify-center">
        {!! QrCode::size(400)->format('svg')->generate($timestamp) !!}
    </div>
    <p class="mt-4"></p>
    <p class="mt-2 text-xs text-gray-500">Scan QR Code ini dengan aplikasi karyawan untuk absensi</p>
</div>  