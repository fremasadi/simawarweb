<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Laravel\Sanctum\HasApiTokens; // <-- Import trait ini


class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'address',
        'phone',
        'image',
        'role',
        'fcm_tokens'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    // protected $casts = [
    //     // kolom lain
    //     'fcm_tokens' => 'array',
    // ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function attendances(): HasMany
{
    return $this->hasMany(Attendance::class, 'user_id');
}
public function salaries()
{
    return $this->hasMany(Salary::class);
}

// Di model User (atau model tempat relasi ini dipakai)
public function salarySetting()
{
    return $this->belongsTo(SalarySetting::class);
}
public function orders()
{
    return $this->hasMany(Order::class, 'ditugaskan_ke');
}

public function orderBonuses()
{
    return $this->hasMany(OrderBonus::class);
}


}
