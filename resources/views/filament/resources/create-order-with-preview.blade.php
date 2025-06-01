{{-- resources/views/filament/resources/create-order-with-preview.blade.php --}}
<x-filament-panels::page>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Form Section --}}
        <div class="space-y-6">
            <x-filament-panels::form :wire:key="$this->getId() . '.forms.' . $this->getFormStatePath()" wire:submit="create">
                {{ $this->form }}
                
                <x-filament-actions::modals />
            </x-filament-panels::form>
        </div>

        {{-- Preview Section --}}
        <div class="space-y-6">
            @if($previewContent)
                {!! $previewContent !!}
            @else
                <div class="p-6 bg-gray-50 border rounded-lg text-center">
                    <div class="text-gray-400 mb-4">
                        <svg class="w-16 h-16 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                        </svg>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">Preview Mode</h3>
                    <p class="text-gray-600">Klik tombol "Preview Data" untuk melihat ringkasan order</p>
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>