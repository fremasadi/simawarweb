<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PermintaanIzin extends Model
{
    use HasFactory;

    // Tentukan tabel yang digunakan
    protected $table = 'permintaan_izins';

    // Tentukan kolom yang dapat diisi (mass assignment)
    protected $fillable = [
        'user_id',
        'tanggal_mulai',
        'tanggal_selesai',
        'jenis_izin',
        'alasan',
        'image',
        'status',
    ];

    // Relasi dengan model User
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected static function boot()
{
    parent::boot();

    // Validasi sebelum menyimpan data
    static::saving(function ($permintaanIzin) {
        if ($permintaanIzin->tanggal_mulai > $permintaanIzin->tanggal_selesai) {
            throw new \Exception('Tanggal mulai tidak boleh lebih besar dari tanggal selesai.');
        }
    });

    // Event ketika data di-update
    static::updated(function ($permintaanIzin) {
        if ($permintaanIzin->status == true) {
            $startDate = $permintaanIzin->tanggal_mulai;
            $endDate = $permintaanIzin->tanggal_selesai;

            while ($startDate <= $endDate) {
                Attendance::create([
                    'user_id' => $permintaanIzin->user_id,
                    'date' => $startDate,
                    'status' => 'izin',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $startDate = date('Y-m-d', strtotime($startDate . ' +1 day'));
            }
        }
    });
}
}