<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class CustomerMembership extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $casts = [
        'assigned_at' => 'datetime',
        'grace_expires_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function level()
    {
        return $this->belongsTo(MembershipLevel::class, 'membership_level_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
