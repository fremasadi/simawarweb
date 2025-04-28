<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Http;

class CreateOrder extends CreateRecord
{
    protected static string $resource = OrderResource::class;

    protected function afterCreate(): void
    {
        $record = $this->record;

        $phone = $record->phone;
        $message = "Terima kasih {$record->name} telah memesan! Pesanan Anda akan kami proses.";

        try {
            $response = Http::withHeaders([
                'Authorization' => 'YuHu739HMs1gtWXzD1X7',
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
}
