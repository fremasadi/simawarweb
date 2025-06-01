<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Http;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Actions\Modal\Actions\ModalAction;
use Filament\Actions\Modal\Actions\CloseAction;

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
                $this->notify('success', 'Mode Preview diaktifkan. Periksa data sebelum menyimpan.');
            } else {
                $this->notify('info', 'Kembali ke mode Edit.');
            }
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Jika validasi gagal, tampilkan error
            $this->notify('error', 'Mohon lengkapi semua field yang wajib diisi terlebih dahulu.');
            throw $e;
        }
    }

    /**
     * Method untuk kembali ke mode edit
     */
    public function backToEdit()
    {
        $this->isPreviewMode = false;
        $this->notify('info', 'Kembali ke mode Edit.');
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
    protected function getCreateAnotherFormAction(): Action
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
}
