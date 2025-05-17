<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Attendance;
use App\Models\User;
use App\Models\Salary;
use App\Models\SalarySetting;
use App\Models\SalaryDeductionHistories;
use App\Models\StoreSetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CalculateSalaryDeductions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'salary:calculate-deductions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Hitung pengurangan gaji berdasarkan data absensi, perbarui data gaji, dan simpan riwayat potongan';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
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
        
        // Debug info tentang waktu saat ini
        $this->info("Waktu saat ini: {$currentTime}, Tanggal hari ini: {$today->format('Y-m-d')}, Kemarin: {$yesterday->format('Y-m-d')}");
        
        // For testing purposes, set isAfterCloseTime to true regardless of actual time
        $isAfterCloseTime = true;
        $this->info("PENTING: Mode debug aktif, isAfterCloseTime selalu true");
        
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
            ->orderBy('date', 'asc')
            ->get();
            
        $this->info("Query absensi untuk bulan: {$currentMonth}");
        
        if ($attendances->isEmpty()) {
            $this->info("Tidak ada data absensi yang perlu dihitung potongannya.");
            return 0;
        }

        $this->info("Ditemukan " . $attendances->count() . " data absensi yang perlu dihitung.");
        
        // Debug info untuk absensi yang ditemukan
        foreach ($attendances as $att) {
            $this->info("Data absensi ditemukan: ID {$att->id}, User ID {$att->user_id}, Tanggal {$att->date}, Status {$att->status}, Late Minutes {$att->late_minutes}");
        }
        
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
                
                // Tentukan tanggal pembayaran (untuk mencari data gaji)
                $firstDayOfMonth = Carbon::createFromFormat('Y-m', $currentMonth)->startOfMonth();
                
                // Cari data gaji untuk periode bulan ini untuk user tersebut
                $salary = Salary::where('user_id', $userId)
                    ->whereYear('pay_date', $firstDayOfMonth->year)
                    ->whereMonth('pay_date', $firstDayOfMonth->addMonth()->month)
                    ->first();
                    
                $payDate = Carbon::createFromFormat('Y-m', $currentMonth)
                        ->addMonth()
                        ->format('Y-m-d');
                    
                $this->info("Mencari gaji untuk {$userName} (ID: {$userId}) untuk periode {$currentMonth}, pay_date: {$payDate}");
                
                if (!$salary) {
                    $this->info("Belum ada data gaji untuk {$userName} (ID: {$userId}) pada periode {$currentMonth}. Mencoba membuat baru.");
                    
                    // Tentukan salary_setting_id (gunakan ID 1 yang sudah ada)
                    $salarySettingId = 1;
                    
                    // Ambil data salary setting
                    $salarySetting = SalarySetting::find($salarySettingId);
                    
                    if (!$salarySetting) {
                        $this->warn("Tidak dapat menemukan pengaturan gaji dengan ID {$salarySettingId}. Lewati perhitungan.");
                        continue;
                    }

                    // Pastikan salary tidak null
                    if (is_null($salarySetting->salary)) {
                        $this->warn("Salary setting ID {$salarySettingId} memiliki nilai salary NULL. Lewati perhitungan.");
                        continue;
                    }
                    $this->info("Menggunakan salary setting ID: {$salarySetting->id}, Salary: {$salarySetting->salary}");
                    // Hitung periode gaji (dari awal bulan ini hingga sehari sebelum tanggal pembayaran bulan depan)
                    $startDate = Carbon::createFromFormat('Y-m', $currentMonth)->startOfMonth()->format('Y-m-d');
                    $endDate = Carbon::parse($payDate)->subDay()->format('Y-m-d');
                    
                    try {
                        // Cek apakah sudah ada data gaji untuk periode ini (cek lagi untuk memastikan)
                        $existingSalary = Salary::where('user_id', $userId)
                            ->whereYear('pay_date', Carbon::parse($payDate)->year)
                            ->whereMonth('pay_date', Carbon::parse($payDate)->month)
                            ->first();
                            
                        if ($existingSalary) {
                            $this->info("Ditemukan data gaji yang sudah ada untuk periode {$currentMonth} untuk user {$userName}. Menggunakan data yang ada.");
                            $salary = $existingSalary;
                        } else {
                            // Buat data gaji baru untuk user ini
                            $salary = new Salary();
                            $salary->user_id = $userId;
                            $salary->salary_setting_id = $salarySettingId;
                            $salary->base_salary = $salarySetting->salary; // Pastikan ini tidak null
                            $salary->total_salary = $salarySetting->salary;
                            $salary->total_deduction = 0;
                            $salary->pay_date = $payDate;
                            $salary->status = 'pending';
                            $salary->note = "Initial salary for user - Period: {$salarySetting->periode} from {$startDate} to {$endDate}";
                            $salary->save();
                            
                            $this->info("Berhasil membuat data gaji baru untuk {$userName} dengan ID: {$salary->id}, tanggal pembayaran: {$payDate}");
                        }
                    } catch (\Exception $e) {
                        $this->error("Gagal membuat data gaji untuk {$userName}: " . $e->getMessage());
                        $this->error("Detail error: " . $e->getTraceAsString());
                        continue;
                    }
                }
                
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
                        // Semua keterlambatan diproses, menghapus validasi terhadap jam buka toko
                        // Keterlambatan dihitung berdasarkan nilai late_minutes yang sudah ada
                        $validAttendance = true;
                    }
                    
                    if (!$validAttendance) {
                        $this->info("Melewati absensi ID {$attendance->id} untuk {$userName} tanggal {$attendance->date} karena {$validationNote}");
                        continue;
                    }

                    // Hitung pengurangan berdasarkan keterlambatan
                    if ($attendance->status === 'telat' && $attendance->late_minutes > 0) {
                        // Semua keterlambatan dikenakan potongan, tidak ada minimal menit
                        // (Menghapus pemeriksaan minimal 15 menit)
                        
                        $lateMinutes = $attendance->late_minutes;
                        
                        // VALIDASI: Maksimal potongan per hari untuk keterlambatan
                        $maxDailyDeduction = 100000; // Contoh: maksimal potongan 100.000 per hari
                        
                        // Gunakan nilai minimum deduction per minute jika deduction_per_minute adalah 0
                        $deductionPerMinute = $salarySetting->deduction_per_minute > 0 ? 
                                            $salarySetting->deduction_per_minute : 1000; // Default 1000 per menit jika 0
                        
                        $calculatedDeduction = $lateMinutes * $deductionPerMinute;
                        $deductionAmount = min($calculatedDeduction, $maxDailyDeduction);
                        
                        $this->info("Menghitung potongan untuk keterlambatan {$lateMinutes} menit dengan rate {$deductionPerMinute}/menit = {$calculatedDeduction}");
                        
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
                        // Pastikan ada nilai untuk reduction_if_absent
                        $absenceDeduction = $salarySetting->reduction_if_absent > 0 ? 
                                            $salarySetting->reduction_if_absent : 50000; // Default 50.000 jika 0
                        
                        $deductionAmount = $absenceDeduction;
                        $additionalDeduction += $deductionAmount;
                        
                        $this->info("Menghitung potongan untuk ketidakhadiran sebesar {$deductionAmount}");
                        
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
    }
}