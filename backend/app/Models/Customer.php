<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class Customer extends Model
{
    use BelongsToTenant, Notifiable;

    protected $guarded = [];

    protected $hidden = ['password', 'remember_token'];

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

    public function business()
    {
        return $this->belongsTo(Business::class);
    }
}
