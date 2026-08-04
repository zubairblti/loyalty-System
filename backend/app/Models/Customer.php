<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $hidden = ['password'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function ledger()
    {
        return $this->hasMany(PointsLedger::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function memberships()
    {
        return $this->hasMany(CustomerMembership::class);
    }

    public function currentMembership()
    {
        return $this->hasOne(CustomerMembership::class)->whereNull('ended_at');
    }
}
