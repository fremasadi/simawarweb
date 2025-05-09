<?php

namespace App\Filament\Resources\SalaryResource\Pages;

use App\Filament\Resources\SalaryResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Filament\Notifications\Notification;
use Carbon\Carbon;

class EditSalary extends EditRecord
{
    protected static string $resource = SalaryResource::class;
    
    // Ini akan dipanggil ketika record disimpan
    protected function afterSave(): void
    {
        // Jika base_salary berubah, beri notifikasi
        if ($this->record->wasChanged('base_salary')) {
            Notification::make()
                ->title('Gaji Pokok Diperbarui')
                ->body('Gaji pokok telah diubah dan total gaji telah dihitung ulang.')
                ->success()
                ->send();
        }
    }
    
    // Cek apakah pembayaran sudah lewat tanggalnya
    protected function isPaymentPassed(): bool
    {
        return Carbon::parse($this->record->pay_date)->startOfDay()->lt(now()->startOfDay());
    }
    
    // Cek apakah pembayaran sudah dibayar
    protected function isPaid(): bool
    {
        return $this->record->status === 'paid';
    }
    
    // Override form untuk menonaktifkan field pada kondisi tertentu
    protected function getFormSchema(): array
    {
        $schema = parent::getFormSchema();
        
        // Jika pembayaran sudah lewat tanggalnya atau sudah dibayar
        if ($this->isPaymentPassed() || $this->isPaid()) {
            // Cari field base_salary dan nonaktifkan
            foreach ($schema as $key => $field) {
                if ($field->getName() === 'base_salary') {
                    $schema[$key] = $field->disabled();
                    break;
                }
            }
        }
        
        return $schema;
    }
}