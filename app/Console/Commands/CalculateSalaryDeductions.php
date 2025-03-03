<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Attendance;
use App\Models\Salary;
use App\Models\SalarySetting;
use Carbon\Carbon;

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
    protected $description = 'Calculate salary deductions based on attendances and update salaries.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Ambil semua data attendances
        $attendances = Attendance::all();

        foreach ($attendances as $attendance) {
            // Ambil pengaturan gaji berdasarkan user_id
            $salarySetting = SalarySetting::where('user_id', $attendance->user_id)->first();

            if ($salarySetting) {
                // Hitung pengurangan berdasarkan keterlambatan
                $lateDeduction = 0;
                if ($attendance->late_minutes > 0) {
                    $lateDeduction = $attendance->late_minutes * $salarySetting->deduction_per_minute;
                }

                // Hitung pengurangan jika tidak hadir
                $absenceDeduction = 0;
                if ($attendance->status === 'tidak hadir') {
                    $absenceDeduction = $salarySetting->reduction_if_absent;
                }

                // Total pengurangan
                $totalDeduction = $lateDeduction + $absenceDeduction;

                // Update atau buat data salary
                $salary = Salary::updateOrCreate(
                    [
                        'user_id' => $attendance->user_id,
                        'salary_setting_id' => $salarySetting->id,
                        'pay_date' => Carbon::now()->format('Y-m-d'), // Sesuaikan dengan periode gaji
                    ],
                    [
                        'total_deduction' => $totalDeduction,
                        'total_salary' => $salarySetting->salary - $totalDeduction,
                        'status' => 'pending', // Atau status lainnya
                        'note' => 'Auto-generated salary with deductions',
                    ]
                );
            }
        }

        $this->info('Salary deductions calculated and updated successfully!');
    }
}
