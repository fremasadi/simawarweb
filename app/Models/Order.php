<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

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
        'sizemodel_id',
        'size',
        'status',
        'ditugaskan_ke',
        'images',
    ];

    /**
     * Nilai default untuk atribut model.
     *
     * @var array
     */
    protected $attributes = [
        'status' => 'ditugaskan', // Default value untuk status
    ];

    /**
     * Konfigurasi casting atribut.
     *
     * @var array
     */
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

    /**
     * Relasi ke model User.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'ditugaskan_ke');
    }

    // Remove the images Attribute method as it conflicts with the $casts definition
    // Laravel's automatic casting will handle the JSON conversion
}