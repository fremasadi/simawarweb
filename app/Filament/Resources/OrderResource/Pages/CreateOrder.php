<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Http;
use Filament\Pages\Actions\Action;

class CreateOrder extends CreateRecord
{
    protected static string $resource = OrderResource::class;

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

    protected function getFormActions(): array
{
    return [
        Action::make('preview')
    ->label('Preview')
    ->color('secondary')
    ->action('previewOrder')
    ->icon('heroicon-m-eye'),

        $this->getCreateFormAction()
            ->label('Simpan'),
    ];
}


    public function previewOrder()
{
    $data = $this->form->getState();

    $this->dispatch('open-preview-modal', data: $data);
}


    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
