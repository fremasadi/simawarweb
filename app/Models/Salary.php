<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\SalarySetting;
use App\Models\User;

class Salary extends Model
{
    protected $fillable = [
        'user_id',
        'salary_setting_id',
        'total_salary',
        'total_deduction',
        'pay_date',
        'status',
        'note'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function salarySetting(): BelongsTo
    {
        return $this->belongsTo(SalarySetting::class);
    }
}
