<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    protected $fillable = ['book_id', 'customer_id', 'rating', 'title', 'content', 'is_verified', 'status', 'moderation_reason'];

    protected $casts = [
        'rating' => 'integer',
        'is_verified' => 'boolean',
    ];

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }
}
