<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'book_id',
        'writer_id',
        'order_id',
        'quantity',
        'book_price',
        'royalty_percentage',
        'royalty_amount',
        'sale_date',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'book_price' => 'decimal:2',
        'royalty_percentage' => 'decimal:2',
        'royalty_amount' => 'decimal:2',
        'sale_date' => 'datetime',
    ];

    public function book()
    {
        return $this->belongsTo(Book::class)->withTrashed();
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
