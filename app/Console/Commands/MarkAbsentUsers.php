<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Attendance;
use App\Models\User;
use App\Models\StoreSetting;
use Carbon\Carbon;

class MarkAbsentUsers extends Command
{
    protected $signature = 'attendance:mark-absent';
    protected $description = 'Menandai pengguna yang tidak melakukan absensi sebagai "tidak hadir" setelah close_time.';

    public function handle()
    {
        $today = Carbon::now()->toDateString();
        $storeSetting = StoreSetting::first();

        if (!$storeSetting) {
            $this->error('Store settings not found!');
            return;
        }

        $closeTime = Carbon::today()->setTimeFromTimeString($storeSetting->close_time);
        $currentTime = Carbon::now();

        // Hanya eksekusi jika sudah melewati close_time
        if ($currentTime->lessThan($closeTime)) {
            return;
        }

        // Ambil semua user yang belum absen hari ini
        $users = User::whereDoesntHave('attendances', function ($query) use ($today) {
            $query->where('date', $today);
        })->get();

        foreach ($users as $user) {
            Attendance::create([
                'user_id'       => $user->id,
                'date'          => $today,
                'check_in'      => null,
                'status'        => 'tidak hadir',
                'late_minutes'  => null,
            ]);
        }

        $this->info('Semua pengguna yang belum absen telah ditandai sebagai "tidak hadir".');
    }
}
