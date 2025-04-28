<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Http;

class CreateOrder extends CreateRecord
{
    protected static string $resource = OrderResource::class;

    protected function afterCreate(): void
    {
        $record = $this->record;

        $phone = $record->phone;

        // ✅ Ubah 08xxxxx menjadi 628xxxxx
        if (substr($phone, 0, 1) === '0') {
            $phone = '62' . substr($phone, 1);
        }

        $message = "Terima kasih {$record->name} telah memesan! Pesanan Anda akan segera kami proses.";

        try {
            $response = Http::withHeaders([
                'Authorization' => 'YuHu739HMs1gtWXzD1X7', // API Key Fonnte kamu
            ])->post('https://api.fonnte.com/send', [
                'target' => $phone,
                'message' => $message,
                'countryCode' => '62',
            ]);

            if ($response->failed()) {
                logger()->error('Gagal kirim WhatsApp: ' . $response->body());
            }
        } catch (\Exception $e) {
            logger()->error('Error kirim WhatsApp: ' . $e->getMessage());
        }
    }
}
