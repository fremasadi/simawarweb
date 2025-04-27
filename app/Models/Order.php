<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    /**
     * Kolom yang dapat diisi secara massal (mass assignable).
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'address',
        'deadline',
        'phone',
        'image_id',
        'quantity',
        'sizemodel_id', // Tambahkan sizemodel_id ke fillable
        'size',
        'status', // Kolom status dengan default value
        'ditugaskan_ke',
        'images', // Tambahkan kolom images ke fillable
    ];

    /**
     * Nilai default untuk atribut model.
     *
     * @var array
     */
    protected $attributes = [
        'status' => 'ditugaskan', // Default value untuk status
    ];

    protected $casts = [
        'size' => 'array',
        'images' => 'array',

    ];

    /**
     * Relasi ke model SizeModel.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function sizeModel()
    {
        return $this->belongsTo(SizeModel::class, 'sizemodel_id');
    }

    /**
     * Relasi ke model User (ditugaskan_ke).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'ditugaskan_ke');
    }

    /**
     * Relasi ke model ImageModel.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function image()
    {
        return $this->belongsTo(ImageModel::class, 'image_id');
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