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

    protected function images(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? json_decode($value, true) : [],
            set: fn ($value) => is_array($value) ? json_encode($value) : $value,
        );
    }

    public function bonuses()
{
    return $this->hasMany(OrderBonus::class);
}

/**
     * Accessor untuk format images yang clean
     */
    public function getImagesAttribute($value)
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                // Clean format: hanya ambil photo field
                return array_map(function($item) {
                    return isset($item['photo']) ? ['photo' => $item['photo']] : $item;
                }, $decoded);
            }
        }
        return $value;
    }

    /**
     * Mutator untuk images - pastikan format clean sebelum disimpan
     */
    public function setImagesAttribute($value)
    {
        if (is_array($value)) {
            $cleanImages = [];
            foreach ($value as $imageData) {
                if (isset($imageData['photo'])) {
                    // Clean hanya photo field
                    if (is_array($imageData['photo'])) {
                        $photoValue = reset($imageData['photo']);
                        if ($photoValue) {
                            $cleanImages[] = ['photo' => $photoValue];
                        }
                    } else {
                        $cleanImages[] = ['photo' => $imageData['photo']];
                    }
                }
            }
            $this->attributes['images'] = json_encode($cleanImages);
        } else {
            $this->attributes['images'] = $value;
        }
    }

    /**
     * Accessor untuk size - pastikan return array
     */
    public function getSizeAttribute($value)
    {
        if (is_string($value)) {
            return json_decode($value, true) ?: [];
        }
        return $value;
    }

    /**
     * Mutator untuk size - simpan sebagai JSON object
     */
    public function setSizeAttribute($value)
    {
        if (is_array($value)) {
            $this->attributes['size'] = json_encode($value, JSON_UNESCAPED_UNICODE);
        } else {
            $this->attributes['size'] = $value;
        }
    }
}
