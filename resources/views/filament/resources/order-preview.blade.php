{{-- resources/views/filament/resources/order-preview.blade.php --}}
<div class="preview-container space-y-6 p-6 bg-white border rounded-lg shadow-sm">
    <div class="text-center border-b pb-4">
        <h2 class="text-2xl font-bold text-gray-900">Preview Order</h2>
        <p class="text-sm text-gray-600 mt-2">Pastikan semua data sudah benar sebelum menyimpan</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        {{-- Informasi Customer --}}
        <div class="space-y-4">
            <h3 class="text-lg font-semibold text-gray-800 border-b pb-2">Informasi Customer</h3>
            
            <div class="space-y-3">
                <div class="flex justify-between">
                    <span class="font-medium text-gray-600">Nama:</span>
                    <span class="text-gray-900">{{ $data['name'] ?? '-' }}</span>
                </div>
                
                <div class="flex justify-between">
                    <span class="font-medium text-gray-600">No. Telepon:</span>
                    <span class="text-gray-900">{{ $data['phone'] ?? '-' }}</span>
                </div>
                
                <div class="flex justify-between items-start">
                    <span class="font-medium text-gray-600">Alamat:</span>
                    <span class="text-gray-900 text-right max-w-xs">{{ $data['address'] ?? '-' }}</span>
                </div>
            </div>
        </div>

        {{-- Informasi Order --}}
        <div class="space-y-4">
            <h3 class="text-lg font-semibold text-gray-800 border-b pb-2">Detail Order</h3>
            
            <div class="space-y-3">
                <div class="flex justify-between">
                    <span class="font-medium text-gray-600">Jumlah:</span>
                    <span class="text-gray-900">{{ $data['quantity'] ?? 1 }} pcs</span>
                </div>
                
                <div class="flex justify-between">
                    <span class="font-medium text-gray-600">Harga:</span>
                    <span class="text-gray-900 font-semibold">Rp {{ number_format($data['price'] ?? 0, 0, ',', '.') }}</span>
                </div>
                
                <div class="flex justify-between">
                    <span class="font-medium text-gray-600">Deadline:</span>
                    <span class="text-gray-900">
                        @if(!empty($data['deadline']))
                            {{ \Carbon\Carbon::parse($data['deadline'])->format('d/m/Y H:i') }}
                        @else
                            -
                        @endif
                    </span>
                </div>
                
                @if(!empty($data['description']))
                <div class="flex justify-between items-start">
                    <span class="font-medium text-gray-600">Deskripsi:</span>
                    <span class="text-gray-900 text-right max-w-xs">{{ $data['description'] }}</span>
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Gambar/Foto --}}
    @if(!empty($data['images']) && is_array($data['images']) && count($data['images']) > 0)
    <div class="space-y-4">
        <h3 class="text-lg font-semibold text-gray-800 border-b pb-2">Gambar/Foto Model</h3>
        
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
            @foreach($data['images'] as $index => $image)
                @if(is_array($image) && isset($image['photo']) && !empty($image['photo']))
                    <div class="border rounded-lg p-3 bg-gray-50">
                        <img src="{{ Storage::disk('public')->url($image['photo']) }}" 
                             alt="Gambar {{ $index + 1 }}" 
                             class="w-full h-32 object-cover rounded border">
                        <p class="text-xs text-gray-600 text-center mt-2">Gambar {{ $index + 1 }}</p>
                    </div>
                @endif
            @endforeach
        </div>
    </div>
    @endif

    {{-- Accessories --}}
    @if(!empty($data['accessories_list']) && is_array($data['accessories_list']) && count($data['accessories_list']) > 0)
    <div class="space-y-4">
        <h3 class="text-lg font-semibold text-gray-800 border-b pb-2">Accessories</h3>
        
        <div class="space-y-2">
            @php
                try {
                    $accessories = \App\Models\Accessory::whereIn('id', $data['accessories_list'])->get();
                    $totalAccessoryPrice = $accessories->sum('price');
                } catch (\Exception $e) {
                    $accessories = collect();
                    $totalAccessoryPrice = 0;
                }
            @endphp
            
            @foreach($accessories as $accessory)
                <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                    <span class="text-gray-900">{{ $accessory->name }}</span>
                    <span class="text-gray-600 font-medium">Rp {{ number_format($accessory->price, 0, ',', '.') }}</span>
                </div>
            @endforeach
            
            @if($totalAccessoryPrice > 0)
                <div class="flex justify-between items-center p-3 bg-blue-50 rounded-lg border border-blue-200">
                    <span class="font-semibold text-blue-900">Total Accessories:</span>
                    <span class="font-bold text-blue-900">Rp {{ number_format($totalAccessoryPrice, 0, ',', '.') }}</span>
                </div>
            @endif
        </div>
    </div>
    @endif

    {{-- Ukuran --}}
    @if(!empty($data['size']) && is_array($data['size']) && count($data['size']) > 0)
    <div class="space-y-4">
        <h3 class="text-lg font-semibold text-gray-800 border-b pb-2">Ukuran</h3>
        
        <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
            @foreach($data['size'] as $key => $value)
                @if(!empty($value))
                    <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                        <span class="font-medium text-gray-700 capitalize">{{ str_replace('_', ' ', $key) }}:</span>
                        <span class="text-gray-900 font-semibold">{{ $value }}</span>
                    </div>
                @endif
            @endforeach
        </div>
    </div>
    @endif

    {{-- Ditugaskan Ke --}}
    @if(!empty($data['ditugaskan_ke']))
    <div class="space-y-4">
        <h3 class="text-lg font-semibold text-gray-800 border-b pb-2">Assignment</h3>
        
        <div class="p-4 bg-green-50 rounded-lg border border-green-200">
            @php
                try {
                    $user = \App\Models\User::find($data['ditugaskan_ke']);
                } catch (\Exception $e) {
                    $user = null;
                }
            @endphp
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 bg-green-500 rounded-full flex items-center justify-center">
                    <span class="text-white font-semibold text-sm">
                        {{ $user ? substr($user->name, 0, 2) : '??' }}
                    </span>
                </div>
                <div>
                    <p class="font-semibold text-green-900">{{ $user->name ?? 'User tidak ditemukan' }}</p>
                    <p class="text-sm text-green-700">Karyawan</p>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Total Summary --}}
    <div class="border-t pt-4">
        <div class="bg-gray-100 rounded-lg p-4">
            @php
                $basePrice = $data['price'] ?? 0;
                $accessoryPrice = $totalAccessoryPrice ?? 0;
                $totalPrice = $basePrice + $accessoryPrice;
            @endphp
            <div class="flex justify-between items-center">
                <span class="text-lg font-semibold text-gray-800">Total Keseluruhan:</span>
                <span class="text-2xl font-bold text-green-600">
                    Rp {{ number_format($totalPrice, 0, ',', '.') }}
                </span>
            </div>
            @if($accessoryPrice > 0)
                <div class="mt-2 text-sm text-gray-600">
                    <span>Harga Dasar: Rp {{ number_format($basePrice, 0, ',', '.') }}</span>
                    <span class="mx-2">+</span>
                    <span>Accessories: Rp {{ number_format($accessoryPrice, 0, ',', '.') }}</span>
                </div>
            @endif
        </div>
    </div>
</div>

<style>
.preview-container {
    max-height: 70vh;
    overflow-y: auto;
}

.preview-container::-webkit-scrollbar {
    width: 6px;
}

.preview-container::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 3px;
}

.preview-container::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 3px;
}

.preview-container::-webkit-scrollbar-thumb:hover {
    background: #a1a1a1;
}
</style>