<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    use HasFactory;

    protected $table = 'attendances';

    protected $fillable = [
        'user_id',
        'date',
        'check_in',
        'check_out',
        'status',
        'late_minutes', // Tambahkan late_minutes agar bisa diisi secara massal
    ];

    // Relasi ke User
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function salaryDeductionHistory() {
        return $this->hasOne(SalaryDeductionHistories::class, 'attendance_id');
    }

}
