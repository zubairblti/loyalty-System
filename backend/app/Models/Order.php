<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $casts = ['metadata' => 'array', 'paid_at' => 'datetime'];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
