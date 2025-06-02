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
        'price',
        'sizemodel_id',
        'size',
        'status',
        'ditugaskan_ke',
        'images',
        'description',
        'accessories_list',
    ];

    protected $attributes = [
        'status' => 'dikerjakan',
    ];

    protected $casts = [
        'size' => 'array',
        'images' => 'array',
        'accessories_list' => 'array',
        'deadline' => 'datetime',
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

    public function imageModels()
    {
        return $this->belongsToMany(ImageModel::class, 'order_images');
    }

    public function bonuses()
    {
        return $this->hasMany(OrderBonus::class);
    }

    /**
     * Boot method untuk memastikan data tersimpan dengan benar
     */
    // protected static function boot()
    // {
    //     parent::boot();
        
    //     static::saving(function ($model) {
    //         // Pastikan array fields disimpan dengan benar sebagai JSON
    //         if (isset($model->attributes['images']) && is_string($model->attributes['images'])) {
    //             // Jika sudah string JSON, biarkan
    //             $decoded = json_decode($model->attributes['images'], true);
    //             if (json_last_error() === JSON_ERROR_NONE) {
    //                 // Valid JSON, convert back to array for casting
    //                 $model->attributes['images'] = $decoded;
    //             }
    //         }
            
    //         if (isset($model->attributes['size']) && is_string($model->attributes['size'])) {
    //             $decoded = json_decode($model->attributes['size'], true);
    //             if (json_last_error() === JSON_ERROR_NONE) {
    //                 $model->attributes['size'] = $decoded;
    //             }
    //         }
            
    //         if (isset($model->attributes['accessories_list']) && is_string($model->attributes['accessories_list'])) {
    //             $decoded = json_decode($model->attributes['accessories_list'], true);
    //             if (json_last_error() === JSON_ERROR_NONE) {
    //                 $model->attributes['accessories_list'] = $decoded;
    //             }
    //         }
    //     });
    // }

    /**
     * Helper method untuk mendapatkan gambar dengan URL lengkap
     */
    public function getImageUrlsAttribute()
    {
        if (!is_array($this->images)) {
            return [];
        }

        return collect($this->images)->map(function ($image) {
            if (isset($image['photo']) && $image['photo']) {
                return \Illuminate\Support\Facades\Storage::disk('public')->url($image['photo']);
            }
            return null;
        })->filter()->values()->toArray();
    }

    /**
     * Helper method untuk mendapatkan accessories dengan detail
     */
    public function getAccessoriesDetailAttribute()
    {
        if (!is_array($this->accessories_list) || empty($this->accessories_list)) {
            return collect([]);
        }

        return collect($this->accessories_list)->map(function ($accessoryId) {
            return \App\Models\Accessory::find($accessoryId);
        })->filter()->values();
    }

    /**
     * Scope untuk filter berdasarkan status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope untuk filter berdasarkan deadline
     */
    public function scopeByDeadline($query, $from = null, $to = null)
    {
        if ($from) {
            $query->whereDate('deadline', '>=', $from);
        }
        
        if ($to) {
            $query->whereDate('deadline', '<=', $to);
        }
        
        return $query;
    }

    /**
     * Accessor untuk total harga termasuk accessories
     */
    public function getTotalPriceAttribute()
    {
        $basePrice = $this->price ?? 0;
        
        if (!is_array($this->accessories_list) || empty($this->accessories_list)) {
            return $basePrice;
        }

        $accessoriesPrice = collect($this->accessories_list)->sum(function ($accessoryId) {
            $accessory = \App\Models\Accessory::find($accessoryId);
            return $accessory ? $accessory->price : 0;
        });

        return $basePrice + $accessoriesPrice;
    }
}