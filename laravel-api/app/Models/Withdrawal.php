<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Withdrawal extends Model
{
    protected $fillable = [
        'withdrawal_code',
        'writer_id',
        'withdrawal_number',
        'amount',
        'bank_name',
        'account_number',
        'account_name',
        'notes',
        'status',
        'processed_at',
        'processed_by',
        'rejection_reason',
        'approved_by',
        'approved_at',
        'completed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'processed_at' => 'datetime',
        'approved_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function writer()
    {
        return $this->belongsTo(User::class, 'writer_id');
    }
}
