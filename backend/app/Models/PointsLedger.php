<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class PointsLedger extends Model
{
    use BelongsToTenant;

    protected $table = 'points_ledger';

    protected $guarded = [];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
