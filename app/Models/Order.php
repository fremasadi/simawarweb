<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'address',
        'deadline',
        'phone',
        'quantity',
        'sizemodel_id',
        'size',
        'status',
        'ditugaskan_ke',
        'images',
    ];

    protected $attributes = [
        'status' => 'ditugaskan',
    ];

    protected $casts = [
        'size' => 'array',
        'images' => 'array',
    ];

    public function sizeModel()
    {
        return $this->belongsTo(SizeModel::class, 'sizemodel_id');
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'ditugaskan_ke');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'ditugaskan_ke');
    }

    protected function images(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? json_decode($value, true) : [],
            set: fn ($value) => is_array($value) ? json_encode($value) : $value,
        );
    }
}
