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

// Perintah untuk debugging data absensi
Artisan::command('attendance:debug {month?} {year?}', function ($month = null, $year = null) {
    // Set periode yang akan diperiksa
    if ($month === null || $year === null) {
        $currentMonth = Carbon::now()->month;
        $currentYear = Carbon::now()->year;
    } else {
        $currentMonth = (int)$month;
        $currentYear = (int)$year;
    }
    
    $this->info("=== DEBUG DATA ABSENSI ===");
    $this->info("Periode: {$currentYear}-{$currentMonth}");
    
    // 1. Cek struktur tabel attendances
    $this->info("\n== STRUKTUR TABEL ==");
    try {
        $columns = Schema::getColumnListing('attendances');
        foreach ($columns as $column) {
            $type = DB::select("SHOW COLUMNS FROM attendances WHERE Field = '{$column}'")[0]->Type;
            $this->info("- {$column}: {$type}");
        }
    } catch (\Exception $e) {
        $this->error("Gagal mendapatkan struktur tabel: " . $e->getMessage());
    }
    
    // 2. Cek data absensi untuk bulan yang diberikan
    $this->info("\n== DATA ABSENSI PER TANGGAL ==");
    try {
        // Query database langsung untuk meminimalkan masalah
        $attendances = DB::select("
            SELECT id, user_id, date, status, late_minutes, check_in
            FROM attendances
            WHERE YEAR(date) = ? AND MONTH(date) = ?
            ORDER BY date, user_id
        ", [$currentYear, $currentMonth]);
        
        if (empty($attendances)) {
            $this->warn("Tidak ada data absensi untuk periode {$currentYear}-{$currentMonth}");
        } else {
            $this->info("Total data: " . count($attendances));
            $this->table(
                ['ID', 'User ID', 'Tanggal', 'Status', 'Late Minutes', 'Check In'],
                array_map(function($item) {
                    return [
                        $item->id,
                        $item->user_id,
                        $item->date,
                        $item->status,
                        $item->late_minutes,
                        $item->check_in
                    ];
                }, $attendances)
            );
        }
    } catch (\Exception $e) {
        $this->error("Gagal mendapatkan data absensi: " . $e->getMessage());
    }
    
    // 3. Cek jumlah data berdasarkan status
    $this->info("\n== JUMLAH DATA PER STATUS ==");
    try {
        $statusCounts = DB::select("
            SELECT status, COUNT(*) as jumlah
            FROM attendances
            WHERE YEAR(date) = ? AND MONTH(date) = ?
            GROUP BY status
        ", [$currentYear, $currentMonth]);
        
        if (empty($statusCounts)) {
            $this->warn("Tidak ada data untuk ditampilkan");
        } else {
            $this->table(
                ['Status', 'Jumlah'],
                array_map(function($item) {
                    return [$item->status, $item->jumlah];
                }, $statusCounts)
            );
        }
    } catch (\Exception $e) {
        $this->error("Gagal mendapatkan jumlah per status: " . $e->getMessage());
    }
    
    // 4. Periksa lagi query yang digunakan dalam calculate-deductions
    $this->info("\n== VERIFIKASI QUERY CALCULATE-DEDUCTIONS ==");
    try {
        $monthStr = $currentYear . '-' . str_pad($currentMonth, 2, '0', STR_PAD_LEFT);
        
        // Gunakan query mentah untuk menghindari masalah dengan Eloquent
        $matchingAttendances = DB::select("
            SELECT id, user_id, date, status, late_minutes
            FROM attendances
            WHERE status IN ('telat', 'tidak hadir')
            AND DATE_FORMAT(date, '%Y-%m') = ?
        ", [$monthStr]);
        
        if (empty($matchingAttendances)) {
            $this->warn("Tidak ada data yang cocok dengan filter salary:calculate-deductions");
        } else {
            $this->info("Data yang cocok dengan salary:calculate-deductions: " . count($matchingAttendances));
            $this->table(
                ['ID', 'User ID', 'Tanggal', 'Status', 'Late Minutes'],
                array_map(function($item) {
                    return [
                        $item->id,
                        $item->user_id,
                        $item->date,
                        $item->status,
                        $item->late_minutes
                    ];
                }, $matchingAttendances)
            );
        }
        
        // Cek juga formatted date untuk memastikan format benar
        $formattedDateCheck = DB::select("
            SELECT id, date, DATE_FORMAT(date, '%Y-%m') as formatted_date
            FROM attendances
            WHERE YEAR(date) = ? AND MONTH(date) = ?
            LIMIT 5
        ", [$currentYear, $currentMonth]);
        
        if (!empty($formattedDateCheck)) {
            $this->info("\nVerifikasi Format Tanggal:");
            $this->table(
                ['ID', 'Tanggal Asli', 'Format Y-m'],
                array_map(function($item) {
                    return [$item->id, $item->date, $item->formatted_date];
                }, $formattedDateCheck)
            );
        }
        
    } catch (\Exception $e) {
        $this->error("Gagal memverifikasi query: " . $e->getMessage());
    }
})->purpose('Debugging data absensi dan verifikasi query yang digunakan');

// Perintah untuk menghitung pengurangan gaji
Artisan::command('salary:calculate-deductions {month?} {year?} {--force : Paksa hitung ulang meskipun sudah diproses} {--debug : Tampilkan informasi debug}', function ($month = null, $year = null) {
    // Set periode yang akan dihitung
    if ($month === null || $year === null) {
        $currentMonth = Carbon::now()->format('Y-m');
    } else {
        $currentMonth = sprintf('%04d-%02d', $year, $month); 
    }
    
    $this->info("Menghitung pengurangan gaji untuk periode: {$currentMonth}");
    
    // Mode debug
    $debug = $this->option('debug');
    $force = $this->option('force');
    
    if ($debug) {
        $this->info("Mode DEBUG: aktif");
        $this->info("Mode FORCE: " . ($force ? "aktif" : "tidak aktif"));
    }
    
    // Ambil semua data attendances dengan filter bulan ini
    $query = Attendance::whereIn('status', ['telat', 'tidak hadir'])
        ->whereRaw("DATE_FORMAT(date, '%Y-%m') = ?", [$currentMonth]);
    
    // Jika tidak dalam mode force, tambahkan filter yang belum diproses
    if (!$force) {
        $query->whereNotExists(function ($subquery) {
            $subquery->select(DB::raw(1))
                   ->from('salary_deduction_histories')
                   ->whereRaw('salary_deduction_histories.attendance_id = attendances.id');
        });
    }
    
    // Get the attendances
    $attendances = $query->get();
    
    if ($debug) {
        // Tampilkan semua absensi untuk periode tersebut
        $allAttendances = Attendance::whereIn('status', ['telat', 'tidak hadir'])
            ->whereRaw("DATE_FORMAT(date, '%Y-%m') = ?", [$currentMonth])
            ->get();
            
        $this->info("Total data absensi dengan status telat/tidak hadir: " . $allAttendances->count());
        
        // Cek yang sudah punya potongan
        $alreadyProcessed = 0;
        foreach ($allAttendances as $attendance) {
            $hasDeduction = DB::table('salary_deduction_histories')
                ->where('attendance_id', $attendance->id)
                ->exists();
                
            if ($hasDeduction) {
                $alreadyProcessed++;
            }
        }
        
        $this->info("- Data yang sudah diproses: " . $alreadyProcessed);
        $this->info("- Data yang belum diproses: " . ($allAttendances->count() - $alreadyProcessed));
    }
        
    if ($attendances->isEmpty()) {
        $this->info("Tidak ada data absensi yang perlu dihitung potongannya.");
        return 0;
    }

    $this->info("Ditemukan " . $attendances->count() . " data absensi yang perlu dihitung.");

    // Group attendances by user_id
    $groupedAttendances = $attendances->groupBy('user_id');

    // Jika mode force, hapus riwayat potongan yang sudah ada
    if ($force) {
        $attendanceIds = $attendances->pluck('id')->toArray();
        if (!empty($attendanceIds)) {
            $deletedCount = DB::table('salary_deduction_histories')
                ->whereIn('attendance_id', $attendanceIds)
                ->delete();
            $this->info("Menghapus {$deletedCount} riwayat potongan yang sudah ada untuk perhitungan ulang.");
        }
    }

    // Mulai transaksi database
    DB::beginTransaction();

    try {
        foreach ($groupedAttendances as $userId => $userAttendances) {
            // Ambil data salary berdasarkan user_id untuk bulan ini
            $salary = Salary::where('user_id', $userId)
                ->where(function($query) use ($currentMonth) {
                    // 1. Pay date di bulan ini, atau
                    $query->whereRaw("DATE_FORMAT(pay_date, '%Y-%m') = ?", [$currentMonth])
                          // 2. Pay date di awal bulan berikutnya (untuk gaji bulanan)
                          ->orWhere(function($q) use ($currentMonth) {
                              // Ambil tanggal 1 bulan berikutnya
                              $nextMonthFirstDay = Carbon::createFromFormat('Y-m', $currentMonth)
                                                  ->addMonth()
                                                  ->startOfMonth()
                                                  ->format('Y-m-d');
                              $q->where('pay_date', $nextMonthFirstDay);
                          });
                })
                ->first();

            // Debugging: Tampilkan informasi pencarian salary
            if ($debug) {
                $this->info("-------------------------------------");
                $this->info("Mencari gaji untuk user ID {$userId} periode {$currentMonth}");
                $nextMonthFirstDay = Carbon::createFromFormat('Y-m', $currentMonth)
                                    ->addMonth()
                                    ->startOfMonth()
                                    ->format('Y-m-d');
                $this->info("Atau dengan pay_date: {$nextMonthFirstDay}");
                
                // Cek semua gaji user
                $allUserSalaries = Salary::where('user_id', $userId)->get();
                $this->info("Total data gaji untuk user ID {$userId}: " . $allUserSalaries->count());
                
                foreach ($allUserSalaries as $s) {
                    $this->info("- ID: {$s->id}, Pay Date: {$s->pay_date}, Status: {$s->status}");
                }
            }
            
            if (!$salary) {
                $this->warn("Tidak ada data gaji untuk user_id {$userId} pada periode {$currentMonth}. Lewati perhitungan.");
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

            $salary->update([
                'total_deduction' => $totalDeduction,
                'total_salary' => $newTotalSalary,
                'status' => 'pending', // Atau status lainnya
                'note' => $salary->note . " | Potongan diperbarui pada " . Carbon::now()->format('Y-m-d H:i:s'),
            ]);

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

