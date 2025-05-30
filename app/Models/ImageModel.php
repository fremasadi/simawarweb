<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImageModel extends Model
{
    use HasFactory;

    protected $table = 'image_models'; // Nama tabel di database

    protected $fillable = [
        'name',
        'image',
        'price'
    ];

    public function getImageUrlAttribute()
    {
        return asset('storage/' . $this->image);
    }
}
