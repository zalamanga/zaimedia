<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Article extends Model
{
    protected $fillable = [
        'author_id',
        'title',
        'slug',
        'excerpt',
        'content',
        'cover_image',
        'status',
        'published_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    protected $appends = ['cover_url'];

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function getCoverUrlAttribute(): ?string
    {
        return $this->cover_image ? asset("uploads/articles/{$this->cover_image}") : null;
    }
}
