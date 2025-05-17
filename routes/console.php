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
    $messaging = app('firebase.messaging');

    $now = now();
    $today = $now->format('Y-m-d');
    $tomorrow = $now->addDay()->format('Y-m-d');

    $this->info("Looking for orders with status 'dikerjakan' and deadlines on $today or $tomorrow");

    $orders = Order::where('status', 'dikerjakan')
                  ->where(function($query) use ($today, $tomorrow) {
                      $query->whereDate('deadline', $today)
                            ->orWhereDate('deadline', $tomorrow);
                  })
                  ->get();

    $this->info("Found " . $orders->count() . " orders with status 'dikerjakan' and upcoming deadlines");

    foreach ($orders as $order) {
        // Periksa apakah notifikasi untuk order ini sudah pernah dikirim
        $notificationExists = DB::table('notification_logs')
            ->where('order_id', $order->id)
            ->exists();
        
        if ($notificationExists) {
            $this->info("Notification for order ID: {$order->id} already sent. Skipping...");
            continue; // Lewati order ini dan lanjut ke order berikutnya
        }

        $assignedUser = $order->user;

        if ($assignedUser && $assignedUser->fcm_token) {
            $token = $assignedUser->fcm_token;

            $isToday = $order->deadline === $today;
            $title = $isToday 
                ? 'Penting: Batas Waktu Pemesanan Hari Ini!' 
                : 'Pengingat: Batas Waktu Pemesanan Besok';
            $body = $isToday 
                ? 'Order atas nama "' . $order->name . '" jatuh tempo hari ini!' 
                : 'Order atas nama "' . $order->name . '" jatuh tempo besok!';

            $this->info("Preparing message for token: $token");

            $message = CloudMessage::withTarget('token', $token)
                ->withNotification([
                    'title' => $title,
                    'body' => $body
                ]);

            try {
                $this->info('Sending notification to token: ' . $token);
                $result = $messaging->send($message);
                $fcmResponse = json_encode($result);
                $this->info('Notification sent successfully. Firebase response: ' . $fcmResponse);
                
                // Simpan log notifikasi
                DB::table('notification_logs')->insert([
                    'order_id' => $order->id,
                    'user_id' => $assignedUser->id,
                    'title' => $title,
                    'body' => $body,
                    'status' => 'sent',
                    'fcm_token' => $token,
                    'fcm_response' => $fcmResponse,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
                
                $this->info("Notification log created for order ID: {$order->id}");
                
            } catch (\Exception $e) {
                $this->error('Error sending notification: ' . $e->getMessage());
                
                // Simpan log jika notifikasi gagal
                DB::table('notification_logs')->insert([
                    'order_id' => $order->id,
                    'user_id' => $assignedUser->id,
                    'title' => $title,
                    'body' => $body,
                    'status' => 'failed',
                    'fcm_token' => $token,
                    'fcm_response' => $e->getMessage(),
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
                
                $this->info("Failed notification log created for order ID: {$order->id}");
            }
        } else {
            $this->info('No FCM token found for user assigned to order: ' . $order->name);
        }
    }

    $this->info('Firebase notifications process completed!');
});

// Perintah untuk menghitung pengurangan gaji
// Perintah untuk menghitung pengurangan gaji
// Perintah untuk menghitung pengurangan gaji
Artisan::command('salary:calculate-deductions', function () {
    // Dapatkan bulan dan tahun saat ini untuk filter
    $currentMonth = Carbon::now()->format('Y-m');
    $this->info("Menghitung pengurangan gaji untuk periode: {$currentMonth}");
    
    // Ambil pengaturan toko
    $storeSetting = StoreSetting::first();

    if (!$storeSetting) {
        $this->warn("Tidak ada pengaturan toko yang ditemukan.");
        return 0;
    }
    
    $this->info("Jam operasional toko: {$storeSetting->open_time} - {$storeSetting->close_time}");
    
    // TAMBAHAN: Pemeriksaan waktu saat ini terhadap waktu tutup toko
    $now = Carbon::now();
    $currentTime = $now->format('H:i:s');
    $today = Carbon::today();
    $yesterday = Carbon::yesterday();
    
    // Tentukan apakah kita sudah melewati waktu tutup toko
    $closeTime = Carbon::parse($storeSetting->close_time);
    $isAfterCloseTime = false;
    
    // Jika waktu tutup lebih kecil dari waktu buka, artinya tutup di hari berikutnya
    if ($closeTime->format('H:i:s') < $storeSetting->open_time) {
        // Jika sekarang antara 00:00 sampai waktu tutup, kita masih dalam operasional hari kemarin
        if ($now->format('H:i:s') <= $closeTime->format('H:i:s')) {
            $this->info("Saat ini ({$currentTime}) masih dalam jam operasional kemarin (tutup: {$closeTime->format('H:i:s')})");
            $isAfterCloseTime = false;
        } else {
            $this->info("Saat ini ({$currentTime}) sudah melewati jam tutup toko ({$closeTime->format('H:i:s')})");
            $isAfterCloseTime = true;
        }
    } else {
        // Jika waktu tutup di hari yang sama
        // Jika sekarang lebih dari waktu tutup, berarti sudah bisa cek
        if ($now->format('H:i:s') >= $closeTime->format('H:i:s')) {
            $this->info("Saat ini ({$currentTime}) sudah melewati jam tutup toko ({$closeTime->format('H:i:s')})");
            $isAfterCloseTime = true;
        } else {
            $this->info("Saat ini ({$currentTime}) belum melewati jam tutup toko ({$closeTime->format('H:i:s')})");
            $isAfterCloseTime = false;
        }
    }
    
    // Hanya lanjutkan proses jika sudah melewati waktu tutup toko
    if (!$isAfterCloseTime) {
        $this->warn("Belum melewati waktu tutup toko. Perhitungan potongan gaji ditunda.");
        return 0;
    }
    
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
    
    // Filter attendances untuk mengecualikan hari Minggu dan hari libur lainnya
    $filteredAttendances = collect();
    
    foreach ($attendances as $attendance) {
        $attendanceDate = Carbon::parse($attendance->date);
        $dayOfWeek = $attendanceDate->dayOfWeek; // 0 (Sunday) - 6 (Saturday)
        
        // Cek apakah hari kerja (bukan hari Minggu)
        $isWorkDay = $dayOfWeek > 0 && $dayOfWeek < 7;
        
        // Tambahan: cek jika hari libur dari tabel khusus (jika ada)
        // $isHoliday = Holiday::where('date', $attendance->date)->exists();
        // if ($isHoliday) {
        //     $isWorkDay = false;
        // }
        
        // VALIDASI: Cek apakah toko buka berdasarkan store_settings
        $isStoreOpen = $storeSetting->is_open;
        
        // TAMBAHAN: Cek apakah tanggal absensi adalah hari ini atau kemarin
        $isToday = $attendance->date === $today->format('Y-m-d');
        $isYesterday = $attendance->date === $yesterday->format('Y-m-d');
        
        // VALIDASI: Hanya proses absensi hari kemarin atau hari ini jika sudah setelah jam tutup
        $shouldProcess = ($isYesterday) || ($isToday && $isAfterCloseTime);
        
        // Kombinasikan validasi: hari kerja DAN toko buka DAN sesuai dengan periode waktu yang valid
        if ($isWorkDay && $isStoreOpen && $shouldProcess) {
            $filteredAttendances->push($attendance);
        } else {
            $reasonText = [];
            if (!$isWorkDay) $reasonText[] = "hari libur (hari {$dayOfWeek})";
            if (!$isStoreOpen) $reasonText[] = "toko tutup (is_open = 0)";
            if (!$shouldProcess) {
                if ($isToday) {
                    $reasonText[] = "belum melewati jam tutup toko untuk hari ini";
                } else if (!$isYesterday && !$isToday) {
                    $reasonText[] = "bukan hari ini atau kemarin (tanggal: {$attendance->date})";
                }
            }
            
            $reason = implode(" dan ", $reasonText);
            $this->info("Melewati absensi ID {$attendance->id} untuk tanggal {$attendance->date} karena {$reason}");
        }
    }
    
    // Update variabel attendances dengan hasil filter
    $attendances = $filteredAttendances;
    
    $this->info("Setelah filter hari kerja, toko buka, dan validasi waktu: " . $attendances->count() . " data absensi yang perlu dihitung.");
    
    if ($attendances->isEmpty()) {
        $this->info("Tidak ada data absensi yang memenuhi kriteria untuk dihitung potongannya.");
        return 0;
    }

    // Group attendances by user_id
    $groupedAttendances = $attendances->groupBy('user_id');

    // Mulai transaksi database
    DB::beginTransaction();

    try {
        foreach ($groupedAttendances as $userId => $userAttendances) {
            // Ambil informasi user
            $user = User::find($userId);
            $userName = $user ? $user->name : "User #{$userId}";
            
            // Cari gaji untuk periode bulan berjalan (yang biasanya dibayarkan di awal bulan berikutnya)
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
                ->latest('created_at') // Ambil yang terbaru jika ada lebih dari satu
                ->first();

            // Debugging: Tampilkan informasi pencarian salary
            $this->info("Mencari gaji untuk {$userName} (ID: {$userId}) periode {$currentMonth}");
            $nextMonthFirstDay = Carbon::createFromFormat('Y-m', $currentMonth)
                                ->addMonth()
                                ->startOfMonth()
                                ->format('Y-m-d');
            $this->info("Atau dengan pay_date: {$nextMonthFirstDay}");
            
            if (!$salary) {
                $this->warn("Tidak ada data gaji untuk {$userName} (ID: {$userId}) pada periode {$currentMonth}. Lewati perhitungan.");
                continue;
            }
            
            $this->info("Ditemukan data gaji dengan ID: {$salary->id}, pay_date: {$salary->pay_date}");

            // VALIDASI: Cek apakah salary sudah berstatus 'paid'
            if ($salary->status === 'paid') {
                $this->warn("Gaji untuk {$userName} (ID: {$userId}) periode {$currentMonth} sudah dibayarkan. Lewati perhitungan.");
                continue;
            }

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
            
            // Buat array untuk mencatat riwayat potongan detail untuk log
            $deductionDetails = [];

            foreach ($userAttendances as $attendance) {
                $deductionAmount = 0;
                $deductionType = $attendance->status;
                $lateMinutes = null;

                // VALIDASI: Cek jam check-in terhadap jam buka toko
                $validAttendance = true;
                $validationNote = "";
                
                if ($attendance->status === 'telat' && $attendance->check_in) {
                    $checkInTime = Carbon::parse($attendance->check_in)->format('H:i:s');
                    $openTime = $storeSetting->open_time;
                    
                    // Toleransi keterlambatan (bisa disesuaikan)
                    $gracePeriodMinutes = 15; // 15 menit toleransi
                    $openTimePlusGrace = Carbon::parse($openTime)->addMinutes($gracePeriodMinutes)->format('H:i:s');
                    
                    // Jika check-in sebelum jam buka + toleransi, maka tidak dianggap telat
                    if ($checkInTime <= $openTimePlusGrace) {
                        $validAttendance = false;
                        $validationNote = "check-in pada {$checkInTime} masih dalam batas toleransi (jam buka: {$openTime} + {$gracePeriodMinutes} menit)";
                    }
                }
                
                if (!$validAttendance) {
                    $this->info("Melewati absensi ID {$attendance->id} untuk {$userName} tanggal {$attendance->date} karena {$validationNote}");
                    continue;
                }

                // Hitung pengurangan berdasarkan keterlambatan
                if ($attendance->status === 'telat' && $attendance->late_minutes > 0) {
                    // VALIDASI: Minimal menit untuk dikenakan potongan
                    $minimumLateMinutes = 15; // Contoh: minimal telat 15 menit baru kena potongan
                    
                    if ($attendance->late_minutes < $minimumLateMinutes) {
                        $this->info("Melewati absensi ID {$attendance->id} untuk {$userName} tanggal {$attendance->date} karena telat hanya {$attendance->late_minutes} menit (minimal {$minimumLateMinutes} menit)");
                        continue;
                    }
                    
                    $lateMinutes = $attendance->late_minutes;
                    
                    // VALIDASI: Maksimal potongan per hari untuk keterlambatan
                    $maxDailyDeduction = 100000; // Contoh: maksimal potongan 100.000 per hari
                    
                    $calculatedDeduction = $lateMinutes * $salarySetting->deduction_per_minute;
                    $deductionAmount = min($calculatedDeduction, $maxDailyDeduction);
                    
                    if ($calculatedDeduction > $maxDailyDeduction) {
                        $this->info("Potongan untuk {$userName} tanggal {$attendance->date} dibatasi dari {$calculatedDeduction} menjadi {$maxDailyDeduction} (batas maksimal per hari)");
                    }
                    
                    $additionalDeduction += $deductionAmount;
                    
                    // Catat detail
                    $deductionDetails[] = [
                        'tanggal' => $attendance->date,
                        'tipe' => 'telat',
                        'menit' => $lateMinutes,
                        'potongan' => $deductionAmount,
                        'catatan' => "Keterlambatan {$lateMinutes} menit"
                    ];
                }

                // Hitung pengurangan jika tidak hadir
                if ($attendance->status === 'tidak hadir') {
                    $deductionAmount = $salarySetting->reduction_if_absent;
                    $additionalDeduction += $deductionAmount;
                    
                    // Catat detail
                    $deductionDetails[] = [
                        'tanggal' => $attendance->date,
                        'tipe' => 'tidak hadir',
                        'potongan' => $deductionAmount,
                        'catatan' => "Tidak hadir"
                    ];
                }

                // Periksa apakah riwayat potongan sudah ada sebelum membuat yang baru
                $existingHistory = SalaryDeductionHistories::where('attendance_id', $attendance->id)->first();
                if ($existingHistory) {
                    $this->info("Riwayat potongan untuk attendance ID {$attendance->id} sudah ada. Lewati.");
                    continue;
                }

                // Simpan riwayat potongan untuk setiap attendance
                try {
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
                        $this->info("Berhasil menambahkan potongan untuk {$userName}, " .
                                   "tanggal {$attendance->date}, status {$attendance->status}, " .
                                   "jumlah potongan {$deductionAmount}");
                    }
                } catch (\Exception $historyError) {
                    $this->error("Error saat membuat riwayat potongan: " . $historyError->getMessage());
                    \Log::error("Error saat membuat riwayat potongan: " . $historyError->getMessage());
                }
            }

            // Hanya update salary jika ada attendance yang diproses
            if (count($processedAttendanceIds) > 0) {
                // VALIDASI: Cek batas maksimal pengurangan (% dari total gaji)
                $maxDeductionPercentage = 50; // Maksimal 50% dari total gaji
                $baseSalary = $salarySetting->salary;
                $maxTotalDeduction = ($baseSalary * $maxDeductionPercentage) / 100;
                
                // Total pengurangan baru
                $totalDeduction = $existingDeduction + $additionalDeduction;
                
                // Terapkan batasan maksimal
                if ($totalDeduction > $maxTotalDeduction) {
                    $this->warn("Total potongan ({$totalDeduction}) untuk {$userName} melebihi {$maxDeductionPercentage}% dari gaji ({$maxTotalDeduction}). Potongan dibatasi.");
                    $totalDeduction = $maxTotalDeduction;
                }
                
                $newTotalSalary = $baseSalary - $totalDeduction;
                
                // Pastikan total gaji tidak minus
                if ($newTotalSalary < 0) {
                    $newTotalSalary = 0;
                    $this->warn("Total gaji untuk {$userName} kurang dari 0. Disetel ke 0.");
                }

                // Buat catatan detail potongan untuk disimpan di field note
                $detailNotes = "Detail potongan:";
                foreach ($deductionDetails as $detail) {
                    $detailNotes .= "\n- {$detail['tanggal']} ({$detail['tipe']}): Rp" . number_format($detail['potongan'], 0, ',', '.') . " - {$detail['catatan']}";
                }
                
                try {
                    $salary->total_deduction = $totalDeduction;
                    $salary->total_salary = $newTotalSalary;
                    $salary->status = 'pending';
                    
                    // Tambahkan detail potongan ke catatan
                    $existingNote = $salary->note ?: '';
                    $updateTimeNote = "Potongan diperbarui pada " . Carbon::now()->format('Y-m-d H:i:s');
                    $salary->note = $existingNote . "\n\n" . $updateTimeNote . "\n" . $detailNotes;
                    
                    $updateResult = $salary->save();

                    // Debugging: Tampilkan status update
                    if ($updateResult) {
                        $this->info("Berhasil update data gaji dengan ID {$salary->id} untuk {$userName}");
                    } else {
                        $this->warn("Gagal update data gaji dengan ID {$salary->id} untuk {$userName}");
                        \Log::error("Gagal update data gaji: ID {$salary->id}, User {$userName}, Total Deduction {$totalDeduction}");
                    }
                } catch (\Exception $updateError) {
                    $this->error("Error saat update salary: " . $updateError->getMessage());
                    \Log::error("Error saat update salary: " . $updateError->getMessage() . "\n" . $updateError->getTraceAsString());
                }

                // Debugging: Tampilkan nilai yang dihitung
                $this->info("User: {$userName} (ID: {$userId})");
                $this->info("Potongan sebelumnya: Rp" . number_format($existingDeduction, 0, ',', '.'));
                $this->info("Tambahan potongan: Rp" . number_format($additionalDeduction, 0, ',', '.'));
                $this->info("Total potongan baru: Rp" . number_format($totalDeduction, 0, ',', '.'));
                $this->info("Updated Total Salary: Rp" . number_format($newTotalSalary, 0, ',', '.'));
                $this->info("Riwayat potongan berhasil disimpan");
            } else {
                $this->info("Tidak ada data attendance baru yang valid untuk diproses untuk {$userName} (ID: {$userId})");
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

    // PERUBAHAN: Menggunakan hari ini dan kemarin
    $today = Carbon::today();
    $yesterday = Carbon::yesterday();
    $todayDate = $today->format('Y-m-d');
    $yesterdayDate = $yesterday->format('Y-m-d');
    
    $this->info("Hari ini: {$todayDate}, Kemarin: {$yesterdayDate}");
    
    // Cek tanggal saat ini dan tanggal kemarin
    $this->info("Memeriksa absensi untuk tanggal: {$todayDate}");
    
    // Ambil semua user dengan role karyawan
    $users = User::where('role', 'karyawan')->get();
    
    if ($users->isEmpty()) {
        $this->info("Tidak ada karyawan yang ditemukan.");
        return 0;
    }

    // PERUBAHAN: Menggunakan Carbon::now() untuk mendapatkan waktu saat ini
    $now = Carbon::now();
    $currentTime = $now->format('H:i:s');
    
    // Tentukan apakah kita sudah melewati waktu tutup toko
    // Jika toko tutup di hari berikutnya (misalnya tutup jam 04:00 pagi)
    $closeTime = Carbon::parse($storeSetting->close_time);
    $isAfterCloseTime = false;
    
    // Jika waktu tutup lebih kecil dari waktu buka, artinya tutup di hari berikutnya
    if ($closeTime->format('H:i:s') < $storeSetting->open_time) {
        // Jika sekarang antara 00:00 sampai waktu tutup, berarti kita mengecek data kemarin
        if ($now->format('H:i:s') <= $closeTime->format('H:i:s')) {
            $isAfterCloseTime = true;
            $dateToCheck = $yesterdayDate; // Periksa absensi kemarin
            $this->info("Sekarang masih dalam jam operasional kemarin (tutup: {$closeTime->format('H:i:s')}), memeriksa absensi tanggal: {$dateToCheck}");
        } else {
            $dateToCheck = $todayDate; // Periksa absensi hari ini
            $this->info("Memeriksa absensi hari ini: {$dateToCheck}");
        }
    } else {
        // Jika waktu tutup di hari yang sama
        $dateToCheck = $todayDate;
        
        // Jika sekarang lebih dari waktu tutup, berarti sudah bisa cek absensi hari ini
        if ($now->format('H:i:s') >= $closeTime->format('H:i:s')) {
            $isAfterCloseTime = true;
        }
    }
    
    // Cek apakah toko buka pada tanggal yang diperiksa (berdasarkan hari)
    $dayOfWeek = Carbon::parse($dateToCheck)->dayOfWeek; // 0 (Sunday) - 6 (Saturday)
    
    // Asumsikan toko buka dari Senin-Sabtu (1-6) dan tutup hari Minggu (0)
    // Anda bisa menyesuaikan logika ini berdasarkan model data Anda
    $isOpenDay = $dayOfWeek > 0 && $dayOfWeek < 7;
    
    // Tambahan: cek jika hari yang diperiksa adalah hari libur dari tabel khusus (jika ada)
    // $isHoliday = Holiday::where('date', $dateToCheck)->exists();
    // if ($isHoliday) {
    //     $isOpenDay = false;
    // }

    $this->info("Tanggal: {$dateToCheck}, Hari: {$dayOfWeek}, Status toko buka: " . ($isOpenDay ? "Ya" : "Tidak"));
    $this->info("Waktu saat ini: {$currentTime}, Waktu tutup: {$storeSetting->close_time}, Sudah lewat waktu tutup: " . ($isAfterCloseTime ? "Ya" : "Tidak"));

    // Hanya lakukan pengecekan absensi jika:
    // 1. Toko buka pada tanggal yang diperiksa, DAN
    // 2. Sudah melewati waktu tutup toko untuk hari tersebut
    if ($isOpenDay && $isAfterCloseTime) {
        $processedCount = 0;
        $skippedCount = 0;
        
        foreach ($users as $user) {
            // Cek apakah sudah ada absensi untuk tanggal yang diperiksa
            $existingAttendance = Attendance::where('user_id', $user->id)
                ->where('date', $dateToCheck)
                ->first();

            // Jika belum ada absensi, buat absensi dengan status tidak hadir
            if (!$existingAttendance) {
                try {
                    Attendance::create([
                        'user_id' => $user->id,
                        'date' => $dateToCheck,
                        'status' => 'tidak hadir',
                        'check_in' => null,
                        'check_out' => null,
                        'late_minutes' => null,
                        'note' => "Auto-generated karena tidak melakukan check-in"
                    ]);

                    $this->info("Menambahkan absensi 'tidak hadir' untuk {$user->name} pada tanggal {$dateToCheck}");
                    $processedCount++;
                } catch (\Exception $e) {
                    $this->error("Error saat membuat absensi untuk {$user->name}: " . $e->getMessage());
                }
            } else {
                $this->info("Absensi untuk {$user->name} pada tanggal {$dateToCheck} sudah ada. Status: {$existingAttendance->status}");
                $skippedCount++;
            }
        }
        
        $this->info("Pengecekan absensi selesai. Ditambahkan: {$processedCount}, Dilewati: {$skippedCount}");
    } else {
        if (!$isOpenDay) {
            $this->info("Toko tutup pada tanggal {$dateToCheck}. Tidak perlu menambahkan absensi 'tidak hadir'.");
        } else {
            $this->info("Belum melewati waktu tutup toko ({$storeSetting->close_time}). Pengecekan absensi ditunda.");
        }
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