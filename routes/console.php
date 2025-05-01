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

Artisan::command('salary:calculate-deductions', function () {
    $currentMonth = Carbon::now()->format('Y-m');
    $previousMonth = Carbon::now()->subMonth()->format('Y-m');
    $this->info("Menghitung pengurangan gaji untuk periode: {$currentMonth}");

    // 1. Cari semua absensi yang perlu diproses
    $attendances = Attendance::with('user')
        ->whereIn('status', ['telat', 'tidak hadir'])
        ->where(function($query) use ($currentMonth, $previousMonth) {
            $query->whereRaw("DATE_FORMAT(date, '%Y-%m') = ?", [$currentMonth])
                  ->orWhereRaw("DATE_FORMAT(date, '%Y-%m') = ?", [$previousMonth]);
        })
        ->whereDoesntHave('salaryDeductionHistory', function($query) {
            $query->where('deduction_amount', '>', 0);
        })
        ->get();

    if ($attendances->isEmpty()) {
        $this->info("Tidak ada data absensi yang perlu dihitung potongannya.");
        return 0;
    }

    $this->info("Ditemukan ".$attendances->count()." data absensi yang perlu dihitung.");

    DB::beginTransaction();

    try {
        foreach ($attendances->groupBy('user_id') as $userId => $userAttendances) {
            $user = $userAttendances->first()->user;
            $this->info("\nMemproses karyawan: {$user->name} (ID: {$userId})");

            // 2. Cari data gaji yang sesuai
            $salary = Salary::where('user_id', $userId)
                ->where(function($query) use ($currentMonth, $previousMonth) {
                    $query->whereRaw("DATE_FORMAT(pay_date, '%Y-%m') = ?", [$currentMonth])
                          ->orWhereRaw("DATE_FORMAT(pay_date, '%Y-%m') = ?", [$previousMonth])
                          ->orWhere('pay_date', Carbon::createFromFormat('Y-m', $currentMonth)
                              ->addMonth()->startOfMonth()->format('Y-m-d'));
                })
                ->with('salarySetting')
                ->orderBy('pay_date', 'desc')
                ->first();

            if (!$salary) {
                $this->warn("Tidak ada data gaji untuk user ini.");
                continue;
            }

            if (!$salary->salarySetting) {
                $this->warn("Tidak ada pengaturan gaji untuk salary ID {$salary->id}.");
                continue;
            }

            $totalDeduction = $salary->total_deduction;
            $this->info("Potongan sebelumnya: Rp".number_format($totalDeduction, 2));

            // 3. Proses setiap absensi
            foreach ($userAttendances as $attendance) {
                $deductionAmount = $this->calculateDeduction($attendance, $salary->salarySetting);
                
                SalaryDeductionHistory::create([
                    'user_id' => $userId,
                    'salary_id' => $salary->id,
                    'attendance_id' => $attendance->id,
                    'deduction_type' => $attendance->status,
                    'late_minutes' => $attendance->late_minutes,
                    'deduction_amount' => $deductionAmount,
                    'deduction_date' => $attendance->date,
                    'note' => $this->generateDeductionNote($attendance)
                ]);

                $totalDeduction += $deductionAmount;
                $this->info("Potongan untuk absensi {$attendance->date}: Rp".number_format($deductionAmount, 2));
            }

            // 4. Update total gaji
            $newTotalSalary = max(0, $salary->salarySetting->salary - $totalDeduction);
            
            $salary->update([
                'total_deduction' => $totalDeduction,
                'total_salary' => $newTotalSalary,
                'status' => 'pending',
                'updated_at' => now()
            ]);

            $this->info("Total potongan baru: Rp".number_format($totalDeduction, 2));
            $this->info("Gaji bersih: Rp".number_format($newTotalSalary, 2));
        }

        DB::commit();
        $this->info("\nProses penghitungan pengurangan gaji selesai.");
        return 0;
    } catch (\Exception $e) {
        DB::rollBack();
        $this->error("Error: ".$e->getMessage());
        return 1;
    }
});

// Helper functions
 function calculateDeduction($attendance, $salarySetting) {
    if ($attendance->status === 'telat') {
        return $attendance->late_minutes * $salarySetting->deduction_per_minute;
    } elseif ($attendance->status === 'tidak hadir') {
        return $salarySetting->reduction_if_absent;
    }
    return 0;
}

 function generateDeductionNote($attendance) {
    return $attendance->status === 'telat' 
        ? "Terlambat {$attendance->late_minutes} menit" 
        : "Tidak hadir";
}

Artisan::command('salary:generate', function () {
    $this->info("Memulai proses generate gaji...");
    
    $users = User::where('role', 'karyawan')
        ->with(['salarySetting' => function($query) {
            $query->select('id', 'salary', 'periode');
        }])
        ->get();
    
    if ($users->isEmpty()) {
        $this->warn("Tidak ada karyawan yang ditemukan.");
        return 0;
    }
    
    $this->info("Ditemukan " . $users->count() . " karyawan.");

    $results = [
        'success' => 0,
        'skipped' => 0,
        'error' => 0
    ];

    foreach ($users as $user) {
        try {
            if (!$user->salarySetting) {
                $this->warn("User {$user->name} tidak memiliki pengaturan gaji.");
                $results['error']++;
                continue;
            }

            $periodData = $this->getPeriodData($user->salarySetting->periode);
            
            // Cek apakah gaji sudah ada
            if (Salary::where('user_id', $user->id)
                ->where('pay_date', $periodData['nextPayDate'])
                ->exists()) {
                $this->info("Gaji untuk {$user->name} periode {$periodData['periodLabel']} sudah ada.");
                $results['skipped']++;
                continue;
            }

            // Buat record gaji
            DB::transaction(function () use ($user, $periodData) {
                Salary::create([
                    'user_id' => $user->id,
                    'salary_setting_id' => $user->salarySetting->id,
                    'total_salary' => $user->salarySetting->salary,
                    'total_deduction' => 0,
                    'pay_date' => $periodData['nextPayDate'],
                    'status' => 'pending',
                    'note' => "Auto-generated untuk periode {$periodData['periodLabel']}"
                ]);
            });

            $this->info("Berhasil generate gaji untuk {$user->name} ({$periodData['periodLabel']})");
            $results['success']++;
        } catch (\Exception $e) {
            $this->error("Error untuk {$user->name}: " . $e->getMessage());
            $results['error']++;
        }
    }

    $this->info("\nRingkasan:");
    $this->info("Berhasil: {$results['success']}");
    $this->info("Dilewati: {$results['skipped']}");
    $this->info("Error: {$results['error']}");
    
    return 0;
})->purpose('Generate salary data automatically based on period');

// Fungsi helper untuk menentukan periode
 function getPeriodData($period) {
    $now = Carbon::now();
    
    switch ($period) {
        case 'daily':
            return [
                'periodStart' => $now->copy()->startOfDay(),
                'periodEnd' => $now->copy()->endOfDay(),
                'nextPayDate' => $now->copy()->startOfDay(),
                'periodLabel' => 'harian ' . $now->format('Y-m-d')
            ];
            
        case 'weekly':
            return [
                'periodStart' => $now->copy()->startOfWeek(),
                'periodEnd' => $now->copy()->endOfWeek(),
                'nextPayDate' => $now->copy()->startOfWeek(),
                'periodLabel' => 'mingguan ' . $now->startOfWeek()->format('Y-m-d') . ' hingga ' . $now->endOfWeek()->format('Y-m-d')
            ];
            
        case 'monthly':
            return [
                'periodStart' => $now->copy()->startOfMonth(),
                'periodEnd' => $now->copy()->endOfMonth(),
                'nextPayDate' => $now->copy()->addMonth()->startOfMonth(),
                'periodLabel' => 'bulanan ' . $now->format('Y-m')
            ];
            
        default:
            throw new \Exception("Periode gaji tidak valid: {$period}");
    }
}

// Perintah untuk mengisi absensi otomatis
Artisan::command('attendance:check', function () {
    $this->info("Memeriksa absensi karyawan...");
    
    // Ambil pengaturan toko
    $storeSetting = StoreSetting::first();

    if (!$storeSetting) {
        $this->warn("Tidak ada pengaturan toko yang ditemukan.");
        return 0;
    }

    // Ambil semua user dengan role karyawan
    $users = User::where('role', 'karyawan')->get();
    
    if ($users->isEmpty()) {
        $this->info("Tidak ada karyawan yang ditemukan.");
        return 0;
    }

    // Cek kemarin
    $yesterday = Carbon::yesterday();
    $yesterdayDate = $yesterday->format('Y-m-d');
    
    // Cek apakah toko buka kemarin (berdasarkan hari)
    $dayOfWeek = $yesterday->dayOfWeek; // 0 (Sunday) - 6 (Saturday)
    
    // Asumsikan toko buka dari Senin-Sabtu (1-6) dan tutup hari Minggu (0)
    // Anda bisa menyesuaikan logika ini berdasarkan model data Anda
    $isOpenYesterday = $dayOfWeek > 0 && $dayOfWeek < 7;
    
    // Tambahan: cek jika kemarin adalah hari libur dari tabel khusus (jika ada)
    // $isHoliday = Holiday::where('date', $yesterdayDate)->exists();
    // if ($isHoliday) {
    //     $isOpenYesterday = false;
    // }

    $this->info("Tanggal: {$yesterdayDate}, Hari: {$dayOfWeek}, Status toko buka: " . ($isOpenYesterday ? "Ya" : "Tidak"));

    // Jika toko buka kemarin
    if ($isOpenYesterday) {
        $processedCount = 0;
        $skippedCount = 0;
        
        foreach ($users as $user) {
            // Cek apakah sudah ada absensi untuk tanggal kemarin
            $existingAttendance = Attendance::where('user_id', $user->id)
                ->where('date', $yesterdayDate)
                ->first();

            // Jika belum ada absensi, buat absensi dengan status tidak hadir
            if (!$existingAttendance) {
                try {
                    Attendance::create([
                        'user_id' => $user->id,
                        'date' => $yesterdayDate,
                        'status' => 'tidak hadir',
                        'check_in' => null,
                        'check_out' => null,
                        'late_minutes' => null,
                        'note' => "Auto-generated karena tidak melakukan check-in"
                    ]);

                    $this->info("Menambahkan absensi 'tidak hadir' untuk {$user->name} pada tanggal {$yesterdayDate}");
                    $processedCount++;
                } catch (\Exception $e) {
                    $this->error("Error saat membuat absensi untuk {$user->name}: " . $e->getMessage());
                }
            } else {
                $this->info("Absensi untuk {$user->name} pada tanggal {$yesterdayDate} sudah ada. Status: {$existingAttendance->status}");
                $skippedCount++;
            }
        }
        
        $this->info("Pengecekan absensi selesai. Ditambahkan: {$processedCount}, Dilewati: {$skippedCount}");
    } else {
        $this->info("Kemarin toko tutup. Tidak perlu menambahkan absensi 'tidak hadir'.");
    }
    
    return 0;
})->purpose('Memeriksa dan mengisi absensi otomatis untuk karyawan yang tidak absen');

