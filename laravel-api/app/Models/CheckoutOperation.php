<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CheckoutOperation extends Model
{
    protected $fillable = ['customer_id', 'operation_key', 'intent_hash', 'order_id', 'response_payload'];

    protected $casts = ['response_payload' => 'array'];
}
