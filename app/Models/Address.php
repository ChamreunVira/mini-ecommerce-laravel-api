<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Address extends Model
{

    protected $fillable = ['user_id', 'recipient_name', 'phone', 'address_line', 'city', 'is_default'];
    
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
