<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $guarded = [];

    protected $casts = ['metadata' => 'array', 'paid_at' => 'datetime'];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
