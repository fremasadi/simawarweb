<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Salary extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'salary_setting_id',
        'base_salary',       // Menambahkan base_salary
        'total_salary',
        'total_deduction',
        'status',
        'note',
        'pay_date'
    ];

    protected $casts = [
        'pay_date' => 'date',
        'base_salary' => 'decimal:2',
        'total_salary' => 'decimal:2',
        'total_deduction' => 'decimal:2',
    ];

    /**
     * Mendapatkan user pemilik gaji
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Mendapatkan setting gaji
     */
    public function salarySetting(): BelongsTo
    {
        return $this->belongsTo(SalarySetting::class);
    }

    /**
     * Menghitung ulang total gaji berdasarkan base_salary dikurangi potongan
     */
    public function recalculateTotalSalary(): void
    {
        // Gunakan base_salary jika tersedia, jika tidak gunakan dari salary setting
        $baseSalary = $this->base_salary ?? $this->salarySetting->salary;
        $this->total_salary = $baseSalary - ($this->total_deduction ?? 0);
    }
    
    /**
     * Hook sebelum menyimpan untuk memastikan total_salary dihitung dengan benar
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($salary) {
            // Saat membuat record baru, ambil salary dari setting dan simpan ke base_salary
            if (empty($salary->base_salary) && $salary->salarySetting) {
                $salary->base_salary = $salary->salarySetting->salary;
            }
            
            // Hitung total_salary
            $salary->recalculateTotalSalary();
        });

        static::saving(function ($salary) {
            // Update total_salary jika total_deduction atau base_salary diubah
            if ($salary->isDirty('total_deduction') || $salary->isDirty('base_salary')) {
                $salary->recalculateTotalSalary();
            }
        });
    }
}