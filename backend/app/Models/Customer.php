<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $guarded = [];

    public function ledger()
    {
        return $this->hasMany(PointsLedger::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
