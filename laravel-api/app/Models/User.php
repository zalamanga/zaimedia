<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'username',
        'email',
        'password',
        'full_name',
        'phone',
        'role',
        'avatar',
        'bio',
        'address',
        'city',
        'postal_code',
        'date_of_birth',
        'website',
        'facebook',
        'twitter',
        'instagram',
        'linkedin',
        'status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $appends = ['avatar_url'];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'password' => 'hashed',
        'date_of_birth' => 'date',
    ];

    public function books()
    {
        return $this->hasMany(Book::class, 'writer_id');
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'customer_id');
    }

    public function writerBalance()
    {
        return $this->hasOne(WriterBalance::class, 'writer_id');
    }

    public function writerRequests()
    {
        return $this->hasMany(WriterRequest::class);
    }

    public function notifications()
    {
        return $this->hasMany(AppNotification::class);
    }

    public function wishlists()
    {
        return $this->hasMany(Wishlist::class);
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, ['admin', 'super_admin'], true);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isWriter(): bool
    {
        return $this->role === 'writer';
    }

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatar ? asset("uploads/avatars/{$this->avatar}") : null;
    }
}
