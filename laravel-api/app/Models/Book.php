<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Book extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'writer_id',
        'title',
        'slug',
        'author',
        'publisher',
        'year',
        'pages',
        'price',
        'isbn',
        'category_id',
        'cover_image',
        'pdf_file',
        'short_description',
        'long_description',
        'status',
        'rejection_reason',
        'views',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'year' => 'integer',
        'pages' => 'integer',
        'views' => 'integer',
        'deleted_at' => 'datetime',
    ];

    protected $hidden = ['pdf_file'];

    protected $appends = ['cover_url', 'has_pdf'];

    public function writer()
    {
        return $this->belongsTo(User::class, 'writer_id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function wishlists()
    {
        return $this->hasMany(Wishlist::class);
    }

    public function getCoverUrlAttribute(): ?string
    {
        return $this->cover_image ? asset("uploads/books/covers/{$this->cover_image}") : null;
    }

    public function getHasPdfAttribute(): bool
    {
        return (bool) $this->pdf_file;
    }
}
