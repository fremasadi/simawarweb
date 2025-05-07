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
use App\Models\Order;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\RegistrationToken;
use Kreait\Firebase\Messaging\FirebaseMessaging;
use Illuminate\Support\Facades\Notification;
use Kreait\Firebase\Messaging;

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
Artisan::command('check:firebase-setup', function () {
    $this->info('Checking Firebase setup...');
    
    // Check if the firebase config file exists
    if (file_exists(config_path('firebase.php'))) {
        $this->info('✓ Firebase config file exists');
    } else {
        $this->error('✗ Firebase config file is missing');
    }
    
    // Check if firebase services are registered
    try {
        $messaging = app('firebase.messaging');
        $this->info('✓ Firebase messaging service is registered');
    } catch (\Exception $e) {
        $this->error('✗ Firebase messaging service is not properly registered: ' . $e->getMessage());
    }
    
    // Check for credentials file
    $credentialsPath = config('firebase.credentials.file');
    if ($credentialsPath) {
        if (file_exists($credentialsPath)) {
            $this->info('✓ Firebase credentials file exists at: ' . $credentialsPath);
        } else {
            $this->error('✗ Firebase credentials file is missing at: ' . $credentialsPath);
        }
    } else {
        $this->warn('! Firebase credentials file path is not set in config');
    }
    
    $this->info('Firebase setup check completed');
});

Artisan::command('send:firebase-notification', function () {
    // Get the Firebase Messaging instance from the service container
    $messaging = app('firebase.messaging');

    // Untuk tracking waktu sekarang
    $now = now();
    $today = $now->format('Y-m-d');
    $tomorrow = $now->addDay()->format('Y-m-d');
    
    // Log untuk debugging
    $this->info("Looking for orders with deadlines on $today or $tomorrow");
    
    // Ambil semua order yang deadline-nya adalah hari ini atau besok
    $orders = Order::whereDate('deadline', $today)
                  ->orWhereDate('deadline', $tomorrow)
                  ->get();
                  
    $this->info("Found " . $orders->count() . " orders with upcoming deadlines");

    // Loop untuk setiap order
    foreach ($orders as $order) {
        // 1. Cek apakah notifikasi untuk order ini sudah dikirim hari ini
        $alreadySent = DB::table('notification_logs')
            ->where('order_id', $order->id)
            ->whereDate('created_at', now()->format('Y-m-d'))
            ->exists();
            
        if ($alreadySent) {
            $this->info("Notification for order #{$order->id} ({$order->name}) already sent today. Skipping.");
            continue;
        }
        
        // Ambil pengguna yang ditugaskan untuk order ini
        $assignedUser = $order->user; // Menggunakan relasi user() yang sudah didefinisikan
        
        if ($assignedUser && $assignedUser->fcm_tokens) {
            // Mengambil fcm_tokens dari pengguna
            $fcmTokens = $assignedUser->fcm_tokens; // Pastikan fcm_tokens adalah array
            $this->info("User " . $assignedUser->name . " has FCM tokens: " . json_encode($fcmTokens));
            
            // Cek apakah deadline hari ini atau besok
            $isToday = $order->deadline === $today;
            $title = $isToday ? 'Penting: Batas Waktu Pemesanan Hari Ini!' : 'Pengingat: Batas Waktu Pemesanan Besok';
            $body = $isToday 
                ? 'Order atas nama "' . $order->name . '" jatuh tempo hari ini!'
                : 'Order atas nama "' . $order->name . '" jatuh tempo besok!';
                
            $this->info("Preparing message with title: $title");
            
            $notificationSent = false;
            
            // Hanya mencoba mengirim ke satu token yang valid
            foreach ($fcmTokens as $token) {
                if ($notificationSent) {
                    break; // Keluar dari loop jika sudah berhasil mengirim
                }
                
                $message = CloudMessage::withTarget('token', $token)
                    ->withNotification([
                        'title' => $title,
                        'body' => $body
                    ]);

                // Mengirim pesan
                try {
                    $this->info('Attempting to send notification for order #' . $order->id . ' to token: ' . $token);
                    $result = $messaging->send($message);
                    
                    // Tandai bahwa notifikasi sudah terkirim
                    $notificationSent = true;
                    
                    // 2. Simpan log notifikasi ke database
                    DB::table('notification_logs')->insert([
                        'order_id' => $order->id,
                        'user_id' => $assignedUser->id,
                        'title' => $title,
                        'body' => $body,
                        'status' => 'sent',
                        'fcm_response' => json_encode($result),
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                    
                    $this->info('Notification sent to user with order: ' . $order->name);
                    $this->info('Firebase response: ' . json_encode($result));
                    
                    // Keluar dari loop setelah berhasil mengirim
                    break;
                    
                } catch (\Exception $e) {
                    $this->warn('Error sending notification to token: ' . $token);
                    $this->warn('Error message: ' . $e->getMessage());
                    
                    // Jika gagal, coba token berikutnya
                    continue;
                }
            }
            
            // Jika sudah mencoba semua token tapi gagal semua
            if (!$notificationSent) {
                $this->error('Failed to send notification to any token for order: ' . $order->name);
                
                // Tetap catat upaya pengiriman ke log
                DB::table('notification_logs')->insert([
                    'order_id' => $order->id,
                    'user_id' => $assignedUser->id,
                    'title' => $title,
                    'body' => $body,
                    'status' => 'failed',
                    'fcm_response' => json_encode(['error' => 'All tokens failed']),
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
        } else {
            $this->info('No FCM tokens found for user assigned to order: ' . $order->name);
        }
    }

    $this->info('Firebase notifications process completed!');
})->purpose('Mengirim notifikasi Firebase untuk order dengan deadline hari ini atau besok');

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

    // Group attendances by user_id
    $groupedAttendances = $attendances->groupBy('user_id');

    // Mulai transaksi database
    DB::beginTransaction();

    try {
        foreach ($groupedAttendances as $userId => $userAttendances) {
            // === FIX 1: Perbaikan logika pencarian salary untuk bulan ini ===
            // Logika: Cari gaji untuk periode bulan berjalan (yang biasanya dibayarkan di awal bulan berikutnya)
            $salary = Salary::where('user_id', $userId)
                ->where(function($query) use ($currentMonth) {
                    // Format untuk mencari pay_date di awal bulan berikutnya
                    $nextMonthFirstDay = Carbon::createFromFormat('Y-m', $currentMonth)
                        ->addMonth()
                        ->startOfMonth()
                        ->format('Y-m-d');
                    
                    // Format untuk mencari tanggal di bulan berjalan
                    $currentMonthStart = Carbon::createFromFormat('Y-m', $currentMonth)
                        ->startOfMonth()
                        ->format('Y-m-d');
                    $currentMonthEnd = Carbon::createFromFormat('Y-m', $currentMonth)
                        ->endOfMonth()
                        ->format('Y-m-d');
                    
                    // Cari salary dengan pay_date di awal bulan berikutnya (kasus normal)
                    $query->where('pay_date', $nextMonthFirstDay);
                    
                    // ATAU cari salary dengan pay_date di bulan berjalan (kasus khusus)
                    $query->orWhereBetween('pay_date', [$currentMonthStart, $currentMonthEnd]);
                })
                ->latest('created_at') // === FIX 2: Ambil yang terbaru jika ada lebih dari satu ===
                ->first();

            // Debugging: Tampilkan informasi pencarian salary
            $this->info("Mencari gaji untuk user ID {$userId} periode {$currentMonth}");
            $nextMonthFirstDay = Carbon::createFromFormat('Y-m', $currentMonth)
                                ->addMonth()
                                ->startOfMonth()
                                ->format('Y-m-d');
            $this->info("Atau dengan pay_date: {$nextMonthFirstDay}");
            
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

            // Array untuk menyimpan ID attendance yang diproses
            $processedAttendanceIds = [];

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

                // === FIX 3: Periksa apakah riwayat potongan sudah ada sebelum membuat yang baru ===
                $existingHistory = SalaryDeductionHistories::where('attendance_id', $attendance->id)->first();
                if ($existingHistory) {
                    $this->info("Riwayat potongan untuk attendance ID {$attendance->id} sudah ada. Lewati.");
                    continue;
                }

                // Simpan riwayat potongan untuk setiap attendance
                $history = SalaryDeductionHistories::create([
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

                // Jika history berhasil dibuat, tambahkan ID attendance ke array
                if ($history) {
                    $processedAttendanceIds[] = $attendance->id;
                }

                $this->info("Berhasil menambahkan potongan untuk attendance ID {$attendance->id}, user ID {$userId}, " .
                           "tanggal {$attendance->date}, status {$attendance->status}, " .
                           "jumlah potongan {$deductionAmount}");
            }

            // === FIX 4: Hanya update salary jika ada attendance yang diproses ===
            if (count($processedAttendanceIds) > 0) {
                // Update data salary dengan total pengurangan baru
                $totalDeduction = $existingDeduction + $additionalDeduction;
                $newTotalSalary = $salarySetting->salary - $totalDeduction;
                
                // Pastikan total gaji tidak minus
                if ($newTotalSalary < 0) {
                    $newTotalSalary = 0;
                    $this->warn("Total gaji untuk user ID {$userId} kurang dari 0. Disetel ke 0.");
                }

                // === FIX 5: Perbaikan update salary ===
                try {
                    $salary->total_deduction = $totalDeduction;
                    $salary->total_salary = $newTotalSalary;
                    $salary->status = 'pending';
                    $salary->note = ($salary->note ?: '') . " | Potongan diperbarui pada " . Carbon::now()->format('Y-m-d H:i:s');
                    $updateResult = $salary->save();

                    // Debugging: Tampilkan status update
                    if ($updateResult) {
                        $this->info("Berhasil update data gaji dengan ID {$salary->id}");
                    } else {
                        $this->warn("Gagal update data gaji dengan ID {$salary->id}");
                        // === FIX 6: Log error jika gagal update ===
                        \Log::error("Gagal update data gaji: ID {$salary->id}, User ID {$userId}, Total Deduction {$totalDeduction}");
                    }
                } catch (\Exception $updateError) {
                    $this->error("Error saat update salary: " . $updateError->getMessage());
                    \Log::error("Error saat update salary: " . $updateError->getMessage() . "\n" . $updateError->getTraceAsString());
                }

                // Debugging: Tampilkan nilai yang dihitung
                $this->info("User ID: {$userId}");
                $this->info("Potongan sebelumnya: {$existingDeduction}");
                $this->info("Tambahan potongan: {$additionalDeduction}");
                $this->info("Total potongan baru: {$totalDeduction}");
                $this->info("Updated Total Salary: {$newTotalSalary}");
                $this->info("Riwayat potongan berhasil disimpan");
            } else {
                $this->info("Tidak ada data attendance baru untuk diproses untuk user ID {$userId}");
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


Artisan::command('debug:salary-update {userId?}', function ($userId = null) {
    $this->info("Starting salary update debug...");
    
    // Dapatkan bulan dan tahun saat ini untuk filter
    $currentMonth = Carbon::now()->format('Y-m');
    $this->info("Current period: {$currentMonth}");
    
    // Jika userId diberikan, hanya debug user tersebut
    $query = Salary::query();
    if ($userId) {
        $query->where('user_id', $userId);
        $this->info("Debugging specific user ID: {$userId}");
    }
    
    // Ambil semua salary pada periode saat ini
    $salaries = $query->whereRaw("DATE_FORMAT(pay_date, '%Y-%m') = ?", [$currentMonth])
        ->orWhere(function($q) use ($currentMonth) {
            // Untuk gaji yang dibayarkan di awal bulan berikutnya
            $nextMonthFirstDay = Carbon::createFromFormat('Y-m', $currentMonth)
                ->addMonth()
                ->startOfMonth()
                ->format('Y-m-d');
            $q->where('pay_date', $nextMonthFirstDay);
        })
        ->get();
    
    if ($salaries->isEmpty()) {
        $this->error("No salaries found for period {$currentMonth}");
        return;
    }
    
    $this->info("Found " . $salaries->count() . " salary records to examine");
    
    foreach ($salaries as $salary) {
        $this->info("------- Salary ID: {$salary->id} -------");
        $this->info("User ID: {$salary->user_id}");
        $this->info("Pay Date: {$salary->pay_date}");
        $this->info("Status: {$salary->status}");
        $this->info("Current Total Salary: {$salary->total_salary}");
        $this->info("Current Total Deduction: {$salary->total_deduction}");
        
        // Cek apakah ada SalarySetting yang terkait
        $salarySetting = SalarySetting::find($salary->salary_setting_id);
        if (!$salarySetting) {
            $this->error("No SalarySetting found for this salary! (ID: {$salary->salary_setting_id})");
            continue;
        }
        
        $this->info("Base Salary: {$salarySetting->salary}");
        
        // Cek deduction histories untuk salary ini
        $deductions = SalaryDeductionHistories::where('salary_id', $salary->id)->get();
        $this->info("Found " . $deductions->count() . " deduction records");
        
        $totalCalculatedDeduction = 0;
        foreach ($deductions as $deduction) {
            $this->info("  - Deduction ID: {$deduction->id}");
            $this->info("    Type: {$deduction->deduction_type}");
            $this->info("    Amount: {$deduction->deduction_amount}");
            $this->info("    Date: {$deduction->deduction_date}");
            $totalCalculatedDeduction += $deduction->deduction_amount;
        }
        
        $this->info("Total calculated deduction: {$totalCalculatedDeduction}");
        $expectedSalary = $salarySetting->salary - $totalCalculatedDeduction;
        if ($expectedSalary < 0) $expectedSalary = 0;
        
        $this->info("Expected total salary: {$expectedSalary}");
        
        // Cek perbedaan dengan nilai yang tersimpan
        if ($salary->total_deduction != $totalCalculatedDeduction) {
            $this->error("DISCREPANCY DETECTED in total_deduction!");
            $this->error("Stored: {$salary->total_deduction} vs Calculated: {$totalCalculatedDeduction}");
            
            // Tawaran untuk memperbaiki
            if ($this->confirm("Do you want to fix this discrepancy?")) {
                DB::transaction(function() use ($salary, $totalCalculatedDeduction, $expectedSalary) {
                    $salary->update([
                        'total_deduction' => $totalCalculatedDeduction,
                        'total_salary' => $expectedSalary,
                        'note' => ($salary->note ?? '') . " | Fixed by debug command on " . now()->format('Y-m-d H:i:s')
                    ]);
                });
                $this->info("Salary record updated successfully!");
            }
        } else {
            $this->info("✓ Deduction amounts match correctly");
        }
        
        if ($salary->total_salary != $expectedSalary) {
            $this->error("DISCREPANCY DETECTED in total_salary!");
            $this->error("Stored: {$salary->total_salary} vs Expected: {$expectedSalary}");
            
            // Tawaran untuk memperbaiki
            if ($this->confirm("Do you want to fix this discrepancy?")) {
                DB::transaction(function() use ($salary, $expectedSalary) {
                    $salary->update([
                        'total_salary' => $expectedSalary,
                        'note' => ($salary->note ?? '') . " | Fixed by debug command on " . now()->format('Y-m-d H:i:s')
                    ]);
                });
                $this->info("Salary record updated successfully!");
            }
        } else {
            $this->info("✓ Total salary amounts match correctly");
        }
    }
    
    $this->info("Debug process completed!");
});