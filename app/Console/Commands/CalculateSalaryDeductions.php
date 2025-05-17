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
use Illuminate\Support\Facades\Log;

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
     * Default values for salary calculations
     */
    protected const DEFAULT_DEDUCTION_PER_MINUTE = 1000;
    protected const DEFAULT_REDUCTION_IF_ABSENT = 50000;
    protected const DEFAULT_MAX_DAILY_DEDUCTION = 100000;
    protected const MAX_DEDUCTION_PERCENTAGE = 50;

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
        
        // Waktu saat ini
        $now = Carbon::now();
        $currentTime = $now->format('H:i:s');
        $today = Carbon::today();
        $yesterday = Carbon::yesterday();
        
        // Debug info tentang waktu
        $this->info("Waktu saat ini: {$currentTime}, Tanggal: {$today->format('Y-m-d')}, Kemarin: {$yesterday->format('Y-m-d')}");
        
        // For testing purposes, set isAfterCloseTime to true regardless of actual time
        $isAfterCloseTime = true;
        $this->info("PENTING: Mode debug aktif, isAfterCloseTime selalu true");
        
        // Jika belum melewati waktu tutup toko, tidak perlu menghitung
        if (!$isAfterCloseTime) {
            $this->warn("Belum melewati waktu tutup toko. Perhitungan potongan gaji ditunda.");
            return 0;
        }
        
        // Ambil semua data absensi dengan filter bulan ini dan belum ada potongan
        $attendances = $this->getUnprocessedAttendances($currentMonth);
        
        if ($attendances->isEmpty()) {
            $this->info("Tidak ada data absensi yang perlu dihitung potongannya.");
            return 0;
        }

        $this->info("Ditemukan " . $attendances->count() . " data absensi yang perlu dihitung.");
        
        // Debug info untuk absensi yang ditemukan
        foreach ($attendances as $att) {
            $this->info("Absensi: ID {$att->id}, User ID {$att->user_id}, Tanggal {$att->date}, Status {$att->status}, Late Minutes {$att->late_minutes}");
        }
        
        // Filter attendances untuk hanya memproses yang valid
        $filteredAttendances = $this->filterValidAttendances($attendances, $storeSetting, $today, $yesterday, $isAfterCloseTime);
        
        if ($filteredAttendances->isEmpty()) {
            $this->info("Tidak ada data absensi yang memenuhi kriteria untuk dihitung potongannya.");
            return 0;
        }

        // Group attendances by user_id untuk diproses per user
        $groupedAttendances = $filteredAttendances->groupBy('user_id');

        // Mulai transaksi database
        DB::beginTransaction();

        try {
            foreach ($groupedAttendances as $userId => $userAttendances) {
                $this->processUserAttendances($userId, $userAttendances, $currentMonth);
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
            Log::error("Salary Deduction Error: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return 1;
        }
    }

    /**
     * Ambil data absensi yang belum diproses
     * 
     * @param string $currentMonth Format YYYY-MM
     * @return \Illuminate\Support\Collection
     */
    protected function getUnprocessedAttendances($currentMonth)
    {
        return Attendance::whereIn('status', ['telat', 'tidak hadir'])
            ->whereRaw("DATE_FORMAT(date, '%Y-%m') = ?", [$currentMonth])
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('salary_deduction_histories')
                    ->whereRaw('salary_deduction_histories.attendance_id = attendances.id');
            })
            ->orderBy('date', 'asc')
            ->get();
    }

    /**
     * Filter absensi berdasarkan kriteria validasi
     * 
     * @param \Illuminate\Support\Collection $attendances
     * @param \App\Models\StoreSetting $storeSetting
     * @param \Carbon\Carbon $today
     * @param \Carbon\Carbon $yesterday
     * @param bool $isAfterCloseTime
     * @return \Illuminate\Support\Collection
     */
    protected function filterValidAttendances($attendances, $storeSetting, $today, $yesterday, $isAfterCloseTime)
    {
        $filteredAttendances = collect();
        
        foreach ($attendances as $attendance) {
            $attendanceDate = Carbon::parse($attendance->date);
            $dayOfWeek = $attendanceDate->dayOfWeek;
            
            // Cek apakah hari kerja (bukan hari Minggu)
            $isWorkDay = $dayOfWeek > 0 && $dayOfWeek < 7;
            
            // Cek apakah toko buka
            $isStoreOpen = $storeSetting->is_open;
            
            // Cek apakah tanggal absensi adalah hari ini atau kemarin
            $isToday = $attendance->date === $today->format('Y-m-d');
            $isYesterday = $attendance->date === $yesterday->format('Y-m-d');
            
            // Validasi: Hanya proses absensi hari kemarin atau hari ini jika sudah setelah jam tutup
            $shouldProcess = ($isYesterday) || ($isToday && $isAfterCloseTime);
            
            // Kombinasikan validasi
            if ($isWorkDay && $isStoreOpen && $shouldProcess) {
                $filteredAttendances->push($attendance);
            } else {
                $this->logSkippedAttendance($attendance, $isWorkDay, $isStoreOpen, $shouldProcess, $isToday, $dayOfWeek);
            }
        }
        
        $this->info("Setelah filter: " . $filteredAttendances->count() . " data absensi valid.");
        
        return $filteredAttendances;
    }

    /**
     * Log informasi absensi yang dilewati
     */
    protected function logSkippedAttendance($attendance, $isWorkDay, $isStoreOpen, $shouldProcess, $isToday, $dayOfWeek)
    {
        $reasonText = [];
        if (!$isWorkDay) $reasonText[] = "hari libur (hari {$dayOfWeek})";
        if (!$isStoreOpen) $reasonText[] = "toko tutup (is_open = 0)";
        if (!$shouldProcess) {
            if ($isToday) {
                $reasonText[] = "belum melewati jam tutup toko untuk hari ini";
            } else {
                $reasonText[] = "bukan hari ini atau kemarin (tanggal: {$attendance->date})";
            }
        }
        
        $reason = implode(" dan ", $reasonText);
        $this->info("Melewati absensi ID {$attendance->id} untuk tanggal {$attendance->date} karena {$reason}");
    }

    /**
     * Proses semua absensi untuk satu user
     * 
     * @param int $userId
     * @param \Illuminate\Support\Collection $userAttendances
     * @param string $currentMonth Format YYYY-MM
     * @return void
     */
    protected function processUserAttendances($userId, $userAttendances, $currentMonth)
    {
        // Ambil informasi user
        $user = User::find($userId);
        if (!$user) {
            $this->warn("User dengan ID {$userId} tidak ditemukan. Lewati perhitungan.");
            return;
        }
        
        $userName = $user->name;
        
        // Ambil atau buat data salary untuk user ini
        $salary = $this->getSalaryForUser($userId, $userName, $currentMonth);
        
        if (!$salary) {
            $this->warn("Tidak dapat memproses gaji untuk user {$userName} (ID: {$userId}). Lewati perhitungan.");
            return;
        }
        
        // VALIDASI: Cek apakah salary sudah berstatus 'paid'
        if ($salary->status === 'paid') {
            $this->warn("Gaji untuk {$userName} (ID: {$userId}) periode {$currentMonth} sudah dibayarkan. Lewati perhitungan.");
            return;
        }
        
        // Ambil pengaturan gaji berdasarkan salary_setting_id
        $salarySetting = SalarySetting::find($salary->salary_setting_id);
    
        if (!$salarySetting) {
            $this->warn("Tidak ada pengaturan gaji untuk salary_setting_id {$salary->salary_setting_id}. Lewati perhitungan.");
            return;
        }
        
        // Hitung potongan untuk semua absensi
        $this->calculateUserDeductions($userId, $userName, $userAttendances, $salary, $salarySetting);
    }

    /**
     * Ambil atau buat data salary untuk user
     * 
     * @param int $userId
     * @param string $userName
     * @param string $currentMonth Format YYYY-MM
     * @return \App\Models\Salary|null
     */
    protected function getSalaryForUser($userId, $userName, $currentMonth)
    {
        // Tentukan tanggal pembayaran (untuk mencari data gaji)
        $firstDayOfMonth = Carbon::createFromFormat('Y-m', $currentMonth)->startOfMonth();
        $nextMonth = $firstDayOfMonth->copy()->addMonth();
        
        // Cari data gaji untuk periode bulan ini untuk user tersebut
        $salary = Salary::where('user_id', $userId)
            ->whereYear('pay_date', $nextMonth->year)
            ->whereMonth('pay_date', $nextMonth->month)
            ->first();
            
        $payDate = $nextMonth->format('Y-m-d');
        
        $this->info("Mencari gaji untuk {$userName} (ID: {$userId}) untuk periode {$currentMonth}, pay_date: {$payDate}");
        
        if (!$salary) {
            $this->info("Belum ada data gaji untuk {$userName}. Mencoba membuat baru.");
            
            // PERBAIKAN: Gunakan default salary_setting_id=1 jika tidak ada yang lain
            $salarySettingId = 1;
            
            // Ambil data salary setting
            $salarySetting = SalarySetting::find($salarySettingId);
            
            if (!$salarySetting) {
                $this->warn("Tidak dapat menemukan pengaturan gaji dengan ID {$salarySettingId}");
                return null;
            }

            // PERBAIKAN: Cek dan pastikan salary setting memiliki nilai base_salary
            if (is_null($salarySetting->salary)) {
                $this->warn("Salary setting ID {$salarySettingId} memiliki nilai salary NULL");
                
                // PERBAIKAN: Set nilai default untuk salary jika null
                $baseSalary = 0; // Default jika tidak ada
                
                // Update salary setting dengan nilai default
                $salarySetting->salary = $baseSalary;
                $salarySetting->save();
                
                $this->info("Nilai salary di SalarySetting diupdate menjadi {$baseSalary}");
            } else {
                $baseSalary = $salarySetting->salary;
            }
            
            $this->info("Menggunakan salary setting ID: {$salarySetting->id}, Salary: {$baseSalary}");
            
            // Hitung periode gaji
            $startDate = Carbon::createFromFormat('Y-m', $currentMonth)->startOfMonth()->format('Y-m-d');
            $endDate = Carbon::parse($payDate)->subDay()->format('Y-m-d');
            
            try {
                // Cek apakah sudah ada data gaji untuk periode ini (cek lagi untuk memastikan)
                $existingSalary = Salary::where('user_id', $userId)
                    ->whereYear('pay_date', Carbon::parse($payDate)->year)
                    ->whereMonth('pay_date', Carbon::parse($payDate)->month)
                    ->first();
                    
                if ($existingSalary) {
                    $this->info("Ditemukan data gaji yang sudah ada untuk periode {$currentMonth}. Menggunakan data yang ada.");
                    $salary = $existingSalary;
                } else {
                    // Buat data gaji baru untuk user ini
                    $salary = new Salary();
                    $salary->user_id = $userId;
                    $salary->salary_setting_id = $salarySettingId;
                    $salary->base_salary = $baseSalary; // PERBAIKAN: Pastikan tidak NULL
                    $salary->total_salary = $baseSalary;
                    $salary->total_deduction = 0;
                    $salary->pay_date = $payDate;
                    $salary->status = 'pending';
                    $salary->note = "Initial salary for user - Period: {$salarySetting->periode} from {$startDate} to {$endDate}";
                    $salary->save();
                    
                    $this->info("Berhasil membuat data gaji baru untuk {$userName} dengan ID: {$salary->id}");
                }
            } catch (\Exception $e) {
                $this->error("Gagal membuat data gaji untuk {$userName}: " . $e->getMessage());
                Log::error("Gagal membuat data gaji: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
                return null;
            }
        }
        
        return $salary;
    }

    /**
     * Hitung dan simpan potongan gaji untuk user
     * 
     * @param int $userId
     * @param string $userName
     * @param \Illuminate\Support\Collection $userAttendances
     * @param \App\Models\Salary $salary
     * @param \App\Models\SalarySetting $salarySetting
     * @return void
     */
    protected function calculateUserDeductions($userId, $userName, $userAttendances, $salary, $salarySetting)
    {
        // Ambil total pengurangan yang sudah ada
        $existingDeduction = $salary->total_deduction ?? 0;
        $additionalDeduction = 0;

        // Array untuk menyimpan ID attendance yang diproses
        $processedAttendanceIds = [];
        
        // Buat array untuk mencatat riwayat potongan detail untuk log
        $deductionDetails = [];

        // PERBAIKAN: Pastikan nilai untuk penghitungan
        $deductionPerMinute = $salarySetting->deduction_per_minute > 0 ? 
                              $salarySetting->deduction_per_minute : self::DEFAULT_DEDUCTION_PER_MINUTE;
                              
        $absenceDeduction = $salarySetting->reduction_if_absent > 0 ? 
                           $salarySetting->reduction_if_absent : self::DEFAULT_REDUCTION_IF_ABSENT;

        foreach ($userAttendances as $attendance) {
            $deductionAmount = 0;
            $deductionType = $attendance->status;
            $lateMinutes = null;

            // Hitung pengurangan berdasarkan keterlambatan
            if ($attendance->status === 'telat' && $attendance->late_minutes > 0) {
                $lateMinutes = $attendance->late_minutes;
                
                // Maksimal potongan per hari untuk keterlambatan
                $calculatedDeduction = $lateMinutes * $deductionPerMinute;
                $deductionAmount = min($calculatedDeduction, self::DEFAULT_MAX_DAILY_DEDUCTION);
                
                $this->info("Potongan untuk keterlambatan {$lateMinutes} menit: {$deductionAmount}");
                
                if ($calculatedDeduction > self::DEFAULT_MAX_DAILY_DEDUCTION) {
                    $this->info("Potongan dibatasi dari {$calculatedDeduction} menjadi {$deductionAmount} (batas maksimal)");
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
                $deductionAmount = $absenceDeduction;
                $additionalDeduction += $deductionAmount;
                
                $this->info("Potongan untuk ketidakhadiran: {$deductionAmount}");
                
                // Catat detail
                $deductionDetails[] = [
                    'tanggal' => $attendance->date,
                    'tipe' => 'tidak hadir',
                    'potongan' => $deductionAmount,
                    'catatan' => "Tidak hadir"
                ];
            }

            // Periksa apakah riwayat potongan sudah ada
            $existingHistory = SalaryDeductionHistories::where('attendance_id', $attendance->id)->first();
            if ($existingHistory) {
                $this->info("Riwayat potongan untuk attendance ID {$attendance->id} sudah ada. Lewati.");
                continue;
            }

            // Simpan riwayat potongan untuk attendance
            if ($this->saveSalaryDeductionHistory($userId, $userName, $salary->id, $attendance, $deductionType, 
                                                $lateMinutes, $deductionAmount, $deductionPerMinute, $absenceDeduction)) {
                $processedAttendanceIds[] = $attendance->id;
            }
        }

        // Hanya update salary jika ada attendance yang diproses
        if (count($processedAttendanceIds) > 0) {
            $this->updateSalaryWithDeductions($salary, $salarySetting, $existingDeduction, $additionalDeduction, $userName, $deductionDetails);
        } else {
            $this->info("Tidak ada data attendance baru yang valid untuk {$userName} (ID: {$userId})");
        }
    }

    /**
     * Simpan riwayat potongan gaji
     * 
     * @return bool
     */
    protected function saveSalaryDeductionHistory($userId, $userName, $salaryId, $attendance, $deductionType, 
                                               $lateMinutes, $deductionAmount, $deductionPerMinute, $absenceDeduction)
    {
        try {
            $history = SalaryDeductionHistories::create([
                'user_id' => $userId,
                'salary_id' => $salaryId,
                'attendance_id' => $attendance->id,
                'deduction_type' => $deductionType,
                'late_minutes' => $lateMinutes,
                'deduction_amount' => $deductionAmount,
                'deduction_per_minute' => $attendance->status === 'telat' ? $deductionPerMinute : null,
                'reduction_if_absent' => $attendance->status === 'tidak hadir' ? $absenceDeduction : null,
                'deduction_date' => Carbon::now()->toDateString(),
                'note' => "Potongan karena " . ($attendance->status === 'telat' ? 
                        "keterlambatan {$lateMinutes} menit" : "tidak hadir"),
            ]);

            if ($history) {
                $this->info("Berhasil menambahkan potongan untuk {$userName}, " .
                           "tanggal {$attendance->date}, status {$attendance->status}, " .
                           "jumlah potongan {$deductionAmount}");
                return true;
            }
            
            return false;
        } catch (\Exception $historyError) {
            $this->error("Error saat membuat riwayat potongan: " . $historyError->getMessage());
            Log::error("Error saat membuat riwayat potongan: " . $historyError->getMessage());
            return false;
        }
    }

    /**
     * Update data salary dengan potongan baru
     * 
     * @return void
     */
    protected function updateSalaryWithDeductions($salary, $salarySetting, $existingDeduction, $additionalDeduction, $userName, $deductionDetails)
    {
        // PERBAIKAN: Pastikan base_salary di salary tidak null
        if (is_null($salary->base_salary)) {
            $salary->base_salary = $salarySetting->salary ?? 0;
            $this->info("Base salary untuk {$userName} adalah null, diatur ke {$salary->base_salary}");
        }
        
        // Cek batas maksimal pengurangan (% dari total gaji)
        $baseSalary = $salary->base_salary;
        $maxTotalDeduction = ($baseSalary * self::MAX_DEDUCTION_PERCENTAGE) / 100;
        
        // Total pengurangan baru
        $totalDeduction = $existingDeduction + $additionalDeduction;
        
        // Terapkan batasan maksimal
        if ($totalDeduction > $maxTotalDeduction) {
            $this->warn("Total potongan ({$totalDeduction}) melebihi {$maxTotalDeduction} ({self::MAX_DEDUCTION_PERCENTAGE}% dari gaji). Potongan dibatasi.");
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
            $detailNotes .= "\n- {$detail['tanggal']} ({$detail['tipe']}): Rp" . 
                          number_format($detail['potongan'], 0, ',', '.') . 
                          " - {$detail['catatan']}";
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

            if ($updateResult) {
                $this->info("Berhasil update data gaji dengan ID {$salary->id} untuk {$userName}");
            } else {
                $this->warn("Gagal update data gaji dengan ID {$salary->id} untuk {$userName}");
                Log::error("Gagal update data gaji: ID {$salary->id}, User {$userName}, Total Deduction {$totalDeduction}");
            }
            
            // Debugging: Tampilkan nilai yang dihitung
            $this->info("User: {$userName}, Potongan sebelumnya: Rp" . number_format($existingDeduction, 0, ',', '.'));
            $this->info("Tambahan potongan: Rp" . number_format($additionalDeduction, 0, ',', '.'));
            $this->info("Total potongan baru: Rp" . number_format($totalDeduction, 0, ',', '.'));
            $this->info("Updated Total Salary: Rp" . number_format($newTotalSalary, 0, ',', '.'));
        } catch (\Exception $updateError) {
            $this->error("Error saat update salary: " . $updateError->getMessage());
            Log::error("Error saat update salary: " . $updateError->getMessage());
        }
    }
}