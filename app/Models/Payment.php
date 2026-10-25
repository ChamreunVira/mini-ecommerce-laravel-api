<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{

    protected $fillable = [
        'transaction_id',
        'order_id',
        'status',
        'method',
        'amount',
        'md5'
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
