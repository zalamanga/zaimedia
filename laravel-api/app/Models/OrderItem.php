<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['order_id', 'book_id', 'quantity', 'price', 'subtotal'];

    protected $casts = [
        'quantity' => 'integer',
        'price' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    public function book()
    {
        return $this->belongsTo(Book::class)->withTrashed();
    }
}
