<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalarySetting extends Model
{
    protected $table = 'salary_settings';

    protected $fillable = [
        'name',
        'salary',
        'periode',
        'reduction_if_absent',
        'permit_reduction',
        'deduction_per_minute'

    ];

    public function salaries()
{
    return $this->hasMany(Salary::class);
}
}
