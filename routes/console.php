<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Models\User;
use App\Models\Salary;
use App\Models\SalarySetting;
use App\Models\StoreSetting;
use App\Models\Attendance;
use App\Models\SalaryDeductionHistories;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Schedule;


/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

// Daftarkan perintah
// Perintah untuk menghitung pengurangan gaji
Artisan::command('salary:calculate-deductions', function () {
    // Ambil semua data attendances
    $attendances = Attendance::whereIn('status', ['telat', 'tidak hadir'])->get();

    // Group attendances by user_id
    $groupedAttendances = $attendances->groupBy('user_id');

    // Mulai transaksi database
    DB::beginTransaction();

    try {
        foreach ($groupedAttendances as $userId => $userAttendances) {
            // Ambil data salary berdasarkan user_id
            $salary = Salary::where('user_id', $userId)->first();

            if ($salary) {
                // Ambil pengaturan gaji berdasarkan salary_setting_id
                $salarySetting = SalarySetting::find($salary->salary_setting_id);

                if ($salarySetting) {
                    // Inisialisasi total pengurangan
                    $totalDeduction = 0;

                    foreach ($userAttendances as $attendance) {
                        $deductionAmount = 0;
                        $deductionType = $attendance->status;
                        $lateMinutes = null;

                        // Hitung pengurangan berdasarkan keterlambatan
                        if ($attendance->status === 'telat' && $attendance->late_minutes > 0) {
                            $lateMinutes = $attendance->late_minutes;
                            $deductionAmount = $lateMinutes * $salarySetting->deduction_per_minute;
                            $totalDeduction += $deductionAmount;
                        }

                        // Hitung pengurangan jika tidak hadir
                        if ($attendance->status === 'tidak hadir') {
                            $deductionAmount = $salarySetting->reduction_if_absent;
                            $totalDeduction += $deductionAmount;
                        }

                        // Simpan riwayat potongan untuk setiap attendance
                        SalaryDeductionHistories::create([
                            'user_id' => $userId,
                            'salary_id' => $salary->id,
                            'attendance_id' => $attendance->id,
                            'deduction_type' => $deductionType,
                            'late_minutes' => $lateMinutes,
                            'deduction_amount' => $deductionAmount,
                            'deduction_per_minute' => $attendance->status === 'telat' ? $salarySetting->deduction_per_minute : null,
                            'reduction_if_absent' => $attendance->status === 'tidak hadir' ? $salarySetting->reduction_if_absent : null,
                            'deduction_date' => Carbon::now()->toDateString(),
                            'note' => "Potongan karena " . ($attendance->status === 'telat' ? "keterlambatan {$lateMinutes} menit" : "tidak hadir"),
                        ]);
                    }

                    // Update data salary
                    $salary->update([
                        'total_deduction' => $totalDeduction,
                        'total_salary' => $salarySetting->salary - $totalDeduction,
                        'status' => 'pending', // Atau status lainnya
                        'note' => 'Auto-generated salary with deductions',
                    ]);

                    // Debugging: Tampilkan nilai yang dihitung
                    $this->info("User ID: {$userId}");
                    $this->info("Total Deduction: {$totalDeduction}");
                    $this->info("Updated Total Salary: {$salary->total_salary}");
                    $this->info("Riwayat potongan berhasil disimpan");
                } else {
                    $this->warn("Tidak ada pengaturan gaji untuk salary_setting_id {$salary->salary_setting_id}.");
                }
            } else {
                $this->warn("Tidak ada data gaji untuk user_id {$userId}.");
            }
        }

        // Commit transaksi jika semua proses berhasil
        DB::commit();
        $this->info("Proses penghitungan pengurangan gaji selesai.");
        return 0;
    } catch (\Exception $e) {
        // Rollback transaksi jika terjadi kesalahan
        DB::rollBack();
        $this->error("Terjadi kesalahan: " . $e->getMessage());
        return 1;
    }
})->purpose('Hitung pengurangan gaji berdasarkan data absensi, perbarui data gaji, dan simpan riwayat potongan');

// Jadwalkan perintah
Schedule::command('salary:calculate-deductions')->monthlyOn('last day of this month', '23:59');

Artisan::command('salary:generate', function () {
    // Ambil semua user dengan role "karyawan"
    $users = User::where('role', 'karyawan')->get();

    foreach ($users as $user) {
        // Coba ambil salary_setting dari user
        $salarySetting = SalarySetting::find($user->salary_setting_id);

        // Jika tidak ditemukan, ambil salary_setting pertama sebagai default
        if (!$salarySetting) {
            $salarySetting = SalarySetting::first();

            if (!$salarySetting) {
                $this->warn("User {$user->name} tidak memiliki Salary Setting dan tidak ada Salary Setting default.");
                continue;
            }

            $this->info("User {$user->name} tidak memiliki Salary Setting. Menggunakan Salary Setting default.");
        }

        // Cek apakah sudah ada gaji untuk periode ini
        $lastSalary = Salary::where('user_id', $user->id)
            ->where('salary_setting_id', $salarySetting->id)
            ->orderBy('pay_date', 'desc')
            ->first();

        $shouldGenerate = false;
        $nextPayDate = null;

        if ($salarySetting->periode === 'daily') {
            $nextPayDate = Carbon::now()->startOfDay();
            $shouldGenerate = !$lastSalary || Carbon::parse($lastSalary->pay_date)->lt($nextPayDate);
        } elseif ($salarySetting->periode === 'weekly') {
            $nextPayDate = Carbon::now()->startOfWeek();
            $shouldGenerate = !$lastSalary || Carbon::parse($lastSalary->pay_date)->lt($nextPayDate);
        } elseif ($salarySetting->periode === 'monthly') {
            // Untuk periode bulanan, paydate adalah tanggal 1 bulan depan
            $nextPayDate = Carbon::now()->addMonth()->startOfMonth();

            // Cek jika sudah ada salary untuk bulan depan
            $shouldGenerate = !$lastSalary || Carbon::parse($lastSalary->pay_date)->lt($nextPayDate);
        }

        if ($shouldGenerate) {
            DB::transaction(function () use ($user, $salarySetting, $nextPayDate) {
                Salary::create([
                    'user_id' => $user->id,
                    'salary_setting_id' => $salarySetting->id,
                    'total_salary' => $salarySetting->salary,
                    'total_deduction' => 0,
                    'pay_date' => $nextPayDate,
                    'status' => 'pending',
                    'note' => ''
                ]);
            });

            $this->info("Salary generated for {$user->name} for date {$nextPayDate->format('Y-m-d')}.");
        } else {
            $this->info("Salary for {$user->name} already exists for the current period.");
        }
    }

    return 0;
})->purpose('Generate salary data automatically based on period');

// Perintah untuk mengisi absensi otomatis
Artisan::command('attendance:check', function () {
    // Ambil pengaturan toko
    $storeSetting = StoreSetting::first();

    if (!$storeSetting) {
        $this->warn("Tidak ada pengaturan toko yang ditemukan.");
        return 0;
    }

    // Ambil semua user dengan role karyawan
    $users = User::where('role', 'karyawan')->get();

    // Cek kemarin, jika toko buka
    $yesterday = Carbon::yesterday()->format('Y-m-d');

    // Jika toko buka kemarin
    if ($storeSetting->is_open) {
        foreach ($users as $user) {
            // Cek apakah sudah ada absensi untuk tanggal kemarin
            $existingAttendance = Attendance::where('user_id', $user->id)
                ->where('date', $yesterday)
                ->first();

            // Jika belum ada absensi, buat absensi dengan status tidak hadir
            if (!$existingAttendance) {
                Attendance::create([
                    'user_id' => $user->id,
                    'date' => $yesterday,
                    'status' => 'tidak hadir',
                    'check_in' => null,
                    'late_minutes' => null
                ]);

                $this->info("Menambahkan absensi 'tidak hadir' untuk {$user->name} pada tanggal {$yesterday}");
            }
        }
    }

    $this->info("Pengecekan absensi selesai.");
    return 0;
})->purpose('Memeriksa dan mengisi absensi otomatis untuk karyawan yang tidak absen');
