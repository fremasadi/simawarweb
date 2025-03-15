<?php

namespace App\Observers;

use App\Models\PermintaanIzin;
use App\Models\Attendance;
use Carbon\Carbon;
class PermintaanIzinObserver
{
    /**
     * Handle the PermintaanIzin "created" event.
     */
    public function created(PermintaanIzin $permintaanIzin): void
    {
        //
    }

    /**
     * Handle the PermintaanIzin "updated" event.
     */
    public function updated(PermintaanIzin $permintaanIzin)
    {
        // Cek jika status berubah menjadi true
        if ($permintaanIzin->status == true && $permintaanIzin->isDirty('status')) {
            $startDate = Carbon::parse($permintaanIzin->tanggal_mulai);
            $endDate = Carbon::parse($permintaanIzin->tanggal_selesai);

            // Loop melalui setiap tanggal dalam rentang
            while ($startDate <= $endDate) {
                // Buat data attendance
                Attendance::create([
                    'user_id' => $permintaanIzin->user_id,
                    'date' => $startDate->toDateString(),
                    'status' => 'izin', // Status izin
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Tambahkan 1 hari ke tanggal
                $startDate->addDay();
            }
        }
    }

    /**
     * Handle the PermintaanIzin "deleted" event.
     */
    public function deleted(PermintaanIzin $permintaanIzin): void
    {
        //
    }

    /**
     * Handle the PermintaanIzin "restored" event.
     */
    public function restored(PermintaanIzin $permintaanIzin): void
    {
        //
    }

    /**
     * Handle the PermintaanIzin "force deleted" event.
     */
    public function forceDeleted(PermintaanIzin $permintaanIzin): void
    {
        //
    }
}
