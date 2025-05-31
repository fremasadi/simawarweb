<?php

// app/Models/OrderBonus.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderBonus extends Model
{
    protected $fillable = [
        'order_id',
        'user_id',
        'salary_id',
        'bonus_amount',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function salary()
    {
        return $this->belongsTo(Salary::class);
    }
}
