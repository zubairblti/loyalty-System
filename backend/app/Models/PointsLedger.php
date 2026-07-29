<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PointsLedger extends Model
{
    protected $table = 'points_ledger';

    protected $guarded = [];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
