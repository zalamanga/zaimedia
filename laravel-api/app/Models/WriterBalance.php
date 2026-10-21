<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WriterBalance extends Model
{
    protected $table = 'writer_balance';

    public $timestamps = false;

    protected $fillable = ['writer_id', 'available_balance', 'pending_balance', 'total_withdrawn', 'updated_at'];

    protected $casts = [
        'available_balance' => 'decimal:2',
        'pending_balance' => 'decimal:2',
        'total_withdrawn' => 'decimal:2',
    ];
}
