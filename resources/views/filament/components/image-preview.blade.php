{{-- resources/views/filament/components/image-preview.blade.php --}}

@if($imageUrl)
<div x-data="{ showModal: false }" class="mt-2">
    <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg border">
        <img src="{{ $imageUrl }}" 
             alt="{{ $imageName }}" 
             class="w-20 h-20 object-cover rounded border cursor-pointer hover:opacity-80 transition-opacity shadow-sm" 
             @click="showModal = true">
        <div class="flex flex-col gap-2">
            <p class="text-sm font-medium text-gray-700">{{ $imageName }}</p>
            <button type="button" 
                    @click="showModal = true"
                    class="inline-flex items-center px-3 py-1.5 text-xs font-medium bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-1">
                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7"></path>
                </svg>
                Lihat Lebih Besar
            </button>
        </div>
    </div>
    
    <!-- Modal -->
    <div x-show="showModal" 
         x-cloak
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click="showModal = false"
         @keydown.escape.window="showModal = false"
         class="fixed inset-0 bg-black/75 z-50 flex items-center justify-center p-4"
         style="z-index: 9999;">
        
        <div @click.stop 
             x-show="showModal"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95"
             class="relative max-w-4xl max-h-full bg-white rounded-lg shadow-2xl overflow-hidden">
            
            <!-- Header -->
            <div class="flex items-center justify-between p-4 border-b bg-white">
                <h3 class="text-lg font-semibold text-gray-900">{{ $imageName }}</h3>
                <button @click="showModal = false"
                        class="rounded-full p-1 text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            <!-- Image Container -->
            <div class="p-4 bg-gray-50">
                <img src="{{ $imageUrl }}" 
                     alt="{{ $imageName }}" 
                     class="max-w-full max-h-[70vh] mx-auto object-contain rounded-lg shadow-sm">
            </div>
            
            <!-- Footer Actions -->
            <div class="flex items-center justify-center gap-3 p-4 bg-white border-t">
                <a href="{{ $imageUrl }}" 
                   target="_blank" 
                   class="inline-flex items-center px-4 py-2 text-sm font-medium bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                    </svg>
                    Buka di Tab Baru
                </a>
                <button @click="navigator.clipboard.writeText('{{ $imageUrl }}').then(() => alert('URL berhasil disalin!'))"
                        class="inline-flex items-center px-4 py-2 text-sm font-medium bg-gray-600 text-white rounded-md hover:bg-gray-700 transition-colors">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                    </svg>
                    Salin URL
                </button>
            </div>
        </div>
    </div>
</div>
@endif