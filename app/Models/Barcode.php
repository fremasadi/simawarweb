<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Barcode extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        // 'is_active',
        // 'valid_until',
    ];

    // protected $casts = [
    //     'is_active' => 'boolean',
    //     'valid_until' => 'datetime',
    // ];
}