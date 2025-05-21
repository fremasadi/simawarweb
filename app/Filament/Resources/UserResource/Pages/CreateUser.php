<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use App\Models\SalarySetting;
use App\Models\Salary;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Filament\Notifications\Notification;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function afterCreate(): void
    {
        // Mendapatkan model user yang baru dibuat
        $user = $this->record;
        
        // Cek apakah user adalah karyawan
        if ($user->role === 'karyawan') {
            $result = $this->generateInitialSalaryForNewUser($user->id);
            
            // Jika berhasil, tampilkan notifikasi sukses
            if ($result === true) {
                Notification::make()
                    ->title('Data gaji awal berhasil dibuat')
                    ->success()
                    ->send();
            } else {
                // Jika gagal, tampilkan pesan error
                Notification::make()
                    ->title('Gagal membuat data gaji awal')
                    ->body($result)
                    ->warning()
                    ->send();
            }
        }
    }

    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction()
                ->label('Simpan'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    /**
     * Generate gaji awal untuk user baru
     * 
     * @param int $userId ID user yang baru dibuat
     * @return bool|string True jika berhasil, pesan error jika gagal
     */
    private function generateInitialSalaryForNewUser($userId)
    {
        try {
            // Ambil data user
            $user = $this->record;
            
            if (!$user) {
                return "User tidak ditemukan.";
            }
            
            // Cek role user (pastikan karyawan)
            if ($user->role !== 'karyawan') {
                return "User bukan karyawan, tidak perlu generate gaji.";
            }
            
            // Coba ambil salary_setting dari user
            $salarySetting = null;
            
            if ($user->salary_setting_id) {
                $salarySetting = SalarySetting::find($user->salary_setting_id);
            }

            // Jika tidak ditemukan, ambil salary_setting pertama sebagai default
            if (!$salarySetting) {
                $salarySetting = SalarySetting::first();

                if (!$salarySetting) {
                    return "User tidak memiliki Salary Setting dan tidak ada Salary Setting default.";
                }
            }

            // Tentukan periode gaji berdasarkan tanggal saat ini (bukan mengikuti periode global)
            $now = Carbon::now();
            $payPeriodStart = $now->copy();
            $payPeriodEnd = null;
            $nextPayDate = null;

            // Set periode berdasarkan jenis periode gaji
            if ($salarySetting->periode === 'daily') {
                $payPeriodEnd = $now->copy()->endOfDay();
                $nextPayDate = $now->copy()->addDay()->startOfDay();
            } elseif ($salarySetting->periode === 'weekly') {
                $payPeriodEnd = $now->copy()->addDays(6); // Periode 7 hari dari hari ini
                $nextPayDate = $now->copy()->addDays(7)->startOfDay();
            } elseif ($salarySetting->periode === 'monthly') {
                $payPeriodEnd = $now->copy()->addMonth()->subDay(); // Periode 1 bulan dari hari ini
                $nextPayDate = $now->copy()->addMonth()->startOfDay();
            }

            // Cek apakah sudah pernah dibuat gaji untuk user ini
            $existingSalary = Salary::where('user_id', $user->id)->first();
            
            if ($existingSalary) {
                return "User sudah memiliki data gaji, tidak perlu membuat yang baru.";
            }

            // Buat record gaji baru
            DB::transaction(function () use ($user, $salarySetting, $nextPayDate, $payPeriodStart, $payPeriodEnd) {
                Salary::create([
                    'user_id' => $user->id,
                    'salary_setting_id' => $salarySetting->id,
                    'total_salary' => $salarySetting->salary,
                    'total_deduction' => 0,
                    'pay_date' => $nextPayDate,
                    'status' => 'pending',
                    'note' => "Initial salary for new user - Period: {$salarySetting->periode} from " . 
                            $payPeriodStart->format('Y-m-d') . " to " . 
                            $payPeriodEnd->format('Y-m-d')
                ]);
            });

            return true;
        } catch (\Exception $e) {
            return "Error generating initial salary: " . $e->getMessage();
        }
    }
}