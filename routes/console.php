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

// Perintah untuk menghitung ulang pengurangan gaji dari riwayat potongan
Artisan::command('salary:recalculate-from-history', function () {
    // Dapatkan bulan dan tahun saat ini untuk filter
    $currentMonth = Carbon::now()->format('Y-m');
    $this->info("Menghitung ulang gaji dari riwayat potongan untuk periode: {$currentMonth}");
    
    // Ambil tanggal 1 bulan berikutnya (untuk gaji yang dibayar di awal bulan depan)
    $nextMonthFirstDay = Carbon::createFromFormat('Y-m', $currentMonth)
                        ->addMonth()
                        ->startOfMonth()
                        ->format('Y-m-d');
    
    // Ambil semua salary untuk bulan ini atau yang dibayarkan awal bulan depan
    $salaries = Salary::where(function($query) use ($currentMonth, $nextMonthFirstDay) {
            $query->whereRaw("DATE_FORMAT(pay_date, '%Y-%m') = ?", [$currentMonth])
                  ->orWhere('pay_date', $nextMonthFirstDay);
        })
        ->get();
    
    if ($salaries->isEmpty()) {
        $this->info("Tidak ada data gaji yang perlu dihitung ulang.");
        return 0;
    }

    $this->info("Ditemukan " . $salaries->count() . " data gaji yang perlu dihitung ulang.");
    
    // Debug: tampilkan semua ID gaji yang akan diproses
    $this->info("Data gaji yang akan diproses: " . json_encode($salaries->pluck('id', 'user_id')));

    // Mulai transaksi database
    DB::beginTransaction();

    try {
        foreach ($salaries as $salary) {
            $userId = $salary->user_id;
            $salaryId = $salary->id;
            
            // Debug: tampilkan informasi gaji yang sedang diproses
            $this->info("Memproses gaji ID: {$salaryId}, user ID: {$userId}, pay_date: {$salary->pay_date}");
            
            // Ambil pengaturan gaji
            $salarySetting = SalarySetting::find($salary->salary_setting_id);
            
            if (!$salarySetting) {
                $this->warn("Tidak ada pengaturan gaji untuk salary_setting_id {$salary->salary_setting_id}. Lewati perhitungan.");
                continue;
            }
            
            // Ambil semua riwayat potongan untuk gaji ini
            $deductionHistories = SalaryDeductionHistories::where('salary_id', $salaryId)->get();
            
            if ($deductionHistories->isEmpty()) {
                $this->info("Tidak ada riwayat potongan untuk gaji ID {$salaryId}. Gaji tetap sama.");
                continue;
            }
            
            $this->info("Ditemukan " . $deductionHistories->count() . " riwayat potongan untuk gaji ID {$salaryId}");
            
            // Hitung total potongan dari history
            $totalDeduction = $deductionHistories->sum('deduction_amount');
            $this->info("Total potongan dihitung: {$totalDeduction}");
            
            // Hitung gaji bersih
            $baseSalary = $salarySetting->salary;
            $newTotalSalary = $baseSalary - $totalDeduction;
            
            // Pastikan total gaji tidak minus
            if ($newTotalSalary < 0) {
                $newTotalSalary = 0;
                $this->warn("Total gaji untuk user ID {$userId} kurang dari 0. Disetel ke 0.");
            }
            
            // Debug: tampilkan nilai sebelum update
            $this->info("Nilai sebelum update - total_deduction: {$salary->total_deduction}, total_salary: {$salary->total_salary}");
            $this->info("Nilai yang akan diupdate - total_deduction: {$totalDeduction}, total_salary: {$newTotalSalary}");
            
            // Update data salary dengan total pengurangan baru
            $updateResult = $salary->update([
                'total_deduction' => $totalDeduction,
                'total_salary' => $newTotalSalary,
                'note' => ($salary->note ?? '') . " | Total potongan diperbarui pada " . Carbon::now()->format('Y-m-d H:i:s'),
            ]);
            
            // Debug: cek hasil update
            $this->info("Hasil update salary: " . ($updateResult ? "Berhasil" : "Gagal"));
            
            // Verifikasi data setelah update
            $updatedSalary = Salary::find($salaryId);
            if (!$updatedSalary) {
                $this->error("Gagal mengambil data gaji setelah update!");
                continue;
            }
            
            $this->info("Data setelah update - total_deduction: {$updatedSalary->total_deduction}, total_salary: {$updatedSalary->total_salary}");
            
            // Check if data was actually updated
            if ($updatedSalary->total_deduction != $totalDeduction) {
                $this->warn("PERINGATAN: Total deduction tidak terupdate dengan benar!");
            }
            
            if ($updatedSalary->total_salary != $newTotalSalary) {
                $this->warn("PERINGATAN: Total salary tidak terupdate dengan benar!");
            }
            
            $this->info("Selesai memproses gaji ID: {$salaryId}");
        }

        // Commit transaksi jika semua proses berhasil
        DB::commit();
        $this->info("Proses penghitungan ulang gaji selesai.");
        return 0;
    } catch (\Exception $e) {
        // Rollback transaksi jika terjadi kesalahan
        DB::rollBack();
        $this->error("Terjadi kesalahan: " . $e->getMessage());
        $this->error("Trace: " . $e->getTraceAsString());
        return 1;
    }
})->purpose('Hitung ulang total gaji berdasarkan riwayat potongan yang sudah ada');

// Perintah untuk menghitung pengurangan gaji
Artisan::command('salary:calculate-deductions', function () {
    // Dapatkan bulan dan tahun saat ini untuk filter
    $currentMonth = Carbon::now()->format('Y-m');
    $this->info("Menghitung pengurangan gaji untuk periode: {$currentMonth}");
    
    // Ambil semua data attendances dengan filter bulan ini dan belum ada potongan
    $attendances = Attendance::whereIn('status', ['telat', 'tidak hadir'])
        ->whereRaw("DATE_FORMAT(date, '%Y-%m') = ?", [$currentMonth])
        ->whereNotExists(function ($query) {
            $query->select(DB::raw(1))
                  ->from('salary_deduction_histories')
                  ->whereRaw('salary_deduction_histories.attendance_id = attendances.id');
        })
        ->get();
        
    if ($attendances->isEmpty()) {
        $this->info("Tidak ada data absensi yang perlu dihitung potongannya.");
        return 0;
    }

    $this->info("Ditemukan " . $attendances->count() . " data absensi yang perlu dihitung.");
    $this->info("Data absensi yang akan diproses: " . json_encode($attendances->pluck('id', 'user_id')));

    // Group attendances by user_id
    $groupedAttendances = $attendances->groupBy('user_id');

    // Mulai transaksi database
    DB::beginTransaction();

    try {
        foreach ($groupedAttendances as $userId => $userAttendances) {
            // Debug: Tampilkan user_id yang sedang diproses
            $this->info("Memproses potongan untuk user ID: {$userId}");
            
            // Debugging: Tampilkan informasi pencarian salary
            $this->info("Mencari gaji untuk user ID {$userId} periode {$currentMonth}");
            $nextMonthFirstDay = Carbon::createFromFormat('Y-m', $currentMonth)
                                ->addMonth()
                                ->startOfMonth()
                                ->format('Y-m-d');
            $this->info("Atau dengan pay_date: {$nextMonthFirstDay}");
            
            // Ambil data salary dengan kondisi yang diperbaiki
            $salary = Salary::where('user_id', $userId)
                ->where(function($query) use ($currentMonth, $nextMonthFirstDay) {
                    // PERBAIKAN: Langsung gunakan variabel $nextMonthFirstDay yang sudah didefinisikan
                    $query->whereRaw("DATE_FORMAT(pay_date, '%Y-%m') = ?", [$currentMonth])
                          ->orWhere('pay_date', $nextMonthFirstDay);
                })
                ->first();
            
            // Debug: cek nilai salary yang ditemukan
            if (!$salary) {
                // PERBAIKAN: Coba cari semua data gaji user untuk debugging
                $allUserSalaries = Salary::where('user_id', $userId)->get();
                $this->warn("Tidak ada data gaji untuk user_id {$userId} pada periode {$currentMonth}.");
                $this->info("Semua data gaji user: " . json_encode($allUserSalaries->pluck('pay_date')));
                continue;
            }
            
            $this->info("Ditemukan data gaji dengan ID: {$salary->id}, pay_date: {$salary->pay_date}");

            // Ambil pengaturan gaji berdasarkan salary_setting_id
            $salarySetting = SalarySetting::find($salary->salary_setting_id);

            if (!$salarySetting) {
                $this->warn("Tidak ada pengaturan gaji untuk salary_setting_id {$salary->salary_setting_id}. Lewati perhitungan.");
                continue;
            }

            // Ambil total pengurangan yang sudah ada
            $existingDeduction = $salary->total_deduction ?? 0;
            $additionalDeduction = 0;

            foreach ($userAttendances as $attendance) {
                $deductionAmount = 0;
                $deductionType = $attendance->status;
                $lateMinutes = null;

                // Hitung pengurangan berdasarkan keterlambatan
                if ($attendance->status === 'telat' && $attendance->late_minutes > 0) {
                    $lateMinutes = $attendance->late_minutes;
                    $deductionAmount = $lateMinutes * $salarySetting->deduction_per_minute;
                    $additionalDeduction += $deductionAmount;
                }

                // Hitung pengurangan jika tidak hadir
                if ($attendance->status === 'tidak hadir') {
                    $deductionAmount = $salarySetting->reduction_if_absent;
                    $additionalDeduction += $deductionAmount;
                }

                // Simpan riwayat potongan untuk setiap attendance
                // PERBAIKAN: Tambahkan variable untuk menyimpan hasil pembuatan history
                $deductionHistory = SalaryDeductionHistories::create([
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

                // Debug: Cek ID history yang dibuat
                $this->info("History dibuat dengan ID: " . $deductionHistory->id);
                $this->info("Berhasil menambahkan potongan untuk attendance ID {$attendance->id}, user ID {$userId}, " .
                           "tanggal {$attendance->date}, status {$attendance->status}, " .
                           "jumlah potongan {$deductionAmount}");
            }

            // Update data salary dengan total pengurangan baru
            $totalDeduction = $existingDeduction + $additionalDeduction;
            $newTotalSalary = $salarySetting->salary - $totalDeduction;
            
            // Pastikan total gaji tidak minus
            if ($newTotalSalary < 0) {
                $newTotalSalary = 0;
                $this->warn("Total gaji untuk user ID {$userId} kurang dari 0. Disetel ke 0.");
            }

            // PERBAIKAN: Simpan hasil update ke variabel untuk memastikan berhasil
            $updateResult = $salary->update([
                'total_deduction' => $totalDeduction,
                'total_salary' => $newTotalSalary,
                'status' => 'pending', // Atau status lainnya
                'note' => $salary->note . " | Potongan diperbarui pada " . Carbon::now()->format('Y-m-d H:i:s'),
            ]);

            // Debug: cek hasil update
            $this->info("Hasil update salary: " . ($updateResult ? "Berhasil" : "Gagal"));
            
            // PERBAIKAN: Cek data setelah update
            $updatedSalary = Salary::find($salary->id);
            $this->info("Data salary setelah update: total_deduction={$updatedSalary->total_deduction}, total_salary={$updatedSalary->total_salary}");

            // Debugging: Tampilkan nilai yang dihitung
            $this->info("User ID: {$userId}");
            $this->info("Potongan sebelumnya: {$existingDeduction}");
            $this->info("Tambahan potongan: {$additionalDeduction}");
            $this->info("Total potongan baru: {$totalDeduction}");
            $this->info("Updated Total Salary: {$newTotalSalary}");
            $this->info("Riwayat potongan berhasil disimpan");
        }

        // Commit transaksi jika semua proses berhasil
        DB::commit();
        $this->info("Proses penghitungan pengurangan gaji selesai.");
        return 0;
    } catch (\Exception $e) {
        // Rollback transaksi jika terjadi kesalahan
        DB::rollBack();
        $this->error("Terjadi kesalahan: " . $e->getMessage());
        $this->error("Trace: " . $e->getTraceAsString());
        return 1;
    }
})->purpose('Hitung pengurangan gaji berdasarkan data absensi, perbarui data gaji, dan simpan riwayat potongan');
// Perintah untuk generate gaji karyawan
Artisan::command('salary:generate', function () {
    $this->info("Memulai proses generate gaji...");
    
    // Ambil semua user dengan role "karyawan"
    $users = User::where('role', 'karyawan')->get();
    
    if ($users->isEmpty()) {
        $this->warn("Tidak ada karyawan yang ditemukan.");
        return 0;
    }
    
    $this->info("Ditemukan " . $users->count() . " karyawan.");

    $successCount = 0;
    $skipCount = 0;
    $errorCount = 0;

    foreach ($users as $user) {
        try {
            // Coba ambil salary_setting dari user
            $salarySetting = null;
            
            if ($user->salary_setting_id) {
                $salarySetting = SalarySetting::find($user->salary_setting_id);
            }

            // Jika tidak ditemukan, ambil salary_setting pertama sebagai default
            if (!$salarySetting) {
                $salarySetting = SalarySetting::first();

                if (!$salarySetting) {
                    $this->warn("User {$user->name} tidak memiliki Salary Setting dan tidak ada Salary Setting default.");
                    $errorCount++;
                    continue;
                }

                $this->info("User {$user->name} tidak memiliki Salary Setting. Menggunakan Salary Setting default.");
            }

            // Tentukan periode gaji dan tanggal pembayaran berikutnya
            $now = Carbon::now();
            $payPeriodStart = null;
            $payPeriodEnd = null;
            $nextPayDate = null;

            if ($salarySetting->periode === 'daily') {
                $payPeriodStart = $now->copy()->startOfDay();
                $payPeriodEnd = $now->copy()->endOfDay();
                $nextPayDate = $now->copy()->startOfDay();
            } elseif ($salarySetting->periode === 'weekly') {
                $payPeriodStart = $now->copy()->startOfWeek();
                $payPeriodEnd = $now->copy()->endOfWeek();
                $nextPayDate = $now->copy()->startOfWeek();
            } elseif ($salarySetting->periode === 'monthly') {
                $payPeriodStart = $now->copy()->startOfMonth();
                $payPeriodEnd = $now->copy()->endOfMonth();
                // Untuk periode bulanan, paydate adalah tanggal 1 bulan depan
                $nextPayDate = $now->copy()->addMonth()->startOfMonth();
            }

            // Cek apakah sudah ada gaji untuk periode ini berdasarkan pay_date saja
            // Perubahan: Tidak menggunakan period_start dan period_end karena tidak ada di tabel
            $existingSalary = Salary::where('user_id', $user->id)
                ->where('pay_date', $nextPayDate)
                ->first();

            if ($existingSalary) {
                $this->info("Salary untuk {$user->name} sudah ada untuk periode saat ini ({$salarySetting->periode}). ID: {$existingSalary->id}");
                $skipCount++;
                continue;
            }

            // Buat record gaji baru
            DB::transaction(function () use ($user, $salarySetting, $nextPayDate, $payPeriodStart, $payPeriodEnd) {
                // Perubahan: Menghapus period_start dan period_end dari data yang disimpan
                $salary = Salary::create([
                    'user_id' => $user->id,
                    'salary_setting_id' => $salarySetting->id,
                    'total_salary' => $salarySetting->salary,
                    'total_deduction' => 0,
                    'pay_date' => $nextPayDate,
                    'status' => 'pending',
                    'note' => "Auto-generated untuk periode {$salarySetting->periode} dari " . 
                            $payPeriodStart->format('Y-m-d') . " hingga " . 
                            $payPeriodEnd->format('Y-m-d')
                ]);
            });

            $this->info("Salary generated for {$user->name} for period {$payPeriodStart->format('Y-m-d')} to {$payPeriodEnd->format('Y-m-d')}.");
            $successCount++;
        } catch (\Exception $e) {
            $this->error("Error generating salary for {$user->name}: " . $e->getMessage());
            $errorCount++;
        }
    }

    $this->info("Proses generate gaji selesai.");
    $this->info("Berhasil: {$successCount}, Dilewati: {$skipCount}, Error: {$errorCount}");
    return 0;
})->purpose('Generate salary data automatically based on period');

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

