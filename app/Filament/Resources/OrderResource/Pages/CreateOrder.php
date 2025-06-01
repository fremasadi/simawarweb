<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Http;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;

class CreateOrder extends CreateRecord
{
    protected static string $resource = OrderResource::class;
    public bool $isPreviewMode = false;

    protected function afterCreate(): void
    {
        $record = $this->record;

        $phone = $record->phone;
        $message = "Terima kasih telah memesan di Rumah Jahit Mawar! Pesanan Anda akan segera kami proses.";

        try {
            $response = Http::withHeaders([
                'Authorization' => 'R5uHqhjeppTQbDefuzxY',
            ])->post('https://api.fonnte.com/send', [
                'target' => $phone,
                'message' => $message,
                'countryCode' => '62',
            ]);

            if ($response->failed()) {
                logger()->error('Gagal kirim WA: ' . $response->body());
            }
        } catch (\Exception $e) {
            logger()->error('Error kirim WA: ' . $e->getMessage());
        }
    }

    public function togglePreview()
    {
        try {
            // Validasi form sebelum preview
            $this->form->validate();
            
            // Toggle preview mode
            $this->isPreviewMode = !$this->isPreviewMode;
            
            // Notification
            if ($this->isPreviewMode) {
                Notification::make()
                    ->success()
                    ->title('Mode Preview diaktifkan')
                    ->body('Periksa data sebelum menyimpan.')
                    ->send();
            } else {
                Notification::make()
                    ->info()
                    ->title('Kembali ke mode Edit')
                    ->send();
            }
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Jika validasi gagal, tampilkan error
            Notification::make()
                ->danger()
                ->title('Error Validasi')
                ->body('Mohon lengkapi semua field yang wajib diisi terlebih dahulu.')
                ->send();
            throw $e;
        }
    }

    /**
     * Get form data untuk preview dengan real-time data
     */
    public function getFormData(): array
    {
        // Ambil data dari livewire component state
        $data = $this->data ?? [];
        
        // Debug log
        if (app()->environment('local')) {
            logger('Current Form Data:', $data);
        }
        
        return $data;
    }

    /**
     * Render preview content dengan HTML sederhana
     */
    public function getPreviewHtml(): string
    {
        if (!$this->isPreviewMode) {
            return '';
        }

        $data = $this->getFormData();
        
        $html = '<div class="bg-white border rounded-lg p-6 space-y-4">';
        $html .= '<h3 class="text-lg font-bold text-gray-900 border-b pb-2">Preview Order</h3>';
        
        // Customer Info
        $html .= '<div class="grid grid-cols-2 gap-4">';
        $html .= '<div>';
        $html .= '<h4 class="font-semibold text-gray-800 mb-2">Informasi Customer</h4>';
        $html .= '<p><strong>Nama:</strong> ' . ($data['name'] ?? '-') . '</p>';
        $html .= '<p><strong>Telepon:</strong> ' . ($data['phone'] ?? '-') . '</p>';
        $html .= '<p><strong>Alamat:</strong> ' . ($data['address'] ?? '-') . '</p>';
        $html .= '</div>';
        
        // Order Info
        $html .= '<div>';
        $html .= '<h4 class="font-semibold text-gray-800 mb-2">Detail Order</h4>';
        $html .= '<p><strong>Jumlah:</strong> ' . ($data['quantity'] ?? 1) . ' pcs</p>';
        $html .= '<p><strong>Harga:</strong> Rp ' . number_format($data['price'] ?? 0, 0, ',', '.') . '</p>';
        
        if (!empty($data['deadline'])) {
            $html .= '<p><strong>Deadline:</strong> ' . \Carbon\Carbon::parse($data['deadline'])->format('d/m/Y H:i') . '</p>';
        }
        
        if (!empty($data['description'])) {
            $html .= '<p><strong>Deskripsi:</strong> ' . $data['description'] . '</p>';
        }
        $html .= '</div>';
        $html .= '</div>';
        
        // Total
        $html .= '<div class="border-t pt-4">';
        $html .= '<div class="bg-gray-100 rounded p-3">';
        $html .= '<div class="flex justify-between items-center">';
        $html .= '<span class="font-semibold">Total:</span>';
        $html .= '<span class="text-xl font-bold text-green-600">Rp ' . number_format($data['price'] ?? 0, 0, ',', '.') . '</span>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
        
        $html .= '</div>';
        
        return $html;
    }

    /**
     * Method untuk kembali ke mode edit
     */
    public function backToEdit()
    {
        $this->isPreviewMode = false;
        Notification::make()
            ->info()
            ->title('Kembali ke mode Edit')
            ->send();
    }

    protected function getFormActions(): array
    {
        return [
            // Tombol Preview/Edit
            Action::make('preview')
                ->label($this->isPreviewMode ? 'Edit Data' : 'Preview Data')
                ->icon($this->isPreviewMode ? 'heroicon-o-pencil-square' : 'heroicon-o-eye')
                ->color($this->isPreviewMode ? 'warning' : 'info')
                ->action('togglePreview')
                ->visible(fn () => !$this->isPreviewMode)
                ->keyBindings(['ctrl+p', 'cmd+p']),
                
            // Tombol Kembali (hanya tampil saat preview)
            Action::make('back_to_edit')
                ->label('Kembali ke Edit')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->action('backToEdit')
                ->visible(fn () => $this->isPreviewMode)
                ->keyBindings(['esc']),

            // Tombol Simpan
            $this->getCreateFormAction()
                ->label($this->isPreviewMode ? 'Konfirmasi & Simpan' : 'Simpan Order')
                ->icon($this->isPreviewMode ? 'heroicon-o-check-circle' : 'heroicon-o-document-plus')
                ->color($this->isPreviewMode ? 'success' : 'primary')
                ->requiresConfirmation($this->isPreviewMode)
                ->modalHeading($this->isPreviewMode ? 'Konfirmasi Penyimpanan Order' : null)
                ->modalDescription($this->isPreviewMode ? 'Apakah Anda yakin semua data sudah benar dan siap untuk disimpan? Data yang disimpan tidak dapat diubah kembali.' : null)
                ->modalSubmitActionLabel($this->isPreviewMode ? 'Ya, Simpan Order' : null)
                ->modalIcon($this->isPreviewMode ? 'heroicon-o-check-circle' : null)
                ->keyBindings($this->isPreviewMode ? ['ctrl+s', 'cmd+s'] : null),
        ];
    }

    /**
     * Customize create another action (optional)
     */
    protected function getCreateAnotherFormAction(): Actions\Action
    {
        return parent::getCreateAnotherFormAction()
            ->visible(fn () => !$this->isPreviewMode)
            ->disabled(fn () => $this->isPreviewMode);
    }

    /**
     * Override mutateFormDataBeforeCreate untuk additional processing
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Reset preview mode setelah save
        $this->isPreviewMode = false;
        
        // Add any additional data processing here
        $data['created_by'] = auth()->id(); // contoh: track who created
        
        return parent::mutateFormDataBeforeCreate($data);
    }

    /**
     * Customize success notification
     */
    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Order berhasil dibuat!';
    }

    /**
     * Redirect after create
     */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    /**
     * Custom header actions (optional)
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('help')
                ->label('Panduan')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Panduan Membuat Order')
                ->modalDescription('
                    1. Isi semua data customer dan detail order
                    2. Klik tombol "Preview Data" untuk melihat ringkasan
                    3. Periksa semua informasi dengan teliti
                    4. Jika sudah benar, klik "Konfirmasi & Simpan"
                    
                    Shortcut:
                    - Ctrl/Cmd + P: Preview
                    - Esc: Kembali ke Edit (saat preview)
                    - Ctrl/Cmd + S: Simpan (saat preview)
                ')
                ->modalIcon('heroicon-o-information-circle')
                ->visible(fn () => !$this->isPreviewMode),
        ];
    }

    /**
     * Customize page title
     */
    public function getTitle(): string
    {
        return $this->isPreviewMode ? 'Preview Order Baru' : 'Buat Order Baru';
    }

    /**
     * Add custom CSS untuk preview mode
     */
    public function getExtraBodyAttributes(): array
    {
        return $this->isPreviewMode 
            ? ['class' => 'preview-mode'] 
            : [];
    }

    /**
     * Override form untuk menambahkan preview section
     */
    public function form(\Filament\Forms\Form $form): \Filament\Forms\Form
    {
        $baseForm = $this->getResource()::form($form);
        
        // Jika preview mode, tambahkan preview section
        if ($this->isPreviewMode) {
            $schema = $baseForm->getSchema();
            
            $schema[] = \Filament\Forms\Components\Section::make('Preview Order')
                ->description('Periksa kembali data order Anda sebelum menyimpan')
                ->schema([
                    \Filament\Forms\Components\Placeholder::make('preview')
                        ->content(fn () => new \Illuminate\Support\HtmlString($this->getPreviewHtml()))
                        ->columnSpanFull()
                ])
                ->columnSpanFull()
                ->collapsible(false);
                
            $baseForm->schema($schema);
        }
        
        return $baseForm;
    }
}