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
            // Simpan data form terlebih dahulu
            $this->data = $this->form->getState();
            
            // Validasi form sebelum preview
            $this->form->validate();
            
            // Toggle preview mode
            $this->isPreviewMode = !$this->isPreviewMode;
            
            // Refresh form untuk menampilkan/menyembunyikan preview section
            $this->form->fill($this->data);
            
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
     * Method untuk kembali ke mode edit
     */
    public function backToEdit()
    {
        $this->isPreviewMode = false;
        // Pastikan data tetap tersimpan
        $this->data = $this->form->getState();
        $this->form->fill($this->data);
        
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
        // Debug: Log data sebelum create
        logger('Data before create:', $data);
        
        // Reset preview mode setelah save
        $this->isPreviewMode = false;
        
        // Pastikan semua field required ada
        if (empty($data['name'])) {
            throw new \Exception('Nama pemesanan tidak boleh kosong');
        }
        
        if (empty($data['phone'])) {
            throw new \Exception('No. Telepon tidak boleh kosong');
        }
        
        if (empty($data['address'])) {
            throw new \Exception('Alamat tidak boleh kosong');
        }
        
        // Set default status jika belum ada
        if (!isset($data['status'])) {
            $data['status'] = 'dikerjakan';
        }
        
        // Pastikan size adalah array atau JSON string
        if (isset($data['size']) && is_array($data['size'])) {
            $data['size'] = json_encode($data['size']);
        } elseif (!isset($data['size'])) {
            $data['size'] = json_encode([]);
        }
        
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
     * Override create method untuk handling preview mode
     */
    public function create(bool $another = false): void
    {
        // Pastikan kita keluar dari preview mode
        $this->isPreviewMode = false;
        
        // Ambil data terbaru dari form
        $this->data = $this->form->getState();
        
        // Debug log
        logger('Final data before create:', $this->data);
        
        // Panggil parent create
        parent::create($another);
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
}