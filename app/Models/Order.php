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

    public function bonuses()
    {
        return $this->hasMany(OrderBonus::class);
    }

    /**
     * Accessor untuk format images yang clean
     * Laravel's array casting sudah handle json_decode/encode
     */
    public function getImagesAttribute($value)
    {
        // Jika sudah array (dari casting), langsung proses
        if (is_array($value)) {
            return array_map(function($item) {
                return isset($item['photo']) ? ['photo' => $item['photo']] : $item;
            }, $value);
        }
        
        // Jika masih string, decode dulu
        if (is_string($value) && !empty($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return array_map(function($item) {
                    return isset($item['photo']) ? ['photo' => $item['photo']] : $item;
                }, $decoded);
            }
        }
        
        return [];
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
            // Simpan langsung sebagai array, Laravel casting akan handle JSON encoding
            $this->attributes['images'] = $cleanImages;
        } else {
            $this->attributes['images'] = $value;
        }
    }

    /**
     * Accessor untuk size - Laravel casting sudah handle ini
     */
    public function getSizeAttribute($value)
    {
        // Jika sudah array dari casting, return langsung
        if (is_array($value)) {
            return $value;
        }
        
        // Jika string, decode
        if (is_string($value) && !empty($value)) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }
        
        return [];
    }

    /**
     * Mutator untuk size - simpan sebagai array, biar Laravel casting yang handle
     */
    public function setSizeAttribute($value)
    {
        if (is_array($value)) {
            // Simpan langsung sebagai array, casting akan encode ke JSON
            $this->attributes['size'] = $value;
        } else if (is_string($value) && !empty($value)) {
            // Jika string JSON, decode dulu
            $decoded = json_decode($value, true);
            $this->attributes['size'] = is_array($decoded) ? $decoded : [];
        } else {
            $this->attributes['size'] = [];
        }
    }
}