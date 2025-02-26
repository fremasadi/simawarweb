<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SizeModel extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'size',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'size' => 'array',
    ];

    // Event untuk menangani sebelum data disimpan
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            // Pastikan size selalu berupa array
            if (is_null($model->size)) {
                $model->size = [];
            }
        });
    }
}