<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class LoyaltyPointRule extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $casts = ['purchase_amount' => 'decimal:2', 'active' => 'boolean'];
}
