<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalarySetting extends Model
{
    protected $table = 'salary_settings';

    protected $fillable = [
        'name',
        'salary',
        'periode',
        'reduction_if_absent',
        'permit_reduction',
        'deduction_per_minute'

    ];

    public function salaries()
{
    return $this->hasMany(Salary::class);
}
/**
     * Hook untuk update salary yang belum jatuh tempo jika terjadi perubahan salary pokok
     */
    protected static function boot()
    {
        parent::boot();

        static::updated(function ($salarySetting) {
            // Jika nilai salary berubah
            if ($salarySetting->isDirty('salary')) {
                // Update HANYA salary yang 
                // 1. masih pending (belum dibayar) DAN
                // 2. tanggal pembayaran belum lewat (hari ini atau masa depan)
                $salarySetting->salaries()
                    ->where('status', 'pending')
                    ->where('pay_date', '>=', now()->startOfDay())
                    ->each(function ($salary) use ($salarySetting) {
                        // Update base_salary ke nilai baru
                        $salary->base_salary = $salarySetting->salary;
                        
                        // Hitung ulang total_salary
                        $salary->total_salary = $salarySetting->salary - ($salary->total_deduction ?? 0);
                        
                        $salary->save();
                    });
            }
        });
    }
}
